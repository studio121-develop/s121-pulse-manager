<?php
defined('ABSPATH') || exit;

function spm_register_cpt_clienti() {
	register_post_type('clienti', [
		'labels' => [
			'name' => 'Clienti',
			'singular_name' => 'Cliente',
			'add_new' => 'Aggiungi Cliente',
			'add_new_item' => 'Nuovo Cliente',
			'edit_item' => 'Modifica Cliente',
			'new_item' => 'Nuovo Cliente',
			'view_item' => 'Visualizza Cliente',
			'search_items' => 'Cerca Cliente',
			'not_found' => 'Nessun cliente trovato',
		],
		'public' => false,
		'show_ui' => true,
		'show_in_menu' => true,
		'menu_position' => 20,
		'menu_icon' => 'dashicons-businessperson',
		'supports' => ['title'],
		'capability_type' => 'post',
		'has_archive' => false,
	]);
}
add_action('init', 'spm_register_cpt_clienti');

/**
 * =========================
 *  CLIENTI: META & COLONNE
 * =========================
 *
 * Meta usati su CPT "clienti":
 * - has_contratti_attivi   : '1' | '0'
 * - next_scadenza_cliente  : 'YYYY-MM-DD' | ''
 *
 * Obiettivo:
 * - Colonna "Attivi" con LED verde lampeggiante se has_contratti_attivi=1.
 * - Colonna "Prossima scadenza" con data più vicina (futura, altrimenti minima assoluta).
 * - Ordinabili + filtro Sì/No.
 */

/**
 * Calcola e salva i flag/statistiche minime per un CLIENTE.
 * - has_contratti_attivi = esiste almeno un contratto con stato 'attivo'
 * - next_scadenza_cliente = min(data_prossima_scadenza) tra TUTTI i contratti del cliente:
 *     preferenza per scadenze >= oggi; se nessuna futura, prende la min assoluta; se nulla, ''.
 */
function spm_update_cliente_flags_min($cliente_id) {
	if (!$cliente_id || get_post_type($cliente_id) !== 'clienti') return;

	$today = current_time('Y-m-d');

	// 1) almeno un contratto attivo?
	$q_attivi = new WP_Query([
		'post_type'      => 'contratti',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => [
			'relation' => 'AND',
			['key' => 'cliente', 'value' => $cliente_id],
			['key' => 'stato',   'value' => 'attivo'],
		],
	]);
	$has_active = $q_attivi->have_posts() ? '1' : '0';

	// 2) prossima scadenza tra tutti i contratti del cliente
	$q_all = new WP_Query([
		'post_type'      => 'contratti',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => [
			['key' => 'cliente', 'value' => $cliente_id],
		],
	]);

	$future_min = null; // min >= today
	$abs_min    = null; // min assoluta (fallback)
	if ($q_all->have_posts()) {
		foreach ($q_all->posts as $contratto_id) {
			$due = get_post_meta($contratto_id, 'data_prossima_scadenza', true);
			if (!$due) continue;

			// aggiorna min assoluta
			if ($abs_min === null || strcmp($due, $abs_min) < 0) {
				$abs_min = $due;
			}
			// aggiorna min futura
			if (strcmp($due, $today) >= 0) {
				if ($future_min === null || strcmp($due, $future_min) < 0) {
					$future_min = $due;
				}
			}
		}
	}
	$next_due = $future_min ?: ($abs_min ?: '');

	// Salva i meta (no ACF richiesti)
	update_post_meta($cliente_id, 'has_contratti_attivi', $has_active);
	update_post_meta($cliente_id, 'next_scadenza_cliente', $next_due);
}

/**
 * Tocca i flag cliente a partire da un CONTRATTO (nuovo cliente).
 */
function spm_touch_cliente_from_contract($contratto_id) {
	if (!$contratto_id || get_post_type($contratto_id) !== 'contratti') return;
	$cliente_id = (int) get_post_meta($contratto_id, 'cliente', true);
	if ($cliente_id) spm_update_cliente_flags_min($cliente_id);
}

/**
 * -------------------------
 *  PATCH MINIMAL — HOOKS
 * -------------------------
 * Assicura che aggiorniamo i meta del cliente DOPO che ACF ha scritto i valori del contratto.
 */

// 1) Dopo ACF (priorità tarda)
add_action('acf/save_post', function($post_id){
	if (get_post_type($post_id) !== 'contratti') return;
	spm_touch_cliente_from_contract($post_id);
}, 99);

// 2) Ridondanza su save_post_contratti (import/CLI ecc.) a priorità tarda
add_action('save_post_contratti', function($post_id, $post, $update){
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
	spm_touch_cliente_from_contract($post_id);
}, 99, 3);

// 3) Trash / Untrash / Delete contratti → ricalcola cliente
add_action('wp_trash_post', function($post_id){
	if (get_post_type($post_id) !== 'contratti') return;
	spm_touch_cliente_from_contract($post_id);
}, 10);
add_action('untrash_post', function($post_id){
	if (get_post_type($post_id) !== 'contratti') return;
	spm_touch_cliente_from_contract($post_id);
}, 10);
add_action('before_delete_post', function($post_id){
	if (get_post_type($post_id) !== 'contratti') return;
	spm_touch_cliente_from_contract($post_id);
}, 10);

/**
 * ---------------------
 *  CRON di sanità dati
 * ---------------------
 * Se rimane qualche incoerenza, un job giornaliero ricalcola tutti i clienti.
 */
add_action('init', function(){
	if (!wp_next_scheduled('spm_daily_cliente_flags')) {
		// alle 05:10 (ora server)
		wp_schedule_event(strtotime('tomorrow 5:10am'), 'daily', 'spm_daily_cliente_flags');
	}
});
add_action('spm_daily_cliente_flags', function(){
	$q = new WP_Query([
		'post_type'      => 'clienti',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);
	if ($q->have_posts()) {
		foreach ($q->posts as $cid) spm_update_cliente_flags_min($cid);
	}
});

/**
 * =================
 *  COLONNE ADMIN
 * =================
 */
 add_filter('manage_clienti_posts_columns', function($cols){
	 $new = [];
	 $new['cb']     = $cols['cb'];
	 $new['title']  = __('Cliente', 'spm');
	 $new['spm_attivi'] = __('Attivi', 'spm');
	 $new['spm_next']   = __('Prossima scadenza', 'spm');
 
	 // conserva le altre colonne ma salta 'date'
	 foreach ($cols as $k=>$v) {
		 if (in_array($k, ['cb','title','date'])) continue; // <-- qui togli la colonna Data
		 $new[$k] = $v;
	 }
	 return $new;
 });
 


add_action('manage_clienti_posts_custom_column', function($col, $post_id){
	if ($col === 'spm_attivi') {
		$has = get_post_meta($post_id, 'has_contratti_attivi', true);
		if ($has === '1') {
			// LED verde lampeggiante
			echo '<span class="spm-led spm-led-green" title="Contratti attivi"></span>';
		} else {
			// richiesto: nessun output
			echo '';
		}
	}

	if ($col === 'spm_next') {
		$due = get_post_meta($post_id, 'next_scadenza_cliente', true);
		if ($due) {
			$today = current_time('Y-m-d');
			$days  = (strtotime($due) - strtotime($today)) / DAY_IN_SECONDS;
			$label = date_i18n('d/m/Y', strtotime($due));
			if ($days < 0) {
				echo '<span style="color:#dc3232">⚠️ ' . esc_html($label) . '</span>';
			} elseif ($days <= 30) {
				echo '<span style="color:#d98300">⏰ ' . esc_html($label) . '</span>';
			} else {
				echo '<span style="color:#2271b1">' . esc_html($label) . '</span>';
			}
		} else {
			echo '—';
		}
	}
}, 10, 2);

/**
 * Ordinabilità colonne.
 */
add_filter('manage_edit-clienti_sortable_columns', function($cols){
	$cols['spm_attivi'] = 'has_contratti_attivi';
	$cols['spm_next']   = 'next_scadenza_cliente';
	return $cols;
});

/**
 * Filtri UI in lista clienti: “Contratti attivi: Tutti / Sì / No”.
 */
add_action('restrict_manage_posts', function($post_type){
	if ($post_type !== 'clienti') return;
	$val = isset($_GET['filter_attivi']) ? sanitize_text_field($_GET['filter_attivi']) : '';
	?>
	<select name="filter_attivi" style="margin-left:8px">
		<option value=""><?php esc_html_e('Tutti', 'spm');?></option>
		<option value="1" <?php selected($val, '1'); ?>><?php esc_html_e('Con contratti attivi', 'spm');?></option>
		<option value="0" <?php selected($val, '0'); ?>><?php esc_html_e('Senza contratti attivi', 'spm');?></option>
	</select>
	<?php
});

/**
 * Logica ordinamento + filtri meta.
 */
add_action('pre_get_posts', function($q){
	if (!is_admin() || !$q->is_main_query()) return;
	if ($q->get('post_type') !== 'clienti') return;

	// Filtro Sì/No
	if (isset($_GET['filter_attivi']) && $_GET['filter_attivi'] !== '') {
		$want = $_GET['filter_attivi'] === '1' ? '1' : '0';
		$mq = $q->get('meta_query') ?: [];
		$mq[] = [
			'key'     => 'has_contratti_attivi',
			'value'   => $want,
			'compare' => '='
		];
		$q->set('meta_query', $mq);
	}

	// Ordinamento
	$orderby = $q->get('orderby');
	if ($orderby === 'has_contratti_attivi') {
		$q->set('meta_key', 'has_contratti_attivi');
		$q->set('orderby', 'meta_value_num');
	}
	if ($orderby === 'next_scadenza_cliente') {
		$q->set('meta_key', 'next_scadenza_cliente');
		$q->set('orderby', 'meta_value'); // 'Y-m-d' ordina OK come stringa
	}
});

/**
 * CSS per il LED verde lampeggiante in admin.
 */
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
		.column-spm_attivi { width: 80px; text-align:center; }
		.column-spm_next   { width: 160px; }
	</style>
	<?php
});

/**
 * ============================
 *  BACKFILL MANUALE ON-DEMAND
 * ============================
 * Esegui una volta per popolare tutti i clienti già presenti:
 * vai in wp-admin e aggiungi alla URL: ?spm_backfill_clienti=1
 */
add_action('admin_init', function(){
	if (!current_user_can('manage_options')) return;
	if (empty($_GET['spm_backfill_clienti'])) return;

	$q = new WP_Query([
		'post_type'      => 'clienti',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);
	$count = 0;
	if ($q->have_posts()) {
		foreach ($q->posts as $cid) {
			spm_update_cliente_flags_min($cid);
			$count++;
		}
	}
	wp_safe_redirect( add_query_arg(['spm_backfill_done'=>$count], remove_query_arg('spm_backfill_clienti')) );
	exit;
});

add_action('admin_notices', function(){
	if (!current_user_can('manage_options')) return;
	if (isset($_GET['spm_backfill_done'])) {
		$done = (int) $_GET['spm_backfill_done'];
		echo '<div class="notice notice-success is-dismissible"><p>Backfill clienti completato: ' . esc_html($done) . ' record aggiornati.</p></div>';
	}
});