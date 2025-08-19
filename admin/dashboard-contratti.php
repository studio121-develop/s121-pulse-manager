<?php
/**
 * Dashboard Contratti - Vista rapida e debug
 *
 * Funzioni principali:
 * - spm_render_dashboard(): pagina admin con KPI, scadenze, controlli periodo/vista e debug mese.
 * - spm_render_revenue_view(): grafico a barre o tabella su intervallo selezionato (MRR da KPI materializzati).
 * - spm_render_month_debug(): riconcilia KPI del mese con dettaglio righe history per contratto.
 * - spm_get_contract_stats(), spm_get_prossime_scadenze(), spm_get_rinnovi_oggi(): utilità runtime.
 *
 * Dipendenze:
 * - Classe SPM_Statistics_Handler (tabelle spm_contract_history e spm_kpi_monthly).
 * - SPM_Date_Helper per formattazioni e differenze di giorni.
 */

defined('ABSPATH') || exit;


// Export serie KPI (range)
add_action('admin_post_spm_export_kpi_csv', 'spm_export_kpi_csv');
// Export dettaglio mese
add_action('admin_post_spm_export_month_csv', 'spm_export_month_csv');

/**
 * Pagina principale Dashboard
 * - KPI sintetici
 * - Scadenze e azioni rapide
 * - Vista Ricavi (Grafico/Tabella) su range selezionabile
 * - Debug mese (riconciliazione KPI vs history)
 */
function spm_render_dashboard() {
	// Statistiche sintetiche
	$stats        = spm_get_contract_stats();
	$scadenze     = spm_get_prossime_scadenze();
	$rinnovi_oggi = spm_get_rinnovi_oggi();

	// KPI “sicuri” (fallback se alcune fonti non sono presenti)
	$mrr              = (float)($stats['valore_mensile'] ?? 0);     // MRR mese corrente (da KPI)
	$attivi           = (int)($stats['attivi'] ?? 0);
	$active_customers = (int)($stats['active_customers'] ?? $attivi);
	$arpu             = $active_customers > 0 ? ($mrr / $active_customers) : 0.0;
	$arr              = (float)($stats['arr'] ?? ($mrr * 12));

	// Parametri UI: periodo (from/to) + vista (chart/table) + debug (mese singolo)
	list($from_ym, $to_ym, $view_mode) = spm_dashboard_parse_range_params();
	$debug_month = isset($_GET['debug_month']) ? sanitize_text_field($_GET['debug_month']) : '';

	?>
	<div class="wrap">
		<h1>📊 Dashboard Contratti</h1>

		<!-- CONTROLLI: Periodo + Vista + Debug -->
		<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin:12px 0 18px 0; background:#fff; padding:12px 12px 6px; border:1px solid #ddd;">
			<input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">

			<strong>Periodo:</strong>
			<select name="period" onchange="this.form.submit()">
				<?php
				$curPeriod = $_GET['period'] ?? 'last6';
				$opts = [
					'last6'  => 'Ultimi 6 mesi',
					'last12' => 'Ultimi 12 mesi',
					'last24' => 'Ultimi 24 mesi',
					'ytd'    => 'Anno in corso (YTD)',
					'custom' => 'Personalizzato',
				];
				foreach ($opts as $k => $label) {
					printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($curPeriod, $k, false), esc_html($label));
				}
				?>
			</select>

			<span style="margin-left:10px;">
				<label>Da:
					<input type="month" name="from" value="<?php echo esc_attr($from_ym); ?>"
						   onchange="document.querySelector('select[name=period]').value='custom'">
				</label>
				<label style="margin-left:5px;">A:
					<input type="month" name="to" value="<?php echo esc_attr($to_ym); ?>"
						   onchange="document.querySelector('select[name=period]').value='custom'">
				</label>
			</span>

			<span style="margin-left:15px;">
				<strong>Vista:</strong>
				<label style="margin-left:4px;">
					<input type="radio" name="view" value="chart" <?php checked($view_mode, 'chart'); ?> onchange="this.form.submit()"> Grafico
				</label>
				<label style="margin-left:6px;">
					<input type="radio" name="view" value="table" <?php checked($view_mode, 'table'); ?> onchange="this.form.submit()"> Tabella
				</label>
			</span>

			<span style="margin-left:15px;">
				<strong>Debug mese:</strong>
				<input type="month" name="debug_month" value="<?php echo esc_attr($debug_month); ?>">
				<button class="button" type="submit">Aggiorna</button>
			</span>
		</form>

		<!-- RIGA 1: Stati contratti -->
		<div style="display:flex; flex-wrap:wrap; gap:20px; margin:20px 0;">
			<div style="flex:1 1 240px; background:white; padding:20px; border-left:4px solid #00a32a; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top:0; color:#00a32a;">🟢 Attivi</h3>
				<p style="font-size:32px; margin:0; font-weight:bold;"><?php echo (int)$attivi; ?></p>
				<p style="color:#666; margin:5px 0 0 0;">Contratti attivi</p>
			</div>

			<div style="flex:1 1 240px; background:white; padding:20px; border-left:4px solid #dba617; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top:0; color:#dba617;">⏰ In Scadenza</h3>
				<p style="font-size:32px; margin:0; font-weight:bold;"><?php echo (int)($stats['in_scadenza'] ?? 0); ?></p>
				<p style="color:#666; margin:5px 0 0 0;">Prossimi 30 giorni</p>
			</div>

			<div style="flex:1 1 240px; background:white; padding:20px; border-left:4px solid #dc3232; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top:0; color:#dc3232;">🔴 Scaduti</h3>
				<p style="font-size:32px; margin:0; font-weight:bold;"><?php echo (int)($stats['scaduti'] ?? 0); ?></p>
				<p style="color:#666; margin:5px 0 0 0;">Da rinnovare</p>
			</div>
		</div>

		<!-- RIGA 2: KPI economici -->
		<div style="display:flex; flex-wrap:wrap; gap:20px; margin:20px 0;">
			<div style="flex:1 1 240px; background:white; padding:20px; border-left:4px solid #2271b1; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top:0; color:#2271b1;">💰 MRR</h3>
				<p style="font-size:32px; margin:0; font-weight:bold;">€ <?php echo number_format($mrr, 2, ',', '.'); ?></p>
				<p style="color:#666; margin:5px 0 0 0;">Monthly Recurring Revenue (mese corrente)</p>
			</div>

			<div style="flex:1 1 240px; background:white; padding:20px; border-left:4px solid #6c2eb9; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top:0; color:#6c2eb9;">👤 ARPU</h3>
				<p style="font-size:32px; margin:0; font-weight:bold;">€ <?php echo number_format($arpu, 2, ',', '.'); ?></p>
				<p style="color:#666; margin:5px 0 0 0;">
					<?php echo $active_customers > 0 ? "Su {$active_customers} clienti attivi" : "Nessun cliente attivo"; ?>
				</p>
			</div>

			<div style="flex:1 1 240px; background:white; padding:20px; border-left:4px solid #1e8cbe; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top:0; color:#1e8cbe;">📆 ARR</h3>
				<p style="font-size:32px; margin:0; font-weight:bold;">€ <?php echo number_format($arr, 2, ',', '.'); ?></p>
				<p style="color:#666; margin:5px 0 0 0;">Annual Recurring Revenue (mese corrente × 12)</p>
			</div>
		</div>

		<div style="display:flex; gap:20px;">
			<!-- Prossime scadenze -->
			<div style="flex:1; background:white; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h2>📅 Prossime Scadenze</h2>
				<?php if (empty($scadenze)): ?>
					<p style="color:#666;">Nessun contratto in scadenza nei prossimi 30 giorni.</p>
				<?php else: ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>Cliente</th>
								<th>Servizio</th>
								<th>Scadenza</th>
								<th>Giorni</th>
								<th>Azioni</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($scadenze as $contratto): ?>
							<tr>
								<td><strong><?php echo esc_html($contratto['cliente']); ?></strong></td>
								<td><?php echo esc_html($contratto['servizio']); ?></td>
								<td>
									<?php $color = $contratto['giorni'] <= 7 ? 'red' : ($contratto['giorni'] <= 15 ? 'orange' : 'green'); ?>
									<span style="color: <?php echo esc_attr($color); ?>">
										<?php echo esc_html($contratto['scadenza_display']); ?>
									</span>
								</td>
								<td>
									<span style="background: <?php echo esc_attr($color); ?>; color:white; padding:2px 8px; border-radius:12px;">
										<?php echo (int)$contratto['giorni']; ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url(get_edit_post_link($contratto['id'])); ?>" class="button button-small">Gestisci</a>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Azioni rapide -->
			<div style="flex:0 0 300px; background:white; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h2>⚡ Azioni Rapide</h2>
				<p><a href="<?php echo esc_url(admin_url('post-new.php?post_type=contratti')); ?>" class="button button-primary" style="width:100%;">➕ Nuovo Contratto</a></p>
				<p><a href="<?php echo esc_url(admin_url('edit.php?post_type=contratti&filter_stato=scaduto')); ?>" class="button" style="width:100%;">🔴 Vedi Scaduti (<?php echo (int)($stats['scaduti'] ?? 0); ?>)</a></p>
				<p><a href="<?php echo esc_url(admin_url('edit.php?post_type=contratti&filter_stato=attivo')); ?>" class="button" style="width:100%;">🟢 Vedi Attivi (<?php echo (int)$attivi; ?>)</a></p>
				<hr>
				<h3>🔄 Rinnovi Automatici Oggi</h3>
				<?php if ($rinnovi_oggi > 0): ?>
					<p style="color:green; font-weight:bold;">✅ <?php echo (int)$rinnovi_oggi; ?> contratti rinnovati automaticamente</p>
				<?php else: ?>
					<p style="color:#666;">Nessun rinnovo automatico oggi</p>
				<?php endif; ?>
				<hr>
				<h3>📧 Ultima Sincronizzazione FIC</h3>
				<?php $last_sync = get_option('spm_last_sync_timestamp'); ?>
				<p style="color:#666;"><?php echo $last_sync ? esc_html($last_sync) : 'Mai sincronizzato'; ?></p>
				<p><a href="<?php echo esc_url(admin_url('admin.php?spm_test_sync=1')); ?>" class="button" style="width:100%;">🔄 Sincronizza Clienti</a></p>
			</div>
		</div>

		
		<!-- Vista Ricavi: Grafico/Tabella sul range -->
		<div style="background:white; padding:20px; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
			<h2>📈 Ricavi Ricorrenti Mensili</h2>
			<?php
			// URL sicuro per export KPI (serie sul range selezionato)
			$export_kpi_url = spm_dashboard_export_url(
				'spm_export_kpi_csv',
				[
					'action' => 'spm_export_kpi_csv',
					'from'   => $from_ym,
					'to'     => $to_ym,
				]
			);
			?>
			<p style="margin:0 0 10px;">
				<a class="button button-secondary" href="<?php echo esc_url($export_kpi_url); ?>">⬇️ Esporta CSV (KPI nel periodo)</a>
			</p>
			<?php spm_render_revenue_view($from_ym, $to_ym, $view_mode); ?>
		</div>
		
		<!-- Debug mese (opzionale) -->
		<?php if ($debug_month): ?>
			<div style="background:white; padding:20px; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h2>🛠️ Debug Mese: <?php echo esc_html($debug_month); ?></h2>
				<?php
				$export_month_url = spm_dashboard_export_url(
					'spm_export_month_csv',
					[
						'action' => 'spm_export_month_csv',
						'month'  => $debug_month,
					]
				);
				?>
				<p style="margin:0 0 10px;">
					<a class="button button-secondary" href="<?php echo esc_url($export_month_url); ?>">⬇️ Esporta CSV (dettaglio mese)</a>
				</p>
				<?php spm_render_month_debug($debug_month); ?>
			</div>
		<?php endif; ?>

	</div>
	<?php
}

/**
 * Parser parametri UI (GET) per periodo e vista
 * Ritorna: [from_ym, to_ym, view_mode]
 * - period: last6|last12|last24|ytd|custom
 * - from/to: YYYY-MM (solo se period=custom, altrimenti sovrascritti)
 * - view: chart|table
 */
function spm_dashboard_parse_range_params(): array {
	$tz_now_ym = current_time('Y-m');
	$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'last6';
	$view   = isset($_GET['view'])   ? sanitize_text_field($_GET['view'])   : 'chart';

	$to_ym   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : $tz_now_ym;
	$from_ym = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';

	if ($period !== 'custom') {
		$to_ym = $tz_now_ym;
		$dt = DateTime::createFromFormat('Y-m', $to_ym);
		switch ($period) {
			case 'last12': $dt_from = (clone $dt)->modify('-11 months'); break; // include il mese corrente
			case 'last24': $dt_from = (clone $dt)->modify('-23 months'); break;
			case 'ytd':
				$dt_from = DateTime::createFromFormat('Y-m-d', current_time('Y') . '-01-01');
				$from_ym = $dt_from->format('Y-m');
				return [$from_ym, $to_ym, ($view === 'table' ? 'table' : 'chart')];
			case 'last6':
			default:
				$dt_from = (clone $dt)->modify('-5 months');
				break;
		}
		$from_ym = $dt_from->format('Y-m');
	} else {
		// Validazione minima custom
		if (!$from_ym || !preg_match('/^\d{4}-\d{2}$/', $from_ym)) $from_ym = $tz_now_ym;
		if (!$to_ym   || !preg_match('/^\d{4}-\d{2}$/', $to_ym))   $to_ym   = $tz_now_ym;
		if (strcmp($from_ym, $to_ym) > 0) { $tmp = $from_ym; $from_ym = $to_ym; $to_ym = $tmp; }
	}

	if ($view !== 'chart' && $view !== 'table') $view = 'chart';
	return [$from_ym, $to_ym, $view];
}

/**
 * Vista ricavi: grafico a barre oppure tabella KPI
 * Dati: serie materializzata da SPM_Statistics_Handler::get_monthly_series()
 */
function spm_render_revenue_view(string $from_ym, string $to_ym, string $view_mode = 'chart') {
	$labels = [];
	$data   = [];
	$series = [];

	if (class_exists('SPM_Statistics_Handler')) {
		$handler = SPM_Statistics_Handler::instance();
		// idempotente: assicura i KPI sul range richiesto
		$handler->rebuild_kpis_range($from_ym, $to_ym);
		$series = $handler->get_monthly_series($from_ym, $to_ym); // ['YYYY-MM'=>['mrr'=>..., 'arr'=>..., 'arpu'=>..., 'active_customers'=>...]]
	}

	// Genera sequenza mesi dal from al to inclusi
	$cursor = DateTime::createFromFormat('Y-m', $from_ym);
	$end    = DateTime::createFromFormat('Y-m', $to_ym);
	$loop   = [];

	while ($cursor <= $end) {
		$ym = $cursor->format('Y-m');
		$labels[$ym] = date_i18n('M Y', $cursor->getTimestamp());
		$loop[] = $ym;
		$cursor->modify('+1 month');
	}

	// Prepara valori MRR per il grafico/tabla
	foreach ($loop as $ym) {
		$data[$ym] = isset($series[$ym]) ? (float)$series[$ym]['mrr'] : 0.0;
	}

	if ($view_mode === 'table') {
		// Tabella KPI completa
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Mese</th>
					<th style="text-align:right;">MRR</th>
					<th style="text-align:right;">ARR</th>
					<th style="text-align:right;">Clienti attivi</th>
					<th style="text-align:right;">ARPU</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($loop as $ym): 
				$row = $series[$ym] ?? ['mrr'=>0,'arr'=>0,'arpu'=>0,'active_customers'=>0];
			?>
				<tr>
					<td><?php echo esc_html($labels[$ym] ?? $ym); ?></td>
					<td style="text-align:right;">€ <?php echo number_format((float)$row['mrr'], 2, ',', '.'); ?></td>
					<td style="text-align:right;">€ <?php echo number_format((float)$row['arr'], 2, ',', '.'); ?></td>
					<td style="text-align:right;"><?php echo (int)($row['active_customers'] ?? 0); ?></td>
					<td style="text-align:right;">€ <?php echo number_format((float)$row['arpu'], 2, ',', '.'); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return;
	}

	// Grafico a barre semplice (MRR per mese)
	$max = 0;
	foreach ($data as $v) { if ($v > $max) $max = $v; }
	$max = $max ?: 1;
	?>
	<div style="display:flex; align-items:flex-end; height:220px; gap:10px; padding:20px 0;">
		<?php foreach ($loop as $ym): 
			$val = $data[$ym] ?? 0.0;
			$percentuale = ($val / $max) * 100;
			$mese_label  = esc_html($labels[$ym] ?? $ym);
		?>
			<div style="flex:1; display:flex; flex-direction:column; align-items:center;">
				<div style="background:#2271b1; width:100%; height:<?php echo esc_attr($percentuale); ?>%; min-height:2px; position:relative; border-radius:4px;">
					<span style="position:absolute; top:-20px; left:50%; transform:translateX(-50%); white-space:nowrap; font-size:12px;">
						€<?php echo number_format($val, 0, ',', '.'); ?>
					</span>
				</div>
				<div style="margin-top:10px; font-size:12px; color:#666;"><?php echo $mese_label; ?></div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Debug mese: riconcilia KPI con dettaglio history per contratto
 * - Mostra: KPI ufficiale del mese (MRR/ARR/ARPU/active_customers)
 * - Elenco righe spm_contract_history del mese, con mrr per contratto, stato e metadati
 * - Totale dettaglio vs KPI (diff evidenziata se ≠ 0)
 */
function spm_render_month_debug(string $ym) {
	if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
		echo '<p style="color:#dc3232;">Formato mese non valido. Usa YYYY-MM.</p>';
		return;
	}

	$month_date = $ym . '-01';

	global $wpdb;
	$kpi_tbl = $wpdb->prefix . 'spm_kpi_monthly';
	$his_tbl = $wpdb->prefix . 'spm_contract_history';

	// Assicura KPI pronti per il mese richiesto
	if (class_exists('SPM_Statistics_Handler')) {
		SPM_Statistics_Handler::instance()->update_kpi_for_month($ym);
	}

	$kpi = $wpdb->get_row(
		$wpdb->prepare("SELECT mrr_total, arr_total, active_customers, arpu FROM {$kpi_tbl} WHERE month = %s", $month_date),
		ARRAY_A
	);

	// Dettaglio righe history del mese (join al titolo contratto)
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT h.*, p.post_title AS contract_title
			   FROM {$his_tbl} h
		  LEFT JOIN {$wpdb->posts} p ON p.ID = h.contract_id
			  WHERE h.month = %s
		   ORDER BY h.mrr_value DESC, h.contract_id ASC",
			$month_date
		),
		ARRAY_A
	);

	// Header KPI
	if ($kpi) {
		?>
		<table class="wp-list-table widefat fixed striped" style="margin-bottom:16px;">
			<thead><tr>
				<th colspan="5">KPI mese <?php echo esc_html(date_i18n('F Y', strtotime($month_date))); ?></th>
			</tr></thead>
			<tbody>
				<tr>
					<td><strong>MRR totale</strong></td>
					<td><strong>ARR</strong></td>
					<td><strong>Clienti attivi</strong></td>
					<td><strong>ARPU</strong></td>
					<td></td>
				</tr>
				<tr>
					<td>€ <?php echo number_format((float)$kpi['mrr_total'], 2, ',', '.'); ?></td>
					<td>€ <?php echo number_format((float)$kpi['arr_total'], 2, ',', '.'); ?></td>
					<td><?php echo (int)$kpi['active_customers']; ?></td>
					<td>€ <?php echo number_format((float)$kpi['arpu'], 2, ',', '.'); ?></td>
					<td></td>
				</tr>
			</tbody>
		</table>
		<?php
	} else {
		echo '<p style="color:#666;">Nessun KPI salvato per questo mese.</p>';
	}

	// Dettaglio righe
	if (empty($rows)) {
		echo '<p style="color:#666;">Nessuna riga di dettaglio trovata in history per questo mese.</p>';
		return;
	}

	$total_detail = 0.0;
	?>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:80px;">ID</th>
				<th>Contratto</th>
				<th>Cliente</th>
				<th>Servizio</th>
				<th>Stato</th>
				<th>Freq</th>
				<th style="text-align:right;">Importo periodo</th>
				<th style="text-align:right;">MRR</th>
				<th>Fonte</th>
				<th>Generato</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($rows as $r):
			$total_detail += (float)$r['mrr_value'];
			$cliente  = $r['customer_id'] ? get_the_title((int)$r['customer_id']) : '—';
			$servizio = $r['service_id']  ? get_the_title((int)$r['service_id'])  : '—';
			$contr_t  = $r['contract_id'] . ' — ' . ($r['contract_title'] ?: 'Contratto');
		?>
			<tr>
				<td><?php echo (int)$r['contract_id']; ?></td>
				<td><a href="<?php echo esc_url(get_edit_post_link((int)$r['contract_id'])); ?>"><?php echo esc_html($contr_t); ?></a></td>
				<td><?php echo esc_html($cliente); ?></td>
				<td><?php echo esc_html($servizio); ?></td>
				<td><?php echo esc_html(ucfirst($r['status'])); ?></td>
				<td><?php echo esc_html($r['billing_freq']); ?></td>
				<td style="text-align:right;">€ <?php echo number_format((float)$r['amount_original'], 2, ',', '.'); ?></td>
				<td style="text-align:right;"><strong>€ <?php echo number_format((float)$r['mrr_value'], 2, ',', '.'); ?></strong></td>
				<td><?php echo esc_html($r['source_event']); ?></td>
				<td><?php echo esc_html($r['generated_at']); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr>
				<th colspan="7" style="text-align:right;">Totale dettaglio (somma MRR righe)</th>
				<th style="text-align:right;">€ <?php echo number_format($total_detail, 2, ',', '.'); ?></th>
				<th colspan="2"></th>
			</tr>
			<?php if (!empty($kpi)): 
				$diff = round($total_detail - (float)$kpi['mrr_total'], 2);
				$diffColor = abs($diff) < 0.01 ? '#00a32a' : '#dc3232';
			?>
			<tr>
				<th colspan="7" style="text-align:right;">Δ vs KPI (MRR dettaglio − MRR KPI)</th>
				<th style="text-align:right; color:<?php echo esc_attr($diffColor); ?>">
					€ <?php echo number_format($diff, 2, ',', '.'); ?>
				</th>
				<th colspan="2"></th>
			</tr>
			<?php endif; ?>
		</tfoot>
	</table>
	<?php
}

/**
 * Statistiche sintetiche:
 * - conteggi per stato (runtime, per immediatezza in admin)
 * - KPI del mese corrente (materializzati, fonte autorevole per MRR/ARR/ARPU)
 */
function spm_get_contract_stats() {
	$stats = [
		'totali'           => 0,
		'attivi'           => 0,
		'scaduti'          => 0,
		'sospesi'          => 0,
		'cessati'          => 0,
		'in_scadenza'      => 0,
		'valore_mensile'   => 0.0, // MRR del mese corrente
		'arr'              => 0.0,
		'arpu'             => 0.0,
		'active_customers' => 0,
	];

	// Conteggi per stato (query snelle)
	$stati = ['attivo', 'scaduto', 'sospeso', 'cessato'];
	foreach ($stati as $stato) {
		$q = new WP_Query([
			'post_type'      => 'contratti',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => [[ 'key' => 'stato', 'value' => $stato ]],
			'fields'         => 'ids',
		]);
		$key = ($stato === 'attivo') ? 'attivi' : (($stato === 'scaduto') ? 'scaduti' : (($stato === 'sospeso') ? 'sospesi' : 'cessati'));
		$stats[$key]     = (int)$q->found_posts;
		$stats['totali'] += (int)$q->found_posts;
	}

	// In scadenza nei prossimi 30 giorni (solo attivi)
	$date_limit  = date('Y-m-d', strtotime('+30 days'));
	$in_scadenza = new WP_Query([
		'post_type'      => 'contratti',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => [
			'relation' => 'AND',
			[ 'key' => 'stato', 'value' => 'attivo' ],
			[ 'key' => 'data_prossima_scadenza', 'value' => date('Y-m-d'), 'compare' => '>=', 'type' => 'DATE' ],
			[ 'key' => 'data_prossima_scadenza', 'value' => $date_limit,    'compare' => '<=', 'type' => 'DATE' ],
		],
		'fields' => 'ids',
	]);
	$stats['in_scadenza'] = (int)$in_scadenza->found_posts;

	// KPI del mese corrente (materializzati)
	if (class_exists('SPM_Statistics_Handler')) {
		$handler = SPM_Statistics_Handler::instance();
		$ym      = current_time('Y-m');
		$handler->update_kpi_for_month($ym); // garantisce la riga KPI corrente

		global $wpdb;
		$table = $wpdb->prefix . 'spm_kpi_monthly';
		$month = $ym . '-01';
		$row = $wpdb->get_row(
			$wpdb->prepare("SELECT mrr_total, arr_total, active_customers, arpu FROM {$table} WHERE month = %s", $month),
			ARRAY_A
		);
		if ($row) {
			$stats['valore_mensile']   = (float)$row['mrr_total'];
			$stats['arr']              = (float)$row['arr_total'];
			$stats['active_customers'] = (int)$row['active_customers'];
			$stats['arpu']             = (float)$row['arpu'];
		}
	}

	return $stats;
}

/**
 * Prossime scadenze (default: primi 10)
 */
function spm_get_prossime_scadenze($limit = 10) {
	$scadenze = [];

	$query = new WP_Query([
		'post_type'      => 'contratti',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'meta_query'     => [
			[ 'key' => 'stato', 'value' => 'attivo' ],
			[ 'key' => 'data_prossima_scadenza', 'value' => date('Y-m-d'), 'compare' => '>=', 'type' => 'DATE' ],
		],
		'meta_key' => 'data_prossima_scadenza',
		'orderby'  => 'meta_value',
		'order'    => 'ASC'
	]);

	if ($query->have_posts()) {
		foreach ($query->posts as $post) {
			$cliente_id  = get_field('cliente',  $post->ID);
			$servizio_id = get_field('servizio', $post->ID);
			$scadenza    = get_field('data_prossima_scadenza', $post->ID);

			$scadenze[] = [
				'id'               => $post->ID,
				'cliente'          => get_the_title($cliente_id),
				'servizio'         => get_the_title($servizio_id),
				'scadenza'         => $scadenza,
				'scadenza_display' => SPM_Date_Helper::to_display_format($scadenza),
				'giorni'           => SPM_Date_Helper::days_until_due($scadenza),
			];
		}
	}

	return $scadenze;
}

/**
 * Rinnovi automatici “oggi” (conteggio dallo storico)
 * Nota: ottimizzabile con chiavi specifiche nello storico, ma per ora è sufficiente.
 */
function spm_get_rinnovi_oggi() {
	$count = 0;

	$query = new WP_Query([
		'post_type'      => 'contratti',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => [[ 'key' => 'storico_contratto', 'compare' => 'EXISTS' ]]
	]);

	if ($query->have_posts()) {
		$today = date('Y-m-d');
		foreach ($query->posts as $post_id) {
			$storico = get_field('storico_contratto', $post_id);
			if ($storico && is_array($storico)) {
				foreach ($storico as $entry) {
					$tipo = $entry['tipo_operazione'] ?? $entry['tipo'] ?? '';
					$data = $entry['data_operazione'] ?? $entry['data_rinnovo'] ?? '';
					if ($tipo === 'rinnovo_automatico' && $data === $today) {
						$count++;
						break;
					}
				}
			}
		}
	}

	return $count;
}

/**
 * Costruisce un URL firmato (nonce) per admin-post.php
 * $action = slug di azione (es. 'spm_export_kpi_csv') usato per nonce
 * $args   = query args (deve includere 'action' => lo stesso slug)
 */
function spm_dashboard_export_url(string $action, array $args): string {
	$url = add_query_arg($args, admin_url('admin-post.php'));
	return wp_nonce_url($url, $action);
}

/**
 * Esporta CSV della serie KPI per un intervallo [from..to] (YYYY-MM)
 * Colonne: month, mrr, arr, active_customers, arpu
 */
function spm_export_kpi_csv() {
	// Permessi minimi: vedere admin e leggere contenuti
	if ( ! current_user_can('edit_posts') ) wp_die('Non autorizzato', 403);

	// Verifica nonce
	if ( empty($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'spm_export_kpi_csv') ) {
		wp_die('Nonce non valido', 403);
	}

	$from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
	$to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : '';

	if ( !preg_match('/^\d{4}-\d{2}$/',$from) || !preg_match('/^\d{4}-\d{2}$/',$to) ) {
		wp_die('Parametri periodo non validi', 400);
	}

	// Prepara dati
	$series = [];
	if ( class_exists('SPM_Statistics_Handler') ) {
		$h = SPM_Statistics_Handler::instance();
		$h->rebuild_kpis_range($from, $to); // idempotente
		$series = $h->get_monthly_series($from, $to);
	}

	// Header CSV
	$filename = sprintf('kpi_%s_%s.csv', str_replace('-','',$from), str_replace('-','',$to));
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');

	$out = fopen('php://output', 'w');
	// BOM per Excel (facoltativo ma utile)
	fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

	// Intestazioni
	fputcsv($out, ['month','mrr','arr','active_customers','arpu']);

	// Itera mese per mese per garantire ordine anche se serie è sparsa
	$cursor = DateTime::createFromFormat('Y-m', $from);
	$end    = DateTime::createFromFormat('Y-m', $to);

	while ($cursor <= $end) {
		$ym = $cursor->format('Y-m');
		$row = $series[$ym] ?? ['mrr'=>0,'arr'=>0,'arpu'=>0,'active_customers'=>0];
		fputcsv($out, [
			$ym,
			number_format((float)$row['mrr'], 2, '.', ''),  // decimali con punto per CSV “neutro”
			number_format((float)$row['arr'], 2, '.', ''),
			(int)($row['active_customers'] ?? 0),
			number_format((float)$row['arpu'], 2, '.', ''),
		]);
		$cursor->modify('+1 month');
	}

	fclose($out);
	exit;
}

/**
 * Esporta CSV del dettaglio mese (righe della history)
 * Colonne: month, contract_id, contract_title, customer, service, status, billing_freq, amount_original, mrr_value, currency, source_event, generated_at
 */
function spm_export_month_csv() {
	if ( ! current_user_can('edit_posts') ) wp_die('Non autorizzato', 403);

	if ( empty($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'spm_export_month_csv') ) {
		wp_die('Nonce non valido', 403);
	}

	$ym = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : '';
	if ( !preg_match('/^\d{4}-\d{2}$/', $ym) ) {
		wp_die('Parametro month non valido (usa YYYY-MM)', 400);
	}
	$month_date = $ym.'-01';

	global $wpdb;
	$kpi_tbl = $wpdb->prefix . 'spm_kpi_monthly';
	$his_tbl = $wpdb->prefix . 'spm_contract_history';

	// Assicura KPI pronti
	if ( class_exists('SPM_Statistics_Handler') ) {
		SPM_Statistics_Handler::instance()->update_kpi_for_month($ym);
	}

	// Query dettaglio
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT h.*, p.post_title AS contract_title
			   FROM {$his_tbl} h
		  LEFT JOIN {$wpdb->posts} p ON p.ID = h.contract_id
			  WHERE h.month = %s
		   ORDER BY h.mrr_value DESC, h.contract_id ASC",
			$month_date
		),
		ARRAY_A
	);

	// Header CSV
	$filename = sprintf('history_%s.csv', str_replace('-','',$ym));
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');

	$out = fopen('php://output', 'w');
	fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

	// Intestazioni
	fputcsv($out, [
		'month','contract_id','contract_title','customer','service',
		'status','billing_freq','amount_original','mrr_value','currency',
		'source_event','effective_from','generated_at','version'
	]);

	$total = 0.0;

	foreach ($rows as $r) {
		$customer_title = $r['customer_id'] ? get_the_title((int)$r['customer_id']) : '';
		$service_title  = $r['service_id']  ? get_the_title((int)$r['service_id'])  : '';
		$total += (float)$r['mrr_value'];

		fputcsv($out, [
			$r['month'],
			$r['contract_id'],
			$r['contract_title'],
			$customer_title,
			$service_title,
			$r['status'],
			$r['billing_freq'],
			number_format((float)$r['amount_original'], 2, '.', ''),
			number_format((float)$r['mrr_value'], 2, '.', ''),
			$r['currency'],
			$r['source_event'],
			$r['effective_from'],
			$r['generated_at'],
			$r['version'],
		]);
	}

	// Riga di totale a fine file (utile per riconciliazione rapida)
	fputcsv($out, []);
	fputcsv($out, ['TOTAL_MRR_DETAIL', number_format($total, 2, '.', '')]);

	// KPI di riferimento (opzionale ma utile)
	$kpi = $wpdb->get_row(
		$wpdb->prepare("SELECT mrr_total, arr_total, active_customers, arpu FROM {$kpi_tbl} WHERE month = %s", $month_date),
		ARRAY_A
	);
	if ($kpi) {
		fputcsv($out, ['KPI_MRR', number_format((float)$kpi['mrr_total'], 2, '.', '')]);
		fputcsv($out, ['KPI_ARR', number_format((float)$kpi['arr_total'], 2, '.', '')]);
		fputcsv($out, ['KPI_ACTIVE_CUSTOMERS', (int)$kpi['active_customers']]);
		fputcsv($out, ['KPI_ARPU', number_format((float)$kpi['arpu'], 2, '.', '')]);
		$delta = round($total - (float)$kpi['mrr_total'], 2);
		fputcsv($out, ['DELTA_DETAIL_MINUS_KPI', number_format($delta, 2, '.', '')]);
	}

	fclose($out);
	exit;
}

