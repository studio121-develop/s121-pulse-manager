<?php
defined('ABSPATH') || exit;

global $wpdb;
$t = $wpdb->prefix . 'spm_billing_ledger';

$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'due';
$from   = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
$to     = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : '';

// Finestra predefinita per "due": EOM-10 → EOM+grace
$tz = wp_timezone();
$eom = (new DateTime('today', $tz))->modify('last day of this month')->setTime(0,0,0);

// prendiamo i valori da metodi protected
$ref = new ReflectionClass('SPM_Billing_Manager');
$noticeDays = $ref->getMethod('notice_days_before_eom'); $noticeDays->setAccessible(true);
$graceDays  = $ref->getMethod('grace_days');            $graceDays->setAccessible(true);
$notice = $noticeDays->invoke(null);
$grace  = $graceDays->invoke(null);

$default_from = (clone $eom)->modify('-'. $notice .' days')->format('Y-m-d');
$default_to   = (clone $eom)->modify('+'. $grace .' days')->format('Y-m-d');
if ($from === '' && $to === '' && $status === 'due') {
  $from = $default_from;
  $to   = $default_to;
}

$where = '1=1';
$args = [];
if ($status !== '') { $where .= " AND status = %s"; $args[] = $status; }
if ($from !== '')   { $where .= " AND due_date >= %s"; $args[] = $from; }
if ($to !== '')     { $where .= " AND due_date <= %s"; $args[] = $to; }

$sql  = "SELECT * FROM $t WHERE $where ORDER BY due_date ASC, period_start ASC LIMIT 1000";
$rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
?>
<div class="wrap">
  <h1>Fatture da emettere</h1>

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
		<th>Azioni</th>
	  </tr>
	</thead>
	<tbody>
	<?php if ($rows): foreach ($rows as $r): ?>
	  <tr>
		<td><?php echo (int)$r['id']; ?></td>
		<td><a href="<?php echo esc_url(get_edit_post_link((int)$r['contract_id'])); ?>">#<?php echo (int)$r['contract_id']; ?></a></td>
		<td><?php echo esc_html(get_the_title((int)$r['cliente_id']) ?: '—'); ?></td>
		<td><?php echo esc_html(get_the_title((int)$r['servizio_id']) ?: '—'); ?></td>
		<td><?php echo esc_html(SPM_Date_Helper::to_display_format($r['period_start']).' → '.SPM_Date_Helper::to_display_format($r['period_end'])); ?></td>
		<td><?php echo esc_html(SPM_Date_Helper::to_display_format($r['due_date'])); ?></td>
		<td>€ <?php echo number_format((float)$r['amount'], 2, ',', '.'); ?></td>
		<td><?php echo esc_html($r['status']); ?></td>
		<td>
		  <?php if ($r['status']==='due'): ?>
			<button class="button spm-bill" data-id="<?php echo (int)$r['id']; ?>" data-to="issued">Segna emessa</button>
			<button class="button spm-bill" data-id="<?php echo (int)$r['id']; ?>" data-to="skipped">Salta…</button>
		  <?php else: ?>
			—
		  <?php endif; ?>
		</td>
	  </tr>
	<?php endforeach; else: ?>
	  <tr><td colspan="9">Nessun risultato.</td></tr>
	<?php endif; ?>
	</tbody>
  </table>
</div>

<script>
  jQuery(function($){
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
  });
</script>
