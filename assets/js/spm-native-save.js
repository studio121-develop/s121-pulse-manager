(function(){
  if (!window.spmNativePrimaryClick) {
	window.spmNativePrimaryClick = function(){
	  // 1) Gutenberg: pulsante primario (Pubblica/Aggiorna)
	  var gBtn = document.querySelector('.edit-post-header__settings .components-button.is-primary');
	  if (gBtn) { gBtn.click(); return; }

	  // 2) Classic editor: bottone #publish
	  var cBtn = document.getElementById('publish') || document.querySelector('#submitpost #publish');
	  if (cBtn) { cBtn.click(); return; }

	  // 3) Fallback: submit del form
	  var form = document.getElementById('post');
	  if (form) form.submit();
	};
  }
})();
