<?php
/**
 * Plugin Name: S121 Pulse Manager
 * Description: Gestione ricorrente dei servizi clienti con reminder, fatturazione e integrazione con Fatture in Cloud.
 * Version: 2.0.0
 * Author: Studio 121
 */

defined('ABSPATH') || exit;

// Puntatore al file principale del plugin (usato per link rapidi, ecc.)
if (!defined('SPM_PLUGIN_FILE')) {
    define('SPM_PLUGIN_FILE', __FILE__);
}

// Carica stile admin quando si editano i post del plugin
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_style('spm-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css');
    }
});

// ==========================================================
// CPT & ACF
// ==========================================================
require_once plugin_dir_path(__FILE__) . 'post-types/clienti.php';
require_once plugin_dir_path(__FILE__) . 'post-types/servizi.php';
require_once plugin_dir_path(__FILE__) . 'post-types/contratti.php';  // NUOVO

require_once plugin_dir_path(__FILE__) . 'acf-fields/acf-clienti.php';
require_once plugin_dir_path(__FILE__) . 'acf-fields/acf-servizi.php';
require_once plugin_dir_path(__FILE__) . 'acf-fields/acf-contratti.php';  // NUOVO

// ==========================================================
// CORE SISTEMA
// ==========================================================
require_once plugin_dir_path(__FILE__) . 'includes/class-date-helper.php';  // NUOVO
require_once plugin_dir_path(__FILE__) . 'includes/class-contract-handler.php';  // NUOVO
require_once plugin_dir_path(__FILE__) . 'includes/spm-list-inline-actions.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-statistics-handler.php';

register_activation_hook(__FILE__, function() {
    SPM_Statistics_Handler::instance()->maybe_install();
});


// ==========================================================
// ADMIN
// ==========================================================
require_once plugin_dir_path(__FILE__) . 'admin/dashboard-contratti.php';  // NUOVO


// ==========================================================
// FATTURE IN CLOUD
// ==========================================================
require_once plugin_dir_path(__FILE__) . 'includes/oauth-utils.php';
require_once plugin_dir_path(__FILE__) . 'api/fatture-in-cloud.php';

// ==========================================================
// BACKEND MENU & SETTINGS
// ==========================================================
// Menu amministrazione centralizzato (raggruppa dashboard + CPT sotto "Pulse Manager")
require_once plugin_dir_path(__FILE__) . 'includes/class-admin-menu.php';
SPM_Admin_Menu::init();

// Pagina Impostazioni plugin (Policy contratti, ecc.)
require_once plugin_dir_path(__FILE__) . 'includes/class-settings-page.php';
SPM_Settings_Page::init();


// ==========================================================
// INIT
// ==========================================================

// Inizializza handler contratti
add_action('init', ['SPM_Contract_Handler', 'init']);

// Mantieni CRON sync clienti FIC
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('spm_sync_clienti_cron')) {
        wp_schedule_event(strtotime('00:00:00'), 'daily', 'spm_sync_clienti_cron');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('spm_sync_clienti_cron');
    wp_clear_scheduled_hook('spm_daily_check');
});

// Esecuzione job di sync con Fatture in Cloud
add_action('spm_sync_clienti_cron', function () {
    require_once plugin_dir_path(__FILE__) . 'api/fatture-in-cloud.php';
    sync_clienti_da_fic(false);
});

// Debug sync manuale (solo admin, via querystring)
add_action('admin_init', function () {
    if (isset($_GET['spm_test_sync']) && current_user_can('manage_options')) {
        sync_clienti_da_fic(true);
        exit;
    }
});


// ==========================================================
// DASHBOARD REDIRECT
// ==========================================================
// Callback usata per aprire la dashboard dei contratti dal menu plugin
function spm_render_main_dashboard() {
    // Redirect alla dashboard contratti
    wp_redirect(admin_url('edit.php?post_type=contratti&page=contratti-dashboard'));
    exit;
}


// Admin trigger per backfill (solo admin)
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;

    // Esempi:
    // ?spm_backfill=all
    // ?spm_backfill=contract&cid=123
    // ?spm_backfill=range&from=2024-01&to=2025-08
    if (!isset($_GET['spm_backfill'])) return;

    $act = sanitize_text_field($_GET['spm_backfill']);
    $handler = SPM_Statistics_Handler::instance();

    if ($act === 'all') {
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : null;
        $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : null;
        $res = $handler->backfill_all($from, $to);
        wp_die('Backfill ALL completato: ' . esc_html(json_encode($res)));
    }

    if ($act === 'contract') {
        $cid  = isset($_GET['cid']) ? (int) $_GET['cid'] : 0;
        if ($cid <= 0) wp_die('CID mancante');
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : null;
        $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : null;
        $n = $handler->backfill_contract($cid, $from, $to);
        wp_die('Backfill contratto #' . $cid . ' righe generate: ' . (int)$n);
    }

    if ($act === 'range') {
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : null;
        $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : null;
        if (!$from || !$to) wp_die('from/to mancanti');
        $res = $handler->backfill_all($from, $to);
        wp_die('Backfill RANGE completato: ' . esc_html(json_encode($res)));
    }
});
