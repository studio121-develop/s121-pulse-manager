(function($){
  if (typeof acf === 'undefined') return;

  // Calcolo della data scadenza da mostrare in anteprima
  function calcolaDataScadenzaPreview() {
    const freqField = acf.getField('field_spm_frequenza_corrente');
    const startDateField = acf.getField('field_spm_data_inizio');
    const previewField = document.getElementById('data-scadenza-preview');

    if (!freqField || !startDateField || !previewField) return;

    const frequenza = freqField.val();
    const dataInizio = startDateField.val();

    if (!frequenza || !dataInizio) {
      previewField.textContent = '—';
      return;
    }

    const date = new Date(dataInizio);
    if (isNaN(date.getTime())) {
      previewField.textContent = 'Formato data non valido';
      return;
    }

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
    }

    previewField.textContent = date.toISOString().split('T')[0];
  }

  // Hook su ready
  acf.addAction('ready', function(){
    const servizioField = acf.getField('field_spm_servizio');
    if (!servizioField) return;

    // Aggiungi anteprima accanto alla data_scadenza
    const dataScadenzaField = acf.getField('field_spm_data_scadenza');
    if (dataScadenzaField) {
      const wrapper = dataScadenzaField.$el;
      wrapper.append('<p style="margin-top:5px;"><strong>Anteprima scadenza:</strong> <span id="data-scadenza-preview">—</span></p>');
    }

    // Campo readonly: data_ultimo_rinnovo
    const readonlyField = $('[data-key="field_spm_data_ultimo_rinnovo"] input');
    if (readonlyField.length) {
      readonlyField.attr('readonly', true).css({
        'background': '#f5f5f5',
        'color': '#555',
        'cursor': 'not-allowed',
        'pointer-events': 'none'
      });
    }

    // Ricalcola anche su load
    setTimeout(calcolaDataScadenzaPreview, 300);

    // Bind calcolo dinamico su cambio di frequenza o data_inizio
    const freqField = acf.getField('field_spm_frequenza_corrente');
    const startDateField = acf.getField('field_spm_data_inizio');

    if (freqField) freqField.$input().on('change', calcolaDataScadenzaPreview);
    if (startDateField) startDateField.$input().on('change', calcolaDataScadenzaPreview);

    // Al cambio del servizio: richiesta AJAX
    servizioField.on('change', function() {
      const servizioID = servizioField.val();
      if (!servizioID) return;

      $.ajax({
        url: ajaxurl,
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'spm_get_servizio_defaults',
          servizio_id: servizioID
        },
        success: function(response) {
          if (!response.success || !response.data) return;
          const data = response.data;

          const prezzoField = acf.getField('field_spm_prezzo_personalizzato');
          if (prezzoField && !prezzoField.val() && data.prezzo_base !== undefined) {
            prezzoField.val(data.prezzo_base);
          }

          const freqField = acf.getField('field_spm_frequenza_corrente');
          if (freqField && !freqField.val() && data.ricorrenza !== undefined) {
            freqField.val(data.ricorrenza);
          }

          const rinnovoField = acf.getField('field_spm_tipo_rinnovo');
          if (rinnovoField && !rinnovoField.val() && data.tipo_rinnovo !== undefined) {
            rinnovoField.val(data.tipo_rinnovo);
          }

          const reminderField = acf.getField('field_spm_giorni_pre_reminder');
          if (reminderField && !reminderField.val() && data.giorni_pre_reminder !== undefined) {
            reminderField.val(data.giorni_pre_reminder);
          }

          // Ricalcolo anteprima scadenza dopo update
          setTimeout(calcolaDataScadenzaPreview, 200);
        }
      });
    });
  });

})(jQuery);
