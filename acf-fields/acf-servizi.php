<?php
defined('ABSPATH') || exit;

if (function_exists('acf_add_local_field_group')) {
	acf_add_local_field_group([
		'key' => 'group_spm_servizi',
		'title' => 'Dettagli Servizio',
		'fields' => [

			// Categoria di classificazione (uso interno)
			[
				'key' => 'field_spm_categoria_servizio',
				'label' => 'Categoria',
				'name' => 'categoria_servizio',
				'type' => 'select',
				'choices' => [
					'hosting_domini' => 'Hosting e Domini',
					'manutenzioni'    => 'Manutenzioni',
					'social'          => 'Social',
					'advertising'     => 'Advertising',
					'altro'           => 'Altro',
				],
				'ui' => 1,
			],

			// Prezzo base proposto dal servizio
			[
				'key' => 'field_spm_prezzo_base',
				'label' => 'Prezzo Base (€)',
				'name' => 'prezzo_base',
				'type' => 'number',
				'instructions' => 'Prezzo standard da proporre (può essere sovrascritto nel contratto)',
				'required' => 1,
				'prepend' => '€',
				'min' => 0,
				'step' => 0.01,
			],

			// Frequenza predefinita (usata per precompilazioni e reminder)
			[
				'key' => 'field_spm_ricorrenza',
				'label' => 'Frequenza Ricorrenza',
				'name' => 'frequenza_ricorrenza',
				'type' => 'select',
				'instructions' => 'Periodicità di rinnovo e reminder suggerita',
				'required' => 1,
				'choices' => [
					'mensile'     => 'Mensile',
					'trimestrale' => 'Trimestrale',
					'semestrale'  => 'Semestrale',
					'annuale'     => 'Annuale',
				],
				'default_value' => 'annuale',
				'ui' => 1,
			],

			// Offset per reminder automatico
			[
				'key' => 'field_spm_reminder_days',
				'label' => 'Giorni Pre-Reminder',
				'name' => 'giorni_pre_reminder',
				'type' => 'number',
				'instructions' => 'Quanti giorni prima della scadenza va avvisato il cliente',
				'default_value' => 15,
				'min' => 1,
				'step' => 1,
			],

			// Flag attivo/disattivo per visibilità nei contratti
			[
				'key' => 'field_spm_attivo',
				'label' => 'Attivo',
				'name' => 'attivo',
				'type' => 'true_false',
				'instructions' => 'Se disattivato, non sarà più selezionabile nei nuovi contratti',
				'ui' => 1,
				'default_value' => 1,
			],

			// Descrizione interna per gestione amministrativa
			[
				'key' => 'field_spm_descrizione_admin',
				'label' => 'Descrizione Interna',
				'name' => 'descrizione_admin',
				'type' => 'textarea',
				'instructions' => 'Testo descrittivo interno (non visibile al cliente)',
				'rows' => 4,
			],
		],
		'location' => [[
			['param' => 'post_type', 'operator' => '==', 'value' => 'servizi']
		]],
		'position' => 'normal',
		'style' => 'default',
	]);
}
