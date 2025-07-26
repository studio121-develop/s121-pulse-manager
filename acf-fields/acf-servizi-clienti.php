<?php

defined('ABSPATH') || exit;

if (function_exists('acf_add_local_field_group')) {
	acf_add_local_field_group([
		'key' => 'group_spm_servizi_clienti',
		'title' => 'Assegnazione Servizio a Cliente',
		'fields' => [

			[
				'key' => 'field_spm_cliente',
				'label' => 'Cliente',
				'name' => 'cliente',
				'type' => 'post_object',
				'post_type' => ['clienti'],
				'required' => 1,
				'ui' => 1,
			],

			[
				'key' => 'field_spm_servizio',
				'label' => 'Servizio',
				'name' => 'servizio',
				'type' => 'post_object',
				'post_type' => ['servizi'],
				'required' => 1,
				'ui' => 1,
			],

			[
				'key' => 'field_spm_prezzo_personalizzato',
				'label' => 'Prezzo Personalizzato (€)',
				'name' => 'prezzo_personalizzato',
				'type' => 'number',
				'prepend' => '€',
				'min' => 0,
				'step' => 0.01,
				'instructions' => 'Se vuoto, verrà usato il prezzo del servizio',
			],

			[
				'key' => 'field_spm_ricorrenza_custom',
				'label' => 'Ricorrenza Personalizzata',
				'name' => 'ricorrenza_personalizzata',
				'type' => 'select',
				'choices' => [
					'mensile' => 'Mensile',
					'trimestrale' => 'Trimestrale',
					'semestrale' => 'Semestrale',
					'annuale' => 'Annuale',
				],
				'ui' => 1,
				'instructions' => 'Se vuoto, verrà usata la ricorrenza del servizio',
			],

			[
				'key' => 'field_spm_data_inizio',
				'label' => 'Data Inizio',
				'name' => 'data_inizio',
				'type' => 'date_picker',
				'required' => 1,
				'display_format' => 'd/m/Y',
			],

			[
				'key' => 'field_spm_data_scadenza',
				'label' => 'Data Scadenza',
				'name' => 'data_scadenza',
				'type' => 'date_picker',
				'display_format' => 'd/m/Y',
				'instructions' => 'Se lasciata vuota, verrà calcolata automaticamente',
			],

			[
				'key' => 'field_spm_reminder_off',
				'label' => 'Disattiva Reminder Email',
				'name' => 'reminder_disattivato',
				'type' => 'true_false',
				'ui' => 1,
			],

			[
				'key' => 'field_spm_note',
				'label' => 'Note Interne',
				'name' => 'note_interne',
				'type' => 'textarea',
				'rows' => 3,
			],
		],
		'location' => [[
			['param' => 'post_type', 'operator' => '==', 'value' => 'servizi_cliente']
		]],
		'position' => 'normal',
		'style' => 'default',
	]);
}
