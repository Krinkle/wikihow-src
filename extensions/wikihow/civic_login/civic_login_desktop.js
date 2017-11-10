/**
 * @file Desktop Civic login/signup
 */

(function(window, document, $) {
	'use strict';

	if (typeof window.WH == 'undefined' || typeof window.WH.social == 'undefined' || !window.WH.social.c.isEnabled()) {
		return;
	}

	function doCivicSignup() {
		whCivic.civicSip.signup({
			style: 'popup',
			scopeRequest: whCivic.civicSip.ScopeRequests.BASIC_SIGNUP
		});
	}

	var whCivic = window.WH.social.c;

	$(document).ready(function()
	{
		var loading = 0, loaded = 0;
		$('body').on('click', '#civic_login_head, #civic_login', function(e)
		{
			e.preventDefault();
			if (loading) { // User clicks button again before Civic is initialized
				return;
			} else if (!loaded) { // User clicks button for the first time
				loading = 1;
				var $btns = $('#civic_login_head, #civic_login');
				$btns.addClass('loading');
				whCivic.addInitCallback(function() // Called once Civic is initialized
				{
					loading = 0; loaded = 1;
					// Handle a successful sign-in event
					whCivic.civicSip.on('auth-code-received', function (event) {
						whCivic.login(event.response, $('#social-login-navbar').data('returnTo'));
					});
					doCivicSignup();
					setTimeout(function() { $btns.removeClass('loading'); }, 2000);
				});
				whCivic.init();
			} else { // User clicks the button and Civic is already initialized
				doCivicSignup();
			}
		});
	});

	/* Not used [Alberto, 2017-09]
	var showDisconnectDialog = function() {
		var confirm = false;
		$('<div></div>').appendTo('body')
			.html('Are you sure you want to disconnect your Civic account from wikiHow?')
			.dialog({
				modal: true,
				title: 'Please confirm',
				zIndex: 10000,
				autoOpen: true,
				width: 400,
				resizable: false,
				closeText: 'x',
				buttons: {
					Disconnect: function() {
						confirm = true;
						$(this).dialog('close');
					},
					Cancel: function() {
						confirm = false;
						$(this).dialog('close');
					}
				},
				close: function() {
					$(this).remove();
					if (confirm) {
						whCivic.authInstance.disconnect();
						location.href='/Special:CivicLogin?disconnect=user';
					}
				}
			});
	};

	// Disconnect button on user profile page (/User:<username>)
	$('#civic_disconnect').click(showDisconnectDialog);
	*/

}(window, document, jQuery));
