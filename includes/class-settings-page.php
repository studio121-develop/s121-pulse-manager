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
			
			// Frontend
			'frontend_mode'                 => 'static', // normal | static | off
			'frontend_redirect_logged_in'   => 1,        // 1/0
			'frontend_noindex'              => 1,        // 1/0
			'frontend_static_html'          => '',       // HTML custom opzionale
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

		// Campo: Tolleranza “scaduto poco”
		add_settings_field(
			'tolleranza_scaduto_giorni',
			'Tolleranza “scaduto poco” (giorni)',
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

	/* =========================
	 * Page render (UI a carte + manutenzione)
	 * ========================= */

	public static function render() {
		if (!current_user_can('manage_options')) wp_die('Non autorizzato');
	
		$tolleranza = (int) self::get('tolleranza_scaduto_giorni');
		$auto       = (int) self::get('auto_cessazione_giorni');
	
		$notice = isset($_GET['spm_notice']) ? sanitize_text_field($_GET['spm_notice']) : '';
		?>
		<div class="wrap">
			<h1>⚙️ Impostazioni – S121 Pulse Manager</h1>
	
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
	
			<div style="margin:12px 0 20px 0; color:#555;">
				Definisci la <strong>policy di scadenza e rinnovo</strong>. Le regole impattano rinnovi automatici, azioni manuali e KPI.
			</div>
	
			<!-- RIGA 1: RIASSUNTO POLICY -->
			<div style="display:flex; flex-wrap:wrap; gap:20px; margin:20px 0;">
				<div style="flex:1 1 280px; background:white; padding:20px; border-left:4px solid #dba617; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin-top:0; color:#dba617;">⏰ Tolleranza “Scaduto poco”</h3>
					<p style="font-size:28px; margin:0; font-weight:bold;"><?php echo esc_html($tolleranza); ?> giorni</p>
					<p style="color:#666; margin:6px 0 0 0;">Entro questa finestra il rinnovo allinea la scadenza senza cessare il contratto.</p>
				</div>
	
				<div style="flex:1 1 280px; background:white; padding:20px; border-left:4px solid #dc3232; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin-top:0; color:#dc3232;">⛔ Auto-cessazione</h3>
					<p style="font-size:28px; margin:0; font-weight:bold;"><?php echo esc_html($auto); ?> giorni</p>
					<p style="color:#666; margin:6px 0 0 0;">Oltre questa soglia lo stato diventa <strong>cessato</strong> e il rinnovo è bloccato.</p>
				</div>
	
				<div style="flex:1 1 280px; background:white; padding:20px; border-left:4px solid #2271b1; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin-top:0; color:#2271b1;">📐 Regola effettiva</h3>
					<p style="margin:0; line-height:1.6; color:#333;">
						<span style="display:block; margin-bottom:6px;">• <strong>Scaduto ≤ <?php echo esc_html($tolleranza); ?>gg</strong> → <em>Rinnovo consentito</em> (catch-up)</span>
						<span style="display:block;">• <strong>Scaduto &gt; <?php echo esc_html($auto); ?>gg</strong> → <em>Auto-cessato</em> (rinnovo bloccato)</span>
					</p>
				</div>
			</div>
	
			<div style="display:flex; flex-wrap:wrap; gap:20px;">
				<!-- ===== FORM OPZIONI (SOLO questa card) ===== -->
				<form method="post" action="options.php" style="flex:1 1 520px; background:white; padding:20px; border-left:4px solid #2c3338; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<?php settings_fields(self::OPTION_GROUP); ?>
					<h2 style="margin:0 0 12px 0;">🔧 Policy contratti</h2>
	
					<div style="display:flex; align-items:center; gap:12px; margin:12px 0;">
						<label for="spm_tol" style="width:260px; font-weight:600;">Tolleranza “scaduto poco”</label>
						<input id="spm_tol" type="number" min="0" name="<?php echo esc_attr(self::OPTION_NAME); ?>[tolleranza_scaduto_giorni]"
							   value="<?php echo esc_attr($tolleranza); ?>" class="small-text" />
						<span style="color:#666;">giorni</span>
					</div>
					<p style="margin:0 0 16px 0; color:#666;">Entro questa soglia consentiamo il rinnovo con allineamento (roll-forward fino a superare oggi).</p>
	
					<div style="display:flex; align-items:center; gap:12px; margin:12px 0;">
						<label for="spm_auto" style="width:260px; font-weight:600;">Soglia auto-cessazione</label>
						<input id="spm_auto" type="number" min="1" name="<?php echo esc_attr(self::OPTION_NAME); ?>[auto_cessazione_giorni]"
							   value="<?php echo esc_attr($auto); ?>" class="small-text" />
						<span style="color:#666;">giorni</span>
					</div>
					<p style="margin:0; color:#666;">Superata questa soglia, lo stato passa a <strong>cessato</strong> e il rinnovo è bloccato.</p>
					
					<hr style="margin:24px 0;">
					<h2 style="margin:0 0 12px 0;">🌐 Frontend pubblico</h2>
					
					<div style="display:grid; grid-template-columns:260px 1fr; gap:12px; align-items:center;">
					
						<label for="spm_frontend_mode" style="font-weight:600;">Modalità</label>
						<select id="spm_frontend_mode" name="<?php echo esc_attr(self::OPTION_NAME); ?>[frontend_mode]">
							<?php $m = (string) self::get('frontend_mode'); ?>
							<option value="normal" <?php selected($m,'normal'); ?>>normal – lascia il tema attivo</option>
							<option value="static" <?php selected($m,'static'); ?>>static – pagina statica minimale</option>
							<option value="off"    <?php selected($m,'off');    ?>>off – solo backend (login)</option>
						</select>
						<span class="description" style="grid-column:2 / span 1; color:#666;">Richiede il file/class SPM_Frontend_Controller.</span>
					
						<label for="spm_frontend_redirect" style="font-weight:600;">Se loggato, vai in Bacheca</label>
						<input id="spm_frontend_redirect" type="checkbox" value="1"
							name="<?php echo esc_attr(self::OPTION_NAME); ?>[frontend_redirect_logged_in]"
							<?php checked( (int) self::get('frontend_redirect_logged_in'), 1 ); ?> />
						<span class="description" style="grid-column:2 / span 1; color:#666;">Valido in modalità <em>static</em>.</span>
					
						<label for="spm_frontend_noindex" style="font-weight:600;">Noindex/Nofollow</label>
						<input id="spm_frontend_noindex" type="checkbox" value="1"
							name="<?php echo esc_attr(self::OPTION_NAME); ?>[frontend_noindex]"
							<?php checked( (int) self::get('frontend_noindex'), 1 ); ?> />
						<span class="description" style="grid-column:2 / span 1; color:#666;">Invia X-Robots-Tag e meta robots come noindex.</span>
					
						<label for="spm_frontend_html" style="font-weight:600; align-self:start;">HTML pagina statica (opzionale)</label>
						<textarea id="spm_frontend_html" rows="8" style="width:100%;"
							name="<?php echo esc_attr(self::OPTION_NAME); ?>[frontend_static_html]"><?php
							echo esc_textarea( (string) self::get('frontend_static_html') ); ?></textarea>
						<span class="description" style="grid-column:2 / span 1; color:#666;">Lascia vuoto per usare il template predefinito del controller.</span>
					</div>

	
					<div style="margin-top:18px;">
						<?php submit_button('💾 Salva impostazioni'); ?>
					</div>
				</form>
	
				<!-- ===== CARD MANUTENZIONE (FUORI dal form options.php) ===== -->
				<div style="flex:1 1 380px; background:white; padding:20px; border-left:4px solid #72aee6; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<h2 style="margin:0 0 12px 0;">🛠️ Manutenzione statistiche</h2>
					<p style="margin:0 0 10px 0; color:#555;">Operazioni idempotenti per ripristinare la coerenza di history & KPI.</p>
	
					<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin:10px 0;">
						<input type="hidden" name="action" value="spm_stats_rebuild_kpis" />
						<?php wp_nonce_field('spm_stats_rebuild_kpis'); ?>
						<button type="submit" class="button">🔁 Ricostruisci KPI</button>
						<span style="color:#666; margin-left:8px;">Ricalcola i KPI per tutto lo storico disponibile.</span>
					</form>
	
					<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin:10px 0;">
						<input type="hidden" name="action" value="spm_stats_purge_orphans" />
						<?php wp_nonce_field('spm_stats_purge_orphans'); ?>
						<button type="submit" class="button">🧽 Pulisci orfani + KPI</button>
						<span style="color:#666; margin-left:8px;">Cancella history di contratti rimossi e ricostruisci KPI.</span>
					</form>
	
					<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin:10px 0;" onsubmit="return confirm('ATTENZIONE: questa operazione svuota le tabelle history e KPI e rimaterializza tutto da zero. Procedere?');">
						<input type="hidden" name="action" value="spm_stats_hard_reindex" />
						<?php wp_nonce_field('spm_stats_hard_reindex'); ?>
						<button type="submit" class="button button-primary">⚠️ Hard Reindex (TRUNCATE + Rebuild)</button>
						<span style="color:#666; display:block; margin-top:6px;">Usa solo in caso di import massivi o incoerenze gravi.</span>
					</form>
				</div>
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
