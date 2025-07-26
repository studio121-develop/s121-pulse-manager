<?php

defined('ABSPATH') || exit;

/**
 * 🔁 Rinnovo manuale del contratto servizi_cliente
 */
function spm_rinnova_contratto_manualmente($post_id) {
	if (get_post_type($post_id) !== 'servizi_cliente') return;

	$frequenza = get_field('frequenza_corrente', $post_id);
	$scadenza_attuale = get_field('data_scadenza', $post_id);
	$stato = get_field('stato_contratto', $post_id);
	if ($stato === 'dismesso' || !$frequenza) return;

	// 🛑 Blocco rinnovo se la scadenza è già nel futuro
	$oggi = new DateTime();
	$scadenza_dt = DateTime::createFromFormat('d/m/Y', $scadenza_attuale);
	if ($scadenza_dt && $scadenza_dt > $oggi) {
		return;
	}

	// Calcolo decorrenza: da scadenza, altrimenti da data_inizio
	$base_data = $scadenza_attuale ?: get_field('data_inizio', $post_id);
	$base = DateTime::createFromFormat('d/m/Y', $base_data);
	if (!$base) return;

	// Calcolo nuova scadenza
	switch ($frequenza) {
		case 'mensile':     $base->modify('+1 month'); break;
		case 'trimestrale': $base->modify('+3 months'); break;
		case 'semestrale':  $base->modify('+6 months'); break;
		case 'annuale':     $base->modify('+1 year'); break;
		default: return;
	}

	update_field('data_ultimo_rinnovo', $base_data, $post_id);
	update_field('data_scadenza', $base->format('d/m/Y'), $post_id);
	update_field('stato_contratto', 'attivo', $post_id);

	$log = get_field('log_eventi', $post_id) ?: [];
	$log[] = [
		'data' => $oggi->format('d/m/Y'),
		'tipo' => 'rinnovo_manuale',
		'descrizione' => 'Rinnovo manuale eseguito da admin – nuova scadenza: ' . $base->format('d/m/Y'),
	];
	update_field('log_eventi', $log, $post_id);
}


/**
 * 🔄 Rinnovo automatico via CRON
 */
function spm_rinnovo_cron_automatico() {
	$args = [
		'post_type' => 'servizi_cliente',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'meta_query' => [
			[
				'key' => 'tipo_rinnovo',
				'value' => 'auto_ricorrente',
			],
			[
				'key' => 'stato_contratto',
				'value' => 'attivo',
			],
		],
	];

	$query = new WP_Query($args);
	if (!$query->have_posts()) return;

	foreach ($query->posts as $post) {
		$post_id = $post->ID;
		$scadenza = get_field('data_scadenza', $post_id);
		if (!$scadenza) continue;

		$oggi = new DateTime();
		$scadenza_date = DateTime::createFromFormat('d/m/Y', $scadenza);
		if (!$scadenza_date || $scadenza_date > $oggi) continue;

		// Rinnova e logga
		spm_rinnova_contratto_manualmente($post_id);

		// Aggiunta log separato solo per cron (per chiarezza)
		$log = get_field('log_eventi', $post_id) ?: [];
		$log[] = [
			'data' => $oggi->format('d/m/Y'),
			'tipo' => 'rinnovo_automatico',
			'descrizione' => 'Rinnovo automatico eseguito dal sistema – nuova scadenza calcolata',
		];
		update_field('log_eventi', $log, $post_id);
	}
}

// 🔁 Hook giornaliero del CRON
if (!wp_next_scheduled('spm_cron_rinnovi')) {
	wp_schedule_event(time(), 'daily', 'spm_cron_rinnovi');
}
add_action('spm_cron_rinnovi', 'spm_rinnovo_cron_automatico');
