<?php
// Inline JS SOLO nella lista contratti
add_action('admin_footer-edit.php', function () {
	if (empty($_GET['post_type']) || $_GET['post_type'] !== 'contratti') {
		return;
	}

	$nonce = wp_create_nonce('spm_contract_action');
	?>
	<script>
	jQuery(function($){
		$(document).off('click.spm', '.spm-action').on('click.spm', '.spm-action', function(e){
			e.preventDefault();
			var $btn   = $(this);
			var action = $btn.data('action');
			var id     = parseInt($btn.data('id'), 10);
			if (!action || !id) return;
			if (action === 'cessa' && !confirm('Sei sicuro di voler cessare definitivamente il contratto?')) {
				return;
			}
			var originalText = $btn.text();
			$btn.prop('disabled', true).text('Elaborazione...');
			$.post(ajaxurl, {
				action: 'spm_contract_action',
				contract_action: action,
				post_id: id,
				_wpnonce: '<?php echo esc_js($nonce); ?>'
			})
			.done(function(resp){
				if (resp && resp.success) {
					alert(resp.data.message || 'Operazione eseguita');
					location.reload();
				} else {
					alert('Errore: ' + (resp?.data?.message || 'Richiesta non valida'));
					$btn.prop('disabled', false).text(originalText);
				}
			})
			.fail(function(){
				alert('Errore di rete o permessi.');
				$btn.prop('disabled', false).text(originalText);
			});
		});
	});
	</script>
	<?php
});
