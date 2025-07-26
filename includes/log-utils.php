<?php
// Funzione per aggiungere una voce al log_eventi (repeater ACF)
function spm_log_evento($post_id, $tipo, $descrizione = '') {
  if (!$post_id || !$tipo) return;

  $log = get_field('log_eventi', $post_id) ?: [];

  $log[] = [
	'tipo' => $tipo,
	'data' => current_time('Y-m-d'),
	'descrizione' => $descrizione
  ];

  update_field('log_eventi', $log, $post_id);
}
