<?php
defined('ABSPATH') || exit;

if (function_exists('acf_add_local_field_group')) {
	acf_add_local_field_group([
		'key' => 'group_cliente',
		'title' => 'Dati Cliente',
		'fields' => [
			[
				'key' => 'field_email_cliente',
				'label' => 'Email',
				'name' => 'email',
				'type' => 'email',
			],
			[
				'key' => 'field_piva_cliente',
				'label' => 'Partita IVA',
				'name' => 'partita_iva',
				'type' => 'text',
			],
			[
				'key' => 'field_telefono_cliente',
				'label' => 'Telefono',
				'name' => 'telefono',
				'type' => 'text',
			],
			[
				'key' => 'field_id_fic_cliente',
				'label' => 'ID Fatture in Cloud',
				'name' => 'id_fatture_in_cloud',
				'type' => 'text',
				'instructions' => 'Non modificare: gestito automaticamente dalla sincronizzazione.',
				'readonly' => 1,
			],
			[
				'key' => 'field_note_fic',
				'label' => 'Note da Fatture in Cloud',
				'name' => 'note_fic',
				'type' => 'textarea',
				'readonly' => 1,
				'rows' => 3,
			],
		],
		'location' => [[
			['param' => 'post_type', 'operator' => '==', 'value' => 'cliente']
		]]
	]);
}
