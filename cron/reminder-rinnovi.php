<?php
defined('ABSPATH') || exit;

add_action('spm_check_rinnovi', 'spm_controlla_rinnovi_servizi');

function spm_controlla_rinnovi_servizi() {
	$oggi = new DateTime();

	$servizi = get_posts([
		'post_type' => 'servizio_cliente',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'meta_query' => [
			[
				'key' => 'invia_reminder',
				'value' => '1',
				'compare' => '='
			],
			[
				'key' => 'data_prossimo_rinnovo',
				'compare' => 'EXISTS'
			]
		]
	]);

	foreach ($servizi as $servizio) {
		$rinnovo_str = get_field('data_prossimo_rinnovo', $servizio->ID);
		if (!$rinnovo_str) continue;

		$rinnovo_data = DateTime::createFromFormat('Y-m-d', $rinnovo_str);
		$reminder_list = get_field('reminder_personalizzati', $servizio->ID);
		$cliente_id = get_field('cliente_associato', $servizio->ID);
		$email = get_field('email', $cliente_id);
		$nome_cliente = get_the_title($cliente_id);
		$nome_servizio = get_the_title($servizio->ID);

		if (!$reminder_list || !$email) continue;

		foreach ($reminder_list as $i => $reminder) {
			if (!empty($reminder['inviato'])) continue;

			$data_target = clone $rinnovo_data;
			$data_target->modify('-' . intval($reminder['giorni_anticipo']) . ' days');

			if ($data_target->format('Y-m-d') === $oggi->format('Y-m-d')) {
				$messaggio = $reminder['testo_reminder'] ?: 
					"Gentile $nome_cliente,\n\nil tuo servizio \"$nome_servizio\" scadrà tra {$reminder['giorni_anticipo']} giorni. A breve riceverai la fattura.";

				wp_mail($email, "🔁 Promemoria servizio in scadenza", $messaggio);

				$reminder_list[$i]['inviato'] = true;
				update_field('reminder_personalizzati', $reminder_list, $servizio->ID);
			}
		}
	}

	wp_reset_postdata();
}
