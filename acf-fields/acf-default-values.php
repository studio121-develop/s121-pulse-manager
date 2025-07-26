<?php
defined('ABSPATH') || exit;

/**
 * Prepopola i campi di servizi_cliente con i dati del servizio selezionato
 */

// 1. Ricorrenza personalizzata ← frequenza_ricorrenza
add_filter('acf/load_value/name=ricorrenza_personalizzata', function ($value, $post_id) {
	if (!empty($value)) return $value;

	$servizio_id = get_field('servizio', $post_id);
	if (!$servizio_id) return $value;

	return get_field('frequenza_ricorrenza', $servizio_id);
}, 10, 2);

// 2. Prezzo personalizzato ← prezzo_base
add_filter('acf/load_value/name=prezzo_personalizzato', function ($value, $post_id) {
	if (!empty($value)) return $value;

	$servizio_id = get_field('servizio', $post_id);
	if (!$servizio_id) return $value;

	return get_field('prezzo_base', $servizio_id);
}, 10, 2);

// 3. Giorni pre-reminder ← giorni_pre_reminder
add_filter('acf/load_value/name=giorni_pre_reminder', function ($value, $post_id) {
	if (!empty($value)) return $value;

	$servizio_id = get_field('servizio', $post_id);
	if (!$servizio_id) return $value;

	return get_field('giorni_pre_reminder', $servizio_id);
}, 10, 2);

// 4. Calcolo automatico della data di scadenza se non compilata manualmente
add_action('acf/save_post', function ($post_id) {
	if (get_post_type($post_id) !== 'servizi_cliente') return;

	$data_inizio = get_field('data_inizio', $post_id);
	$data_scadenza = get_field('data_scadenza', $post_id);
	if (!$data_inizio || $data_scadenza) return;

	// Parsing data_inizio nel formato ACF visivo (d/m/Y)
	$date = DateTime::createFromFormat('d/m/Y', $data_inizio);
	if (!$date) return;

	$ricorrenza = get_field('ricorrenza_personalizzata', $post_id);
	if (!$ricorrenza) {
		$servizio_id = get_field('servizio', $post_id);
		$ricorrenza = get_field('frequenza_ricorrenza', $servizio_id);
	}

	switch ($ricorrenza) {
		case 'mensile':
			$date->modify('+1 month');
			break;
		case 'trimestrale':
			$date->modify('+3 months');
			break;
		case 'semestrale':
			$date->modify('+6 months');
			break;
		case 'annuale':
			$date->modify('+1 year');
			break;
		default:
			return;
	}

	// Salva nel formato richiesto da ACF (Ymd)
	update_field('data_scadenza', $date->format('Ymd'), $post_id);
}, 20);
