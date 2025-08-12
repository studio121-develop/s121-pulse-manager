<?php
defined('ABSPATH') || exit;

if (function_exists('acf_add_local_field_group')) {
	acf_add_local_field_group([
		'key' => 'group_spm_servizi_clienti',
		'title' => 'Gestione Contratto Cliente-Servizio',
		'fields' => [

			// Cliente collegato
			[
				'key' => 'field_spm_cliente',
				'label' => 'Cliente',
				'name' => 'cliente',
				'type' => 'post_object',
				'post_type' => ['clienti'],
				'required' => 1,
				'ui' => 1,
			],

			// Servizio associato
			[
				'key' => 'field_spm_servizio',
				'label' => 'Servizio',
				'name' => 'servizio',
				'type' => 'post_object',
				'post_type' => ['servizi'],
				'required' => 1,
				'ui' => 1,
			],

			// Prezzo personalizzabile per cliente (override del servizio)
			[
				'key' => 'field_spm_prezzo_personalizzato',
				'label' => 'Prezzo Personalizzato (€)',
				'name' => 'prezzo_personalizzato',
				'type' => 'number',
				'prepend' => '€',
				'step' => 0.01,
				'min' => 0,
				'instructions' => 'Se lasciato vuoto, verrà usato il prezzo base del servizio selezionato',
			],

			// Frequenza attiva per rinnovi
			[
				'key' => 'field_spm_frequenza_corrente',
				'label' => 'Frequenza Attiva',
				'name' => 'frequenza_corrente',
				'type' => 'select',
				'choices' => [
					'mensile' => 'Mensile',
					'trimestrale' => 'Trimestrale',
					'semestrale' => 'Semestrale',
					'annuale' => 'Annuale',
				],
				'required' => 1,
				'ui' => 1,
			],

			// Data di inizio contratto (fissa, retroattiva ammessa)
			[
				'key' => 'field_spm_data_inizio',
				'label' => 'Data Inizio',
				'name' => 'data_inizio',
				'type' => 'date_picker',
				'display_format' => 'd/m/Y',
				'return_format' => 'Y-m-d',
				'required' => 1,
			],

			// Data scadenza calcolata o manuale
			[
				'key' => 'field_spm_data_scadenza',
				'label' => 'Data Scadenza',
				'name' => 'data_scadenza',
				'type' => 'date_picker',
				'display_format' => 'd/m/Y',
				'return_format' => 'Y-m-d',
				'instructions' => 'Calcolata automaticamente se non inserita',
			],

			// Tipo di rinnovo (manuale o automatico)
			[
				'key' => 'field_spm_tipo_rinnovo',
				'label' => 'Tipo Rinnovo',
				'name' => 'tipo_rinnovo',
				'type' => 'select',
				'choices' => [
					'manuale' => 'Manuale',
					'auto_ricorrente' => 'Automatico (ricorrenza attiva)',
				],
				'default_value' => 'manuale',
				'required' => 1,
				'ui' => 1,
			],

			// Stato corrente del contratto
			[
				'key' => 'field_spm_stato_contratto',
				'label' => 'Stato Contratto',
				'name' => 'stato_contratto',
				'type' => 'select',
				'choices' => [
					'attivo' => 'Attivo',
					'scaduto' => 'Scaduto',
					'sospeso' => 'Sospeso',
					'dismesso' => 'Dismesso',
				],
				'default_value' => 'attivo',
				'ui' => 1,
			],

			// Log eventi (storico rinnovi, sospensioni, ecc.)
			[
				'key' => 'field_spm_log_eventi',
				'label' => 'Storico Eventi',
				'name' => 'log_eventi',
				'type' => 'repeater',
				'collapsed' => 'field_spm_evento_tipo',
				'layout' => 'table',
				'sub_fields' => [
					[
						'key' => 'field_spm_evento_data',
						'label' => 'Data',
						'name' => 'data',
						'type' => 'date_picker',
						'display_format' => 'd/m/Y',
						'return_format' => 'Y-m-d',
					],
					[
						'key' => 'field_spm_evento_tipo',
						'label' => 'Tipo Evento',
						'name' => 'tipo',
						'type' => 'text',
					],
					[
						'key' => 'field_spm_evento_descrizione',
						'label' => 'Descrizione',
						'name' => 'descrizione',
						'type' => 'textarea',
						'rows' => 2,
					],
				],
			],

			// Data dell’ultimo rinnovo (gestita dal sistema, non editabile)
			[
				'key' => 'field_spm_data_ultimo_rinnovo',
				'label' => 'Data Ultimo Rinnovo',
				'name' => 'data_ultimo_rinnovo',
				'type' => 'date_picker',
				'display_format' => 'd/m/Y',
				'return_format' => 'Y-m-d',
				'instructions' => 'Aggiornata automaticamente al rinnovo. Campo di sola lettura.',
			],
		],
		'location' => [[
			[
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'servizi_cliente'
			]
		]],
		'position' => 'normal',
		'style' => 'default',
	]);
}
