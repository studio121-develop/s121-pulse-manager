<?php
defined('ABSPATH') || exit;

class SPM_Settings_Page {

	const OPTION_GROUP = 'spm_settings';
	const OPTION_NAME  = 'spm_options';

	public static function init() {
		add_action('admin_init', [__CLASS__, 'register_settings']);
	}

	/* =========================
	 * Default & Accessor
	 * ========================= */
	public static function defaults() {
		return [
			'tolleranza_scaduto_giorni' => 60, // entro questa soglia si può allineare
			'auto_cessazione_giorni'    => 90, // oltre questa soglia -> cessato
		];
	}

	public static function get($key) {
		$opts = get_option(self::OPTION_NAME, self::defaults());
		$all  = wp_parse_args($opts, self::defaults());
		return $all[$key] ?? null;
	}

	/* =========================
	 * Register
	 * ========================= */
	public static function register_settings() {
		register_setting(self::OPTION_GROUP, self::OPTION_NAME, [
			'type'              => 'array',
			'sanitize_callback' => [__CLASS__, 'sanitize'],
			'default'           => self::defaults(),
		]);

		// Manteniamo sezione/fields registrati (compat), anche se non usiamo do_settings_sections().
		add_settings_section('spm_contract_policy', 'Policy Contratti', '__return_false', 'spm-settings');

		add_settings_field(
			'tolleranza_scaduto_giorni',
			'Tolleranza “scaduto poco” (giorni)',
			[__CLASS__, 'render_number_field'],
			'spm-settings',
			'spm_contract_policy',
			['key' => 'tolleranza_scaduto_giorni', 'min' => 0, 'desc' => 'Entro questa soglia è consentito il rinnovo con allineamento.']
		);

		add_settings_field(
			'auto_cessazione_giorni',
			'Auto-cessazione (giorni)',
			[__CLASS__, 'render_number_field'],
			'spm-settings',
			'spm_contract_policy',
			['key' => 'auto_cessazione_giorni', 'min' => 1, 'desc' => 'Oltre questa soglia un contratto è considerato cessato.']
		);
	}

	/* =========================
	 * Sanitize
	 * ========================= */
	public static function sanitize($input) {
		$out = self::defaults();
		$in  = is_array($input) ? $input : [];

		$out['tolleranza_scaduto_giorni'] = max(0, intval($in['tolleranza_scaduto_giorni'] ?? $out['tolleranza_scaduto_giorni']));
		$out['auto_cessazione_giorni']    = max(1, intval($in['auto_cessazione_giorni'] ?? $out['auto_cessazione_giorni']));

		// Regola dura: auto_cessazione >= tolleranza (coerenza operativa)
		if ($out['auto_cessazione_giorni'] < $out['tolleranza_scaduto_giorni']) {
			$out['auto_cessazione_giorni'] = $out['tolleranza_scaduto_giorni'];
			add_settings_error(self::OPTION_NAME, 'spm_policy_adjust',
				'Auto-cessazione riallineata alla tolleranza: non può essere inferiore.', 'updated');
		}

		return $out;
	}

	/* =========================
	 * Field renderer (compat)
	 * ========================= */
	public static function render_number_field($args) {
		$key   = $args['key'];
		$min   = intval($args['min'] ?? 0);
		$desc  = esc_html($args['desc'] ?? '');
		$value = esc_attr(self::get($key));
		echo "<input type='number' min='{$min}' name='" . self::OPTION_NAME . "[{$key}]' value='{$value}' class='small-text' />";
		if ($desc) echo "<p class='description'>{$desc}</p>";
	}

	/* =========================
	 * Page render (nuovo layout)
	 * ========================= */
	public static function render() {
		if (!current_user_can('manage_options')) wp_die('Non autorizzato');

		$tolleranza = (int) self::get('tolleranza_scaduto_giorni');
		$auto       = (int) self::get('auto_cessazione_giorni');

		?>
		<div class="wrap">
			<h1>⚙️ Impostazioni – S121 Pulse Manager</h1>

			<?php settings_errors(self::OPTION_NAME); ?>

			<!-- HERO / NOTE -->
			<div style="margin:12px 0 20px 0; color:#555;">
				Definisci la <strong>policy di scadenza e rinnovo</strong>. Queste regole sono usate da rinnovi automatici, azioni manuali e KPI.
			</div>

			<!-- RIGA 1: RIASSUNTO POLICY (carte in stile dashboard) -->
			<div style="display:flex; flex-wrap:wrap; gap:20px; margin:20px 0;">
				<div style="flex:1 1 280px; background:white; padding:20px; border-left:4px solid #dba617; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin-top:0; color:#dba617;">⏰ Tolleranza “Scaduto poco”</h3>
					<p style="font-size:28px; margin:0; font-weight:bold;"><?php echo esc_html($tolleranza); ?> giorni</p>
					<p style="color:#666; margin:6px 0 0 0;">Entro questa finestra il rinnovo allinea la scadenza senza cessare il contratto.</p>
				</div>

				<div style="flex:1 1 280px; background:white; padding:20px; border-left:4px solid #dc3232; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin-top:0; color:#dc3232;">⛔ Auto-cessazione</h3>
					<p style="font-size:28px; margin:0; font-weight:bold;"><?php echo esc_html($auto); ?> giorni</p>
					<p style="color:#666; margin:6px 0 0 0;">Oltre questa soglia lo stato passa a <strong>cessato</strong> e il rinnovo è bloccato.</p>
				</div>

				<div style="flex:1 1 280px; background:white; padding:20px; border-left:4px solid #2271b1; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin-top:0; color:#2271b1;">📐 Regola effettiva</h3>
					<p style="margin:0; line-height:1.6; color:#333;">
						<span style="display:block; margin-bottom:6px;">• <strong>Scaduto ≤ <?php echo esc_html($tolleranza); ?>gg</strong> → <em>Rinnovo consentito</em> (catch-up & allineamento)</span>
						<span style="display:block;">• <strong>Scaduto &gt; <?php echo esc_html($auto); ?>gg</strong> → <em>Auto-cessato</em> (rinnovo bloccato)</span>
					</p>
				</div>
			</div>

			<!-- RIGA 2: FORM IMPOSTAZIONI (stilizzato) -->
			<form method="post" action="options.php" style="margin-top:10px;">
				<?php settings_fields(self::OPTION_GROUP); ?>

				<div style="display:flex; flex-wrap:wrap; gap:20px;">

					<!-- CARD: Policy Contratti -->
					<div style="flex:1 1 520px; background:white; padding:20px; border-left:4px solid #2c3338; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
						<h2 style="margin:0 0 12px 0;">🔧 Policy contratti</h2>

						<!-- Campo: Tolleranza -->
						<div style="display:flex; align-items:center; gap:12px; margin:12px 0;">
							<label for="spm_tol" style="width:260px; font-weight:600;">Tolleranza “scaduto poco”</label>
							<input id="spm_tol" type="number" min="0" name="<?php echo esc_attr(self::OPTION_NAME); ?>[tolleranza_scaduto_giorni]"
								   value="<?php echo esc_attr($tolleranza); ?>" class="small-text" />
							<span style="color:#666;">giorni</span>
						</div>
						<p style="margin:0 0 16px 0; color:#666;">Entro questa soglia consentiamo il rinnovo con allineamento (roll-forward fino a superare oggi).</p>

						<!-- Campo: Auto-cessazione -->
						<div style="display:flex; align-items:center; gap:12px; margin:12px 0;">
							<label for="spm_auto" style="width:260px; font-weight:600;">Soglia auto-cessazione</label>
							<input id="spm_auto" type="number" min="1" name="<?php echo esc_attr(self::OPTION_NAME); ?>[auto_cessazione_giorni]"
								   value="<?php echo esc_attr($auto); ?>" class="small-text" />
							<span style="color:#666;">giorni</span>
						</div>
						<p style="margin:0; color:#666;">Se la scadenza supera questa soglia, lo stato diventa <strong>cessato</strong> e non è più rinnovabile.</p>
					</div>

					<!-- CARD: Suggerimenti operativi -->
					<div style="flex:1 1 320px; background:white; padding:20px; border-left:4px solid #72aee6; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
						<h2 style="margin:0 0 12px 0;">💡 Suggerimenti</h2>
						<ul style="margin:0 0 0 18px; padding:0; color:#555; line-height:1.6;">
							<li>Mantenere <em>auto-cessazione ≥ tolleranza</em> per coerenza operativa.</li>
							<li>Ridurre la tolleranza accelera il “tempo al rischio” → impatto su KPI.</li>
							<li>Le modifiche sono <em>immediate</em> su rinnovi automatici e azioni manuali.</li>
						</ul>
					</div>
				</div>

				<!-- CTA salva -->
				<div style="margin-top:18px;">
					<?php submit_button('Salva impostazioni'); ?>
				</div>
			</form>
		</div>
		<?php
	}
}
