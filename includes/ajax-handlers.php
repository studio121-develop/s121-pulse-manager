<?php
add_action('wp_ajax_spm_get_servizio_defaults', function () {
  if (empty($_POST['servizio_id'])) {
    wp_send_json_error('Missing servizio_id');
  }

  $servizio_id = absint($_POST['servizio_id']);

$data = [
    'prezzo_base' => get_field('prezzo_base', $servizio_id),
    'ricorrenza' => get_field('frequenza_ricorrenza', $servizio_id),
    'tipo_rinnovo' => get_field('tipo_rinnovo', $servizio_id),
    'giorni_pre_reminder' => get_field('giorni_pre_reminder', $servizio_id),
  ];

  wp_send_json_success($data);
});
