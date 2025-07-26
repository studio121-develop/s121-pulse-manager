<?php
// Metabox laterale con pulsanti azione contratto
add_action('add_meta_boxes', function(){
  add_meta_box(
	'azioni_contratto_box',
	'🎛️ Azioni Contratto',
	'render_azioni_contratto_box',
	'servizi_cliente',
	'side',
	'high'
  );
});

// Render dei pulsanti azione
function render_azioni_contratto_box($post) {
  $azioni = [
	'rinnova'    => 'Rinnova',
	'sospendi'   => 'Sospendi',
	'riattiva'   => 'Riattiva',
	'dismetti'   => 'Dismetti'
  ];

  foreach ($azioni as $action => $label) {
	$url = admin_url('admin-post.php?action=spm_' . $action . '_contratto&post_id=' . $post->ID);
	$nonce = wp_create_nonce('spm_contratto_' . $action);

	echo "<p><a href='$url&_wpnonce=$nonce' class='button button-secondary' style='width:100%; margin-bottom:5px;'>$label</a></p>";
  }
}

// Azioni contrattuali
foreach (['rinnova', 'sospendi', 'riattiva', 'dismetti'] as $azione) {
  add_action("admin_post_spm_{$azione}_contratto", function() use ($azione) {
	if (!current_user_can('edit_posts')) wp_die('Non autorizzato');

	$post_id = intval($_GET['post_id'] ?? 0);
	$nonce = $_GET['_wpnonce'] ?? '';
	if (!wp_verify_nonce($nonce, 'spm_contratto_' . $azione)) wp_die('Nonce non valido');

	switch ($azione) {

	  case 'rinnova':
		$frequenza = get_field('frequenza_corrente', $post_id);
		$ultima = get_field('data_ultimo_rinnovo', $post_id) ?: get_field('data_inizio', $post_id);
		$oggi = current_time('Y-m-d');
	  
		// Evita doppio rinnovo nello stesso giorno
		if ($ultima === $oggi) {
		  wp_die('Questo contratto è già stato rinnovato oggi.');
		}
	  
		// Parsing robusto
		$data = false;
		if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $ultima)) {
		  $data = DateTime::createFromFormat('d/m/Y', $ultima);
		}
		if (!$data && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ultima)) {
		  $data = DateTime::createFromFormat('Y-m-d', $ultima);
		}
		if (!$data || !$data instanceof DateTime) {
		  wp_die('Formato data non valido per il rinnovo: ' . esc_html($ultima));
		}
	  
		// Calcolo nuova scadenza
		switch ($frequenza) {
		  case 'mensile':     $data->modify('+1 month'); break;
		  case 'trimestrale': $data->modify('+3 months'); break;
		  case 'semestrale':  $data->modify('+6 months'); break;
		  case 'annuale':     $data->modify('+1 year'); break;
		}
	  
		// Verifica che la nuova scadenza non superi di 2 periodi futuri
		$scadenza_attuale = get_field('data_scadenza', $post_id);
		if ($scadenza_attuale && strtotime($data->format('Y-m-d')) - strtotime($scadenza_attuale) > 2 * 365 * 86400) {
		  wp_die('La nuova scadenza estenderebbe il contratto troppo in avanti. Verifica la frequenza.');
		}
	  
		// Salvataggio
		update_field('data_ultimo_rinnovo', $oggi, $post_id);
		update_field('data_scadenza', $data->format('Y-m-d'), $post_id);
		update_field('stato_contratto', 'attivo', $post_id);
		spm_log_evento($post_id, 'rinnovo', 'Rinnovo manuale effettuato.');
		break;
	  case 'sospendi':
		update_field('stato_contratto', 'sospeso', $post_id);
		spm_log_evento($post_id, 'sospensione', 'Contratto sospeso manualmente.');
		break;

	  case 'riattiva':
		update_field('stato_contratto', 'attivo', $post_id);
		spm_log_evento($post_id, 'attivazione', 'Contratto riattivato manualmente.');
		break;

	  case 'dismetti':
		update_field('stato_contratto', 'dismesso', $post_id);
		spm_log_evento($post_id, 'dismissione', 'Contratto dismesso manualmente.');
		break;
	}

	wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
	exit;
  });
}
