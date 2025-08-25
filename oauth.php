<?php
require_once(__DIR__ . '/vendor/autoload.php');
// Carica WordPress in modo sicuro
$wp_load_path = dirname(__FILE__, 4) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    // Fallback per installazioni non standard
    $wp_load_path = $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
}
require_once($wp_load_path);

use FattureInCloud\OAuth2\OAuth2AuthorizationCode\OAuth2AuthorizationCodeManager;
use FattureInCloud\OAuth2\Scope;
use FattureInCloud\Api\UserApi;
use GuzzleHttp\Client;

session_set_cookie_params(86400);
session_start();

// 🔐 Credenziali app Fatture in Cloud dalle impostazioni
require_once plugin_dir_path(__FILE__) . 'includes/oauth-utils.php';
$creds = get_fic_credentials();

if (!$creds) {
	wp_die('⚠️ Credenziali Fatture in Cloud non configurate. <br><a href="' . esc_url(admin_url('admin.php?page=spm-settings')) . '">Vai alle impostazioni</a> per configurarle.');
}

$clientId = $creds['client_id'];
$clientSecret = $creds['client_secret'];
$redirectUri = $creds['redirect_uri'];

// Istanzia OAuth manager
$oauth = new OAuth2AuthorizationCodeManager($clientId, $clientSecret, $redirectUri);

// ✅ Se già salvato, mostra i token (mascherati per sicurezza)
if (get_option('spm_fic_access_token')) {
	$token = get_option('spm_fic_access_token');
	$masked_token = substr($token, 0, 8) . str_repeat('•', max(0, strlen($token) - 12)) . substr($token, -4);
	echo '✅ Access token già presente in WordPress: <br><code>' . esc_html($masked_token) . '</code>';
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
	$code = sanitize_text_field($_GET['code']);
	$obj = $oauth->fetchToken($code);

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
		
		// Maschera i token per sicurezza
		$access_token = $obj->getAccessToken();
		$refresh_token = $obj->getRefreshToken();
		$masked_access = substr($access_token, 0, 8) . str_repeat('•', max(0, strlen($access_token) - 12)) . substr($access_token, -4);
		$masked_refresh = substr($refresh_token, 0, 8) . str_repeat('•', max(0, strlen($refresh_token) - 12)) . substr($refresh_token, -4);
		
		echo 'Access token: <code>' . esc_html($masked_access) . '</code><br>';
		echo 'Refresh token: <code>' . esc_html($masked_refresh) . '</code><br>';
		echo 'Company ID: ' . esc_html(get_option('spm_fic_company_id'));
	} else {
		echo '❌ Errore: ' . esc_html($obj->error);
	}
} catch (Exception $e) {
	echo '❌ Eccezione: ' . esc_html($e->getMessage());
}
?>
