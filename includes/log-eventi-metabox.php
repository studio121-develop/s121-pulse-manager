<?php
// Metabox per visualizzare il log_eventi nel dettaglio contratto
add_action('add_meta_boxes', function(){
  add_meta_box(
	'log_eventi_box',
	'📘 Log Eventi Contratto',
	'render_log_eventi_box',
	'servizi_cliente',
	'normal',
	'default'
  );
});

function render_log_eventi_box($post){
  $log = get_field('log_eventi', $post->ID);
  if (!$log || !is_array($log)) {
	echo '<p>Nessun evento registrato.</p>';
	return;
  }

  // Ordina per data decrescente
  usort($log, function($a, $b){
	return strtotime($b['data']) - strtotime($a['data']);
  });

  echo '<style>
	.log-evento { margin-bottom: 1em; padding: 0.5em; border-left: 4px solid #ccc; background: #fafafa; }
	.log-evento.green { border-color: #2ecc71; }
	.log-evento.orange { border-color: #f39c12; }
	.log-evento.red { border-color: #e74c3c; }
	.log-evento.blue { border-color: #3498db; }
	.log-evento.gray { border-color: #95a5a6; }
	.log-evento small { color: #555; font-style: italic; }
  </style>';

  foreach ($log as $evento) {
	$tipo = esc_html($evento['tipo'] ?? '—');
	$data = esc_html($evento['data'] ?? '—');
	$descrizione = esc_html($evento['descrizione'] ?? '');

	$colorClass = match($tipo) {
	  'rinnovo' => 'green',
	  'sospensione' => 'orange',
	  'dismissione' => 'red',
	  'attivazione' => 'blue',
	  default => 'gray'
	};

	echo "<div class='log-evento {$colorClass}'>";
	echo "<strong>[$tipo]</strong> <small>$data</small><br>";
	echo "<p>$descrizione</p>";
	echo "</div>";
  }
}
