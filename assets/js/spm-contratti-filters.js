(function($){
	function makeSelect2Ajax($el, postType){
		if (typeof $.fn.select2 !== 'function' || !$el.length) return;
		$el.select2({
			placeholder: $el.data('placeholder') || (postType === 'clienti' ? 'Clienti' : 'Servizi'),
			allowClear: true,
			ajax: {
				url: SPM_FILTERS.ajaxUrl,
				dataType: 'json',
				delay: 250,
				data: function(params){
					return {
						action: 'spm_search_cpt',
						_wpnonce: SPM_FILTERS.nonce,
						post_type: postType,
						q: (params.term || '').trim(),
						page: params.page || 1
					};
				},
				processResults: function(data, params){
					params.page = params.page || 1;
					return {
						results: (data && data.data && data.data.items) ? data.data.items : [],
						pagination: { more: !!(data && data.data && data.data.more) }
					};
				},
				cache: true
			},
			minimumInputLength: 1,
			width: '100%'
		}).on('select2:clear', function(){
			$el.val('').trigger('change');
		});
	}

	function makeSelect2Static($el){
		if (typeof $.fn.select2 !== 'function' || !$el.length) return;
		$el.select2({
			minimumResultsForSearch: Infinity,
			width: '100%'
		});
	}

	$(function(){
		makeSelect2Ajax($('#spm-filter-cliente'),  'clienti');
		makeSelect2Ajax($('#spm-filter-servizio'), 'servizi');
		makeSelect2Static($('#spm-filter-frequenza'));
		makeSelect2Static($('#spm-filter-stato'));
	});
})(jQuery);
