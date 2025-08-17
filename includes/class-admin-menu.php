<?php
/**
 * Gestione menu amministrazione per S121 Pulse Manager
 */

defined('ABSPATH') || exit;

if (!defined('SPM_PLUGIN_FILE')) {
	define('SPM_PLUGIN_FILE', __FILE__);
}

class SPM_Admin_Menu {

	public static function init() {
		add_action('admin_menu', [__CLASS__, 'register_menu']);
		add_action('admin_menu', [__CLASS__, 'tweak_submenus'], 999);
	}

	/**
	 * Registra menu principale e sottomenu
	 */
	public static function register_menu() {
		// Voce top-level → apre direttamente la Dashboard
		add_menu_page(
			'S121 Pulse Manager',
			'Pulse Manager',
			'manage_options',
			's121-pulse-manager',
			'spm_render_dashboard',              // Dashboard diretta
			'dashicons-chart-pie',
			3
		);

		// Dashboard anche come prima voce del sottomenu
		add_submenu_page(
			's121-pulse-manager',
			'Dashboard',
			'🧭 Dashboard',
			'manage_options',
			's121-pulse-manager',
			'spm_render_dashboard'
		);

		// Impostazioni (in coda, riordinato dopo)
		add_submenu_page(
			's121-pulse-manager',
			'Impostazioni',
			'⚙️ Impostazioni',
			'manage_options',
			'spm-settings',
			['SPM_Settings_Page', 'render']
		);
	}

	/**
	 * Rinomina e riordina i sottomenu
	 */
	public static function tweak_submenus() {
		global $submenu;

		if (!isset($submenu['s121-pulse-manager'])) {
			return;
		}

		foreach ($submenu['s121-pulse-manager'] as &$item) {
			switch ($item[2]) {
				case 'edit.php?post_type=contratti':
					$item[0] = '📄 Contratti';
					break;
				case 'edit.php?post_type=clienti':
					$item[0] = '👤 Clienti';
					break;
				case 'edit.php?post_type=servizi':
					$item[0] = '🧩 Servizi';
					break;
			}
		}

		// Riordina voci: Dashboard → Contratti → Clienti → Servizi → Impostazioni
		usort($submenu['s121-pulse-manager'], function($a, $b) {
			$order = [
				's121-pulse-manager'        => 1,
				'edit.php?post_type=contratti' => 2,
				'edit.php?post_type=clienti'   => 3,
				'edit.php?post_type=servizi'   => 4,
				'spm-settings'                 => 99,
			];
			$a_key = $a[2];
			$b_key = $b[2];
			return ($order[$a_key] ?? 50) <=> ($order[$b_key] ?? 50);
		});
	}
}
