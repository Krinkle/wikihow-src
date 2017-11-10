(function() {
	window.WH = window.WH || {};
	
	$(document).ready(function() {
		$('#wpCreateaccount').click(function() {
			WH.maEvent('account_signup', { category: 'account_signup', type: 'wikihow' }, false);
		});
	});
	
})(jQuery);

