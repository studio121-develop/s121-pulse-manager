<?php
/**
 * Dashboard Contratti - Vista rapida situazione
 */

defined('ABSPATH') || exit;

/**
 * Render Dashboard
 */
function spm_render_dashboard() {
	// Raccogli statistiche
	$stats        = spm_get_contract_stats();
	$scadenze     = spm_get_prossime_scadenze();
	$rinnovi_oggi = spm_get_rinnovi_oggi();

	// --- KPI safe --- //
	$mrr              = (float)($stats['valore_mensile'] ?? 0);           // MRR del mese corrente
	$attivi           = (int)($stats['attivi'] ?? 0);                      // contratti attivi
	$active_customers = (int)($stats['active_customers'] ?? $attivi);      // fallback sui contratti attivi
	$arpu             = $active_customers > 0 ? ($mrr / $active_customers) : 0.0;
	$arr              = (float)($stats['arr'] ?? ($mrr * 12));             // fallback 12×MRR
	?>
	<div class="wrap">
		<h1>📊 Dashboard Contratti</h1>
		
		<!-- RIGA 1: STATI CONTRATTI -->
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

		<!-- RIGA 2: KPI ECONOMICI -->
		<div style="display:flex; flex-wrap:wrap; gap:20px; margin:20px 0;">
			<div style="flex:1 1 240px; background:white; padding:20px; border-left:4px solid #2271b1; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top:0; color:#2271b1;">💰 MRR</h3>
				<p style="font-size:32px; margin:0; font-weight:bold;">€ <?php echo number_format($mrr, 2, ',', '.'); ?></p>
				<p style="color:#666; margin:5px 0 0 0;">Monthly Recurring Revenue</p>
			</div>

			<div style="flex:1 1 240px; background:white; padding:20px; border-left:4px solid #6c2eb9; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top:0; color:#6c2eb9;">👤 ARPU</h3>
				<p style="font-size:32px; margin:0; font-weight:bold;">€ <?php echo number_format($arpu, 2, ',', '.'); ?></p>
				<p style="color:#666; margin:5px 0 0 0;">
					<?php
					echo $active_customers > 0
						? "Su {$active_customers} clienti attivi nel mese"
						: "Nessun cliente attivo nel mese";
					?>
				</p>
			</div>

			<div style="flex:1 1 240px; background:white; padding:20px; border-left:4px solid #1e8cbe; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top:0; color:#1e8cbe;">📆 ARR</h3>
				<p style="font-size:32px; margin:0; font-weight:bold;">€ <?php echo number_format($arr, 2, ',', '.'); ?></p>
				<p style="color:#666; margin:5px 0 0 0;">Annual Recurring Revenue</p>
			</div>
		</div>
		
		<div style="display:flex; gap:20px;">
			
			<!-- PROSSIME SCADENZE -->
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
			
			<!-- AZIONI RAPIDE -->
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
		
		<!-- GRAFICO RICAVI -->
		<div style="background:white; padding:20px; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
			<h2>📈 Ricavi Ricorrenti Mensili</h2>
			<?php spm_render_revenue_chart(); ?>
		</div>
		
	</div>
	<?php
}

/**
 * Ottieni statistiche contratti
 * - Stati e "in scadenza" restano query runtime snelle
 * - MRR/ARR/ARPU/active_customers dai KPI materializzati
 */
function spm_get_contract_stats() {
	$stats = [
		'totali'           => 0,
		'attivi'           => 0,
		'scaduti'          => 0,
		'sospesi'          => 0,
		'cessati'          => 0,
		'in_scadenza'      => 0,
		'valore_mensile'   => 0.0, // MRR
		'arr'              => 0.0,
		'arpu'             => 0.0,
		'active_customers' => 0,
	];

	// 1) Conteggi per stato
	$stati = ['attivo', 'scaduto', 'sospeso', 'cessato'];
	foreach ($stati as $stato) {
		$count = new WP_Query([
			'post_type'      => 'contratti',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => [[ 'key' => 'stato', 'value' => $stato ]],
			'fields'         => 'ids',
		]);
		$key = ($stato === 'attivo') ? 'attivi' : (($stato === 'scaduto') ? 'scaduti' : (($stato === 'sospeso') ? 'sospesi' : 'cessati'));
		$stats[$key]     = (int)$count->found_posts;
		$stats['totali'] += (int)$count->found_posts;
	}

	// 2) In scadenza 30 giorni
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

	// 3) KPI del mese corrente (materializzati)
	if (class_exists('SPM_Statistics_Handler')) {
		$handler = SPM_Statistics_Handler::instance();
		$ym      = current_time('Y-m');

		// idempotente: assicura KPI del mese
		$handler->update_kpi_for_month($ym);

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
 * Prossime scadenze
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
				'giorni'           => SPM_Date_Helper::days_until_due($scadenza)
			];
		}
	}
	
	return $scadenze;
}

/**
 * Rinnovi automatici di oggi (da storico_contratto)
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
 * Grafico ricavi (serie materializzata KPI)
 */
function spm_render_revenue_chart() {
	$labels = [];
	$start  = new DateTime(current_time('Y-m-01'));
	$start->modify('-5 months'); // 6 mesi totali
	$from = $start->format('Y-m');
	$to   = (new DateTime(current_time('Y-m-01')))->format('Y-m');

	$data = [];

	if (class_exists('SPM_Statistics_Handler')) {
		$handler = SPM_Statistics_Handler::instance();
		$handler->rebuild_kpis_range($from, $to); // idempotente
		$series = $handler->get_monthly_series($from, $to); // ['YYYY-MM'=>['mrr'=>..., 'arr'=>..., 'arpu'=>..., 'active_customers'=>...]]
		
		$cursor = clone $start;
		for ($i = 0; $i < 6; $i++) {
			$ym = $cursor->format('Y-m');
			$labels[$ym] = date_i18n('M Y', $cursor->getTimestamp());
			$data[$ym] = isset($series[$ym]) ? (float)$series[$ym]['mrr'] : 0.0;
			$cursor->modify('+1 month');
		}
	} else {
		$cursor = clone $start;
		for ($i = 0; $i < 6; $i++) {
			$ym = $cursor->format('Y-m');
			$labels[$ym] = date_i18n('M Y', $cursor->getTimestamp());
			$data[$ym] = 0.0;
			$cursor->modify('+1 month');
		}
	}

	$max = 0;
	foreach ($data as $v) { if ($v > $max) $max = $v; }
	$max = $max ?: 1;
	?>
	<div style="display:flex; align-items:flex-end; height:200px; gap:10px; padding:20px 0;">
		<?php foreach ($data as $ym => $valore): 
			$percentuale = ($valore / $max) * 100;
			$mese_label  = esc_html($labels[$ym] ?? $ym);
		?>
			<div style="flex:1; display:flex; flex-direction:column; align-items:center;">
				<div style="background:#2271b1; width:100%; height:<?php echo esc_attr($percentuale); ?>%; min-height:2px; position:relative;">
					<span style="position:absolute; top:-20px; left:50%; transform:translateX(-50%); white-space:nowrap; font-size:12px;">
						€<?php echo number_format($valore, 0, ',', '.'); ?>
					</span>
				</div>
				<div style="margin-top:10px; font-size:12px; color:#666;"><?php echo $mese_label; ?></div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}
