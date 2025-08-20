<?php
defined('ABSPATH') || exit;

/**
 * Pagina Fatturazione (slug: spm-billing-due)
 * - Query del ledger con filtri
 * - Badge stato in italiano (Da emettere / Emessa / Saltata) + segnale "In ritardo"
 * - Mini-log a colpo d'occhio (match sul periodo); fallback su stato/notes/updated_at
 */

global $wpdb;
$t = $wpdb->prefix . 'spm_billing_ledger';

/* -------------------------------------------------------
 * 1) Parametri filtro da GET
 * ----------------------------------------------------- */
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'due';
$from   = isset($_GET['from'])   ? sanitize_text_field($_GET['from'])   : '';
$to     = isset($_GET['to'])     ? sanitize_text_field($_GET['to'])     : '';

/* -------------------------------------------------------
 * 2) Calcolo finestra default per "Da emettere"
 *    => dalla fine mese - notice_days a fine mese + grace_days
 *    I metodi sono private: uso Reflection come già facevi.
 * ----------------------------------------------------- */
$tz  = wp_timezone();
$eom = (new DateTime('today', $tz))->modify('last day of this month')->setTime(0,0,0);

$ref = new ReflectionClass('SPM_Billing_Manager');
$noticeDays = $ref->getMethod('notice_days_before_eom'); $noticeDays->setAccessible(true);
$graceDays  = $ref->getMethod('grace_days');             $graceDays->setAccessible(true);
$notice = (int) $noticeDays->invoke(null);
$grace  = (int) $graceDays->invoke(null);

$default_from = (clone $eom)->modify('-'. $notice .' days')->format('Y-m-d');
$default_to   = (clone $eom)->modify('+'. $grace .' days')->format('Y-m-d');

if ($from === '' && $to === '' && $status === 'due') {
  $from = $default_from;
  $to   = $default_to;
}

/* -------------------------------------------------------
 * 3) Query del ledger con filtri (limit 1000 per UI)
 * ----------------------------------------------------- */
$where = '1=1';
$args  = [];
if ($status !== '') { $where .= " AND status = %s";     $args[] = $status; }
if ($from   !== '') { $where .= " AND due_date >= %s";  $args[] = $from;   }
if ($to     !== '') { $where .= " AND due_date <= %s";  $args[] = $to;     }

$sql  = "SELECT * FROM $t WHERE $where ORDER BY due_date ASC, period_start ASC LIMIT 1000";
$rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

/* -------------------------------------------------------
 * 4) Prefetch mini-log: recupera lo storico per i contratti presenti
 *    (al massimo UNA chiamata ACF per contratto, poi uso in memoria)
 * ----------------------------------------------------- */
$logs_by_contract = [];
if (!empty($rows)) {
  $ids = array_unique(array_map(fn($r) => (int)$r['contract_id'], $rows));
  foreach ($ids as $cid) {
	$logs_by_contract[$cid] = get_field('storico_contratto', $cid) ?: [];
  }
}

/* -------------------------------------------------------
 * 5) Helper: etichetta italiana dello stato
 *    - usa SPM_Billing_Manager::status_label() se disponibile
 *    - fallback locale altrimenti
 * ----------------------------------------------------- */
if (!function_exists('spm_status_label_it')) {
  function spm_status_label_it(string $status): string {
	if (method_exists('SPM_Billing_Manager', 'status_label')) {
	  return SPM_Billing_Manager::status_label($status);
	}
	$map = ['due'=>'Da emettere', 'issued'=>'Emessa', 'skipped'=>'Saltata'];
	return $map[$status] ?? ucfirst($status);
  }
}

/* -------------------------------------------------------
 * 6) Helper: badge HTML stato + (opzionale) badge "In ritardo"
 *    - $due_date serve solo per capire se evidenziare "In ritardo"
 * ----------------------------------------------------- */
if (!function_exists('spm_status_badge')) {
  function spm_status_badge(string $status, string $due_date = ''): string {
	$label = spm_status_label_it($status);

	// Palette colori WordPress-like
	$bg = '#646970'; // default grigio
	if     ($status === 'due')     $bg = '#f0ad4e'; // arancio
	elseif ($status === 'issued')  $bg = '#46b450'; // verde
	elseif ($status === 'skipped') $bg = '#646970'; // grigio

	$badge  = '<span class="spm-badge" style="background:'.$bg.';">'.esc_html($label).'</span>';

	// Se è "da emettere" e la due_date è già passata => aggiungi badge "In ritardo"
	if ($status === 'due' && $due_date) {
	  $today = (new DateTime('today', wp_timezone()))->format('Y-m-d');
	  if ($due_date < $today) {
		$badge .= '<span class="spm-badge spm-badge--danger">In ritardo</span>';
	  }
	}

	return $badge;
  }
}

/* -------------------------------------------------------
 * 7) Helper: mini-log del periodo
 *    - Prova a cercare nello storico del contratto note che matchino il periodo
 *    - Fallback: mostra "Ultimo stato: <IT>" + notes + updated_at
 * ----------------------------------------------------- */
if (!function_exists('spm_mini_log_for_row')) {
  function spm_mini_log_for_row(array $row, array $logs_by_contract): string {
	$cid = (int)$row['contract_id'];
	$logs = $logs_by_contract[$cid] ?? [];

	$pStartDisp = SPM_Date_Helper::to_display_format($row['period_start']);
	$pEndDisp   = SPM_Date_Helper::to_display_format($row['period_end']);
	$needle1    = "per periodo {$pStartDisp} → {$pEndDisp}";
	$needle2    = "{$pStartDisp} → {$pEndDisp}"; // fallback più lasco

	// Cerca tra i più recenti 15 eventi
	foreach (array_slice($logs, 0, 15) as $e) {
	  $note = (string)($e['note'] ?? '');
	  if ($note && (strpos($note, $needle1) !== false || strpos($note, $needle2) !== false)) {
		$who  = !empty($e['utente']) ? ' · ' . $e['utente'] : '';
		$when = (!empty($e['data_operazione']) && !empty($e['ora_operazione']))
			  ? ' · ' . $e['data_operazione'] . ' ' . $e['ora_operazione'] : '';
		return esc_html($note . $who . $when);
	  }
	}

	// Fallback in italiano
	$statusIt = spm_status_label_it((string)$row['status']);
	$pretty   = 'Ultimo stato: ' . $statusIt;
	if (!empty($row['notes']))      $pretty .= ' · ' . $row['notes'];
	if (!empty($row['updated_at'])) $pretty .= ' · ' . $row['updated_at'];
	return esc_html($pretty);
  }
}

?>
<div class="wrap">
  <h1>Fatturazione</h1>

  <!-- Filtro (slug invariato) -->
  <form method="get" action="<?php echo esc_url( admin_url('admin.php') ); ?>" style="margin:12px 0;">
	<input type="hidden" name="page" value="spm-billing-due"/>

	<label>Stato:
	  <select name="status">
		<option value=""        <?php selected($status,''); ?>>Tutti</option>
		<option value="due"     <?php selected($status,'due'); ?>>Da emettere</option>
		<option value="issued"  <?php selected($status,'issued'); ?>>Emesse</option>
		<option value="skipped" <?php selected($status,'skipped'); ?>>Saltate</option>
	  </select>
	</label>

	<label>Da: <input type="date" name="from" value="<?php echo esc_attr($from); ?>"></label>
	<label>A:  <input type="date" name="to"   value="<?php echo esc_attr($to); ?>"></label>

	<button class="button button-primary">Filtra</button>

	<?php if ($status==='due' && $from===$default_from && $to===$default_to): ?>
	  <span style="margin-left:8px;opacity:.8;">
		Finestra: <?php echo (int)$notice; ?> gg prima EOM → EOM + <?php echo (int)$grace; ?> gg
	  </span>
	<?php endif; ?>

	<a class="button" href="<?php echo esc_url( admin_url('admin.php?page=spm-billing-due') ); ?>" style="margin-left:8px;">Reset</a>
  </form>

  <!-- Tabella con badge stato + mini-log -->
  <table class="widefat fixed striped">
	<thead>
	  <tr>
		<th>#</th>
		<th>Contratto</th>
		<th>Cliente</th>
		<th>Servizio</th>
		<th>Periodo</th>
		<th>Scadenza fattura</th>
		<th>Importo</th>
		<th>Stato</th>
		<!-- <th>Mini log</th> -->
		<th>Azioni</th>
	  </tr>
	</thead>
	<tbody>
	<?php if (!empty($rows)): foreach ($rows as $r): ?>
	  <tr>
		<td><?php echo (int)$r['id']; ?></td>

		<td>
		  <a href="<?php echo esc_url(get_edit_post_link((int)$r['contract_id'])); ?>">
			#<?php echo (int)$r['contract_id']; ?>
		  </a>
		</td>

		<td><?php echo esc_html(get_the_title((int)$r['cliente_id']) ?: '—'); ?></td>
		<td><?php echo esc_html(get_the_title((int)$r['servizio_id']) ?: '—'); ?></td>

		<td><?php echo esc_html(SPM_Date_Helper::to_display_format($r['period_start']).' → '.SPM_Date_Helper::to_display_format($r['period_end'])); ?></td>

		<td>
		  <?php
			// Mostra data scadenza; se già passata, evidenzio con icona
			$dueDisp = SPM_Date_Helper::to_display_format($r['due_date']);
			$isOver  = ($r['due_date'] < (new DateTime('today', $tz))->format('Y-m-d'));
			echo $isOver ? '<span title="In ritardo">⚠️ </span>' : '';
			echo esc_html($dueDisp);
		  ?>
		</td>

		<td>€ <?php echo number_format((float)$r['amount'], 2, ',', '.'); ?></td>

		<!-- STATO (badge) -->
		<td><?php echo spm_status_badge((string)$r['status'], (string)$r['due_date']); ?></td>

		<!-- MINI LOG -->
		<!-- <td class="spm-mini-log">
		  <?php echo spm_mini_log_for_row($r, $logs_by_contract); ?>
		</td> -->

		<!-- AZIONI -->
		<td>
		  <?php if ($r['status'] === 'due'): ?>
			<button class="button spm-bill" data-id="<?php echo (int)$r['id']; ?>" data-to="issued">Segna emessa</button>
			<button class="button spm-bill" data-id="<?php echo (int)$r['id']; ?>" data-to="skipped">Salta…</button>
			<button class="button button-link-delete spm-bill-del" data-id="<?php echo (int)$r['id']; ?>" data-status="due">Elimina</button>
		  <?php elseif ($r['status'] === 'issued' || $r['status'] === 'skipped'): ?>
			—
			<button class="button button-link-delete spm-bill-del" data-id="<?php echo (int)$r['id']; ?>" data-status="<?php echo esc_attr($r['status']); ?>">Elimina</button>
		  <?php else: ?>
			<button class="button button-link-delete spm-bill-del" data-id="<?php echo (int)$r['id']; ?>" data-status="<?php echo esc_attr($r['status']); ?>">Elimina</button>
		  <?php endif; ?>
		</td>
	  </tr>
	<?php endforeach; else: ?>
	  <tr><td colspan="10">Nessun risultato.</td></tr>
	<?php endif; ?>
	</tbody>
  </table>
</div>

<!-- Stili minimi per badge/mini-log; mettili nei tuoi asset se preferisci -->
<style>
  .spm-badge {
	display:inline-block;
	padding: 2px 8px;
	border-radius: 12px;
	color:#fff;
	font-size:12px;
	line-height:1.4;
	margin-right:6px;
  }
  .spm-badge--danger {
	background:#d63638; /* rosso WP */
  }
  .spm-mini-log {
	max-width: 520px;
	font-size:12px;
	line-height:1.35;
	opacity:.95;
  }
</style>

<!-- JS azioni: segna emessa / salta / elimina (già presenti, mantenuti) -->
<script>
jQuery(function($){
  // Segna emessa / Salta
  $('.spm-bill').on('click', function(e){
	e.preventDefault();
	var $b = $(this);
	var id = $b.data('id');
	var to = $b.data('to');
	var reason = '';
	if (to === 'skipped') {
	  reason = prompt('Motivo del salto (obbligatorio):', '');
	  if (reason===null || reason.trim()==='') return;
	}
	$b.prop('disabled', true).text('…');
	$.post(ajaxurl, {
	  action:'spm_billing_mark',
	  id:id, to:to, reason:reason,
	  _wpnonce: '<?php echo wp_create_nonce('spm_billing_mark'); ?>'
	}).done(function(resp){
	  if (resp && resp.success) location.reload();
	  else { alert((resp&&resp.data&&resp.data.message) || 'Errore'); $b.prop('disabled', false).text('Riprova'); }
	}).fail(function(){ alert('Errore di rete'); $b.prop('disabled', false).text('Riprova'); });
  });

  // Elimina riga ledger (qualunque stato)
  $('.spm-bill-del').on('click', function(e){
	e.preventDefault();
	var $b = $(this);
	var id = $b.data('id');
	var status = ($b.data('status') || '').toString();

	var msg = 'Confermi eliminazione di questa riga?';
	if (status === 'issued') msg = 'Questa riga risulta EMESSA. Confermi l\'eliminazione?';
	if (!confirm(msg)) return;

	var reason = '';
	if (status === 'issued') {
	  reason = prompt('Motivo della cancellazione (opzionale):', '');
	  if (reason === null) return; // annullato
	}

	$b.prop('disabled', true).text('Elimino…');
	$.post(ajaxurl, {
	  action: 'spm_billing_delete',
	  id: id,
	  reason: reason,
	  _wpnonce: '<?php echo wp_create_nonce('spm_billing_delete'); ?>'
	}).done(function(resp){
	  if (resp && resp.success) location.reload();
	  else {
		alert((resp && resp.data && resp.data.message) || 'Errore');
		$b.prop('disabled', false).text('Elimina');
	  }
	}).fail(function(){
	  alert('Errore di rete');
	  $b.prop('disabled', false).text('Elimina');
	});
  });
});
</script>
