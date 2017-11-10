/**
 * @file Mobile Google login/signup
 */

(function(window, document, $) {
	'use strict';

	if (typeof window.WH == 'undefined' || typeof window.WH.social == 'undefined') {
		return;
	}

	var whGoogle = window.WH.social.g;

	var showDisconnectDialog = function() {
		var confirm = false;
		$('<div></div>').appendTo('body')
			.html('Are you sure you want to disconnect your Google account from wikiHow?')
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
						whGoogle.authInstance.disconnect();
						location.href='/Special:GPlusLogin?disconnect=user';
					}
				}
			});
	};

	/**
	 * Initialize the login and disconnect buttons
	 */
	var bootstrap = function() {

		var callback = function(googleUser) {
			whGoogle.login(googleUser, $('#social-login-navbar').data('returnTo'));
		};

		// Login button on signup page (/Special:UserLogin)
		whGoogle.authInstance.attachClickHandler($('#gplus_connect')[0],
			{ 'scope': whGoogle.SCOPE }, callback
		);

		// Login button on navigation bar
		whGoogle.authInstance.attachClickHandler($('#gplus_connect_head')[0],
			{ 'scope': whGoogle.SCOPE }, callback
		);

		// Disconnect button on user profile page (/User:<username>)
		$('#gplus_disconnect').click(showDisconnectDialog);
	};

	whGoogle.addInitCallback(bootstrap);

}(window, document, jQuery));
