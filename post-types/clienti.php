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
