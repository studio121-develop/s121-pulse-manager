<?php
defined('ABSPATH') || exit;

if (function_exists('acf_add_local_field_group')) {
	acf_add_local_field_group([
		'key' => 'group_servizio_cliente',
		'title' => 'Assegnazione Servizio al Cliente',
		'fields' => [
			[
				'key' => 'field_cliente_associato',
				'label' => 'Cliente',
				'name' => 'cliente_associato',
				'type' => 'post_object',
				'post_type' => ['clienti'],
				'return_format' => 'id',
				'ui' => 1,
			],
			[
				'key' => 'field_servizio_associato',
				'label' => 'Servizio',
				'name' => 'servizio_associato',
				'type' => 'post_object',
				'post_type' => ['servizio'],
				'return_format' => 'id',
				'ui' => 1,
			],
			[
				'key' => 'field_prezzo_personalizzato',
				'label' => 'Prezzo Personalizzato (€)',
				'name' => 'prezzo_personalizzato',
				'type' => 'number',
				'prepend' => '€',
				'min' => 0,
				'step' => 0.01,
			],
			[
				'key' => 'field_frequenza',
				'label' => 'Frequenza',
				'name' => 'frequenza',
				'type' => 'select',
				'choices' => [
					'1m' => 'Mensile',
					'3m' => 'Trimestrale',
					'6m' => 'Semestrale',
					'12m' => 'Annuale',
				],
				'ui' => 1,
			],
			[
				'key' => 'field_data_attivazione',
				'label' => 'Data Attivazione',
				'name' => 'data_attivazione',
				'type' => 'date_picker',
				'display_format' => 'd/m/Y',
				'return_format' => 'Y-m-d',
			],
			[
				'key' => 'field_data_prossimo_rinnovo',
				'label' => 'Data Prossimo Rinnovo',
				'name' => 'data_prossimo_rinnovo',
				'type' => 'date_picker',
				'display_format' => 'd/m/Y',
				'return_format' => 'Y-m-d',
			],
			[
				'key' => 'field_stato_servizio',
				'label' => 'Stato',
				'name' => 'stato_servizio',
				'type' => 'select',
				'choices' => [
					'attivo' => 'Attivo',
					'sospeso' => 'Sospeso',
					'scaduto' => 'Scaduto',
					'cancellato' => 'Cancellato',
				],
				'ui' => 1,
			],
			[
				'key' => 'field_invia_reminder',
				'label' => 'Attiva Reminder Automatici',
				'name' => 'invia_reminder',
				'type' => 'true_false',
				'ui' => 1,
				'default_value' => 1,
			],
			[
				'key' => 'field_reminder_personalizzati',
				'label' => 'Reminder Personalizzati',
				'name' => 'reminder_personalizzati',
				'type' => 'repeater',
				'button_label' => 'Aggiungi Reminder',
				'layout' => 'table',
				'sub_fields' => [
					[
						'key' => 'field_giorni_anticipo',
						'label' => 'Giorni Prima della Scadenza',
						'name' => 'giorni_anticipo',
						'type' => 'number',
						'min' => 1,
						'instructions' => 'Es. 30 = invio 30 giorni prima della data di rinnovo',
					],
					[
						'key' => 'field_testo_reminder',
						'label' => 'Messaggio Email (opzionale)',
						'name' => 'testo_reminder',
						'type' => 'textarea',
						'rows' => 3,
					],
					[
						'key' => 'field_inviato',
						'label' => 'Inviato',
						'name' => 'inviato',
						'type' => 'true_false',
						'ui' => 1,
						'default_value' => 0,
					],
				],
			],
			[
				'key' => 'field_note_cliente_servizio',
				'label' => 'Note per questo Cliente',
				'name' => 'note_cliente_servizio',
				'type' => 'textarea',
				'rows' => 3,
			],
		],
		'location' => [[
			['param' => 'post_type', 'operator' => '==', 'value' => 'servizio_cliente']
		]]
	]);
}
