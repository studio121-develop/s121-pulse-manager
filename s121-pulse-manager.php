<?php
/**
 * Plugin Name: S121 Pulse Manager
 * Description: Gestione ricorrente dei servizi clienti con reminder, fatturazione e integrazione con Fatture in Cloud.
 * Version: 2.0.0
 * Author: Studio 121
 */

defined('ABSPATH') || exit;

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

add_action('spm_sync_clienti_cron', function () {
    require_once plugin_dir_path(__FILE__) . 'api/fatture-in-cloud.php';
    sync_clienti_da_fic(false);
});

// Debug sync manuale
add_action('admin_init', function () {
    if (isset($_GET['spm_test_sync']) && current_user_can('manage_options')) {
        sync_clienti_da_fic(true);
        exit;
    }
});



// Menu principale plugin
add_action('admin_menu', function() {
    add_menu_page(
        'S121 Pulse Manager',
        'Pulse Manager',
        'manage_options',
        's121-pulse-manager',
        'spm_render_main_dashboard',
        'dashicons-chart-pie',
        3
    );
});

function spm_render_main_dashboard() {
    // Redirect alla dashboard contratti
    wp_redirect(admin_url('edit.php?post_type=contratti&page=contratti-dashboard'));
    exit;
}