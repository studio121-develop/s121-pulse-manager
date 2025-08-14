<?php
/**
 * Dashboard Contratti - Vista rapida situazione
 */

defined('ABSPATH') || exit;

// Aggiungi voce menu
add_action('admin_menu', 'spm_add_dashboard_menu');

function spm_add_dashboard_menu() {
	add_submenu_page(
		'edit.php?post_type=contratti',
		'Dashboard Contratti',
		'📊 Dashboard',
		'manage_options',
		'contratti-dashboard',
		'spm_render_dashboard'
	);
}

/**
 * Render Dashboard
 */
function spm_render_dashboard() {
	// Raccogli statistiche
	$stats = spm_get_contract_stats();
	$scadenze = spm_get_prossime_scadenze();
	$rinnovi_oggi = spm_get_rinnovi_oggi();
	
	?>
	<div class="wrap">
		<h1>📊 Dashboard Contratti</h1>
		
		<!-- STATISTICHE RAPIDE -->
		<div style="display: flex; gap: 20px; margin: 20px 0;">
			
			<div style="flex: 1; background: white; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top: 0; color: #00a32a;">🟢 Attivi</h3>
				<p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo $stats['attivi']; ?></p>
				<p style="color: #666; margin: 5px 0 0 0;">Contratti attivi</p>
			</div>
			
			<div style="flex: 1; background: white; padding: 20px; border-left: 4px solid #dba617; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top: 0; color: #dba617;">⏰ In Scadenza</h3>
				<p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo $stats['in_scadenza']; ?></p>
				<p style="color: #666; margin: 5px 0 0 0;">Prossimi 30 giorni</p>
			</div>
			
			<div style="flex: 1; background: white; padding: 20px; border-left: 4px solid #dc3232; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top: 0; color: #dc3232;">🔴 Scaduti</h3>
				<p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo $stats['scaduti']; ?></p>
				<p style="color: #666; margin: 5px 0 0 0;">Da rinnovare</p>
			</div>
			
			<div style="flex: 1; background: white; padding: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3 style="margin-top: 0; color: #2271b1;">💰 Valore Mensile</h3>
				<p style="font-size: 32px; margin: 0; font-weight: bold;">€ <?php echo number_format($stats['valore_mensile'], 2, ',', '.'); ?></p>
				<p style="color: #666; margin: 5px 0 0 0;">Ricavi ricorrenti</p>
			</div>
			
		</div>
		
		<div style="display: flex; gap: 20px;">
			
			<!-- PROSSIME SCADENZE -->
			<div style="flex: 1; background: white; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h2>📅 Prossime Scadenze</h2>
				
				<?php if (empty($scadenze)): ?>
					<p style="color: #666;">Nessun contratto in scadenza nei prossimi 30 giorni.</p>
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
									<td>
										<strong><?php echo esc_html($contratto['cliente']); ?></strong>
									</td>
									<td><?php echo esc_html($contratto['servizio']); ?></td>
									<td>
										<?php 
										$color = $contratto['giorni'] <= 7 ? 'red' : ($contratto['giorni'] <= 15 ? 'orange' : 'green');
										?>
										<span style="color: <?php echo $color; ?>">
											<?php echo esc_html($contratto['scadenza_display']); ?>
										</span>
									</td>
									<td>
										<span style="background: <?php echo $color; ?>; color: white; padding: 2px 8px; border-radius: 12px;">
											<?php echo $contratto['giorni']; ?>
										</span>
									</td>
									<td>
										<a href="<?php echo get_edit_post_link($contratto['id']); ?>" class="button button-small">
											Gestisci
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
			
			<!-- AZIONI RAPIDE -->
			<div style="flex: 0 0 300px; background: white; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h2>⚡ Azioni Rapide</h2>
				
				<p>
					<a href="<?php echo admin_url('post-new.php?post_type=contratti'); ?>" class="button button-primary" style="width: 100%;">
						➕ Nuovo Contratto
					</a>
				</p>
				
				<p>
					<a href="<?php echo admin_url('edit.php?post_type=contratti&stato=scaduto'); ?>" class="button" style="width: 100%;">
						🔴 Vedi Scaduti (<?php echo $stats['scaduti']; ?>)
					</a>
				</p>
				
				<p>
					<a href="<?php echo admin_url('edit.php?post_type=contratti&stato=attivo'); ?>" class="button" style="width: 100%;">
						🟢 Vedi Attivi (<?php echo $stats['attivi']; ?>)
					</a>
				</p>
				
				<hr>
				
				<h3>🔄 Rinnovi Automatici Oggi</h3>
				<?php if ($rinnovi_oggi > 0): ?>
					<p style="color: green; font-weight: bold;">
						✅ <?php echo $rinnovi_oggi; ?> contratti rinnovati automaticamente
					</p>
				<?php else: ?>
					<p style="color: #666;">
						Nessun rinnovo automatico oggi
					</p>
				<?php endif; ?>
				
				<hr>
				
				<h3>📧 Ultima Sincronizzazione FIC</h3>
				<?php 
				$last_sync = get_option('spm_last_sync_timestamp');
				if ($last_sync): ?>
					<p style="color: #666;">
						<?php echo esc_html($last_sync); ?>
					</p>
				<?php else: ?>
					<p style="color: #666;">
						Mai sincronizzato
					</p>
				<?php endif; ?>
				
				<p>
					<a href="<?php echo admin_url('admin.php?spm_test_sync=1'); ?>" class="button" style="width: 100%;">
						🔄 Sincronizza Clienti
					</a>
				</p>
			</div>
		</div>
		
		<!-- GRAFICO RICAVI -->
		<div style="background: white; padding: 20px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			<h2>📈 Ricavi Ricorrenti Mensili</h2>
			<?php spm_render_revenue_chart(); ?>
		</div>
		
	</div>
	<?php
}

/**
 * Ottieni statistiche contratti
 */
function spm_get_contract_stats() {
	$stats = [
		'totali' => 0,
		'attivi' => 0,
		'scaduti' => 0,
		'sospesi' => 0,
		'cessati' => 0,
		'in_scadenza' => 0,
		'valore_mensile' => 0
	];
	
	// Query per stati
	$stati = ['attivo', 'scaduto', 'sospeso', 'cessato'];
	foreach ($stati as $stato) {
		$count = new WP_Query([
			'post_type' => 'contratti',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'meta_query' => [[
				'key' => 'stato',
				'value' => $stato
			]],
			'fields' => 'ids'
		]);
		$stats[$stato === 'attivo' ? 'attivi' : ($stato === 'scaduto' ? 'scaduti' : ($stato === 'sospeso' ? 'sospesi' : 'cessati'))] = $count->found_posts;
		$stats['totali'] += $count->found_posts;
	}
	
	// Contratti in scadenza (prossimi 30 giorni)
	$date_limit = date('Y-m-d', strtotime('+30 days'));
	$in_scadenza = new WP_Query([
		'post_type' => 'contratti',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'meta_query' => [
			'relation' => 'AND',
			[
				'key' => 'stato',
				'value' => 'attivo'
			],
			[
				'key' => 'data_prossima_scadenza',
				'value' => date('Y-m-d'),
				'compare' => '>=',
				'type' => 'DATE'
			],
			[
				'key' => 'data_prossima_scadenza',
				'value' => $date_limit,
				'compare' => '<=',
				'type' => 'DATE'
			]
		],
		'fields' => 'ids'
	]);
	$stats['in_scadenza'] = $in_scadenza->found_posts;
	
	// Calcola valore mensile ricorrente
	$contratti_attivi = new WP_Query([
		'post_type' => 'contratti',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'meta_query' => [[
			'key' => 'stato',
			'value' => 'attivo'
		]]
	]);
	
	if ($contratti_attivi->have_posts()) {
		foreach ($contratti_attivi->posts as $contratto) {
			$prezzo = get_field('prezzo_contratto', $contratto->ID);
			if (!$prezzo) {
				$servizio_id = get_field('servizio', $contratto->ID);
				$prezzo = get_field('prezzo_base', $servizio_id);
			}
			
			$frequenza = get_field('frequenza', $contratto->ID);
			
			// Converti tutto a base mensile
			switch ($frequenza) {
				case 'mensile':
					$stats['valore_mensile'] += $prezzo;
					break;
				case 'trimestrale':
					$stats['valore_mensile'] += ($prezzo / 3);
					break;
				case 'semestrale':
					$stats['valore_mensile'] += ($prezzo / 6);
					break;
				case 'annuale':
					$stats['valore_mensile'] += ($prezzo / 12);
					break;
			}
		}
	}
	
	return $stats;
}

/**
 * Ottieni prossime scadenze
 */
function spm_get_prossime_scadenze($limit = 10) {
	$scadenze = [];
	
	$query = new WP_Query([
		'post_type' => 'contratti',
		'post_status' => 'publish',
		'posts_per_page' => $limit,
		'meta_query' => [
			[
				'key' => 'stato',
				'value' => 'attivo'
			],
			[
				'key' => 'data_prossima_scadenza',
				'value' => date('Y-m-d'),
				'compare' => '>=',
				'type' => 'DATE'
			]
		],
		'meta_key' => 'data_prossima_scadenza',
		'orderby' => 'meta_value',
		'order' => 'ASC'
	]);
	
	if ($query->have_posts()) {
		foreach ($query->posts as $post) {
			$cliente_id = get_field('cliente', $post->ID);
			$servizio_id = get_field('servizio', $post->ID);
			$scadenza = get_field('data_prossima_scadenza', $post->ID);
			
			$scadenze[] = [
				'id' => $post->ID,
				'cliente' => get_the_title($cliente_id),
				'servizio' => get_the_title($servizio_id),
				'scadenza' => $scadenza,
				'scadenza_display' => SPM_Date_Helper::to_display_format($scadenza),
				'giorni' => SPM_Date_Helper::days_until_due($scadenza)
			];
		}
	}
	
	return $scadenze;
}

/**
 * Conta rinnovi automatici di oggi
 */
function spm_get_rinnovi_oggi() {
	$count = 0;
	
	$query = new WP_Query([
		'post_type' => 'contratti',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'meta_query' => [[
			'key' => 'storico_rinnovi',
			'compare' => 'EXISTS'
		]]
	]);
	
	if ($query->have_posts()) {
		foreach ($query->posts as $post) {
			$storico = get_field('storico_rinnovi', $post->ID);
			if ($storico && is_array($storico)) {
				foreach ($storico as $rinnovo) {
					if (isset($rinnovo['data_rinnovo']) && 
						$rinnovo['data_rinnovo'] === date('Y-m-d') && 
						isset($rinnovo['tipo']) && 
						$rinnovo['tipo'] === 'automatico') {
						$count++;
					}
				}
			}
		}
	}
	
	return $count;
}

/**
 * Render grafico ricavi (semplice)
 */
function spm_render_revenue_chart() {
	// Calcola ricavi ultimi 6 mesi
	$mesi = [];
	for ($i = 5; $i >= 0; $i--) {
		$data = date('Y-m', strtotime("-$i months"));
		$mesi[$data] = 0;
	}
	
	// Query contratti attivi per periodo
	$query = new WP_Query([
		'post_type' => 'contratti',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'meta_query' => [[
			'key' => 'stato',
			'value' => ['attivo', 'scaduto'],
			'compare' => 'IN'
		]]
	]);
	
	if ($query->have_posts()) {
		foreach ($query->posts as $post) {
			$data_attivazione = get_field('data_attivazione', $post->ID);
			if (!$data_attivazione) continue;
			
			$prezzo = get_field('prezzo_contratto', $post->ID);
			if (!$prezzo) {
				$servizio_id = get_field('servizio', $post->ID);
				$prezzo = get_field('prezzo_base', $servizio_id);
			}
			
			$frequenza = get_field('frequenza', $post->ID);
			
			// Calcola valore mensile
			$valore_mensile = 0;
			switch ($frequenza) {
				case 'mensile':
					$valore_mensile = $prezzo;
					break;
				case 'trimestrale':
					$valore_mensile = $prezzo / 3;
					break;
				case 'semestrale':
					$valore_mensile = $prezzo / 6;
					break;
				case 'annuale':
					$valore_mensile = $prezzo / 12;
					break;
			}
			
			// Aggiungi ai mesi attivi
			foreach ($mesi as $mese => &$totale) {
				if ($data_attivazione <= $mese . '-31') {
					$totale += $valore_mensile;
				}
			}
		}
	}
	
	// Render grafico semplice con barre CSS
	?>
	<div style="display: flex; align-items: flex-end; height: 200px; gap: 10px; padding: 20px 0;">
		<?php 
		$max = max($mesi) ?: 1;
		foreach ($mesi as $mese => $valore): 
			$percentuale = ($valore / $max) * 100;
			$mese_label = date('M Y', strtotime($mese . '-01'));
		?>
			<div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
				<div style="background: #2271b1; width: 100%; height: <?php echo $percentuale; ?>%; min-height: 2px; position: relative;">
					<span style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); white-space: nowrap; font-size: 12px;">
						€<?php echo number_format($valore, 0); ?>
					</span>
				</div>
				<div style="margin-top: 10px; font-size: 12px; color: #666;">
					<?php echo $mese_label; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}