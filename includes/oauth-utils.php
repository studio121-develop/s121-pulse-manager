<?php
defined('ABSPATH') || exit;

use FattureInCloud\Api\UserApi;
use FattureInCloud\Configuration;
use GuzzleHttp\Client;

/**
 * Ottiene le credenziali Fatture in Cloud dalle impostazioni
 * @return array|false Array con [client_id, client_secret, redirect_uri] o false se non configurate
 */
function get_fic_credentials() {
	require_once plugin_dir_path(__FILE__) . 'class-settings-page.php';
	$client_id = SPM_Settings_Page::get('fic_client_id');
	$client_secret = SPM_Settings_Page::get('fic_client_secret');
	$redirect_uri = SPM_Settings_Page::get('fic_redirect_uri');
	
	if (empty($client_id) || empty($client_secret)) {
		return false;
	}
	
	// Auto-generate redirect URI se vuoto
	if (empty($redirect_uri)) {
		$redirect_uri = site_url('/wp-content/plugins/s121-pulse-manager/oauth.php');
	}
	
	return [
		'client_id' => $client_id,
		'client_secret' => $client_secret,
		'redirect_uri' => $redirect_uri
	];
}

function get_valid_token() {
	$access_token  = get_option('spm_fic_access_token');
	$refresh_token = get_option('spm_fic_refresh_token');
	
	// Ottieni credenziali dalle impostazioni
	$creds = get_fic_credentials();
	if (!$creds) {
		error_log('[S121 OAuth] ❌ Credenziali Fatture in Cloud non configurate nelle impostazioni.');
		return false;
	}
	
	$client_id = $creds['client_id'];
	$client_secret = $creds['client_secret'];

	$config = Configuration::getDefaultConfiguration()->setAccessToken($access_token);
	$testApi = new UserApi(new Client(), $config);

	try {
		$testApi->listUserCompanies(); // Token valido
		return $access_token;
	} catch (Exception $e) {
		// Token scaduto → tenta refresh
		$client = new Client();

		try {
			$response = $client->post('https://api-v2.fattureincloud.it/oauth/token', [
				'headers' => [
					'Accept' => 'application/json',
					'Content-Type' => 'application/x-www-form-urlencoded'
				],
				'form_params' => [
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh_token,
					'client_id'     => $client_id,
					'client_secret' => $client_secret
				]
			]);

			$body = json_decode($response->getBody(), true);
			if (!isset($body['access_token'])) {
				error_log('[S121 OAuth] ❌ Risposta invalida dal refresh token API.');
				return false;
			}

			update_option('spm_fic_access_token', $body['access_token']);
			if (isset($body['refresh_token'])) {
				update_option('spm_fic_refresh_token', $body['refresh_token']);
			}

			return $body['access_token'];

		} catch (Exception $err) {
			error_log('[S121 OAuth] ❌ Errore nel refresh del token: ' . $err->getMessage());
			return false;
		}
	}
}
