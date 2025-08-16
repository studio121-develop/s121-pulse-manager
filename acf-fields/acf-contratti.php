<?php
defined('ABSPATH') || exit;

/**
 * Campi ACF per CPT Contratti - Versione Migliorata
 * 
 * Migliorie implementate:
 * 1. Data scadenza readonly (calcolata automaticamente)
 * 2. Storico completo (non solo rinnovi)
 * 3. Precompilazione da servizio selezionato
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
				'instructions' => '',
			],
			
			// Prezzo contratto (override del servizio)
			[
				'key' => 'field_spm_contratto_prezzo',
				'label' => 'Prezzo Contratto',
				'name' => 'prezzo_contratto',
				'type' => 'number',
				'instructions' => '',
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
				'instructions' => '',
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
			
			// Data attivazione (inizio contratto)
			[
				'key' => 'field_spm_contratto_data_attivazione',
				'label' => 'Data Attivazione',
				'name' => 'data_attivazione',
				'type' => 'date_picker',
				'instructions' => 'Data di inizio contratto',
				'display_format' => 'd/m/Y',
				'return_format' => 'Y-m-d',
				'first_day' => 1,
				'required' => 1,
				'wrapper' => ['width' => '50'],
			],
			
			// Data prossima scadenza (READONLY - calcolata automaticamente)
			[
				'key' => 'field_spm_contratto_data_scadenza',
				'label' => 'Prossima Scadenza',
				'name' => 'data_prossima_scadenza',
				'type' => 'date_picker',
				'instructions' => 'Data fine Contratto',
				'display_format' => 'd/m/Y',
				'return_format' => 'Y-m-d',
				'first_day' => 1,
				'readonly' => 1,
				'disabled' => 1,
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
				'instructions' => '',
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
				'instructions' => '',
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
				'instructions' => 'Note visibili solo internamente)',
				'rows' => 3,
			],
			
			// === SEZIONE STORICO ===
			[
				'key' => 'field_spm_contratto_tab_storico',
				'label' => 'Storico',
				'type' => 'tab',
				'placement' => 'left',
			],
			
			// Storico completo (rinominato da "storico_rinnovi")
			[
				'key' => 'field_spm_contratto_storico',
				'label' => 'Storico Contratto',
				'name' => 'storico_contratto',
				'type' => 'repeater',
				'instructions' => 'Log completo di tutte le operazioni sul contratto (creazione, rinnovi, sospensioni, ecc.)',
				'layout' => 'table',
				'button_label' => 'Aggiungi Voce',
				'readonly' => 1, // Solo lettura - gestito dal sistema
				'sub_fields' => [
					[
						'key' => 'field_spm_storico_data',
						'label' => 'Data',
						'name' => 'data_operazione',
						'type' => 'date_picker',
						'display_format' => 'd/m/Y',
						'return_format' => 'Y-m-d',
						'wrapper' => ['width' => '15'],
						'readonly' => 1,
					],
					[
						'key' => 'field_spm_storico_ora',
						'label' => 'Ora',
						'name' => 'ora_operazione',
						'type' => 'time_picker',
						'display_format' => 'H:i',
						'return_format' => 'H:i',
						'wrapper' => ['width' => '10'],
						'readonly' => 1,
					],
					[
						'key' => 'field_spm_storico_tipo',
						'label' => 'Operazione',
						'name' => 'tipo_operazione',
						'type' => 'select',
						'choices' => [
							'creazione' => '🆕 Creazione',
							'attivazione' => '✅ Attivazione',
							'rinnovo_automatico' => '🔄 Rinnovo Automatico',
							'rinnovo_manuale' => '🔄 Rinnovo Manuale',
							'sospensione' => '⏸️ Sospensione',
							'riattivazione' => '▶️ Riattivazione',
							'cessazione' => '⛔ Cessazione',
							'modifica' => '✏️ Modifica',
							'scadenza' => '⏰ Scadenza',
						],
						'wrapper' => ['width' => '20'],
						'readonly' => 1,
					],
					[
						'key' => 'field_spm_storico_importo',
						'label' => 'Importo',
						'name' => 'importo',
						'type' => 'number',
						'prepend' => '€',
						'wrapper' => ['width' => '15'],
						'readonly' => 1,
					],
					[
						'key' => 'field_spm_storico_utente',
						'label' => 'Utente',
						'name' => 'utente',
						'type' => 'text',
						'wrapper' => ['width' => '15'],
						'readonly' => 1,
					],
					[
						'key' => 'field_spm_storico_note',
						'label' => 'Note',
						'name' => 'note',
						'type' => 'text',
						'wrapper' => ['width' => '25'],
						'readonly' => 1,
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
	

	
	/**
	 * Contratti: blocca UI del repeater "storico_contratto"
	 * - Rimuove Add/Remove/Duplica/Drag
	 * - Disabilita l'interazione con i SELECT (ACF Select2 e native) dentro il repeater
	 * - NON usa "disabled": i valori restano nel POST
	 */
	
	add_action('acf/input/admin_footer', function () {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'contratti') {
			return;
		}
		?>
		<script>
		(function($){
			var $rep = $('.acf-field-repeater[data-key="field_spm_contratto_storico"]');
			if(!$rep.length) return;
	
			// 1) Via azioni e maniglie
			$rep.find('.acf-actions').remove(); // Add row
			$rep.find('.acf-row-handle, .acf-icon').remove(); // duplica/elimina/drag
	
			// 2) Blocca SELECT (sia Select2 che native) DENTRO il repeater
			//    -> niente apertura menu, niente cambi valore via mouse/tastiera
			var lockSelect = function($sel){
				// Blocca eventi comuni
				$sel.on('mousedown select keydown wheel touchstart', function(e){
					e.preventDefault(); e.stopImmediatePropagation(); return false;
				});
	
				// Se è Select2 (ACF post_object/ui=1 o select con ui=1)
				$sel.on('select2:opening select2:unselecting select2:select', function(e){
					e.preventDefault(); return false;
				});
	
				// Effetto visivo + blocco click sulla “pelle” Select2
				var $s2 = $sel.next('.select2');
				if ($s2.length){
					$s2.addClass('spm-lock').css('pointer-events','none'); // blocca interazione
				} else {
					// native <select>
					$sel.addClass('spm-lock').css('pointer-events','none');
				}
			};
	
			// Seleziona tutti i <select> nel repeater (ACF li lascia nel DOM anche con Select2)
			$rep.find('select').each(function(){ lockSelect($(this)); });
	
			// 3) Optional: impedisci accidentalmente tastiera su tutto il repeater
			$rep.on('keydown', function(e){
				// blocca tasti che cambiano focus/valore/struttura
				if (['ArrowUp','ArrowDown','Enter',' '].includes(e.key)) {
					e.preventDefault(); e.stopPropagation();
				}
			});
		})(jQuery);
		</script>
		<style>
		  /* Nascondi qualsiasi azione residua del repeater */
		  .acf-field-repeater[data-key="field_spm_contratto_storico"] .acf-actions { display:none !important; }
		  .acf-field-repeater[data-key="field_spm_contratto_storico"] .acf-row-handle { display:none !important; }
	
		  /* Aspetto "bloccato" per Select2 */
		  .select2.spm-lock .select2-selection { 
			background: #f6f7f7 !important; 
			cursor: not-allowed !important; 
			opacity: .9;
		  }
		  /* Aspetto "bloccato" per select native */
		  select.spm-lock {
			background: #f6f7f7 !important;
			cursor: not-allowed !important;
			opacity: .9;
		  }
		  /* Nasconde la colonna "Utente" nel repeater storico */
			.acf-field-repeater[data-key="field_spm_contratto_storico"] 
			  table.acf-table th[data-name="utente"],
			.acf-field-repeater[data-key="field_spm_contratto_storico"] 
			  table.acf-table td[data-name="utente"] {
				display: none !important;
			}
		</style>
		<?php
	});

	
}