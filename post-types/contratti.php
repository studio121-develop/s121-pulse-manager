<?php
defined('ABSPATH') || exit;

/**
 * Registrazione CPT "contratti" 
 * Sostituisce il vecchio "servizi_cliente" con naming più chiaro
 */
function spm_register_cpt_contratti() {
	register_post_type('contratti', [
		'labels' => [
			'name' => 'Contratti',
			'singular_name' => 'Contratto',
			'add_new' => 'Nuovo Contratto',
			'add_new_item' => 'Aggiungi Contratto',
			'edit_item' => 'Modifica Contratto',
			'new_item' => 'Nuovo Contratto',
			'view_item' => 'Visualizza Contratto',
			'search_items' => 'Cerca Contratto',
			'not_found' => 'Nessun contratto trovato',
			'all_items' => 'Tutti i Contratti',
			'menu_name' => 'Contratti',
		],
		'public' => false,
		'show_ui' => true,
		'show_in_menu' => true,
		'menu_position' => 22,
		'menu_icon' => 'dashicons-media-document',
		'supports' => ['custom-fields'],
		'capability_type' => 'post',
		'has_archive' => false,
		'rewrite' => ['slug' => 'contratti'],
	]);
}
add_action('init', 'spm_register_cpt_contratti');