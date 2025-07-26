<?php
defined('ABSPATH') || exit;

function spm_register_cpt_servizi() {
	register_post_type('servizio', [
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
		'show_in_menu' => true,
		'menu_position' => 21,
		'menu_icon' => 'dashicons-portfolio',
		'supports' => ['title', 'editor'],
		'capability_type' => 'post',
		'has_archive' => false,
	]);
}
add_action('init', 'spm_register_cpt_servizi');
