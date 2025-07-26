<?php
defined('ABSPATH') || exit;

function spm_register_cpt_servizi_clienti() {
	register_post_type('servizio_cliente', [
		'labels' => [
			'name' => 'Servizi Clienti',
			'singular_name' => 'Servizio Cliente',
			'add_new' => 'Assegna Servizio a Cliente',
			'add_new_item' => 'Nuovo Servizio Cliente',
			'edit_item' => 'Modifica Servizio Cliente',
			'new_item' => 'Nuovo Abbinamento',
			'view_item' => 'Visualizza Servizio Cliente',
			'search_items' => 'Cerca Servizio Cliente',
			'not_found' => 'Nessun abbinamento trovato',
		],
		'public' => false,
		'show_ui' => true,
		'show_in_menu' => true,
		'menu_position' => 22,
		'menu_icon' => 'dashicons-update',
		'supports' => ['title'],
		'capability_type' => 'post',
		'has_archive' => false,
	]);
}
add_action('init', 'spm_register_cpt_servizi_clienti');
