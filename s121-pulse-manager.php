<?php
/**
 * Plugin Name: S121 Pulse Manager
 * Description: Gestione ricorrente dei servizi clienti con reminder, fatturazione e integrazione con Fatture in Cloud.
 * Version: 2.0.0
 * Author: Studio 121
 */

defined('ABSPATH') || exit;

// Puntatore al file principale del plugin (usato per link rapidi, ecc.) ePuntatori globali URL/PATH del plugin (per asset affidabili)
if (!defined('SPM_PLUGIN_FILE')) { define('SPM_PLUGIN_FILE', __FILE__);}
if (!defined('SPM_PLUGIN_URL'))  define('SPM_PLUGIN_URL',  plugin_dir_url(__FILE__));
if (!defined('SPM_PLUGIN_PATH')) define('SPM_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Carica stile admin quando si editano i post del plugin
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_style('spm-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css');
    }
});

// =================================a=========================
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
    require_once plugin_dir_path(__FILE__) . 'includes/class-about-page.php';
// include la classe
require_once plugin_dir_path(__FILE__) . 'includes/class-billing-manager.php';

// attivazione: crea tabella se manca
register_activation_hook(__FILE__, function () {
  SPM_Billing_Manager::maybe_install();
});

// init della classe (AJAX/CRON/safety)
add_action('init', ['SPM_Billing_Manager', 'init']);

// safety add-on: se la tabella non esiste ancora, creala
add_action('admin_init', function () {
  global $wpdb;
  $table = $wpdb->prefix . 'spm_billing_ledger';
  $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
  if ($exists !== $table) {
    SPM_Billing_Manager::maybe_install();
  }
});

// 4) (Opzionale ma consigliato) Safety: se per qualche motivo la tabella non esiste, creala.
add_action('admin_init', function () {
    global $wpdb;
    $table = $wpdb->prefix . 'spm_billing_ledger';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) {
        SPM_Billing_Manager::maybe_install(); // ricrea e aggiorna opzione versione
    }
});

register_activation_hook(__FILE__, function() {
    SPM_Statistics_Handler::instance()->maybe_install();
});


// ==========================================================
// ADMIN
// ==========================================================
require_once plugin_dir_path(__FILE__) . 'admin/dashboard-contratti.php';  // NUOVO
require_once plugin_dir_path(__FILE__) . 'includes/class-spm-frontend-controller.php';



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

// Pagina Impostazioni plugin (Policy contratti, ecc.)
require_once plugin_dir_path(__FILE__) . 'includes/class-settings-page.php';

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

// Admin trigger per backfill FATTURAZIONE (solo admin)
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['spm_billing_backfill'])) return;

    $act   = sanitize_text_field($_GET['spm_billing_backfill']); // all | contract | range
    $from  = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : null; // 'YYYY-MM'
    $to    = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : null; // 'YYYY-MM'
    $reset = isset($_GET['reset']) ? (bool)intval($_GET['reset']) : false;

    if (!class_exists('SPM_Billing_Manager')) {
        wp_die('SPM_Billing_Manager non caricato');
    }

    if ($act === 'all') {
        $res = SPM_Billing_Manager::backfill_all($from, $to, $reset);
        wp_die('Billing backfill ALL completato: ' . esc_html(json_encode($res)));
    }

    if ($act === 'contract') {
        $cid = isset($_GET['cid']) ? (int) $_GET['cid'] : 0;
        if ($cid <= 0) wp_die('CID mancante');
        $deleted = SPM_Billing_Manager::backfill_contract($cid, $from, $to, $reset);
        wp_die('Billing backfill contratto #'.$cid.' completato. Deleted: '.(int)$deleted);
    }

    if ($act === 'range') {
        // Alias di ALL con from/to obbligatori
        if (!$from || !$to) wp_die('from/to mancanti (YYYY-MM)');
        $res = SPM_Billing_Manager::backfill_all($from, $to, $reset);
        wp_die('Billing backfill RANGE completato: ' . esc_html(json_encode($res)));
    }

    wp_die('Azione non valida');
});

// Override Bacheca nativa → destinazione configurata
add_action('load-index.php', function () {
    if (!is_user_logged_in()) return;

    // Non toccare AJAX o REST
    if ((defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) return;

    // Leggi opzioni + fallback ai default
    $opts = get_option(SPM_Settings_Page::OPTION_NAME, SPM_Settings_Page::defaults());
    $opts = wp_parse_args($opts, SPM_Settings_Page::defaults());

    // Attivo?
    if (empty($opts['override_dashboard_enabled'])) return;

    // Bypass admin?
    if (!empty($opts['override_dashboard_bypass_admin']) && current_user_can('manage_options')) return;

    // Se impostato per ruoli specifici, filtra
    $roles_limit = isset($opts['override_dashboard_roles']) && is_array($opts['override_dashboard_roles']) ? $opts['override_dashboard_roles'] : [];
    if ($roles_limit) {
        $user = wp_get_current_user();
        if (!array_intersect($roles_limit, (array)$user->roles)) return;
    }

    // Calcola destinazione
    $target = trim((string)$opts['override_dashboard_target']);
    if ($target === '') return;

    if (preg_match('~^https?://~i', $target)) {
        $dest = $target; // URL assoluto
    } else {
        // slug o path admin
        if (strpos($target, 'admin.php?page=') === 0 || strpos($target, '.php') !== false || strpos($target, '=') !== false) {
            $dest = admin_url($target);
        } else {
            $dest = admin_url('admin.php?page=' . $target); // slug semplice
        }
    }

    // Evita loop: se già sulla destinazione, non reindirizzare
    $current_is_target = false;
    if (isset($_GET['page']) && strpos($dest, 'admin.php?page=') !== false) {
        $current_is_target = (strpos($dest, 'admin.php?page=' . sanitize_key($_GET['page'])) !== false);
    }
    if ($current_is_target) return;

    wp_safe_redirect($dest);
    exit;
});

// Nasconde le voci selezionate (top-level o submenu) usando il registro
add_action('admin_menu', function () {
    if (!is_user_logged_in()) return;

    $opts = get_option(SPM_Settings_Page::OPTION_NAME, SPM_Settings_Page::defaults());
    $opts = wp_parse_args($opts, SPM_Settings_Page::defaults());

    if (empty($opts['hide_menus_enabled'])) return;
    if (!empty($opts['hide_menus_bypass_admin']) && current_user_can('manage_options')) return;

    // Ruoli limitati?
    $roles_limit = isset($opts['hide_menus_roles']) && is_array($opts['hide_menus_roles']) ? $opts['hide_menus_roles'] : [];
    if ($roles_limit) {
        $user = wp_get_current_user();
        if (!array_intersect($roles_limit, (array)$user->roles)) return;
    }

    $to_hide = isset($opts['hide_menus_items']) && is_array($opts['hide_menus_items']) ? $opts['hide_menus_items'] : [];
    if (!$to_hide) return;

    $registry = get_option('spm_menu_registry');
    $items    = (is_array($registry) && !empty($registry['items'])) ? $registry['items'] : [];

    // Mappa slug -> parent (null se top-level)
    $parent_of = [];
    foreach ($items as $it) {
        $parent_of[(string)$it['slug']] = $it['parent'] ? (string)$it['parent'] : null;
    }

    foreach ($to_hide as $slug) {
        $parent = $parent_of[$slug] ?? null;
        if ($parent) {
            remove_submenu_page($parent, $slug);
        } else {
            remove_menu_page($slug);
            // Dashboard: rimuovi anche sottovoci note
            if ($slug === 'index.php') {
                remove_submenu_page('index.php', 'index.php');
                remove_submenu_page('index.php', 'update-core.php');
            }
        }

        // ACF: compat vecchie installazioni
        if ($slug === 'edit.php?post_type=acf-field-group') {
            remove_menu_page('acf');
        }
    }
}, 999);

add_action('admin_bar_menu', function ($bar) {
    if (!is_user_logged_in()) return;

    $opts = get_option(SPM_Settings_Page::OPTION_NAME, SPM_Settings_Page::defaults());
    $opts = wp_parse_args($opts, SPM_Settings_Page::defaults());

    if (empty($opts['hide_menus_enabled'])) return;
    if (!empty($opts['hide_menus_bypass_admin']) && current_user_can('manage_options')) return;

    $roles_limit = isset($opts['hide_menus_roles']) && is_array($opts['hide_menus_roles']) ? $opts['hide_menus_roles'] : [];
    if ($roles_limit) {
        $user = wp_get_current_user();
        if (!array_intersect($roles_limit, (array)$user->roles)) return;
    }

    $slugs = isset($opts['hide_menus_items']) && is_array($opts['hide_menus_items']) ? $opts['hide_menus_items'] : [];

    // Commenti (icona a campanella per commenti)
    if (in_array('edit-comments.php', $slugs, true)) {
        $bar->remove_node('comments');
    }

    // "Nuovo" e figli (post, media, page, user, ecc.)
    if (array_intersect($slugs, ['edit.php', 'upload.php', 'edit.php?post_type=page'])) {
        $bar->remove_node('new-post');
        $bar->remove_node('new-page');
        $bar->remove_node('new-media');
    }
}, 999);
// Cattura dinamica del menu admin (inclusi plugin terzi)
add_action('admin_menu', function () {
    if (!is_user_logged_in()) return;

    // Lavora sempre con il menu completo visibile all'utente corrente
    // (per un registro "full" assicurati che un admin esegua almeno 1 volta)
    global $menu, $submenu;

    $items = [];

    // Top-level
    if (is_array($menu)) {
        foreach ($menu as $m) {
            // Struttura: [0]=titolo, [1]=cap, [2]=slug, ...
            $slug  = isset($m[2]) ? (string)$m[2] : '';
            if ($slug === '') continue;

            $title = isset($m[0]) ? wp_strip_all_tags((string)$m[0]) : $slug;
            // rimuovi eventuali badge <span class="update-plugins">…</span>
            $title = trim(preg_replace('~\s+~', ' ', $title));

            $items[$slug] = [
                'slug'   => $slug,
                'title'  => $title,
                'parent' => null,
            ];

            // Submenu di questo top-level
            if (isset($submenu[$slug]) && is_array($submenu[$slug])) {
                foreach ($submenu[$slug] as $sm) {
                    // Struttura: [0]=titolo, [1]=cap, [2]=slug
                    $s_slug  = isset($sm[2]) ? (string)$sm[2] : '';
                    if ($s_slug === '') continue;

                    $s_title = isset($sm[0]) ? wp_strip_all_tags((string)$sm[0]) : $s_slug;
                    $s_title = trim(preg_replace('~\s+~', ' ', $s_title));

                    // Evita di sovrascrivere eventuale top-level con stesso slug
                    if (!isset($items[$s_slug])) {
                        $items[$s_slug] = [
                            'slug'   => $s_slug,
                            'title'  => $s_title,
                            'parent' => $slug,
                        ];
                    }
                }
            }
        }
    }

    // Ordina per titolo per UI più gradevole
    uasort($items, function ($a, $b) {
        return strcasecmp($a['title'], $b['title']);
    });

    $registry = [
        'items'       => array_values($items),
        'last_update' => time(),
        'hash'        => md5(wp_json_encode($items)),
    ];

    // Aggiorna solo se cambia per ridurre I/O DB
    $old = get_option('spm_menu_registry');
    if (!is_array($old) || !isset($old['hash']) || $old['hash'] !== $registry['hash']) {
        update_option('spm_menu_registry', $registry, false);
    }
}, 99999);


