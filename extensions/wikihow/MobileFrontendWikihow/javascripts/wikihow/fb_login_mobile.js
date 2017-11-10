/**
 * @file Mobile Facebook login/signup
 */

(function(window, document, $) {
	'use strict';

	if (typeof window.WH == 'undefined' || typeof window.WH.social == 'undefined') {
		return;
	}

	var whFacebook = window.WH.social.fb;

	var initButton = function(FB) {
		$('#facebookButton').click(function() {
			FB.login(function(response) {
				if (response.status === 'connected') {
					whFacebook.login(response.authResponse, $('#social-login-form').data('returnTo'));
				} else {
					alert(mw.msg('mobile-facebook-login-failed'));
				}
			}, { scope: whFacebook.SCOPE });
		});
	};

	$(document).ready(function() {
		whFacebook.addInitCallback(initButton);
		whFacebook.init();
	});

}(window, document, jQuery));
