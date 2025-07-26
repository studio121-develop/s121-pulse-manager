<?php
defined('ABSPATH') || exit;

use FattureInCloud\Api\UserApi;
use FattureInCloud\Configuration;
use GuzzleHttp\Client;

function get_valid_token() {
	$access_token = get_option('spm_fic_access_token');
	$refresh_token = get_option('spm_fic_refresh_token');
	$client_id = "APSzK7BJjV5PsKWnrHwKeV3eSU91isoh";
	$client_secret = "5Xd9ABBPXl1577NsSqyQlDmDrVgcqSRsv9Y85h1yWSSdWwUZAdy6kTcPlpuigU2q";

	$config = Configuration::getDefaultConfiguration()->setAccessToken($access_token);
	$testApi = new UserApi(new Client(), $config);

	try {
		$testApi->listUserCompanies(); // Token valido
		return $access_token;
	} catch (Exception $e) {
		// Token scaduto → tenta refresh
		$client = new Client();
		$response = $client->post('https://api.fattureincloud.it/v1/oauth/token', [
			'form_params' => [
				'grant_type' => 'refresh_token',
				'refresh_token' => $refresh_token,
				'client_id' => $client_id,
				'client_secret' => $client_secret
			]
		]);

		if ($response->getStatusCode() !== 200) {
			error_log('[S121 OAuth] ❌ Errore nel refresh del token.');
			return false;
		}

		$body = json_decode($response->getBody(), true);
		update_option('spm_fic_access_token', $body['access_token']);

		if (isset($body['refresh_token'])) {
			update_option('spm_fic_refresh_token', $body['refresh_token']);
		}

		return $body['access_token'];
	}
}
