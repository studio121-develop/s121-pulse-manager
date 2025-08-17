(function(){
  if (!window.spmNativePrimaryClick) {
	window.spmNativePrimaryClick = function(){
	  console.log('[SPM] Invocato spmNativePrimaryClick');

	  // 1) Gutenberg: pulsante primario (Pubblica/Aggiorna)
	  var gBtn = document.querySelector('.edit-post-header__settings .components-button.is-primary');
	  if (gBtn) { console.log('[SPM] Click Gutenberg primary'); gBtn.click(); return; }

	  // 2) Classic editor: bottone #publish
	  var cBtn = document.getElementById('publish') || document.querySelector('#submitpost #publish');
	  if (cBtn) { console.log('[SPM] Click Classic #publish'); cBtn.click(); return; }

	  // 3) Fallback: submit del form
	  var form = document.getElementById('post');
	  if (form) { console.log('[SPM] Submit form fallback'); form.submit(); }
	};
  }
})();

// --- estensioni per Salva ACF → Rinnova ---
(function($){
  'use strict';

  if (typeof window.SPM_VARS === 'undefined') {
	console.log('[SPM] Nessuna SPM_VARS: esco dallo script');
	return;
  }

  function serializeAcfFields() {
	var $form = $('#post');
	var serialized = (window.acf && typeof acf.serialize === 'function') ? acf.serialize($form, 'acf') : {};
	console.log('[SPM] serializeAcfFields', serialized);
	return serialized;
  }

  function saveAcfFields(postId) {
	console.log('[SPM] saveAcfFields START', {postId});
	return $.ajax({
	  url: SPM_VARS.ajaxUrl,
	  method: 'POST',
	  dataType: 'json',
	  data: {
		action: 'spm_acf_save',
		_wpnonce: SPM_VARS.nonceAcfSave,
		post_id: postId,
		fields: serializeAcfFields()
	  }
	}).then(function(res){
	  console.log('[SPM] saveAcfFields DONE', res);
	  if (!res || !res.success) {
		var msg = (res && res.data && res.data.message) ? res.data.message : 'Salvataggio ACF fallito';
		console.error('[SPM] saveAcfFields FAIL', msg);
		return $.Deferred().reject(msg).promise();
	  }
	  return res;
	});
  }

  function ajaxContractAction(postId, actionName) {
	console.log('[SPM] ajaxContractAction START', {postId, actionName});
	return $.ajax({
	  url: SPM_VARS.ajaxUrl,
	  method: 'POST',
	  dataType: 'json',
	  data: {
		action: 'spm_contract_action',
		_wpnonce: SPM_VARS.nonceContractAction,
		contract_action: actionName,
		post_id: postId
	  }
	}).then(function(res){
	  console.log('[SPM] ajaxContractAction DONE', res);
	  if (!res || !res.success) {
		var msg = (res && res.data && res.data.message) ? res.data.message : 'Azione contratto fallita';
		console.error('[SPM] ajaxContractAction FAIL', msg);
		return $.Deferred().reject(msg).promise();
	  }
	  return res;
	});
  }

  function withBusy(btn, fn){
	var $b = $(btn);
	var old = $b.text();
	console.log('[SPM] withBusy START', {text: old});
	$b.prop('disabled', true).text('Elaborazione…');
	return fn().always(function(){
	  console.log('[SPM] withBusy END');
	  $b.prop('disabled', false).text(old);
	});
  }

  $(document).on('click', '.spm-action[data-action="rinnova"]', function(ev){
	ev.preventDefault();
	ev.stopImmediatePropagation();

	var btn = this;
	var postId = SPM_VARS.postId || 0;
	console.log('[SPM] Click rinnova', {postId});

	if (!postId) {
	  alert('ID contratto mancante');
	  return;
	}

	withBusy(btn, function(){
	  return saveAcfFields(postId)
		.then(function(){
		  return ajaxContractAction(postId, 'rinnova');
		})
		.done(function(res){
		  console.log('[SPM] rinnova SUCCESS', res);
		  alert(res.data && res.data.message ? res.data.message : 'Rinnovo completato');
		  clearUnsavedGuardThenReload(); // <-- al posto di window.location.reload()
		})
		.fail(function(err){
		  console.error('[SPM] rinnova ERROR', err);
		  alert('Errore: ' + (err || 'operazione fallita'));
		});
	});
  });

})(jQuery);

function clearUnsavedGuardThenReload() {
  try {
	// Gutenberg: se l’editor pensa che ci siano modifiche non salvate, salva in modo programmatico
	if (window.wp && wp.data && wp.data.select && wp.data.dispatch) {
	  var sel = wp.data.select('core/editor');
	  var disp = wp.data.dispatch('core/editor');

	  if (sel && disp && typeof sel.isEditedPostDirty === 'function') {
		if (sel.isEditedPostDirty()) {
		  // salva e poi ricarica quando finisce
		  disp.savePost();
		  var unsubscribe = wp.data.subscribe(function(){
			var isSaving = sel.isSavingPost();
			var isAutosaving = sel.isAutosavingPost && sel.isAutosavingPost();
			if (!isSaving && !isAutosaving) {
			  unsubscribe && unsubscribe();
			  // piccolo timeout per sicurezza
			  setTimeout(function(){ window.location.reload(); }, 50);
			}
		  });
		  return; // usciamo: il reload lo facciamo dal subscribe
		}
	  }
	}

	// Classic editor (o fallback): rimuovi i beforeunload e ricarica
	window.onbeforeunload = null;
	if (window.jQuery) {
	  try { jQuery(window).off('beforeunload'); } catch(e){}
	}
  } catch(e) {
	// non bloccare il reload in caso di errori
	console.warn('[SPM] clearUnsavedGuardThenReload fallback', e);
  }
  window.location.reload();
}
