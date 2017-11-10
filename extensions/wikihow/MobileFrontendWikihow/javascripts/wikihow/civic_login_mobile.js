/**
 * @file Mobile Civic login/signup
 */

(function(window, document, $) {
	'use strict';

	if (typeof window.WH == 'undefined' || typeof window.WH.social == 'undefined' || !window.WH.social.c.isEnabled()) {
		return;
	}

	var whCivic = window.WH.social.c;

	var bootstrap = function() {
		var callback = function(authCode) {
			whCivic.login(authCode, $('#social-login-form').data('returnTo'));
		};

		$('body').on('click', '#civicButton', function(e) {
			e.preventDefault();
			whCivic.civicSip.signup({ style: 'popup', scopeRequest: whCivic.civicSip.ScopeRequests.BASIC_SIGNUP });
		});

		// Successful sign in
		whCivic.civicSip.on('auth-code-received', function (event) {
			callback(event.response);
		});
	};

	$(document).ready(function() {
		whCivic.addInitCallback(bootstrap);
		whCivic.init();
	});

}(window, document, jQuery));