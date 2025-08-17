<?php
defined('ABSPATH') || exit;

class SPM_Settings_Page {

	const OPTION_GROUP = 'spm_settings';
	const OPTION_NAME  = 'spm_options';

	public static function init() {
		add_action('admin_init', [__CLASS__, 'register_settings']);
	}

	public static function defaults() {
		return [
			'tolleranza_scaduto_giorni' => 60,
			'auto_cessazione_giorni'    => 90,
		];
	}

	public static function register_settings() {
		register_setting(self::OPTION_GROUP, self::OPTION_NAME, [
			'type'              => 'array',
			'sanitize_callback' => [__CLASS__, 'sanitize'],
			'default'           => self::defaults(),
		]);

		add_settings_section('spm_contract_policy', 'Policy Contratti', function () {
			echo '<p>Definisci la logica di scadenza/rinnovo. Questi valori sostituiscono le costanti nel codice.</p>';
		}, 'spm-settings');

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

	public static function sanitize($input) {
		$out = self::defaults();
		$in  = is_array($input) ? $input : [];
		$out['tolleranza_scaduto_giorni'] = max(0, intval($in['tolleranza_scaduto_giorni'] ?? $out['tolleranza_scaduto_giorni']));
		$out['auto_cessazione_giorni']    = max(1, intval($in['auto_cessazione_giorni'] ?? $out['auto_cessazione_giorni']));

		// hard rule: auto_cessazione deve essere >= tolleranza (ha senso operativo)
		if ($out['auto_cessazione_giorni'] < $out['tolleranza_scaduto_giorni']) {
			$out['auto_cessazione_giorni'] = $out['tolleranza_scaduto_giorni'];
		}
		return $out;
	}

	public static function get($key) {
		$opts = get_option(self::OPTION_NAME, self::defaults());
		$all  = wp_parse_args($opts, self::defaults());
		return $all[$key] ?? null;
	}

	public static function render_number_field($args) {
		$key   = $args['key'];
		$min   = intval($args['min'] ?? 0);
		$desc  = esc_html($args['desc'] ?? '');
		$value = esc_attr(self::get($key));
		echo "<input type='number' min='{$min}' name='" . self::OPTION_NAME . "[{$key}]' value='{$value}' class='small-text' />";
		if ($desc) echo "<p class='description'>{$desc}</p>";
	}

	public static function render() {
		if (!current_user_can('manage_options')) wp_die('Non autorizzato');
		?>
		<div class="wrap">
			<h1>Impostazioni – S121 Pulse Manager</h1>
			<form method="post" action="options.php">
				<?php
					settings_fields(self::OPTION_GROUP);
					do_settings_sections('spm-settings');
					submit_button('Salva impostazioni');
				?>
			</form>
		</div>
		<?php
	}
}