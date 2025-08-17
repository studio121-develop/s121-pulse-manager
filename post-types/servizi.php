<?php
defined('ABSPATH') || exit;

function spm_register_cpt_servizi() {
	register_post_type('servizi', [
		'labels' => [
			'name' => 'Servizi',
			'singular_name' => 'Servizio',
			'add_new' => 'Aggiungi Servizio',
			'add_new_item' => 'Nuovo Servizio',
			'edit_item' => 'Modifica Servizio',
			'new_item' => 'Nuovo Servizio',
			'view_item' => 'Visualizza Servizio',
			'search_items' => 'Cerca Servizio',
			'not_found' => 'Nessun servizio trovato',
		],
		'public' => false,
		'show_ui' => true,
		'show_in_menu'      => 's121-pulse-manager', // <- aggancia al menu del plugin
		'show_in_admin_bar' => false,                 // niente duplicati nella admin bar
		'menu_position'     => null,                  // gestito dal menu principale
		'menu_icon'         => null,                  // l’icona è del top-level
		'supports' => ['title'],
		'capability_type' => 'post',
		'has_archive' => false,
		
		

	]);
}
add_action('init', 'spm_register_cpt_servizi');
/**
 * =========================
 *  SERVIZI: META & COLONNE
 * =========================
 *
 * Meta usata su CPT "servizi":
 * - is_servizio_usato : '1' | '0'
 *
 * Regola:
 * - "Usato" = esiste almeno un contratto con ACF 'servizio' = ID del servizio.
 */

/** Calcola e salva il flag 'is_servizio_usato' per UN servizio. */
function spm_update_servizio_flag($servizio_id){
	if (!$servizio_id || get_post_type($servizio_id) !== 'servizi') return;

	$q = new WP_Query([
		'post_type'      => 'contratti',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => [
			[
				'key'     => 'servizio',
				'value'   => (string) (int) $servizio_id,
				'compare' => '='
			],
		],
	]);

	update_post_meta($servizio_id, 'is_servizio_usato', $q->have_posts() ? '1' : '0');
}

/** Ricalcola tutti i servizi. */
function spm_recalc_all_servizi_flags(){
	$q = new WP_Query([
		'post_type'      => 'servizi',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);
	if ($q->have_posts()) {
		foreach ($q->posts as $sid) spm_update_servizio_flag($sid);
	}
}

/**
 * ============================
 *  GESTIONE HOOK SALVATAGGIO
 * ============================
 * Copriamo anche il cambio servizio (A -> B) nello stesso contratto.
 */
$GLOBALS['spm_prev_servizio_per_contratto'] = [];

/** Prima del salvataggio ACF: memorizza il "vecchio" servizio. */
add_filter('acf/update_value/name=servizio', function($value, $post_id, $field, $original){
	$old = get_post_meta($post_id, 'servizio', true); // prima che venga sovrascritto
	$GLOBALS['spm_prev_servizio_per_contratto'][(int)$post_id] = (int)$old;
	return $value;
}, 5, 4);

/** Dopo ACF ha scritto: aggiorna i flag per vecchio e nuovo servizio. */
add_action('acf/save_post', function($post_id){
	if (get_post_type($post_id) !== 'contratti') return;

	$new = (int) get_post_meta($post_id, 'servizio', true);
	$old = isset($GLOBALS['spm_prev_servizio_per_contratto'][(int)$post_id])
		? (int) $GLOBALS['spm_prev_servizio_per_contratto'][(int)$post_id]
		: 0;

	if ($new) spm_update_servizio_flag($new);
	if ($old && $old !== $new) spm_update_servizio_flag($old);

	unset($GLOBALS['spm_prev_servizio_per_contratto'][(int)$post_id]);
}, 99);

/** Salvataggi non-ACF (import/CLI ecc.). */
add_action('save_post_contratti', function($post_id, $post, $update){
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
	$new = (int) get_post_meta($post_id, 'servizio', true);
	if ($new) spm_update_servizio_flag($new);
}, 99, 3);

/** Trash / Untrash / Delete contratto → aggiorna il servizio attuale. */
add_action('wp_trash_post', function($post_id){
	if (get_post_type($post_id) !== 'contratti') return;
	$serv_id = (int) get_post_meta($post_id, 'servizio', true);
	if ($serv_id) spm_update_servizio_flag($serv_id);
}, 10);

add_action('untrash_post', function($post_id){
	if (get_post_type($post_id) !== 'contratti') return;
	$serv_id = (int) get_post_meta($post_id, 'servizio', true);
	if ($serv_id) spm_update_servizio_flag($serv_id);
}, 10);

add_action('before_delete_post', function($post_id){
	if (get_post_type($post_id) !== 'contratti') return;
	$serv_id = (int) get_post_meta($post_id, 'servizio', true);
	if ($serv_id) spm_update_servizio_flag($serv_id);
}, 10);

/**
 * ===============
 *  CRON & BACKFILL
 * ===============
 */
add_action('init', function(){
	if (!wp_next_scheduled('spm_daily_servizi_flags')) {
		wp_schedule_event(strtotime('tomorrow 5:15am'), 'daily', 'spm_daily_servizi_flags');
	}
});
add_action('spm_daily_servizi_flags', 'spm_recalc_all_servizi_flags');

/** Backfill manuale: ?spm_backfill_servizi=1 */
add_action('admin_init', function(){
	if (!current_user_can('manage_options')) return;
	if (empty($_GET['spm_backfill_servizi'])) return;

	spm_recalc_all_servizi_flags();
	$count = (int) wp_count_posts('servizi')->publish;
	wp_safe_redirect( add_query_arg(['spm_backfill_servizi_done'=>$count], remove_query_arg('spm_backfill_servizi')) );
	exit;
});
add_action('admin_notices', function(){
	if (!current_user_can('manage_options')) return;
	if (isset($_GET['spm_backfill_servizi_done'])) {
		$done = (int) $_GET['spm_backfill_servizi_done'];
		echo '<div class="notice notice-success is-dismissible"><p>Backfill servizi completato. Flag aggiornati per ' . esc_html($done) . ' servizi.</p></div>';
	}
});

/**
 * ==================
 *  COLONNE IN ADMIN
 * ==================
 * - "Usato" con LED verde se is_servizio_usato=1
 * - Rimozione colonna "Data"
 * - Ordinabilità + filtro Sì/No
 * - Ordinamento di default: Usati prima
 */
add_filter('manage_servizi_posts_columns', function($cols){
	$new = [];
	$new['cb']        = $cols['cb'];
	$new['title']     = __('Servizio', 'spm');
	$new['spm_usato'] = __('Usato', 'spm');

	// conserva eventuali altre colonne personalizzate ma rimuovi 'date'
	foreach ($cols as $k=>$v) {
		if (in_array($k, ['cb','title','date'])) continue;
		$new[$k] = $v;
	}
	return $new;
});

add_action('manage_servizi_posts_custom_column', function($col, $post_id){
	if ($col === 'spm_usato') {
		$used = get_post_meta($post_id, 'is_servizio_usato', true);
		echo ($used === '1')
			? '<span class="spm-led spm-led-green" title="'.esc_attr__('Servizio utilizzato in almeno un contratto','spm').'"></span>'
			: '';
	}
}, 10, 2);

add_filter('manage_edit-servizi_sortable_columns', function($cols){
	$cols['spm_usato'] = 'is_servizio_usato';
	return $cols;
});

add_action('restrict_manage_posts', function($post_type){
	if ($post_type !== 'servizi') return;
	$val = isset($_GET['filter_usato']) ? sanitize_text_field($_GET['filter_usato']) : '';
	?>
	<select name="filter_usato" style="margin-left:8px">
		<option value=""><?php esc_html_e('Tutti', 'spm');?></option>
		<option value="1" <?php selected($val, '1'); ?>><?php esc_html_e('Usati', 'spm');?></option>
		<option value="0" <?php selected($val, '0'); ?>><?php esc_html_e('Non usati', 'spm');?></option>
	</select>
	<?php
});

/** Query admin: filtro + ordinamenti + default. */
add_action('pre_get_posts', function($q){
	if (!is_admin() || !$q->is_main_query()) return;
	if ($q->get('post_type') !== 'servizi') return;

	// Filtro Sì/No
	if (isset($_GET['filter_usato']) && $_GET['filter_usato'] !== '') {
		$want = $_GET['filter_usato'] === '1' ? '1' : '0';
		$mq = $q->get('meta_query') ?: [];
		$mq[] = [
			'key'     => 'is_servizio_usato',
			'value'   => $want,
			'compare' => '='
		];
		$q->set('meta_query', $mq);
	}

	// Ordinamento su click intestazione
	$orderby = $q->get('orderby');
	if ($orderby === 'is_servizio_usato') {
		$q->set('meta_key', 'is_servizio_usato');
		$q->set('orderby', 'meta_value_num');
	}

	// Default: usati prima (includi anche post senza meta per evitare liste vuote)
	if (!$orderby) {
		$q->set('meta_key', 'is_servizio_usato');
		$q->set('orderby', 'meta_value_num');
		$q->set('order', 'DESC');
		$q->set('meta_query', [
			'relation' => 'OR',
			['key' => 'is_servizio_usato', 'compare' => 'EXISTS'],
			['key' => 'is_servizio_usato', 'compare' => 'NOT EXISTS'],
		]);
	}
});

/** CSS LED */
add_action('admin_head', function(){
	?>
	<style>
		.spm-led {
			display:inline-block; width:10px; height:10px; border-radius:50%;
			margin-left:2px; vertical-align:middle;
		}
		.spm-led-green {
			background:#00c853;
			box-shadow: 0 0 0 0 rgba(0,200,83,.7);
			animation: spm-pulse 1.8s infinite;
		}
		@keyframes spm-pulse {
			0%   { box-shadow: 0 0 0 0 rgba(0,200,83,.7); }
			70%  { box-shadow: 0 0 0 8px rgba(0,200,83,0); }
			100% { box-shadow: 0 0 0 0 rgba(0,200,83,0); }
		}
		.column-spm_usato { width: 80px; text-align:center; }
	</style>
	<?php
});