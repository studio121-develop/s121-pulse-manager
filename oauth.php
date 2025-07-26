<?php
require_once(__DIR__ . '/vendor/autoload.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/121tools/wp-load.php');

use FattureInCloud\OAuth2\OAuth2AuthorizationCode\OAuth2AuthorizationCodeManager;
use FattureInCloud\OAuth2\Scope;
use FattureInCloud\Api\UserApi;
use GuzzleHttp\Client;

session_set_cookie_params(86400);
session_start();

// 🔐 Credenziali app Fatture in Cloud
$clientId = "APSzK7BJjV5PsKWnrHwKeV3eSU91isoh";
$clientSecret = "5Xd9ABBPXl1577NsSqyQlDmDrVgcqSRsv9Y85h1yWSSdWwUZAdy6kTcPlpuigU2q";
$redirectUri = "https://dev.studio121.it/121tools/wp-content/plugins/s121-pulse-manager/oauth.php";

// Istanzia OAuth manager
$oauth = new OAuth2AuthorizationCodeManager($clientId, $clientSecret, $redirectUri);

// ✅ Se già salvato, mostra i token
if (get_option('spm_fic_access_token')) {
	echo '✅ Access token già presente in WordPress: <br>' . get_option('spm_fic_access_token');
	exit;
}

// 🔁 Step 1: Reindirizza verso pagina autorizzazione
if (!isset($_GET['code'])) {
	$url = $oauth->getAuthorizationUrl([
		Scope::ENTITY_CLIENTS_READ
	], "state_121pulse");

	header('Location: ' . $url);
	exit;
}

// ✅ Step 2: Callback dopo autorizzazione
try {
	$obj = $oauth->fetchToken($_GET['code']);

	if (!isset($obj->error)) {
		// Salva i token
		update_option('spm_fic_access_token', $obj->getAccessToken());
		update_option('spm_fic_refresh_token', $obj->getRefreshToken());

		// Recupera l'elenco aziende
		$config = FattureInCloud\Configuration::getDefaultConfiguration()->setAccessToken($obj->getAccessToken());
		$userApi = new UserApi(new Client(), $config);
		$companies = $userApi->listUserCompanies()->getData()->getCompanies();

		if (isset($companies[1])) {
			update_option('spm_fic_company_id', $companies[1]->getId());
		} elseif (isset($companies[0])) {
			update_option('spm_fic_company_id', $companies[0]->getId());
		}

		echo '✅ Token salvato nella sessione e nelle opzioni di WordPress.<br>';
		echo 'Access token: ' . $obj->getAccessToken() . '<br>';
		echo 'Refresh token: ' . $obj->getRefreshToken() . '<br>';
		echo 'Company ID: ' . get_option('spm_fic_company_id');
	} else {
		echo '❌ Errore: ' . $obj->error;
	}
} catch (Exception $e) {
	echo '❌ Eccezione: ' . $e->getMessage();
}
?>
