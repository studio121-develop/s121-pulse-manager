<?php
/**
 * Plugin Name: S121 Pulse Manager
 * Description: Gestione ricorrente dei servizi clienti con reminder, fatturazione e integrazione con Fatture in Cloud.
 * Version: 1.0.0
 * Author: Studio 121
 * Text Domain: s121-pulse-manager
 */

defined('ABSPATH') || exit;

//
// ✅ CARICAMENTO CPT
//
require_once plugin_dir_path(__FILE__) . 'post-types/clienti.php';
require_once plugin_dir_path(__FILE__) . 'post-types/servizi.php';
require_once plugin_dir_path(__FILE__) . 'post-types/servizi-clienti.php';

//
// ✅ CARICAMENTO ACF
//
require_once plugin_dir_path(__FILE__) . 'acf-fields/acf-clienti.php';
require_once plugin_dir_path(__FILE__) . 'acf-fields/acf-servizi.php';
require_once plugin_dir_path(__FILE__) . 'acf-fields/acf-servizi-clienti.php';

//
// ✅ REMINDER & FUNZIONI MANUALI
//
require_once plugin_dir_path(__FILE__) . 'cron/reminder-rinnovi.php';
require_once plugin_dir_path(__FILE__) . 'admin/reset-reminder.php';
require_once plugin_dir_path(__FILE__) . 'admin/rinnovo-manuale.php';

//
// ✅ INTEGRAZIONE FATTURE IN CLOUD – OAUTH2
//
require_once plugin_dir_path(__FILE__) . 'api/oauth.php';
require_once plugin_dir_path(__FILE__) . 'api/fatture-in-cloud.php';

//
// 🔁 REGISTRAZIONE EVENTO CRON PER I REMINDER
//
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('spm_check_rinnovi')) {
        wp_schedule_event(time(), 'daily', 'spm_check_rinnovi');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('spm_check_rinnovi');
});

//
// 🧪 SYNC MANUALE: TRIGGER via URL (?sync_clienti_fic=1)
//
add_action('admin_init', function () {
    if (current_user_can('manage_options') && isset($_GET['sync_clienti_fic'])) {
        $count = spm_scarica_clienti_fattureincloud();
        add_action('admin_notices', function () use ($count) {
            if ($count > 0) {
                echo '<div class="notice notice-success"><p>✔ ' . $count . ' clienti sincronizzati da Fatture in Cloud.</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>⚠ Nessun cliente ricevuto o errore nella chiamata API.</p></div>';
            }
        });
    }
});

//
// 🔐 CALLBACK OAUTH2: admin.php?page=spm-oauth-callback
//
add_action('admin_menu', function () {
    add_submenu_page(null, 'Callback OAuth', 'Callback OAuth', 'manage_options', 'spm-oauth-callback', 'spm_oauth_callback_page');
});

function spm_oauth_callback_page() {
    require_once plugin_dir_path(__FILE__) . 'api/oauth.php';

    echo '<h2>S121 Pulse Manager – Autenticazione con Fatture in Cloud</h2>';

    if (!isset($_GET['code'])) {
        echo '<p style="color:red;">❌ Nessun codice ricevuto.</p>';
        return;
    }

    $code = sanitize_text_field($_GET['code']);
    $res = spm_scambia_code_con_token($code);

    if (isset($res['success'])) {
        echo '<p style="color:green;">✅ Token salvato con successo!</p>';
        echo '<pre>' . print_r($res['data'], true) . '</pre>';
    } else {
        echo '<p style="color:red;">❌ Errore durante autenticazione:</p>';
        echo '<pre>' . print_r($res, true) . '</pre>';
    }
}
