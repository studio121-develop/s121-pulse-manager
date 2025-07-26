<?php
defined('ABSPATH') || exit;

if (function_exists('acf_add_local_field_group')) {
	acf_add_local_field_group([
		'key' => 'group_servizio',
		'title' => 'Dati Servizio Generico',
		'fields' => [
			[
				'key' => 'field_categoria_servizio',
				'label' => 'Categoria',
				'name' => 'categoria_servizio',
				'type' => 'select',
				'choices' => [
					'hosting' => 'Hosting',
					'seo' => 'SEO',
					'email' => 'Email Marketing',
					'altro' => 'Altro',
				],
				'ui' => 1,
			],
			[
				'key' => 'field_prezzo_listino',
				'label' => 'Prezzo di Listino (€)',
				'name' => 'prezzo_listino',
				'type' => 'number',
				'prepend' => '€',
				'min' => 0,
				'step' => 0.01,
			],
			[
				'key' => 'field_codice_fic_servizio',
				'label' => 'Codice Prodotto Fatture in Cloud',
				'name' => 'codice_fatture_in_cloud',
				'type' => 'text',
			],
		],
		'location' => [[
			['param' => 'post_type', 'operator' => '==', 'value' => 'servizio']
		]]
	]);
}
