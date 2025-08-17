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
