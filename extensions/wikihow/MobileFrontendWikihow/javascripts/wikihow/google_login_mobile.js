/**
 * @file Mobile Google login/signup
 */

(function(window, document, $) {
	'use strict';

	if (typeof window.WH == 'undefined' || typeof window.WH.social == 'undefined') {
		return;
	}

	var whGoogle = window.WH.social.g;

	var callback = function(googleUser) {
		whGoogle.login(googleUser, $('#social-login-form').data('returnTo'));
	};

	var initButton = function() {
		whGoogle.authInstance.attachClickHandler($('#googleButton')[0],
			{ 'scope': whGoogle.SCOPE }, callback
		);
	};

	$(document).ready(function() {
		whGoogle.addInitCallback(initButton);
		whGoogle.init();
	});

}(window, document, jQuery));

