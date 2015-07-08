define(['jquery', 'elgg'], function($, elgg) {

	$(document).on('change', '.ws-endpoints-select-all', function() {
		$(this).closest('table').find('input[type="checkbox"].ws-endpoints-endpoint-enable').prop('checked', $(this).prop('checked'));
	});

});


