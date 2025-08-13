<?php
defined('ABSPATH') || exit;

/**
 * Campi ACF per CPT Contratti - Versione Semplificata
 * Elimina ridondanze e semplifica la gestione
 */

if (function_exists('acf_add_local_field_group')) {
	acf_add_local_field_group([
		'key' => 'group_spm_contratti',
		'title' => '📄 Dettagli Contratto',
		'fields' => [
			
			// === SEZIONE PRINCIPALE ===
			[
				'key' => 'field_spm_contratto_tab_main',
				'label' => 'Contratto',
				'type' => 'tab',
				'placement' => 'left',
			],
			
			// Cliente
			[
				'key' => 'field_spm_contratto_cliente',
				'label' => 'Cliente',
				'name' => 'cliente',
				'type' => 'post_object',
				'post_type' => ['clienti'],
				'required' => 1,
				'ui' => 1,
				'return_format' => 'id',
				'wrapper' => ['width' => '50'],
			],
			
			// Servizio
			[
				'key' => 'field_spm_contratto_servizio',
				'label' => 'Servizio',
				'name' => 'servizio',
				'type' => 'post_object',
				'post_type' => ['servizi'],
				'required' => 1,
				'ui' => 1,
				'return_format' => 'id',
				'wrapper' => ['width' => '50'],
			],
			
			// Prezzo contratto (override del servizio)
			[
				'key' => 'field_spm_contratto_prezzo',
				'label' => 'Prezzo Contratto',
				'name' => 'prezzo_contratto',
				'type' => 'number',
				'instructions' => 'Lascia vuoto per usare il prezzo base del servizio',
				'prepend' => '€',
				'step' => 0.01,
				'min' => 0,
				'wrapper' => ['width' => '33'],
			],
			
			// Frequenza rinnovo
			[
				'key' => 'field_spm_contratto_frequenza',
				'label' => 'Frequenza Rinnovo',
				'name' => 'frequenza',
				'type' => 'select',
				'choices' => [
					'mensile' => 'Mensile',
					'trimestrale' => 'Trimestrale',
					'semestrale' => 'Semestrale',
					'annuale' => 'Annuale',
				],
				'default_value' => 'annuale',
				'required' => 1,
				'ui' => 1,
				'wrapper' => ['width' => '33'],
			],
			
			// Stato contratto
			[
				'key' => 'field_spm_contratto_stato',
				'label' => 'Stato',
				'name' => 'stato',
				'type' => 'select',
				'choices' => [
					'attivo' => '🟢 Attivo',
					'sospeso' => '🟡 Sospeso',
					'scaduto' => '🔴 Scaduto',
					'cessato' => '⚫ Cessato',
				],
				'default_value' => 'attivo',
				'ui' => 1,
				'wrapper' => ['width' => '34'],
			],
			
			// === SEZIONE DATE ===
			[
				'key' => 'field_spm_contratto_tab_date',
				'label' => 'Date',
				'type' => 'tab',
				'placement' => 'left',
			],
			
			// Data attivazione (inizio contratto)
			[
				'key' => 'field_spm_contratto_data_attivazione',
				'label' => 'Data Attivazione',
				'name' => 'data_attivazione',
				'type' => 'date_picker',
				'instructions' => 'Data di inizio del contratto',
				'display_format' => 'd/m/Y',
				'return_format' => 'Y-m-d',
				'first_day' => 1,
				'required' => 1,
				'wrapper' => ['width' => '50'],
			],
			
			// Data prossima scadenza (calcolata automaticamente)
			[
				'key' => 'field_spm_contratto_data_scadenza',
				'label' => 'Prossima Scadenza',
				'name' => 'data_prossima_scadenza',
				'type' => 'date_picker',
				'instructions' => 'Calcolata automaticamente se lasciata vuota',
				'display_format' => 'd/m/Y',
				'return_format' => 'Y-m-d',
				'first_day' => 1,
				'wrapper' => ['width' => '50'],
			],
			
			// === SEZIONE OPZIONI ===
			[
				'key' => 'field_spm_contratto_tab_opzioni',
				'label' => 'Opzioni',
				'type' => 'tab',
				'placement' => 'left',
			],
			
			// Rinnovo automatico
			[
				'key' => 'field_spm_contratto_rinnovo_auto',
				'label' => 'Rinnovo Automatico',
				'name' => 'rinnovo_automatico',
				'type' => 'true_false',
				'instructions' => 'Se attivo, il contratto si rinnova automaticamente alla scadenza',
				'ui' => 1,
				'default_value' => 0,
				'wrapper' => ['width' => '50'],
			],
			
			// Giorni preavviso
			[
				'key' => 'field_spm_contratto_giorni_preavviso',
				'label' => 'Giorni Preavviso',
				'name' => 'giorni_preavviso',
				'type' => 'number',
				'instructions' => 'Giorni di anticipo per inviare reminder',
				'default_value' => 30,
				'min' => 1,
				'max' => 90,
				'step' => 1,
				'append' => 'giorni',
				'wrapper' => ['width' => '50'],
			],
			
			// Note interne
			[
				'key' => 'field_spm_contratto_note',
				'label' => 'Note Interne',
				'name' => 'note_interne',
				'type' => 'textarea',
				'instructions' => 'Note visibili solo internamente',
				'rows' => 3,
			],
			
			// === SEZIONE STORICO ===
			[
				'key' => 'field_spm_contratto_tab_storico',
				'label' => 'Storico',
				'type' => 'tab',
				'placement' => 'left',
			],
			
			// Storico rinnovi (semplificato)
			[
				'key' => 'field_spm_contratto_storico',
				'label' => 'Storico Rinnovi',
				'name' => 'storico_rinnovi',
				'type' => 'repeater',
				'instructions' => 'Registro automatico dei rinnovi effettuati',
				'layout' => 'table',
				'button_label' => 'Aggiungi Rinnovo',
				'sub_fields' => [
					[
						'key' => 'field_spm_storico_data',
						'label' => 'Data',
						'name' => 'data_rinnovo',
						'type' => 'date_picker',
						'display_format' => 'd/m/Y',
						'return_format' => 'Y-m-d',
						'wrapper' => ['width' => '25'],
					],
					[
						'key' => 'field_spm_storico_importo',
						'label' => 'Importo',
						'name' => 'importo',
						'type' => 'number',
						'prepend' => '€',
						'wrapper' => ['width' => '25'],
					],
					[
						'key' => 'field_spm_storico_tipo',
						'label' => 'Tipo',
						'name' => 'tipo',
						'type' => 'select',
						'choices' => [
							'automatico' => 'Automatico',
							'manuale' => 'Manuale',
						],
						'wrapper' => ['width' => '20'],
					],
					[
						'key' => 'field_spm_storico_note',
						'label' => 'Note',
						'name' => 'note',
						'type' => 'text',
						'wrapper' => ['width' => '30'],
					],
				],
			],
		],
		'location' => [[
			['param' => 'post_type', 'operator' => '==', 'value' => 'contratti']
		]],
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'hide_on_screen' => ['the_content', 'excerpt', 'custom_fields', 'discussion', 'comments', 'slug', 'author'],
	]);
}