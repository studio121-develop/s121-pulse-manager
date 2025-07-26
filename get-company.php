<?php
/**
 * Recupero Company ID da Fatture in Cloud
 * @version 1.0
 */

require_once(__DIR__ . '/vendor/autoload.php');

use FattureInCloud\Configuration;
use FattureInCloud\Api\UserApi;
use GuzzleHttp\Client;

session_start();

if (!isset($_SESSION['token'])) {
	die('❌ Nessun token trovato. Autenticati prima con oauth.php');
}

try {
	// Configura SDK con access token salvato
	$config = Configuration::getDefaultConfiguration()->setAccessToken($_SESSION['token']);
	$userApi = new UserApi(new Client(), $config);

	// Richiesta delle aziende associate all'utente
	$response = $userApi->listUserCompanies();

	// Estrae le aziende
	$companies = $response->getData()->getCompanies();

	echo "<h3>✅ Aziende disponibili:</h3>";
	echo "<ul>";
	foreach ($companies as $company) {
		echo "<li><strong>{$company->getName()}</strong> — ID: {$company->getId()}</li>";
	}
	echo "</ul>";

} catch (Exception $e) {
	echo '❌ Errore: ' . $e->getMessage();
}
