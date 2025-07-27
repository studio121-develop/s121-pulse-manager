<?php
defined('ABSPATH') || exit;

use FattureInCloud\Api\UserApi;
use FattureInCloud\Configuration;
use GuzzleHttp\Client;

function get_valid_token() {
	$access_token  = get_option('spm_fic_access_token');
	$refresh_token = get_option('spm_fic_refresh_token');
	$client_id     = "APSzK7BJjV5PsKWnrHwKeV3eSU91isoh";
	$client_secret = "5Xd9ABBPXl1577NsSqyQlDmDrVgcqSRsv9Y85h1yWSSdWwUZAdy6kTcPlpuigU2q";

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
