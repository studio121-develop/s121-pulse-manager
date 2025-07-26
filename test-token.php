<?php
require_once(__DIR__ . '/oauth.php');

$token = get_valid_token();

if ($token) {
	echo "✅ Access token valido:<br>$token";
} else {
	echo "❌ Access token non disponibile o refresh fallito.";
}
