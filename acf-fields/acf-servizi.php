<?php
defined('ABSPATH') || exit;

/**
 * ACF Servizi - Versione Migliorata
 * Aggiunge campi per precompilazione automatica nei contratti
 */

if (function_exists('acf_add_local_field_group')) {
	acf_add_local_field_group([
		'key' => 'group_spm_servizi',
		'title' => 'Dettagli Servizio',
		'fields' => [

			// Categoria di classificazione (uso interno)
			[
				'key' => 'field_smp_categoria_servizio',
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
				'wrapper' => ['width' => '50'],
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
				'wrapper' => ['width' => '50'],
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
				'wrapper' => ['width' => '50'],
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
				'wrapper' => ['width' => '50'],
			],

			// Offset per reminder automatico
			[
				'key' => 'field_spm_reminder_days',
				'label' => 'Giorni Pre-Reminder',
				'name' => 'giorni_pre_reminder',
				'type' => 'number',
				'instructions' => 'Quanti giorni prima della scadenza va avvisato il cliente',
				'default_value' => 30,
				'min' => 1,
				'step' => 1,
				'append' => 'giorni',
				'wrapper' => ['width' => '50'],
			],

			// Tipo rinnovo predefinito
			[
				'key' => 'field_spm_tipo_rinnovo_default',
				'label' => 'Rinnovo Automatico Default',
				'name' => 'rinnovo_automatico_default',
				'type' => 'true_false',
				'instructions' => 'Valore predefinito per il rinnovo automatico nei nuovi contratti',
				'ui' => 1,
				'default_value' => 0,
				'wrapper' => ['width' => '50'],
			],

			// Descrizione interna per gestione amministrativa
			[
				'key' => 'field_spm_descrizione_admin',
				'label' => 'Note Interne Default',
				'name' => 'descrizione_admin',
				'type' => 'textarea',
				'instructions' => 'Note che verranno precompilate nei contratti (visibili solo internamente)',
				'rows' => 4,
			],

			// Tab informazioni aggiuntive
			[
				'key' => 'field_spm_servizio_tab_avanzate',
				'label' => 'Opzioni Avanzate',
				'type' => 'tab',
				'placement' => 'top',
			],

			// Template email reminder personalizzato
			[
				'key' => 'field_spm_template_email',
				'label' => 'Template Email Reminder',
				'name' => 'template_email_reminder',
				'type' => 'textarea',
				'instructions' => 'Template personalizzato per email di reminder. Usa {CLIENTE}, {SERVIZIO}, {GIORNI}, {SCADENZA}',
				'rows' => 6,
				'placeholder' => 'Gentile {CLIENTE},

il tuo servizio "{SERVIZIO}" scadrà tra {GIORNI} giorni ({SCADENZA}).

A breve riceverai la fattura di rinnovo.

Cordiali saluti,
Studio 121',
			],

			// Configurazione fatturazione
			[
				'key' => 'field_spm_codice_fic',
				'label' => 'Codice Prodotto FIC',
				'name' => 'codice_fatture_in_cloud',
				'type' => 'text',
				'instructions' => 'Codice prodotto in Fatture in Cloud per fatturazione automatica',
			],

			// Tab statistiche (sola lettura)
			[
				'key' => 'field_spm_servizio_tab_stats',
				'label' => 'Statistiche',
				'type' => 'tab',
				'placement' => 'top',
			],

			// Contatori automatici
			[
				'key' => 'field_spm_contratti_attivi',
				'label' => 'Contratti Attivi',
				'name' => 'count_contratti_attivi',
				'type' => 'number',
				'readonly' => 1,
				'instructions' => 'Aggiornato automaticamente - Numero di contratti attivi con questo servizio',
				'wrapper' => ['width' => '33'],
			],

			[
				'key' => 'field_spm_ricavo_mensile',
				'label' => 'Ricavo Mensile',
				'name' => 'ricavo_mensile_totale',
				'type' => 'number',
				'readonly' => 1,
				'instructions' => 'Aggiornato automaticamente - Ricavo mensile ricorrente da questo servizio',
				'prepend' => '€',
				'wrapper' => ['width' => '33'],
			],

			[
				'key' => 'field_spm_ultimo_aggiornamento',
				'label' => 'Ultimo Aggiornamento',
				'name' => 'ultimo_aggiornamento_stats',
				'type' => 'date_time_picker',
				'readonly' => 1,
				'instructions' => 'Ultima volta che le statistiche sono state aggiornate',
				'display_format' => 'd/m/Y H:i',
				'wrapper' => ['width' => '34'],
			],

		],
		'location' => [[
			['param' => 'post_type', 'operator' => '==', 'value' => 'servizi']
		]],
		'position' => 'normal',
		'style' => 'default',
	]);

	// Hook per aggiornare automaticamente le statistiche quando un servizio viene salvato
	add_action('acf/save_post', 'spm_update_servizio_stats', 25);
}

/**
 * Aggiorna statistiche del servizio
 */
function spm_update_servizio_stats($post_id) {
	if (get_post_type($post_id) !== 'servizi') {
		return;
	}

	// Conta contratti attivi con questo servizio
	$contratti_attivi = new WP_Query([
		'post_type' => 'contratti',
		'posts_per_page' => -1,
		'meta_query' => [
			'relation' => 'AND',
			[
				'key' => 'servizio',
				'value' => $post_id
			],
			[
				'key' => 'stato',
				'value' => 'attivo'
			]
		],
		'fields' => 'ids'
	]);

	// Calcola ricavo mensile
	$ricavo_mensile = 0;
	if ($contratti_attivi->have_posts()) {
		foreach ($contratti_attivi->posts as $contratto_id) {
			$prezzo = get_field('prezzo_contratto', $contratto_id);
			if (!$prezzo) {
				$prezzo = get_field('prezzo_base', $post_id);
			}

			$frequenza = get_field('frequenza', $contratto_id);

			// Converti a base mensile
			switch ($frequenza) {
				case 'mensile':
					$ricavo_mensile += $prezzo;
					break;
				case 'trimestrale':
					$ricavo_mensile += ($prezzo / 3);
					break;
				case 'semestrale':
					$ricavo_mensile += ($prezzo / 6);
					break;
				case 'annuale':
					$ricavo_mensile += ($prezzo / 12);
					break;
			}
		}
	}

	// Aggiorna campi statistiche
	update_field('count_contratti_attivi', $contratti_attivi->found_posts, $post_id);
	update_field('ricavo_mensile_totale', round($ricavo_mensile, 2), $post_id);
	update_field('ultimo_aggiornamento_stats', current_time('Y-m-d H:i:s'), $post_id);
}