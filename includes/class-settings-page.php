<?php
/**
 * SPM Settings Page
 *
 * - Registra e gestisce le impostazioni di policy contratto (tolleranza / auto-cessazione)
 * - Espone una pagina admin "S121 Pulse Manager" (slug: spm-settings)
 * - Integra strumenti di manutenzione statistiche (rebuild KPI, purge orfani, hard reindex) via admin-post
 *
 * NOTE IMPORTANTI:
 * - Gli handler admin_post_* sono DEFINITI FUORI DALLA CLASSE (alla fine del file).
 * - Richiedono la presenza di SPM_Statistics_Handler (caricato altrove nel plugin).
 */

defined('ABSPATH') || exit;

class SPM_Settings_Page {

	/** Slug gruppo opzioni (Settings API) */
	const OPTION_GROUP = 'spm_settings';
	/** Nome opzione nel DB */
	const OPTION_NAME  = 'spm_options';
	/** Slug pagina admin */
	const PAGE_SLUG    = 'spm-settings';

	/**
	 * Bootstrap: registra hook WP per menu e Settings API.
	 * Chiama questo metodo una volta all'avvio del plugin.
	 */
	public static function init() {
		add_action('admin_init',  [__CLASS__, 'register_settings']);
	}

	/* =========================
	 * Default & Accessor
	 * ========================= */

	/** Valori di default per le impostazioni */
	public static function defaults() {
		return [
			// Policy contratti
			'tolleranza_scaduto_giorni' => 60, // entro questa soglia si può allineare
			'auto_cessazione_giorni'    => 90, // oltre questa soglia → cessato
			
			// Fatture in Cloud API
			'fic_client_id'     => '', // Client ID OAuth Fatture in Cloud
			'fic_client_secret' => '', // Client Secret OAuth Fatture in Cloud
			'fic_redirect_uri'  => '', // URI di redirect OAuth
			
			// Frontend
			'frontend_mode'                 => 'static', // normal | static | off
			'frontend_redirect_logged_in'   => 1,        // 1/0
			'frontend_noindex'              => 1,        // 1/0
			'frontend_static_html'          => '',       // HTML custom opzionale
			
			// Backend (override bacheca)
			'override_dashboard_enabled'      => 0,                      // 1 = attivo
			'override_dashboard_target'       => 's121-pulse-manager',   // slug o URL
			'override_dashboard_bypass_admin' => 1,                      // non toccare gli admin
			'override_dashboard_roles'        => [],                     // vuoto = tutti i ruoli
			
			// Nascondi voci di menu (backend)
			'hide_menus_enabled'       => 0,  // ON/OFF
			'hide_menus_items'         => [], // slugs selezionati
			'hide_menus_bypass_admin'  => 1,  // non applicare a chi ha manage_options
			'hide_menus_roles'         => [], // vuoto = tutti i ruoli

		];
		

		
		
	}

	/** Legge in sicurezza una chiave di opzione, con fallback ai default */
	public static function get($key) {
		$opts = get_option(self::OPTION_NAME, self::defaults());
		$all  = wp_parse_args($opts, self::defaults());
		return $all[$key] ?? null;
	}


	/* =========================
	 * Settings API
	 * ========================= */

	/** Registra gruppo, sezione e campi tramite Settings API */
	public static function register_settings() {
		register_setting(self::OPTION_GROUP, self::OPTION_NAME, [
			'type'              => 'array',
			'sanitize_callback' => [__CLASS__, 'sanitize'],
			'default'           => self::defaults(),
		]);

		// Sezione logica (non renderizzata con do_settings_sections, usiamo UI custom)
		add_settings_section('spm_contract_policy', 'Policy Contratti', '__return_false', self::PAGE_SLUG);
		add_settings_section('smp_fic_api', 'Fatture in Cloud API', '__return_false', self::PAGE_SLUG);

		// Campo: Tolleranza "scaduto poco"
		add_settings_field(
			'tolleranza_scaduto_giorni',
			'Tolleranza "scaduto poco" (giorni)',
			[__CLASS__, 'render_number_field'],
			self::PAGE_SLUG,
			'spm_contract_policy',
			['key' => 'tolleranza_scaduto_giorni', 'min' => 0, 'desc' => 'Entro questa soglia è consentito il rinnovo con allineamento.']
		);

		// Campo: Auto-cessazione
		add_settings_field(
			'auto_cessazione_giorni',
			'Auto-cessazione (giorni)',
			[__CLASS__, 'render_number_field'],
			self::PAGE_SLUG,
			'spm_contract_policy',
			['key' => 'auto_cessazione_giorni', 'min' => 1, 'desc' => 'Oltre questa soglia un contratto è considerato cessato.']
		);

		// Campi API Fatture in Cloud
		add_settings_field(
			'fic_client_id',
			'Client ID',
			[__CLASS__, 'render_text_field'],
			self::PAGE_SLUG,
			'smp_fic_api',
			['key' => 'fic_client_id', 'desc' => 'Client ID dell\'app OAuth Fatture in Cloud']
		);

		add_settings_field(
			'fic_client_secret',
			'Client Secret',
			[__CLASS__, 'render_password_field'],
			self::PAGE_SLUG,
			'smp_fic_api',
			['key' => 'fic_client_secret', 'desc' => 'Client Secret dell\'app OAuth Fatture in Cloud (protetto)']
		);

		add_settings_field(
			'fic_redirect_uri',
			'URI di Redirect',
			[__CLASS__, 'render_text_field'],
			self::PAGE_SLUG,
			'smp_fic_api',
			['key' => 'fic_redirect_uri', 'desc' => 'URI di redirect OAuth (default: /wp-content/plugins/s121-pulse-manager/oauth.php)']
		);
	}

	/* =========================
	 * Sanitize
	 * ========================= */

	/**
	 * Sanifica e normalizza i valori in ingresso dal form impostazioni.
	 * - Enforce numeri interi e soglie minime
	 * - Auto-cessazione mai inferiore alla tolleranza
	 */
	public static function sanitize($input) {
		$out = self::defaults();
		$in  = is_array($input) ? $input : [];

		$out['tolleranza_scaduto_giorni'] = max(0, intval($in['tolleranza_scaduto_giorni'] ?? $out['tolleranza_scaduto_giorni']));
		$out['auto_cessazione_giorni']    = max(1, intval($in['auto_cessazione_giorni'] ?? $out['auto_cessazione_giorni']));

		// Coerenza operativa: auto_cessazione >= tolleranza
		if ($out['auto_cessazione_giorni'] < $out['tolleranza_scaduto_giorni']) {
			$out['auto_cessazione_giorni'] = $out['tolleranza_scaduto_giorni'];
			add_settings_error(self::OPTION_NAME, 'spm_policy_adjust',
				'Auto-cessazione riallineata alla tolleranza: non può essere inferiore.', 'updated');
		}

		// ===== Fatture in Cloud API =====
		$out['fic_client_id']     = sanitize_text_field($in['fic_client_id'] ?? $out['fic_client_id']);
		$out['fic_client_secret'] = sanitize_text_field($in['fic_client_secret'] ?? $out['fic_client_secret']);
		$out['fic_redirect_uri']  = esc_url_raw($in['fic_redirect_uri'] ?? $out['fic_redirect_uri']);

		// Validazione Client ID (formato tipico OAuth)
		if (!empty($out['fic_client_id']) && !preg_match('/^[a-zA-Z0-9_-]+$/', $out['fic_client_id'])) {
			add_settings_error(self::OPTION_NAME, 'fic_client_id_invalid',
				'Client ID non valido: deve contenere solo caratteri alfanumerici, _ e -', 'error');
		}

		// Auto-generate redirect URI se vuoto
		if (empty($out['fic_redirect_uri'])) {
			$out['fic_redirect_uri'] = site_url('/wp-content/plugins/s121-pulse-manager/oauth.php');
		}
		
		// ===== Frontend =====
		$mode = isset($in['frontend_mode']) ? sanitize_text_field($in['frontend_mode']) : $out['frontend_mode'];
		if (!in_array($mode, ['normal','static','off'], true)) {
			$mode = 'static';
		}
		$out['frontend_mode'] = $mode;
		
		$out['frontend_redirect_logged_in'] = !empty($in['frontend_redirect_logged_in']) ? 1 : 0;
		$out['frontend_noindex']            = !empty($in['frontend_noindex']) ? 1 : 0;
		
		if (array_key_exists('frontend_static_html', $in)) {
			$out['frontend_static_html'] = current_user_can('unfiltered_html')
				? (string)$in['frontend_static_html']
				: wp_kses_post((string)$in['frontend_static_html']);
		}
		
		// ===== Override Bacheca (Backend) =====
		$out['override_dashboard_enabled']      = !empty($in['override_dashboard_enabled']) ? 1 : 0;
		$out['override_dashboard_bypass_admin'] = !empty($in['override_dashboard_bypass_admin']) ? 1 : 0;
		
		// target: slug admin (es. s121-pulse-manager) oppure URL assoluto
		$raw_target = isset($in['override_dashboard_target']) ? trim((string)$in['override_dashboard_target']) : '';
		if ($raw_target === '') {
			$raw_target = $out['override_dashboard_target'];
		}
		if (preg_match('~^https?://~i', $raw_target)) {
			$safe = esc_url_raw($raw_target);
			$out['override_dashboard_target'] = $safe ?: 's121-pulse-manager';
		} else {
			$slug = preg_replace('~[^a-z0-9\-\._\?=]~i', '', $raw_target);
			$out['override_dashboard_target'] = $slug ?: 's121-pulse-manager';
		}
		
		// ruoli limitati (opzionale)
		$valid_roles = array_keys(get_editable_roles());
		$roles_in = isset($in['override_dashboard_roles']) && is_array($in['override_dashboard_roles']) ? $in['override_dashboard_roles'] : [];
		$out['override_dashboard_roles'] = array_values(array_intersect($roles_in, $valid_roles));
		
		// ===== Nascondi voci di menu =====
		$out['hide_menus_enabled']      = !empty($in['hide_menus_enabled']) ? 1 : 0;
		$out['hide_menus_bypass_admin'] = !empty($in['hide_menus_bypass_admin']) ? 1 : 0;
		
		// whitelist delle voci consentite
		$allowed_menu_slugs = [
			'index.php',                         // Bacheca
			'edit.php',                          // Articoli
			'upload.php',                        // Media
			'edit.php?post_type=page',           // Pagine
			'edit-comments.php',                 // Commenti
			'themes.php',                        // Aspetto
			'plugins.php',                       // Plugin
			'users.php',                         // Utenti
			'tools.php',                         // Strumenti
			'options-general.php',               // Impostazioni
			'edit.php?post_type=acf-field-group' // ACF (moderno)
			// Nota: tenteremo anche 'acf' in rimozione per compat vecchie
		];
		
		$items_in = isset($in['hide_menus_items']) && is_array($in['hide_menus_items']) ? $in['hide_menus_items'] : [];
		$out['hide_menus_items'] = array_values(array_intersect($items_in, $allowed_menu_slugs));
		
		// limiti di ruolo (opzionale)
		$valid_roles = array_keys(get_editable_roles());
		$roles_in = isset($in['hide_menus_roles']) && is_array($in['hide_menus_roles']) ? $in['hide_menus_roles'] : [];
		$out['hide_menus_roles'] = array_values(array_intersect($roles_in, $valid_roles));

		return $out;
	}

	/* =========================
	 * Field renderer (compat)
	 * ========================= */

	/** Render di un number field generico (usato dalla Settings API) */
	public static function render_number_field($args) {
		$key   = $args['key'];
		$min   = intval($args['min'] ?? 0);
		$desc  = esc_html($args['desc'] ?? '');
		$value = esc_attr(self::get($key));
		echo "<input type='number' min='{$min}' name='" . esc_attr(self::OPTION_NAME) . "[{$key}]' value='{$value}' class='small-text' />";
		if ($desc) echo "<p class='description'>{$desc}</p>";
	}

	public static function render_text_field($args) {
		$key   = $args['key'];
		$desc  = esc_html($args['desc'] ?? '');
		$value = esc_attr(self::get($key));
		echo "<input type='text' name='" . esc_attr(self::OPTION_NAME) . "[{$key}]' value='{$value}' class='regular-text' />";
		if ($desc) echo "<p class='description'>{$desc}</p>";
	}

	public static function render_password_field($args) {
		$key   = $args['key'];
		$desc  = esc_html($args['desc'] ?? '');
		$value = self::get($key);
		$masked = !empty($value) ? str_repeat('•', min(strlen($value), 20)) : '';
		echo "<input type='password' name='" . esc_attr(self::OPTION_NAME) . "[{$key}]' value='" . esc_attr($value) . "' class='regular-text' placeholder='{$masked}' />";
		if ($desc) echo "<p class='description'>{$desc}</p>";
	}

	/* =========================
	 * Helper methods
	 * ========================= */
	
	/** Ottieni URL della pagina impostazioni */
	private static function get_settings_url() {
		return admin_url('admin.php?page=' . self::PAGE_SLUG);
	}
	
	/** Includi CSS e JS per la pagina admin */
	private static function enqueue_admin_assets() {
		// CSS inline per la pagina
		add_action('admin_footer', function() {
			?>
			<style>
			.spm-settings-wrap {
				background: #f1f1f1;
				margin: 20px 0 0 -20px;
				padding: 0;
			}
			.spm-main-title {
				background: #fff;
				margin: 0;
				padding: 20px 30px;
				border-bottom: 1px solid #ddd;
				display: flex;
				align-items: center;
				gap: 12px;
			}
			.spm-logo { font-size: 24px; }
			.spm-version {
				background: #00a32a;
				color: #fff;
				padding: 2px 8px;
				border-radius: 12px;
				font-size: 11px;
				font-weight: 600;
			}
			.spm-description {
				background: #fff;
				margin: 0;
				padding: 0 30px 20px 30px;
				color: #666;
				border-bottom: 1px solid #ddd;
			}
			.spm-tab-wrapper {
				background: #fff;
				margin: 0;
				border-bottom: 1px solid #ddd;
				padding-left: 20px;
			}
			.spm-tab-wrapper .nav-tab {
				display: flex;
				align-items: center;
				gap: 8px;
				border: none;
				background: transparent;
				color: #646970;
				padding: 12px 20px;
			}
			.spm-tab-wrapper .nav-tab-active {
				color: #2271b1;
				border-bottom: 3px solid #2271b1;
				background: #f9f9f9;
			}
			.spm-tab-content {
				background: #fff;
				padding: 30px;
				min-height: 500px;
			}
			.spm-settings-section {
				background: #fff;
				border: 1px solid #ddd;
				border-radius: 6px;
				margin-bottom: 20px;
				overflow: hidden;
			}
			.smp-settings-section h2 {
				background: #f8f9fa;
				margin: 0;
				padding: 15px 20px;
				border-bottom: 1px solid #ddd;
				font-size: 16px;
			}
			.spm-settings-section-content {
				padding: 20px;
			}
			.spm-field-row {
				display: grid;
				grid-template-columns: 200px 1fr;
				gap: 15px;
				align-items: start;
				margin-bottom: 20px;
			}
			.spm-field-row:last-child { margin-bottom: 0; }
			.spm-field-label {
				font-weight: 600;
				color: #23282d;
				padding-top: 5px;
			}
			.spm-field-description {
				color: #666;
				font-size: 13px;
				font-style: italic;
				margin-top: 5px;
			}
			.spm-stats-cards {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
				gap: 20px;
				margin-bottom: 30px;
			}
			.spm-stat-card {
				background: #fff;
				border: 1px solid #ddd;
				border-radius: 6px;
				padding: 20px;
				text-align: center;
				border-left: 4px solid #2271b1;
			}
			.spm-stat-value {
				font-size: 28px;
				font-weight: bold;
				color: #2271b1;
				margin: 10px 0;
			}
			.spm-danger { border-left-color: #dc3232; }
			.spm-danger .spm-stat-value { color: #dc3232; }
			.spm-warning { border-left-color: #dba617; }
			.spm-warning .spm-stat-value { color: #dba617; }
			</style>
			<?php
		});
	}

	/* =========================
	 * Page render (UI a tab moderne)
	 * ========================= */

	public static function render() {
		if (!current_user_can('manage_options')) wp_die('Non autorizzato');
	
		$current_tab = sanitize_text_field($_GET['tab'] ?? 'contracts');
		$notice = isset($_GET['spm_notice']) ? sanitize_text_field($_GET['smp_notice']) : '';
		
		// Includi CSS e JS per la pagina
		self::enqueue_admin_assets();
		?>
		<div class="wrap spm-settings-wrap">
			<h1 class="spm-main-title">
				<span class="spm-logo">⚙️</span>
				S121 Pulse Manager
				<span class="spm-version">v2.0</span>
			</h1>
			
			<p class="spm-description">
				Gestisci configurazioni, policy contratti e strumenti di manutenzione del sistema.
			</p>
	
			<?php settings_errors(self::OPTION_NAME); ?>
	
			<?php if ($notice): ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						switch ($notice) {
							case 'kpi_rebuilt':     echo 'KPI ricostruiti con successo sullo storico disponibile.'; break;
							case 'orphans_purged':  echo 'Righe orfane ripulite e KPI ricostruiti.'; break;
							case 'hard_reindex_ok': echo 'Hard Reindex completato: history e KPI rigenerati da zero.'; break;
							default:                echo 'Operazione completata.'; break;
						}
						?>
					</p>
				</div>
			<?php endif; ?>
			
			<!-- Navigazione Tab -->
			<nav class="nav-tab-wrapper spm-tab-wrapper">
				<a href="<?php echo esc_url(add_query_arg('tab', 'contracts', self::get_settings_url())); ?>" 
				   class="nav-tab <?php echo $current_tab === 'contracts' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-calendar-alt"></span>
					Policy Contratti
				</a>
				<a href="<?php echo esc_url(add_query_arg('tab', 'api', self::get_settings_url())); ?>" 
				   class="nav-tab <?php echo $current_tab === 'api' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-cloud"></span>
					Fatture in Cloud
				</a>
				<a href="<?php echo esc_url(add_query_arg('tab', 'frontend', self::get_settings_url())); ?>" 
				   class="nav-tab <?php echo $current_tab === 'frontend' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-desktop"></span>
					Frontend
				</a>
				<a href="<?php echo esc_url(add_query_arg('tab', 'admin', self::get_settings_url())); ?>" 
				   class="nav-tab <?php echo $current_tab === 'admin' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-settings"></span>
					Amministrazione
				</a>
				<a href="<?php echo esc_url(add_query_arg('tab', 'tools', self::get_settings_url())); ?>" 
				   class="nav-tab <?php echo $current_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-tools"></span>
					Strumenti
				</a>
			</nav>
			
			<!-- Contenuto Tab -->
			<div class="spm-tab-content">
				<?php
				switch ($current_tab) {
					case 'contracts':
						self::render_contracts_tab();
						break;
					case 'api':
						self::render_api_tab();
						break;
					case 'frontend':
						self::render_frontend_tab();
						break;
					case 'admin':
						self::render_admin_tab();
						break;
					case 'tools':
						self::render_tools_tab();
						break;
					default:
						self::render_contracts_tab();
				}
				?>
		</div>
		<?php
	}

	/* =========================
	 * Tab render methods
	 * ========================= */

	/** Render tab Policy Contratti */
	private static function render_contracts_tab() {
		$tolleranza = (int) self::get('tolleranza_scaduto_giorni');
		$auto = (int) self::get('auto_cessazione_giorni');
		?>
		
		<!-- Statistiche Overview -->
		<div class="spm-stats-cards">
			<div class="spm-stat-card spm-warning">
				<h3>⏰ Tolleranza Scaduto</h3>
				<div class="spm-stat-value"><?php echo esc_html($tolleranza); ?> giorni</div>
				<p>Rinnovo con allineamento consentito</p>
			</div>
			<div class="spm-stat-card spm-danger">
				<h3>⛔ Auto-cessazione</h3>
				<div class="spm-stat-value"><?php echo esc_html($auto); ?> giorni</div>
				<p>Contratto automaticamente cessato</p>
			</div>
			<div class="spm-stat-card">
				<h3>📐 Regola Effettiva</h3>
				<div style="font-size: 14px; line-height: 1.6; color: #333; margin-top: 15px;">
					<strong>≤ <?php echo esc_html($tolleranza); ?>gg:</strong> Rinnovo OK<br>
					<strong>> <?php echo esc_html($auto); ?>gg:</strong> Auto-cessato
				</div>
			</div>
		</div>

		<form method="post" action="options.php" class="spm-settings-form">
			<?php settings_fields(self::OPTION_GROUP); ?>
			
			<div class="spm-settings-section">
				<h2>🔧 Policy Contratti</h2>
				<div class="spm-settings-section-content">
					<div class="spm-field-row">
						<label class="spm-field-label" for="spm_tol">Tolleranza "Scaduto poco"</label>
						<div>
							<input id="spm_tol" type="number" min="0" 
								   name="<?php echo esc_attr(self::OPTION_NAME); ?>[tolleranza_scaduto_giorni]"
								   value="<?php echo esc_attr($tolleranza); ?>" 
								   class="small-text" /> giorni
							<p class="spm-field-description">
								Entro questa soglia è consentito il rinnovo con allineamento della scadenza.
							</p>
						</div>
					</div>
					
					<div class="spm-field-row">
						<label class="spm-field-label" for="spm_auto">Soglia Auto-cessazione</label>
						<div>
							<input id="spm_auto" type="number" min="1"
								   name="<?php echo esc_attr(self::OPTION_NAME); ?>[auto_cessazione_giorni]"
								   value="<?php echo esc_attr($auto); ?>" 
								   class="small-text" /> giorni
							<p class="spm-field-description">
								Superata questa soglia, il contratto è automaticamente cessato e il rinnovo bloccato.
							</p>
						</div>
					</div>
				</div>
			</div>

			<?php submit_button('💾 Salva Impostazioni', 'primary', 'submit', false); ?>
		</form>
		<?php
	}

	/** Render tab Fatture in Cloud API */
	private static function render_api_tab() {
		?>
		<form method="post" action="options.php" class="spm-settings-form">
			<?php settings_fields(self::OPTION_GROUP); ?>
			
			<div class="spm-settings-section">
				<h2>🔗 Credenziali OAuth Fatture in Cloud</h2>
				<div class="spm-settings-section-content">
					<div class="spm-field-row">
						<label class="spm-field-label" for="spm_fic_client_id">Client ID</label>
						<div>
							<input id="spm_fic_client_id" type="text"
								   name="<?php echo esc_attr(self::OPTION_NAME); ?>[fic_client_id]"
								   value="<?php echo esc_attr(self::get('fic_client_id')); ?>" 
								   class="regular-text" placeholder="es. APSzK7BJjV5Ps..." />
							<p class="spm-field-description">
								Client ID dell'applicazione OAuth registrata su Fatture in Cloud.
							</p>
						</div>
					</div>
					
					<div class="spm-field-row">
						<label class="smp-field-label" for="spm_fic_client_secret">Client Secret</label>
						<div>
							<input id="spm_fic_client_secret" type="password"
								   name="<?php echo esc_attr(self::OPTION_NAME); ?>[fic_client_secret]"
								   value="<?php echo esc_attr(self::get('fic_client_secret')); ?>" 
								   class="regular-text" placeholder="<?php 
								   $secret = self::get('fic_client_secret');
								   echo !empty($secret) ? str_repeat('•', min(strlen($secret), 20)) : 'es. 5Xd9ABBPX...';
								   ?>" />
							<p class="spm-field-description">
								Client Secret dell'applicazione OAuth (campo protetto).
							</p>
						</div>
					</div>
					
					<div class="spm-field-row">
						<label class="spm-field-label" for="spm_fic_redirect_uri">URI di Redirect</label>
						<div>
							<input id="spm_fic_redirect_uri" type="url"
								   name="<?php echo esc_attr(self::OPTION_NAME); ?>[fic_redirect_uri]"
								   value="<?php echo esc_attr(self::get('fic_redirect_uri')); ?>" 
								   class="regular-text" 
								   placeholder="<?php echo esc_attr(site_url('/wp-content/plugins/s121-pulse-manager/oauth.php')); ?>" />
							<p class="spm-field-description">
								URI di callback OAuth. Lascia vuoto per auto-generazione.
							</p>
						</div>
					</div>
				</div>
			</div>

			<?php submit_button('💾 Salva Credenziali', 'primary', 'submit', false); ?>
		</form>
		
		<div class="spm-settings-section">
			<h2>🔍 Test Connessione</h2>
			<div class="spm-settings-section-content">
				<p>Verifica che le credenziali siano configurate correttamente:</p>
				<p>
					<a href="<?php echo esc_url(site_url('/wp-content/plugins/s121-pulse-manager/oauth.php')); ?>" 
					   class="button" target="_blank">
						🔗 Testa OAuth Flow
					</a>
					<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?spm_test_sync=1'), 'spm_manual_sync')); ?>" 
					   class="button">
						🔄 Sync Manuale Clienti
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/** Render tab Frontend */
	private static function render_frontend_tab() {
		?>
		<form method="post" action="options.php" class="spm-settings-form">
			<?php settings_fields(self::OPTION_GROUP); ?>
			
			<div class="spm-settings-section">
				<h2>🌐 Frontend Pubblico</h2>
				<div class="spm-settings-section-content">
					<div class="spm-field-row">
						<label class="spm-field-label" for="spm_frontend_mode">Modalità Frontend</label>
						<div>
							<select id="spm_frontend_mode" name="<?php echo esc_attr(self::OPTION_NAME); ?>[frontend_mode]">
								<?php $m = (string) self::get('frontend_mode'); ?>
								<option value="normal" <?php selected($m,'normal'); ?>>Normal - Tema WordPress attivo</option>
								<option value="static" <?php selected($m,'static'); ?>>Static - Pagina minimale</option>
								<option value="off" <?php selected($m,'off'); ?>>Off - Solo backend (login)</option>
							</select>
							<p class="spm-field-description">
								Controlla come viene mostrato il frontend pubblico del sito.
							</p>
						</div>
					</div>
					
					<div class="spm-field-row">
						<label class="spm-field-label">Redirect per utenti loggati</label>
						<div>
							<label>
								<input type="checkbox" value="1"
									   name="<?php echo esc_attr(self::OPTION_NAME); ?>[frontend_redirect_logged_in]"
									   <?php checked( (int) self::get('frontend_redirect_logged_in'), 1 ); ?> />
								Reindirizza alla Bacheca
							</label>
							<p class="spm-field-description">
								Se attivo, utenti loggati vengono automaticamente reindirizzati in admin.
							</p>
						</div>
					</div>
					
					<div class="spm-field-row">
						<label class="spm-field-label">SEO Noindex</label>
						<div>
							<label>
								<input type="checkbox" value="1"
									   name="<?php echo esc_attr(self::OPTION_NAME); ?>[frontend_noindex]"
									   <?php checked( (int) self::get('frontend_noindex'), 1 ); ?> />
								Applica noindex/nofollow
							</label>
							<p class="spm-field-description">
								Impedisce l'indicizzazione del frontend da parte dei motori di ricerca.
							</p>
						</div>
					</div>
					
					<div class="spm-field-row">
						<label class="spm-field-label" for="spm_frontend_html">HTML Personalizzato</label>
						<div>
							<textarea id="spm_frontend_html" rows="8" class="large-text"
								name="<?php echo esc_attr(self::OPTION_NAME); ?>[frontend_static_html]"><?php
								echo esc_textarea( (string) self::get('frontend_static_html') ); ?></textarea>
							<p class="spm-field-description">
								HTML personalizzato per la modalità static (opzionale).
							</p>
						</div>
					</div>
				</div>
			</div>

			<?php submit_button('💾 Salva Frontend', 'primary', 'submit', false); ?>
		</form>
		<?php
	}

	/** Render tab Amministrazione Backend */
	private static function render_admin_tab() {
		?>
		<form method="post" action="options.php" class="spm-settings-form">
			<?php settings_fields(self::OPTION_GROUP); ?>
			
			<div class="spm-settings-section">
				<h2>🧭 Override Bacheca WordPress</h2>
				<div class="spm-settings-section-content">
					<div class="spm-field-row">
						<label class="spm-field-label">Attiva Override</label>
						<div>
							<label>
								<input type="checkbox" value="1"
									   name="<?php echo esc_attr(self::OPTION_NAME); ?>[override_dashboard_enabled]"
									   <?php checked( (int) self::get('override_dashboard_enabled'), 1 ); ?> />
								Reindirizza bacheca a pagina personalizzata
							</label>
							<p class="spm-field-description">
								Se attivo, la bacheca WP reindirizzerà alla pagina specificata sotto.
							</p>
						</div>
					</div>
					
					<div class="spm-field-row">
						<label class="spm-field-label" for="smp_dashboard_target">Pagina Destinazione</label>
						<div>
							<input id="spm_dashboard_target" type="text"
								   name="<?php echo esc_attr(self::OPTION_NAME); ?>[override_dashboard_target]"
								   value="<?php echo esc_attr(self::get('override_dashboard_target')); ?>" 
								   class="regular-text" placeholder="s121-pulse-manager" />
							<p class="spm-field-description">
								Slug pagina admin o URL assoluto per il redirect.
							</p>
						</div>
					</div>
					
					<div class="spm-field-row">
						<label class="spm-field-label">Bypass Amministratori</label>
						<div>
							<label>
								<input type="checkbox" value="1"
									   name="<?php echo esc_attr(self::OPTION_NAME); ?>[override_dashboard_bypass_admin]"
									   <?php checked( (int) self::get('override_dashboard_bypass_admin'), 1 ); ?> />
								Non applicare a utenti con privilegi <code>manage_options</code>
							</label>
						</div>
					</div>
				</div>
			</div>
			
			<div class="spm-settings-section">
				<h2>🙈 Nascondi Voci Menu</h2>
				<div class="spm-settings-section-content">
					<div class="spm-field-row">
						<label class="spm-field-label">Attiva Nascondimento</label>
						<div>
							<label>
								<input type="checkbox" value="1"
									   name="<?php echo esc_attr(self::OPTION_NAME); ?>[hide_menus_enabled]"
									   <?php checked( (int) self::get('hide_menus_enabled'), 1 ); ?> />
								Nascondi voci di menu selezionate
							</label>
						</div>
					</div>
					
					<div class="spm-field-row">
						<label class="spm-field-label">Bypass Amministratori</label>
						<div>
							<label>
								<input type="checkbox" value="1"
									   name="<?php echo esc_attr(self::OPTION_NAME); ?>[hide_menus_bypass_admin]"
									   <?php checked( (int) self::get('hide_menus_bypass_admin'), 1 ); ?> />
								Non applicare a utenti con <code>manage_options</code>
							</label>
						</div>
					</div>
					
					<p><em>Le voci da nascondere si configurano dinamicamente in base al menu corrente dell'utente.</em></p>
				</div>
			</div>

			<?php submit_button('💾 Salva Amministrazione', 'primary', 'submit', false); ?>
		</form>
		<?php
	}

	/** Render tab Strumenti di Manutenzione */
	private static function render_tools_tab() {
		?>
		<div class="spm-settings-section">
			<h2>🛠️ Manutenzione Statistiche</h2>
			<div class="spm-settings-section-content">
				<p>Operazioni idempotenti per ripristinare la coerenza di history e KPI.</p>
				
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
					<div style="border: 1px solid #ddd; border-radius: 6px; padding: 20px;">
						<h4>🔁 Ricostruisci KPI</h4>
						<p>Ricalcola i KPI mensili su tutto lo storico disponibile.</p>
						<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
							<input type="hidden" name="action" value="spm_stats_rebuild_kpis" />
							<?php wp_nonce_field('spm_stats_rebuild_kpis'); ?>
							<button type="submit" class="button">Avvia Rebuild KPI</button>
						</form>
					</div>
					
					<div style="border: 1px solid #ddd; border-radius: 6px; padding: 20px;">
						<h4>🧽 Pulisci Orfani</h4>
						<p>Cancella history di contratti rimossi e ricostruisci KPI.</p>
						<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
							<input type="hidden" name="action" value="spm_stats_purge_orphans" />
							<?php wp_nonce_field('spm_stats_purge_orphans'); ?>
							<button type="submit" class="button">Pulisci Orfani</button>
						</form>
					</div>
					
					<div style="border: 1px solid #dc3232; border-radius: 6px; padding: 20px;">
						<h4>⚠️ Hard Reindex</h4>
						<p style="color: #dc3232;"><strong>ATTENZIONE:</strong> Svuota completamente le tabelle e ricostruisce tutto.</p>
						<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" 
							  onsubmit="return confirm('ATTENZIONE: questa operazione svuota le tabelle history e KPI e rimaterializza tutto da zero. Procedere?');">
							<input type="hidden" name="action" value="spm_stats_hard_reindex" />
							<?php wp_nonce_field('spm_stats_hard_reindex'); ?>
							<button type="submit" class="button button-primary">Hard Reindex</button>
						</form>
					</div>
				</div>
			</div>
		</div>
		
		<div class="spm-settings-section">
			<h2>🔗 Link Utili per Sviluppo</h2>
			<div class="spm-settings-section-content">
				<p>Strumenti per test e debug (solo per amministratori):</p>
				<p>
					<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?spm_backfill=all'), 'spm_backfill_ops')); ?>" 
					   class="button">📊 Backfill All Stats</a>
					<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?spm_billing_backfill=all'), 'spm_billing_backfill')); ?>" 
					   class="button">🧾 Backfill Billing</a>
				</p>
				<p><em>Questi link includono già i nonce di sicurezza necessari.</em></p>
			</div>
		</div>
		<?php
	}

}

/* ============================================================
 * BOOTSTRAP DELLA PAGINA IMPOSTAZIONI
 * ============================================================ */
SPM_Settings_Page::init();

/* ============================================================
 * ADMIN-POST HANDLERS (FUORI DALLA CLASSE)
 * - Eseguono le azioni "manutenzione statistiche"
 * - Richiedono capability manage_options + nonce
 * - Redirect alla pagina impostazioni con notice
 * ============================================================ */

/**
 * Ricostruisce SOLO i KPI sullo storico disponibile.
 * Non tocca le righe di history.
 */
add_action('admin_post_spm_stats_rebuild_kpis', function () {
	if (!current_user_can('manage_options')) wp_die('Non autorizzato', 403);
	check_admin_referer('spm_stats_rebuild_kpis');

	if (class_exists('SPM_Statistics_Handler')) {
		SPM_Statistics_Handler::instance()->rebuild_kpis_only();
	}

	wp_safe_redirect( admin_url('admin.php?page=' . SPM_Settings_Page::PAGE_SLUG . '&spm_notice=kpi_rebuilt') );
	exit;
});

/**
 * Pulisce le righe orfane (contratti rimossi) e ricostruisce i KPI.
 */
add_action('admin_post_spm_stats_purge_orphans', function () {
	if (!current_user_can('manage_options')) wp_die('Non autorizzato', 403);
	check_admin_referer('spm_stats_purge_orphans');

	if (class_exists('SPM_Statistics_Handler')) {
		SPM_Statistics_Handler::instance()->purge_orphans_and_rebuild();
	}

	wp_safe_redirect( admin_url('admin.php?page=' . SPM_Settings_Page::PAGE_SLUG . '&spm_notice=orphans_purged') );
	exit;
});

/**
 * HARD REINDEX: TRUNCATE di history + KPI e rimaterializzazione completa.
 */
add_action('admin_post_spm_stats_hard_reindex', function () {
	if (!current_user_can('manage_options')) wp_die('Non autorizzato', 403);
	check_admin_referer('spm_stats_hard_reindex');

	if (class_exists('SPM_Statistics_Handler')) {
		SPM_Statistics_Handler::instance()->hard_reindex_all();
	}

	wp_safe_redirect( admin_url('admin.php?page=' . SPM_Settings_Page::PAGE_SLUG . '&spm_notice=hard_reindex_ok') );
	exit;
});
