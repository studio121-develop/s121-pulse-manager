<?php
defined('ABSPATH') || exit;

/**
 * 🔐 Token personale
 */
function spm_get_fic_access_token() {
	return 'a/eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJyZWYiOiJpTUNPNTdET3BkYjZzbm5Ga1VPZWRnQkI1bzNQbnVJVCJ9.iZ16mBazWDU06NtSoTWTLnEUn6267oUZdp6LkjXdtBo';
}

/**
 * 📥 Importa clienti da Fatture in Cloud nel CPT "cliente"
 */
function spm_scarica_clienti_fattureincloud() {
	$token = spm_get_fic_access_token();
	$company_id = 1138991;

	// 📡 Chiamata API
	$response = wp_remote_get("https://api.fattureincloud.it/v2/companies/$company_id/clients", [
		'headers' => [
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		],
	]);

	// 📦 Debug esteso
	$debug = [
		'timestamp' => current_time('mysql'),
		'http_code' => wp_remote_retrieve_response_code($response),
		'headers'   => wp_remote_retrieve_headers($response),
		'body'      => wp_remote_retrieve_body($response),
	];
	file_put_contents(plugin_dir_path(__FILE__) . 'log-clienti.json', json_encode($debug, JSON_PRETTY_PRINT));

	if (is_wp_error($response)) return 0;

	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);

	if (!isset($data['data']) || empty($data['data'])) return 0;

	foreach ($data['data'] as $cliente) {
		$id_fic   = $cliente['id'];
		$email    = $cliente['email'] ?? '';
		$piva     = $cliente['vat_number'] ?? '';
		$telefono = $cliente['phone'] ?? '';
		$note     = $cliente['notes'] ?? '';
		$nome     = $cliente['name'] ?? ('Cliente ' . $id_fic);

		$esistente = get_posts([
			'post_type'   => 'cliente',
			'meta_key'    => 'id_fatture_in_cloud',
			'meta_value'  => $id_fic,
			'numberposts' => 1
		]);

		$args = [
			'post_type'   => 'cliente',
			'post_status' => 'publish',
			'meta_input'  => [
				'email'                => $email,
				'partita_iva'         => $piva,
				'telefono'            => $telefono,
				'id_fatture_in_cloud' => $id_fic,
				'note_fic'            => $note,
			],
		];

		if ($esistente) {
			$args['ID'] = $esistente[0]->ID;
			wp_update_post($args);
		} else {
			$args['post_title'] = $nome;
			wp_insert_post($args);
		}
	}

	return count($data['data']);
}
