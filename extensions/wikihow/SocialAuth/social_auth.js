/**
 * @file Facebook and Google social authentication
 *
 * Contains code shared by social login features across desktop and mobile.
 * Feature-specific code can be found in: fb_login_desktop.js, fb_login_mobile.js,
 * civic_login_mobile.js, civic_login_desktop.js,
 * google_login_desktop.js, and google_login_mobile.js.
 */

(function(window, document, $) {
	'use strict';

	/* Common */

	window.WH = window.WH || {};
	window.WH.social = window.WH.social || {};

	if (typeof console == 'undefined') console = {};
	if (typeof console.error == 'undefined') console.error = {};

	/**
	 * Initiate the signup/login process by submitting a form to a special page
	 *
	 * @param  {string} url      The URL to which to POST the form
	 * @param  {string} token    Authentication token
	 * @param  {string} returnTo Page to return to after login
	 */
	var doLogin = function(url, token, returnTo) {
		if (typeof returnTo == 'undefined') {
			returnTo = '';
		}
		$(document.createElement('form'))
			.attr('method', 'post')
			.attr('action', url)
			.attr('enctype', 'multipart/form-data')
			.append($('<input name="token" value="' + token + '"/>'))
			.append($('<input name="action" value="login" />'))
			.append($('<input name="returnTo" value="' + returnTo + '" />'))
			.appendTo('body')
			.submit();
	};

	/**
	 * Perform social signup/login in the background
	 *
	 * @param {Object}   data Payload
	 * @param {Function} done Callback when successful
	 * @param {Function} fail Callback when unsuccessful
	 */
	var doAutoLogin = function(data, done, fail) {
		$.ajax({
			type: 'GET',
			dataType: 'jsonp',
			jsonpCallback: 'wh_jsonp_social',
			url: 'https://' + window.location.hostname + '/Special:SocialLogin?action=login',
			data: data
		})
		.done(done)
		.fail(fail);
	};

	/* Facebook */

	var whFacebook = window.WH.social.fb = {}; // Container for shared global variables
	whFacebook.SCOPE = 'public_profile,email,user_friends';

	var facebookIsInit = false;

	/**
	 * Load the Facebook SDK
	 *
	 * This method is to be called only once, after all post-initialization
	 * callbacks have been added with addInitCallback().
	 */
	whFacebook.init = function() {

		if (facebookIsInit) {
			console.error('Facebook is already initialized');
			return;
		}
		facebookIsInit = true;

		(function(d, s, id) {
			var locale;
			if (wgUserLanguage == 'en') {
				locale = 'en_US';
			} else if (wgUserLanguage == 'pt') {
				locale = 'pt_BR';
			} else {
				locale = wgUserLanguage + '_' + wgUserLanguage.toUpperCase();
			}
			var sdkUrl = '//connect.facebook.net/' + locale + '/sdk.js';

			var js, fjs = d.getElementsByTagName(s)[0];
			if (d.getElementById(id)) return;
			js = d.createElement(s); js.id = id;
			js.src = sdkUrl;
			fjs.parentNode.insertBefore(js, fjs);
		}(document, 'script', 'facebook-jssdk'));
	};

	/**
	 * Execute the given function after the Facebook SDK has been loaded
	 * @param {Function} callback
	 */
	whFacebook.addInitCallback = function(callback) {
		var oldInit = window.fbAsyncInit;
		window.fbAsyncInit = function() {
			if (typeof oldInit === 'function') {
				oldInit();
			} else {
				// https://developers.facebook.com/docs/javascript/reference/FB.init/v2.6
				FB.init({
					appId: wgFBAppId,
					xfbml: true,  // Parse XFBML tags used by social plugins
					status: true, // Retrieve the current login status of the user on every page load
					version: 'v2.10'
				});
			}
			callback(FB);
		};
	};

	/**
	 * Initiate the Special:FBLogin signup/login process.
	 *
	 * Used as a callback in fb_login_mobile.js and fb_login_desktop.js.
	 *
	 * @param {Object} authResponse The authResponse object returned by the Facebook API
	 * @param {string} returnTo     Page to return to after login
	 */
	whFacebook.login = function(authResponse, returnTo) {
		var loginUrl = 'https://' + window.location.hostname + '/Special:FBLogin';
		doLogin(loginUrl, authResponse.accessToken, returnTo);
	};

	/**
	 * Send a post request to our back-end, which will create a new user
	 * account if necessary, and then log the user in.
	 *
	 * @param {Object} authResponse The authResponse object returned by the Facebook API
	 * @param {Function} done       Callback when successful
	 * @param {Function} fail       Callback when unsuccessful
	 */
	whFacebook.autoLogin = function(authResponse, done, fail) {
		var data = {
			'type': 'facebook',
			'authToken': authResponse.accessToken
		};
		doAutoLogin(data, done, fail);
	};

	/* Google */

	var whGoogle = window.WH.social.g = {}; // Container for shared global variables
	whGoogle.SCOPE = 'profile email';
	whGoogle.authInstance = null;

	var googleIsInit = false;
	var googleHandlers = [];

	/**
	 * Load the Google API client library
	 *
	 * This method is to be called only once, after all post-initialization
	 * callbacks have been added with addInitCallback().
	 */
	whGoogle.init = function() {

		if (googleIsInit) {
			console.error('Google is already initialized');
			return;
		}
		googleIsInit = true;

		var callHandlers = function() {
			var count = googleHandlers.length;
			for (var i = 0; i < count; i++) {
				googleHandlers[i](gapi);
			}
		};

		$.ajax({
			url: 'https://apis.google.com/js/api:client.js',
			dataType: 'script',
			cache: true,
			success: function() {
				gapi.load('auth2', function() {
					gapi.auth2.init({
						client_id: wgGoogleAppId,
						cookiepolicy: 'http://' + wgCookieDomain
					}).then(function() {
						if (wgUserLanguage == 'ar') {
							// Prevent horizontal bar on RTL languages
							var $iframe = $('#ssIFrame_google');
							var val = $iframe.css('left');
							$iframe.css('left', '');
							$iframe.css('right', val);
						}
						whGoogle.authInstance = gapi.auth2.getAuthInstance();
						callHandlers();
					});
				});

			}
		});
	};

	/**
	 * Execute the given function after the Google SDK has been loaded
	 * @param {Function} callback
	 */
	whGoogle.addInitCallback = function(callback) {
		googleHandlers.push(callback);
	};

	/**
	 * Initiate the Special:GPlusLogin signup/login process.
	 *
	 * Used as a callback in google_login_mobile.js and google_login_desktop.js.
	 *
	 * @param {Object} googleUser The GoogleUser object returned by the Google API
	 * @param {string} returnTo   Page to return to after login
	 */
	whGoogle.login = function(googleUser, returnTo) {
		var loginUrl = 'https://' + window.location.hostname + '/Special:GPlusLogin';
		doLogin(loginUrl, googleUser.getAuthResponse().id_token, returnTo);
	};

	/**
	 * Send a post request to our back-end, which will create a new user
	 * account if necessary, and then log the user in.
	 *
	 * @param {Object} googleUser The GoogleUser object returned by the Google API
	 * @param {Function} done     Callback when successful
	 * @param {Function} fail     Callback when unsuccessful
	 */
	whGoogle.autoLogin = function(googleUser, done, fail) {
		var data = {
			'type': 'google',
			'authToken': googleUser.getAuthResponse().id_token
		};
		doAutoLogin(data, done, fail);
	};

	/* Civic */

	var whCivic = window.WH.social.c = {}; // Container for shared global variables

	var civicIsInit = false;
	var civicHandlers = [];
	whCivic.civicSip = null;

	/**
	 * Load the Civic API client library
	 *
	 * This method is to be called only once, after all post-initialization
	 * callbacks have been added with addInitCallback().
	 */
	whCivic.init = function() {
		if (civicIsInit) {
			console.error('Civic is already initialized');
			return;
		}
		civicIsInit = true;

		var callHandlers = function(civicSip) {
			var count = civicHandlers.length;
			for (var i = 0; i < count; i++) {
				civicHandlers[i](civicSip);
			}
		};

		$.ajax({
			url: 'https://hosted-sip.civic.com/sip/js/civic.sip.min.js',
			dataType: 'script',
			cache: true,
			success: function() {
				$('head').append('<link rel="stylesheet" href="https://hosted-sip.civic.com/sip/css/civic-modal.min.css">');
				whCivic.civicSip = new civic.sip({ appId: mw.config.get('wgCivicAppId') });
				callHandlers(whCivic.civicSip);
			}
		});
	};

	/**
	 * Execute the given function after the Civic SDK has been loaded
	 * @param {Function} callback
	 */
	whCivic.addInitCallback = function(callback) {
		civicHandlers.push(callback);
	};

	/**
	 * Initiate the Special:Civic signup/login process.
	 *
	 * Used as a callback in civic_login_desktop.js.
	 *
	 * @param {Object} civicUser The CivicUser object returned by the Civic API
	 * @param {string} returnTo   Page to return to after login
	 */
	whCivic.login = function(jwtToken, returnTo) {
		var loginUrl = 'https://' + window.location.hostname + '/Special:CivicLogin';
		doLogin(loginUrl, jwtToken, returnTo);
	};

	/**
	 * Send a post request to our back-end, which will create a new user
	 * account if necessary, and then log the user in.
	 *
	 * @param {Object} jwtToken The token  returned by the Civic API
	 * @param {Function} done     Callback when successful
	 * @param {Function} fail     Callback when unsuccessful
	 */
	whCivic.autoLogin = function(jwtToken, done, fail) {
		var data = {
			'type': 'civic',
			'authToken': jwtToken
		};
		doAutoLogin(data, done, fail);
	};

	whCivic.isEnabled = function() {
		return wgUserLanguage == 'en';
	}

	// Add generic civic event handlers when civic is initialized. These
	// handlers work for both desktop and mobile
	whCivic.addInitCallback(function() {
		// User Cancelled
		// whCivic.civicSip.on('user-cancelled', function (event) {
		// 	// Do nothing
		// });

		// Error events.
		whCivic.civicSip.on('civic-sip-error', function (error) {
			// console.log('   Error type = ' + error.type);
			// console.log('   Error message = ' + error.message);
			alert('Oops. We can\'t connect you to Civic. Please check back later');
		});
	});

}(window, document, jQuery));
