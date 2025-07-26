<?php
defined('ABSPATH') || exit;

/**
 * 🔧 Genera il titolo automatico per "servizi_cliente" come:
 * #ID – Cliente – Servizio
 */
add_action('acf/save_post', 'spm_set_titolo_servizi_clienti', 20);
function spm_set_titolo_servizi_clienti($post_id) {
	if (get_post_type($post_id) !== 'servizi_cliente' || !is_numeric($post_id)) return;

	$cliente = get_field('cliente', $post_id);
	$servizio = get_field('servizio', $post_id);

	if (is_object($cliente) && is_object($servizio)) {
		$title = '#' . $post_id . ' – ' . get_the_title($cliente->ID) . ' – ' . get_the_title($servizio->ID);
		$current = get_post_field('post_title', $post_id);

		if ($title !== $current) {
			remove_action('acf/save_post', 'spm_set_titolo_servizi_clienti');
			wp_update_post([
				'ID' => $post_id,
				'post_title' => $title,
				'post_name' => sanitize_title($title),
			]);
			add_action('acf/save_post', 'spm_set_titolo_servizi_clienti');
		}
	}
}

/**
 * 🧼 Nasconde il campo "titolo" nell'editor admin
 */
add_action('admin_head', 'spm_nascondi_titolo_servizi_clienti');
function spm_nascondi_titolo_servizi_clienti() {
	$screen = get_current_screen();
	if ($screen && $screen->post_type === 'servizi_cliente') {
		echo '<style>#titlediv { display: none !important; }</style>';
	}
}

/**
 * 📋 Colonne personalizzate per la tabella admin di "servizi_cliente"
 */
add_filter('manage_servizi_cliente_posts_columns', function ($columns) {
	unset($columns['date']); // rimuove la colonna "Data"
	return array_merge($columns, [
		'cliente' => 'Cliente',
		'servizio' => 'Servizio',
		'decorrenza' => 'Decorrenza Attiva',
		'data_ultimo_rinnovo' => 'Ultimo Rinnovo',
		'data_scadenza' => 'Scadenza',
		'stato_contratto' => 'Stato',
	]);
});

/**
 * 🧩 Valori per le colonne personalizzate
 */
add_action('manage_servizi_cliente_posts_custom_column', function ($column, $post_id) {
	switch ($column) {
		case 'cliente':
			$cliente = get_field('cliente', $post_id);
			echo $cliente ? esc_html(get_the_title($cliente)) : '—';
			break;

		case 'servizio':
			$servizio = get_field('servizio', $post_id);
			echo $servizio ? esc_html(get_the_title($servizio)) : '—';
			break;

		case 'decorrenza':
			$data_rinnovo = get_field('data_ultimo_rinnovo', $post_id);
			$data_inizio = get_field('data_inizio', $post_id);
			echo $data_rinnovo ?: ($data_inizio ?: '—');
			break;

		case 'data_ultimo_rinnovo':
			echo esc_html(get_field('data_ultimo_rinnovo', $post_id)) ?: '—';
			break;

		case 'data_scadenza':
			echo esc_html(get_field('data_scadenza', $post_id)) ?: '—';
			break;

		case 'stato_contratto':
			$stato = get_field('stato_contratto', $post_id);
			$scadenza = get_field('data_scadenza', $post_id);
			$oggi = new DateTime();
			$entro_45 = (new DateTime())->modify('+45 days');
			$is_in_scadenza = false;

			if ($scadenza) {
				$data_scadenza = DateTime::createFromFormat('d/m/Y', $scadenza);
				if ($data_scadenza && $data_scadenza >= $oggi && $data_scadenza <= $entro_45) {
					$is_in_scadenza = true;
				}
			}

			if ($stato) {
				$class = 'badge-' . sanitize_title($stato);
				if ($stato === 'attivo' && $is_in_scadenza) {
					$class = 'badge-in-scadenza';
					$stato = 'In Scadenza';
				}
				echo '<span class="badge-stato ' . esc_attr($class) . '">' . esc_html(ucfirst($stato)) . '</span>';
			} else {
				echo '—';
			}
			break;
	}
}, 10, 2);

/**
 * 🎨 Badge colorati per lo stato contratto
 */
add_action('admin_head', function () {
	$screen = get_current_screen();
	if ($screen && $screen->post_type === 'servizi_cliente') {
		echo '<style>
			.badge-stato {
				display: inline-block;
				padding: 3px 8px;
				border-radius: 12px;
				font-size: 12px;
				color: #fff;
				font-weight: 600;
				text-transform: capitalize;
			}
			.badge-attivo { background-color: #46b450; }
			.badge-sospeso { background-color: #72777c; }
			.badge-dismesso { background-color: #444; }
			.badge-scaduto { background-color: #dc3232; }
			.badge-in-scadenza { background-color: #ffb900; color: #222; }
		</style>';
	}
});

/**
 * 🔍 Filtri personalizzati: Stato + In Scadenza (checkbox)
 */
add_action('restrict_manage_posts', function () {
	global $typenow;
	if ($typenow !== 'servizi_cliente') return;

	// Stato (escludiamo scaduto perché è implicito)
	$stati = [
		'attivo' => 'Attivo',
		'sospeso' => 'Sospeso',
		'dismesso' => 'Dismesso',
	];
	$selected = $_GET['filtro_stato_contratto'] ?? '';
	echo '<select name="filtro_stato_contratto">';
	echo '<option value="">Tutti gli stati</option>';
	foreach ($stati as $val => $label) {
		printf('<option value="%s"%s>%s</option>', $val, selected($selected, $val, false), $label);
	}
	echo '</select>';

	// Checkbox: in scadenza
	$checked = isset($_GET['filtro_in_scadenza']) ? 'checked' : '';
	echo '<label style="margin-left:10px;"><input type="checkbox" name="filtro_in_scadenza" value="1" ' . $checked . '> In Scadenza (entro 45gg)</label>';
});

/**
 * 🔍 Applica i filtri meta_query sulla lista admin
 */
add_filter('pre_get_posts', function ($query) {
	if (!is_admin() || !$query->is_main_query()) return;

	if ($query->get('post_type') === 'servizi_cliente') {
		$meta = [];

		if (!empty($_GET['filtro_stato_contratto'])) {
			$meta[] = [
				'key' => 'stato_contratto',
				'value' => sanitize_text_field($_GET['filtro_stato_contratto']),
			];
		}

		if (!empty($_GET['filtro_in_scadenza'])) {
			$oggi = date('Ymd');
			$limite = date('Ymd', strtotime('+45 days'));
			$meta[] = [
				'key' => 'data_scadenza',
				'value' => [$oggi, $limite],
				'compare' => 'BETWEEN',
				'type' => 'DATE'
			];
		}

		if (!empty($meta)) {
			$query->set('meta_query', $meta);
		}
	}
});
