(function($){
  if (typeof acf === 'undefined') return;

  // Cache elementi DOM
  let $servizioField, $prezzoField, $frequenzaField, $rinnovoField, $reminderField, $noteField, $dataAttivazione, $dataScadenza;
  let $previewAttivazione, $previewFrequenza, $previewScadenza;

  /**
   * Calcola e aggiorna l'anteprima della data di scadenza
   */
  function aggiornaAnteprimaScadenza() {
    if (!$dataAttivazione || !$frequenzaField) return;

    const dataAttivazione = $dataAttivazione.val();
    const frequenza = $frequenzaField.val();

    // Aggiorna anteprima nella sezione dedicata
    if ($previewAttivazione) {
      $previewAttivazione.text(dataAttivazione ? formatDateForDisplay(dataAttivazione) : '—');
    }
    
    if ($previewFrequenza) {
      $previewFrequenza.text(frequenza ? getFrequenzaLabel(frequenza) : '—');
    }

    if (!dataAttivazione || !frequenza || !$previewScadenza) {
      if ($previewScadenza) $previewScadenza.text('—');
      return;
    }

    // Calcola nuova scadenza
    const dataScadenza = calcolaDataScadenza(dataAttivazione, frequenza);
    if (dataScadenza) {
      $previewScadenza.text(formatDateForDisplay(dataScadenza));
      
      // Aggiorna anche il campo readonly
      if ($dataScadenza) {
        $dataScadenza.val(dataScadenza);
      }
    }
  }

  /**
   * Calcola la data di scadenza basata su attivazione e frequenza
   */
  function calcolaDataScadenza(dataAttivazione, frequenza) {
    if (!dataAttivazione || !frequenza) return null;

    const date = new Date(dataAttivazione);
    if (isNaN(date.getTime())) return null;

    switch (frequenza) {
      case 'mensile':
        date.setMonth(date.getMonth() + 1);
        break;
      case 'trimestrale':
        date.setMonth(date.getMonth() + 3);
        break;
      case 'semestrale':
        date.setMonth(date.getMonth() + 6);
        break;
      case 'annuale':
        date.setFullYear(date.getFullYear() + 1);
        break;
      default:
        return null;
    }

    return date.toISOString().split('T')[0];
  }

  /**
   * Formatta data per visualizzazione (dd/mm/yyyy)
   */
  function formatDateForDisplay(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return 'Data non valida';
    
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    
    return `${day}/${month}/${year}`;
  }

  /**
   * Ottieni label frequenza
   */
  function getFrequenzaLabel(frequenza) {
    const labels = {
      'mensile': 'Mensile',
      'trimestrale': 'Trimestrale', 
      'semestrale': 'Semestrale',
      'annuale': 'Annuale'
    };
    return labels[frequenza] || frequenza;
  }

  /**
   * Richiede dati del servizio e precompila i campi
   */
  function precompilaFromServizio(servizioID) {
    if (!servizioID) return;

    // Mostra indicatore caricamento
    const $indicatore = $('<span class="spinner is-active" style="float: none; margin-left: 10px;"></span>');
    if ($servizioField && $servizioField.$el) {
      $servizioField.$el.append($indicatore);
    }

    // Usa variabile globale se disponibile, altrimenti fallback
    const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
    const nonce = (window.spmAjax && window.spmAjax.nonce) || '';

    $.ajax({
      url: ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'spm_get_servizio_defaults',
        servizio_id: servizioID,
        _wpnonce: nonce
      },
      success: function(response) {
        if (response.success && response.data) {
          const data = response.data;

          // Precompila solo se i campi sono vuoti (no override di modifiche manuali)
          if ($prezzoField && !$prezzoField.val() && data.prezzo_base !== undefined) {
            $prezzoField.val(data.prezzo_base);
          }

         if ($frequenzaField && data.frequenza_ricorrenza !== undefined) {
            $frequenzaField.$input().val(data.frequenza_ricorrenza).trigger('change');
             console.log('📅 Frequenza FORZATA a:', data.frequenza_ricorrenza);
         }

          if ($reminderField && !$reminderField.val() && data.giorni_pre_reminder !== undefined) {
            $reminderField.val(data.giorni_pre_reminder);
          }

          if ($noteField && !$noteField.val() && data.descrizione_admin !== undefined) {
            $noteField.val(data.descrizione_admin);
          }

          // Imposta rinnovo automatico se definito nel servizio
          if ($rinnovoField && data.rinnovo_automatico_default !== undefined) {
            $rinnovoField.val(data.rinnovo_automatico_default ? '1' : '0');
          }

          // Ricalcola scadenza dopo precompilazione
          setTimeout(aggiornaAnteprimaScadenza, 200);

          console.log('✅ Dati servizio precompilati:', data);
        } else {
          console.warn('⚠️ Errore nel recupero dati servizio:', response);
        }
      },
      error: function(xhr, status, error) {
        console.error('❌ Errore AJAX servizio:', error);
      },
      complete: function() {
        $indicatore.remove();
      }
    });
  }

  /**
   * Rende readonly il campo data scadenza
   */
  function impostaDataScadenzaReadonly() {
    if (!$dataScadenza) return;

    const $input = $dataScadenza.$input();
    if ($input.length) {
      $input.prop('readonly', true)
            .prop('disabled', false) // Disabled impedisce invio dati
            .css({
              'background': '#f9f9f9',
              'color': '#666',
              'cursor': 'not-allowed',
              'border': '1px solid #ddd'
            })
            .attr('title', 'Campo calcolato automaticamente da data attivazione + frequenza');

      // Nascondi il date picker se presente
      const $datePicker = $dataScadenza.$el.find('.acf-date_picker');
      if ($datePicker.length) {
        $datePicker.find('.acf-icon').hide();
      }
    }
  }

  /**
   * Inizializzazione al caricamento della pagina
   */
  function inizializzaCampi() {
    // Cache campi ACF
    $servizioField = acf.getField('field_spm_contratto_servizio');
    $prezzoField = acf.getField('field_spm_contratto_prezzo');
    $frequenzaField = acf.getField('field_spm_contratto_frequenza');
    $rinnovoField = acf.getField('field_spm_contratto_rinnovo_auto');
    $reminderField = acf.getField('field_spm_contratto_giorni_preavviso');
    $noteField = acf.getField('field_spm_contratto_note');
    $dataAttivazione = acf.getField('field_spm_contratto_data_attivazione');
    $dataScadenza = acf.getField('field_spm_contratto_data_scadenza');

    // Cache elementi anteprima
    $previewAttivazione = $('#preview-attivazione');
    $previewFrequenza = $('#preview-frequenza');
    $previewScadenza = $('#preview-scadenza');

    // Imposta data scadenza come readonly
    impostaDataScadenzaReadonly();

    // Eventi per ricalcolo automatico
    if ($dataAttivazione) {
      $dataAttivazione.$input().on('change', aggiornaAnteprimaScadenza);
    }

    if ($frequenzaField) {
     $frequenzaField.$input().on('change', aggiornaAnteprimaScadenza);
    }

    // Evento cambio servizio
    if ($servizioField) {
      $servizioField.on('change', function() {
        const servizioID = $servizioField.val();
        if (servizioID) {
          precompilaFromServizio(servizioID);
        }
      });
    }

    // Calcolo iniziale
    setTimeout(function() {
      aggiornaAnteprimaScadenza();
      
      // Se è un nuovo contratto e c'è già un servizio selezionato, precompila
      const servizioID = $servizioField ? $servizioField.val() : null;
      const $postId = $('#post_ID');
      const isNewPost = !$postId.length || $postId.val() === '0';
      
      if (isNewPost && servizioID && (!$prezzoField || !$prezzoField.val() || !$frequenzaField || !$frequenzaField.val())) {
        precompilaFromServizio(servizioID);
      }
    }, 500);
  }

  // Hook principale ACF
  acf.addAction('ready', inizializzaCampi);
  acf.addAction('append', inizializzaCampi); // Per repeater fields

  // Debug info
  console.log('🔧 ACF Dynamic Values Script caricato - Versione 2.1.0');

})(jQuery);