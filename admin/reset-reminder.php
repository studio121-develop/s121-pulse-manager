<?php
defined('ABSPATH') || exit;

/**
 * Reset automatico dei reminder se cambia la data del prossimo rinnovo
 */
add_action('acf/save_post', 'spm_reset_reminder_se_rinnovo', 20);

function spm_reset_reminder_se_rinnovo($post_id) {
	if (get_post_type($post_id) !== 'servizio_cliente') return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

	$current_rinnovo = get_field('data_prossimo_rinnovo', $post_id);
	$old_rinnovo = get_post_meta($post_id, '_old_data_prossimo_rinnovo', true);

	// Se la data è cambiata (e non è vuota), resetta i reminder
	if ($current_rinnovo && $current_rinnovo !== $old_rinnovo) {
		$reminder_list = get_field('reminder_personalizzati', $post_id);

		if (is_array($reminder_list)) {
			foreach ($reminder_list as &$r) {
				$r['inviato'] = false;
			}
			update_field('reminder_personalizzati', $reminder_list, $post_id);
		}

		// Aggiorna la copia interna della data per futuri confronti
		update_post_meta($post_id, '_old_data_prossimo_rinnovo', $current_rinnovo);
	}
}
