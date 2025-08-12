<?php
defined('ABSPATH') || exit;

use FattureInCloud\Api\ClientsApi;
use FattureInCloud\Configuration;
use GuzzleHttp\Client;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/oauth-utils.php';

//
// 🔧 Normalizzazione (robusta su null/empty)
//
function spm_normalize_string($value) {
	if ($value === null) return '';
	return ucwords(strtolower(trim((string)$value)));
}

function spm_normalize_telefono($tel) {
	if ($tel === null) return '';
	return preg_replace('/\s+|-|\./', '', trim((string)$tel));
}

//
// ✅ Confronto & set sicuro (2, 3)
//
function spm_values_differ($old, $new) {
	$o = is_string($old) ? trim((string)$old) : $old;
	$n = is_string($new) ? trim((string)$new) : $new;
	return ($n !== '' && $n !== null && $o !== $n);
}

function spm_update_field_safe($post_id, $field_key, $new_val) {
	// Non scrivere null o stringhe vuote
	if ($new_val === null) return false;
	if (is_string($new_val) && trim($new_val) === '') return false;

	// Evita scritture inutili se invariato
	$curr = get_field($field_key, $post_id);
	if (!spm_values_differ($curr, $new_val)) return false;

	return update_field($field_key, $new_val, $post_id);
}

//
// 🚦 Wrapper listClients con retry/backoff (5)
//
function spm_list_clients_with_retry($api, $company_id, $page, $per_page = 100, $max_attempts = 3) {
	$attempt = 0;
	$delay = 0.7; // secondi
	do {
		try {
			return $api->listClients(
				$company_id,
				null,          // fields
				'detailed',    // fieldset
				'name',        // sort
				$page,
				$per_page
			);
		} catch (Exception $e) {
			$code = (int) (method_exists($e, 'getCode') ? $e->getCode() : 0);
			// Retry solo su 429/5xx
			if ($code === 429 || ($code >= 500 && $code <= 599)) {
				$attempt++;
				if ($attempt >= $max_attempts) throw $e;
				usleep((int)($delay * 1e6));
				$delay *= 2; // backoff esponenziale
				continue;
			}
			throw $e; // 4xx e altri: no retry
		}
	} while ($attempt < $max_attempts);
}

//
// 🔁 Recupera tutti i clienti con paginazione (usa retry)
//
function get_all_clienti_fic($company_id, $apiInstance) {
	$all_clienti = [];
	$page = 1;
	$per_page = 100;
	$max_pages = 100; // salvagente

	do {
		$response = spm_list_clients_with_retry($apiInstance, $company_id, $page, $per_page);
		$clienti = is_object($response) && method_exists($response, 'getData') ? $response->getData() : [];
		if (!is_array($clienti) || count($clienti) === 0) break;

		$all_clienti = array_merge($all_clienti, $clienti);
		if (count($clienti) < $per_page) break; // ultima pagina
		$page++;
	} while ($page <= $max_pages);

	return $all_clienti;
}

//
// ✅ Sincronizza CPT "clienti" (2, 3, 4, 5)
// Ritorna un array riepilogo per log o UI admin.
//
function sync_clienti_da_fic($debug = false) {
	$summary = [
		'ok'       => true,
		'error'    => null,
		'total'    => 0,
		'created'  => 0,
		'updated'  => 0,
		'skipped'  => 0,
		'failed'   => 0,
		'duration' => 0.0,
	];

	$access_token = get_valid_token();
	if (!$access_token) {
		$msg = '[S121 Sync] ❌ Token non valido.';
		$debug ? print('<p>'.$msg.'</p>') : error_log($msg);
		$summary['ok'] = false;
		$summary['error'] = 'token';
		return $summary;
	}

	$start_time = microtime(true);

	$company_id = get_option('spm_fic_company_id');
	if (!$company_id) {
		$msg = '[S121 Sync] ❌ Company ID mancante.';
		$debug ? print('<p>'.$msg.'</p>') : error_log($msg);
		$summary['ok'] = false;
		$summary['error'] = 'company_id';
		return $summary;
	}

	$config = Configuration::getDefaultConfiguration()->setAccessToken($access_token);
	$api    = new ClientsApi(new Client(), $config);

	try {
		$clienti = get_all_clienti_fic($company_id, $api);
		$summary['total'] = count($clienti);

		if ($debug) {
			echo '<div style="padding:1rem;font-family:sans-serif">';
			echo '<h2>🔍 Debug: Sincronizzazione Clienti ('.$summary['total'].' da FIC)</h2>';
			echo '<table border="1" cellpadding="6" cellspacing="0">';
			echo '<tr><th>Nome</th><th>Email</th><th>P.IVA</th><th>CF</th><th>Esito</th></tr>';
		}

		foreach ($clienti as $client) {
			try {
				$id_fic   = $client->getId();
				$cf       = strtoupper(sanitize_text_field($client->getTaxCode() ?? ''));
				$piva     = strtoupper(sanitize_text_field($client->getVatNumber() ?? ''));
				$nome     = spm_normalize_string($client->getName());
				$email    = strtolower(sanitize_email($client->getEmail() ?? ''));
				$telefono = $client->getPhone() ? spm_normalize_telefono($client->getPhone()) : '';
				$indirizzo= spm_normalize_string($client->getAddressStreet() ?? '');
				$note     = sanitize_textarea_field($client->getNotes() ?? '');

				// Cerca post esistente per id_fatture_in_cloud
				$existing = new WP_Query([
					'post_type'      => 'clienti',
					'post_status'    => ['publish','draft','pending'],
					'meta_query'     => [[ 'key'=>'id_fatture_in_cloud', 'value'=>$id_fic ]],
					'fields'         => 'ids',
					'posts_per_page' => 1
				]);

				if (!empty($existing->posts)) {
					$post_id = $existing->posts[0];

					// Applica solo cambiamenti reali, senza scrivere vuoti
					$changed = false;
					$changed = spm_update_field_safe($post_id, 'email',          $email)     || $changed;
					$changed = spm_update_field_safe($post_id, 'telefono',       $telefono)  || $changed;
					$changed = spm_update_field_safe($post_id, 'partita_iva',    $piva)      || $changed;
					$changed = spm_update_field_safe($post_id, 'codice_fiscale', $cf)        || $changed;
					$changed = spm_update_field_safe($post_id, 'indirizzo',      $indirizzo) || $changed;
					$changed = spm_update_field_safe($post_id, 'note_fic',       $note)      || $changed;

					if ($changed) {
						$summary['updated']++;
						if ($debug) {
							echo '<tr style="background:#fff8dc"><td>'.esc_html($nome).'</td><td>'.esc_html($email).'</td><td>'.esc_html($piva).'</td><td>'.esc_html($cf).'</td><td>🔁 Aggiornato</td></tr>';
						}
					} else {
						$summary['skipped']++;
						if ($debug) {
							echo '<tr><td>'.esc_html($nome).'</td><td>'.esc_html($email).'</td><td>'.esc_html($piva).'</td><td>'.esc_html($cf).'</td><td>⏭️ Nessun cambiamento</td></tr>';
						}
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
					// Scrive solo valori non vuoti
					spm_update_field_safe($post_id, 'email',               $email);
					spm_update_field_safe($post_id, 'telefono',            $telefono);
					spm_update_field_safe($post_id, 'partita_iva',         $piva);
					spm_update_field_safe($post_id, 'codice_fiscale',      $cf);
					spm_update_field_safe($post_id, 'indirizzo',           $indirizzo);
					// id_fatture_in_cloud è la chiave: deve essere scritto sempre (non è vuoto)
					update_field('id_fatture_in_cloud', $id_fic, $post_id);
					spm_update_field_safe($post_id, 'note_fic',            $note);

					$summary['created']++;
					if ($debug) {
						echo '<tr style="background:#e7f7ee"><td>'.esc_html($nome).'</td><td>'.esc_html($email).'</td><td>'.esc_html($piva).'</td><td>'.esc_html($cf).'</td><td>✅ Creato</td></tr>';
					}
				} else {
					$summary['failed']++;
					if ($debug) {
						echo '<tr style="background:#f2dede"><td>'.esc_html($nome).'</td><td>'.esc_html($email).'</td><td>'.esc_html($piva).'</td><td>'.esc_html($cf).'</td><td>❌ Errore creazione</td></tr>';
					}
				}

			} catch (Exception $perItem) {
				$summary['failed']++;
				error_log('[S121 Sync] ❌ Errore su cliente ID '.$client->getId().': '.$perItem->getMessage());
				if ($debug) {
					echo '<tr style="background:#f2dede"><td colspan="5">'.esc_html('Errore su cliente ID '.$client->getId().': '.$perItem->getMessage()).'</td></tr>';
				}
			}
		}

		if ($debug) {
			$tempo = round(microtime(true) - $start_time, 2);
			echo '</table>';
			echo '<h3>📊 Riepilogo:</h3>';
			echo '<ul>';
			echo '<li>🧾 Totale clienti in FIC: <strong>' . $summary['total']   . '</strong></li>';
			echo '<li>✅ Creati in WordPress:  <strong>' . $summary['created'] . '</strong></li>';
			echo '<li>🔁 Aggiornati:           <strong>' . $summary['updated'] . '</strong></li>';
			echo '<li>⏭️ Skippati:             <strong>' . $summary['skipped'] . '</strong></li>';
			echo '<li>❌ Errori:               <strong>' . $summary['failed']  . '</strong></li>';
			echo '<li>⏱️ Tempo:                 <strong>' . $tempo             . 's</strong></li>';
			echo '</ul></div>';
		}

	} catch (Exception $e) {
		$msg = '[S121 Sync] ❌ Errore API: ' . $e->getMessage();
		$debug ? print('<p>' . esc_html($msg) . '</p>') : error_log($msg);
		$summary['ok'] = false;
		$summary['error'] = 'api';
	}

	// Salva data/ora ultima sync (4)
	update_option('spm_last_sync_timestamp', current_time('mysql'));
	update_option('spm_last_sync_method', $debug ? 'manuale' : 'cron');

	$summary['duration'] = round(microtime(true) - $start_time, 2);
	return $summary;
}
