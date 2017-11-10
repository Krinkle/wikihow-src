(function(M, $) {

	var CtaDrawer = M.require( 'CtaDrawer' );
	var WhCtaDrawer;

	/**
	 * Adds social login to CtaDrawer.js
	 */
	WhCtaDrawer = CtaDrawer.extend( {
		defaults: {
			facebookCaption: mw.msg( 'mobile-cta-drawer-log-in-facebook' ),
			googleCaption: mw.msg( 'mobile-cta-drawer-log-in-google' )
		},
		template: M.template.get( 'modules/whCtaDrawer/whCtaDrawer' ),
		className: CtaDrawer.prototype.className += ' wh_cta_drawer',

		show: function() {
			CtaDrawer.prototype.show.call(this)
			initSocialLogin(this.$el);
		}

	} );

	M.define( 'WhCtaDrawer', WhCtaDrawer );

	var initSocialLogin = function($el) {
		var facebookInitCallback = function() {
			initFacebookButton($el);
		};

		var googleInitCallback = function() {
			initGoogleButton($el);
		};
		if (typeof window.WH.social == 'undefined') {
			mw.loader.using('ext.wikihow.socialauth', function() {
				WH.social.fb.addInitCallback(facebookInitCallback);
				WH.social.fb.init()
				WH.social.g.addInitCallback(googleInitCallback);
				WH.social.g.init()
			});
		} else {
			facebookInitCallback();
			googleInitCallback();
		}
	};

	var facebookLoginCallback = function(response) {
		if (response.status === 'connected') {
			window.WH.social.fb.login(response.authResponse, wgPageName);
		}
	};

	var initFacebookButton = function($el) {
		if (typeof FB == 'undefined') {
			return;
		}

		var $button = $el.find('.facebook_button');
		$button.click(function() {
			FB.login(facebookLoginCallback, { scope: window.WH.social.fb.SCOPE });
		});
	};

	var googleLoginCallback = function(googleUser) {
		window.WH.social.g.login(googleUser, wgPageName);
	};

	var initGoogleButton = function($el) {
		if (typeof window.WH.social.g.authInstance == 'undefined') {
			return;
		}

		var $button = $el.find('.google_button')[0];
		window.WH.social.g.authInstance.attachClickHandler(
			$button, { 'scope': window.WH.social.g.SCOPE }, googleLoginCallback
		);
	};

}(mw.mobileFrontend, jQuery));
