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
// ==========================================================
// 🔁 CARICAMENTO CPT & ACF
// ==========================================================
//

require_once plugin_dir_path(__FILE__) . 'post-types/clienti.php'; // ok
require_once plugin_dir_path(__FILE__) . 'post-types/servizi.php'; // ok
require_once plugin_dir_path(__FILE__) . 'post-types/contratti.php'; // ok

require_once plugin_dir_path(__FILE__) . 'acf-fields/acf-clienti.php'; // ok
require_once plugin_dir_path(__FILE__) . 'acf-fields/acf-servizi.php'; // ok
require_once plugin_dir_path(__FILE__) . 'acf-fields/acf-contratti.php'; // ok
require_once plugin_dir_path(__FILE__) . 'acf-fields/acf-default-values.php'; // ok

require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . 'includes/enqueue-script.php';
require_once plugin_dir_path(__FILE__) . 'includes/rinnovo-contratto.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-ui.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-date-helper.php'; // Date Helper per fix date

require_once plugin_dir_path(__FILE__) . 'includes/class-contract-handler.php'; // Sistema contratti nuovo
require_once plugin_dir_path(__FILE__) . 'admin/dashboard-contratti.php';


add_action('init', ['SPM_Contract_Handler', 'init']); // Inizializza handler contratti

require_once plugin_dir_path(__FILE__) . 'includes/azioni-contratto.php';



require_once plugin_dir_path(__FILE__) . 'includes/log-eventi-metabox.php';
require_once plugin_dir_path(__FILE__) . 'includes/log-utils.php';






//
// ==========================================================
// ⏰ CRON & FUNZIONALITÀ MANUALI
// ==========================================================
//

require_once plugin_dir_path(__FILE__) . 'cron/reminder-rinnovi.php';
require_once plugin_dir_path(__FILE__) . 'admin/reset-reminder.php';
require_once plugin_dir_path(__FILE__) . 'admin/rinnovo-manuale.php';

//
// ==========================================================
// 📦 FATTURE IN CLOUD: OAUTH & API
// ==========================================================
//

require_once plugin_dir_path(__FILE__) . 'includes/oauth-utils.php'; // ok, anche oauth.php nella root del plugin
require_once plugin_dir_path(__FILE__) . 'api/fatture-in-cloud.php'; // ok


//
// ==========================================================
// ⚙️ CRON JOB: Reminder rinnovi (giornaliero)
// ==========================================================
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
// ==========================================================
// ⏱️ CRON SYNC CLIENTI FIC – ogni notte alle 00:00
// ==========================================================
//

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('spm_sync_clienti_cron')) {
        wp_schedule_event(strtotime('00:00:00'), 'daily', 'spm_sync_clienti_cron');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('spm_sync_clienti_cron');
});

add_action('spm_sync_clienti_cron', function () {
    require_once plugin_dir_path(__FILE__) . 'api/fatture-in-cloud.php';
    sync_clienti_da_fic(false);
});



//
// ==========================================================
// 🧪 DEBUG: Sync manuale via URL
// ==========================================================
//

add_action('admin_init', function () {
    if (isset($_GET['spm_test_sync']) && current_user_can('manage_options')) {
        sync_clienti_da_fic(true); // Debug visivo
        exit;
    }

    if (isset($_GET['spm_test_visualizza']) && current_user_can('manage_options')) {
        debug_visualizza_clienti_fic();
        exit;
    }
});

//
// ==========================================================
// 🧩 ADMIN MENU: Voce principale e pagina impostazioni
// ==========================================================
//

add_action('admin_menu', 'spm_add_admin_menu');

function spm_add_admin_menu() {
    add_menu_page(
        'S121 Pulse Manager',
        'Pulse Manager',
        'manage_options',
        's121-pulse-manager',
        'spm_render_dashboard_page',
        'dashicons-chart-pie',
        3
    );

    add_submenu_page(
        's121-pulse-manager',
        'Impostazioni',
        'Impostazioni',
        'manage_options',
        's121-impostazioni',
        'spm_render_impostazioni_page'
    );
}

//
// ==========================================================
// 📊 DASHBOARD PRINCIPALE
// ==========================================================
//

function spm_render_dashboard_page() {
    $last_sync_time = get_option('spm_last_sync_timestamp');
    $last_sync_method = get_option('spm_last_sync_method');

    echo '<div class="wrap" style="font-family: system-ui, sans-serif; max-width: 1100px; margin-top: 20px;">';

    echo '<h1 style="font-size: 2rem; margin-bottom: 0.5em;">🚀 S121 Pulse Manager</h1>';
    echo '<p style="font-size: 1rem; line-height: 1.5;">Benvenuto nel pannello di controllo del plugin <strong>S121 Pulse Manager</strong>, sviluppato da Studio 121 per la gestione ricorrente di servizi, clienti, reminder e integrazione con <em>Fatture in Cloud</em>.</p>';

    // 🔄 Ultima sincronizzazione
    echo '<div style="margin-top:1em; padding:1em; background:#f1f1f1; border-left: 5px solid #2271b1; border-radius: 4px;">';
    echo '<strong>🕒 Ultima sincronizzazione clienti:</strong><br>';
    if ($last_sync_time) {
        echo '📅 ' . esc_html($last_sync_time) . ' via <strong>' . esc_html($last_sync_method) . '</strong>';
    } else {
        echo '⚠️ Nessuna sincronizzazione effettuata finora.';
    }
    echo '</div>';

    echo '<hr style="margin: 2em 0;">';

    echo '<div style="display: flex; flex-wrap: wrap; gap: 1.5rem;">';

    // CLIENTI
    echo '<div style="flex:1; min-width:250px; background:#f9f9f9; border-left: 4px solid #2271b1; padding:1.2em; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h2 style="margin-top:0;">👤 Clienti</h2>
        <p>Visualizza, sincronizza e aggiorna l\'anagrafica clienti da Fatture in Cloud. I clienti sono archiviati come <code>CPT</code> personalizzato.</p>
    </div>';

    // SERVIZI
    echo '<div style="flex:1; min-width:250px; background:#f9f9f9; border-left: 4px solid #00a32a; padding:1.2em; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h2 style="margin-top:0;">🛠️ Servizi</h2>
        <p>Gestisci i tuoi servizi ricorrenti, associa ogni servizio a clienti e specifica costi, durata e frequenza di rinnovo.</p>
    </div>';

    // SITUAZIONE
    echo '<div style="flex:1; min-width:250px; background:#f9f9f9; border-left: 4px solid #dba617; padding:1.2em; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h2 style="margin-top:0;">📅 Situazione</h2>
        <p>Consulta lo stato attuale dei rinnovi, dei reminder inviati e delle prossime azioni da eseguire.</p>
    </div>';

    // FATTURE IN CLOUD
    echo '<div style="flex:1; min-width:250px; background:#f9f9f9; border-left: 4px solid #ee4c7c; padding:1.2em; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h2 style="margin-top:0;">🔗 Integrazione FIC</h2>
        <p>OAuth2 attivo per accedere ai clienti. Puoi avviare la sincronizzazione manuale o attivare quella automatica (CRON ogni notte).</p>
    </div>';

    echo '</div>';

    // CTA
    echo '<hr style="margin: 2em 0;">';
    echo '<div style="padding:1em 0;">';
    echo '<a href="' . esc_url(admin_url('admin.php?spm_test_sync=1')) . '" class="button button-primary button-hero" style="font-size:1.1em;">🔄 Avvia sincronizzazione clienti FIC</a>';
    echo '<p style="margin-top:1em; color: #666;">Questa operazione recupera tutti i clienti da Fatture in Cloud e li salva come post nel CPT <code>clienti</code>. Se già presenti, non verranno duplicati.</p>';
    echo '</div>';

    echo '<hr style="margin: 2em 0;">';
    echo '<p style="font-size:0.9em; color: #999;">🧩 Plugin realizzato da <strong>Studio 121</strong> – versione 1.0.0</p>';
    echo '</div>';
}


//
// ==========================================================
// ⚙️ PAGINA IMPOSTAZIONI
// ==========================================================
//

function spm_render_impostazioni_page() {
    echo '<div class="wrap">';
    echo '<h1>⚙️ Impostazioni</h1>';
    echo '<p>Puoi avviare la sincronizzazione manuale dei clienti da Fatture in Cloud.</p>';

    echo '<form method="post">';
    submit_button('🔁 Avvia Sincronizzazione Clienti');
    echo '</form>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_options')) {
        echo '<hr>';
        sync_clienti_da_fic(true); // debug visivo
        exit;
    }

    echo '</div>';
}


add_action('init', function(){
  if (is_admin() && isset($_GET['test_log_evento'])) {
    spm_log_evento(495, 'rinnovo', 'Test di inserimento log da init');
  }
});