<?php
defined('ABSPATH') || exit;

add_action('add_meta_boxes', function () {
	add_meta_box(
		'spm_rinnovo_servizio_box',
		'📅 Rinnovo Manuale Servizio',
		'spm_rinnovo_servizio_box_callback',
		'servizio_cliente',
		'side',
		'high'
	);
});

function spm_rinnovo_servizio_box_callback($post) {
	wp_nonce_field('spm_rinnovo_nonce', 'spm_rinnovo_nonce_field');
	$val = get_field('data_prossimo_rinnovo', $post->ID);
	echo '<p><label for="spm_nuovo_rinnovo">Nuova data di rinnovo:</label></p>';
	echo '<input type="date" id="spm_nuovo_rinnovo" name="spm_nuovo_rinnovo" value="' . esc_attr($val) . '" style="width:100%">';
	echo '<p><button type="submit" name="spm_rinnova_submit" class="button button-primary" style="width:100%;">🔁 Rinnova Ora</button></p>';
}

add_action('save_post_servizio_cliente', function ($post_id) {
	if (!isset($_POST['spm_rinnovo_nonce_field']) || !wp_verify_nonce($_POST['spm_rinnovo_nonce_field'], 'spm_rinnovo_nonce')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!current_user_can('edit_post', $post_id)) return;

	if (isset($_POST['spm_rinnova_submit']) && isset($_POST['spm_nuovo_rinnovo']) && $_POST['spm_nuovo_rinnovo']) {
		$data = sanitize_text_field($_POST['spm_nuovo_rinnovo']);
		update_field('data_prossimo_rinnovo', $data, $post_id);
	}
});
