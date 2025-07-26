<?php
defined('ABSPATH') || exit;

use FattureInCloud\Api\ClientsApi;
use FattureInCloud\Configuration;
use GuzzleHttp\Client;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/oauth-utils.php';

//
// 🔧 Normalizzazione
//
function spm_normalize_string($value) {
	return ucwords(strtolower(trim($value)));
}

function spm_normalize_telefono($tel) {
	return preg_replace('/\s+|-|\./', '', trim($tel));
}

//
// 🔁 Recupera tutti i clienti con paginazione
//
function get_all_clienti_fic($company_id, $apiInstance) {
	$all_clienti = [];
	$page = 1;
	$max_pages = 50;

	do {
		$response = $apiInstance->listClients(
			$company_id,
			null,         // fields
			'detailed',   // fieldset
			'name',       // sort
			$page,
			100
		);

		$clienti = $response->getData();
		if (!is_array($clienti) || count($clienti) === 0) break;

		$all_clienti = array_merge($all_clienti, $clienti);
		$page++;
	} while ($page <= $max_pages);

	return $all_clienti;
}

//
// ✅ Sincronizza CPT "clienti"
//
function sync_clienti_da_fic($debug = false) {
	$access_token = get_valid_token();
	if (!$access_token) {
		$debug ? print('<p>❌ Token non valido.</p>') : error_log('[S121 Sync] ❌ Token non valido.');
		return;
	}

	$start_time = microtime(true);

	$company_id = get_option('spm_fic_company_id');
	if (!$company_id) {
		$debug ? print('<p>❌ Company ID mancante.</p>') : error_log('[S121 Sync] ❌ Company ID mancante.');
		return;
	}

	$config = Configuration::getDefaultConfiguration()->setAccessToken($access_token);
	$apiInstance = new ClientsApi(new Client(), $config);

	$total_fic      = 0;
	$total_created  = 0;
	$total_updated  = 0;
	$total_failed   = 0;

	try {
		$clienti = get_all_clienti_fic($company_id, $apiInstance);
		$total_fic = count($clienti);

		if ($debug) {
			echo '<div style="padding:1rem;font-family:sans-serif">';
			echo '<h2>🔍 Debug: Sincronizzazione Clienti (' . $total_fic . ' da FIC)</h2>';
			echo '<table border="1" cellpadding="6" cellspacing="0">';
			echo '<tr><th>Nome</th><th>Email</th><th>P.IVA</th><th>CF</th><th>Esito</th></tr>';
		}

		foreach ($clienti as $client) {
			$id_fic   = $client->getId();
			$cf       = strtoupper(sanitize_text_field($client->getTaxCode() ?? ''));
			$piva     = strtoupper(sanitize_text_field($client->getVatNumber() ?? ''));
			$nome     = spm_normalize_string($client->getName());
			$email    = strtolower(sanitize_email($client->getEmail()));
			$telefono = spm_normalize_telefono($client->getPhone());
			$indirizzo= spm_normalize_string($client->getAddressStreet());
			$note     = sanitize_textarea_field($client->getNotes() ?? '');

			$existing = new WP_Query([
				'post_type'      => 'clienti',
				'post_status'    => ['publish', 'draft', 'pending'], // esclude "trash"
				'meta_query'     => [
					[
						'key'   => 'id_fatture_in_cloud',
						'value' => $id_fic
					]
				],
				'fields' => 'ids',
				'posts_per_page' => 1
			]);

			if (!empty($existing->posts)) {
				$post_id = $existing->posts[0];

				update_field('email',               $email,     $post_id);
				update_field('telefono',            $telefono,  $post_id);
				update_field('partita_iva',         $piva,      $post_id);
				update_field('codice_fiscale',      $cf,        $post_id);
				update_field('indirizzo',           $indirizzo, $post_id);
				update_field('note_fic',            $note,      $post_id);

				$total_updated++;
				if ($debug) {
					echo '<tr style="background:#fff8dc"><td>' . esc_html($nome) . '</td><td>' . esc_html($email) . '</td><td>' . esc_html($piva) . '</td><td>' . esc_html($cf) . '</td><td>🔁 Aggiornato</td></tr>';
				}
				continue;
			}

			// Nuovo cliente
			$post_id = wp_insert_post([
				'post_type'   => 'clienti',
				'post_title'  => $nome,
				'post_status' => 'publish'
			]);

			if (!is_wp_error($post_id)) {
				$total_created++;

				update_field('email',               $email,     $post_id);
				update_field('telefono',            $telefono,  $post_id);
				update_field('partita_iva',         $piva,      $post_id);
				update_field('codice_fiscale',      $cf,        $post_id);
				update_field('indirizzo',           $indirizzo, $post_id);
				update_field('id_fatture_in_cloud', $id_fic,    $post_id);
				update_field('note_fic',            $note,      $post_id);

				if ($debug) {
					echo '<tr style="background:#dff0d8"><td>' . esc_html($nome) . '</td><td>' . esc_html($email) . '</td><td>' . esc_html($piva) . '</td><td>' . esc_html($cf) . '</td><td>✅ Creato</td></tr>';
				}
			} else {
				$total_failed++;
				if ($debug) {
					echo '<tr style="background:#f2dede"><td>' . esc_html($nome) . '</td><td>' . esc_html($email) . '</td><td>' . esc_html($piva) . '</td><td>' . esc_html($cf) . '</td><td>❌ Errore</td></tr>';
				}
			}
		}

		if ($debug) {
			$tempo = round(microtime(true) - $start_time, 2);
			echo '</table>';
			echo '<h3>📊 Riepilogo:</h3>';
			echo '<ul>';
			echo '<li>🧾 Totale clienti in FIC: <strong>' . $total_fic . '</strong></li>';
			echo '<li>✅ Creati in WordPress: <strong>' . $total_created . '</strong></li>';
			echo '<li>🔁 Aggiornati: <strong>' . $total_updated . '</strong></li>';
			echo '<li>❌ Errori: <strong>' . $total_failed . '</strong></li>';
			echo '<li>⏱️ Tempo: <strong>' . $tempo . 's</strong></li>';
			echo '</ul></div>';
		}

	} catch (Exception $e) {
		$msg = '[S121 Sync] ❌ Errore API: ' . $e->getMessage();
		$debug ? print('<p>' . esc_html($msg) . '</p>') : error_log($msg);
	}
	
	// Salva data/ora ultima sync
	update_option('spm_last_sync_timestamp', current_time('mysql'));
	update_option('spm_last_sync_method', $debug ? 'manuale' : 'cron');
}
