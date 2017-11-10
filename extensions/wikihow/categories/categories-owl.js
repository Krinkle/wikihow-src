(function($, mw) {
	'use strict';

	$(document).ready(function() {
		var pendingRequest = false;
		var lastScrollTop = 0;
		//var stateObj = { cat: "scrollHistory" };
		$(window).scroll(function() {
			if (pendingRequest) {
				return;
			}
			var currentScrollTop = $(window).scrollTop();
			var downwards = (currentScrollTop > lastScrollTop);
			lastScrollTop = currentScrollTop;

			if (downwards && $(window).height() + currentScrollTop >= $(document).height() - 400) {
				pendingRequest = true;

				$('#bodycontents').append('<div id="cat-content-' + gScrollContext +
						'"></div><div id="spinner-' + gScrollContext + '" class="cat-spinner"></div>');

				// stop appending timestamp to category REST urls
				$.ajaxSetup({
					cache: true
				});

				var params = { restaction: 'pull-chunk', start: gScrollContext };
				$.get('/' + mw.config.get('wgPageName'), params, function(data) {
					setTimeout(function() {
						pendingRequest = false;
					}, 750);
					$('#spinner-' + gScrollContext).remove();
					if (data && data.html) {
						$('#cat-content-' + gScrollContext).html(data.html);
						//update global pg #
						//gScrollContextPage++;
						//update the url
						//history.replaceState(stateObj, "page "+gScrollContextPage, window.location.pathname+"?pg="+gScrollContextPage);
					}
				}, 'json');
			}
		});
	});

}(jQuery, mediaWiki));
