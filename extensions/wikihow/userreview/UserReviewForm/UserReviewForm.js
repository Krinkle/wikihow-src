(function ( mw, $ )  {
	window.WH = WH || {};
	window.WH.UserReviewForm = function () {};

	urf = window.WH.UserReviewForm.prototype = {
		submittedReviewId: null,
		userReviewEndpoint: "/Special:UserReviewForm",
		hasLoggedIn: false,

		getScrollingElement: function(){
			return WH.isMobileDomain ? $('#mw-mf-viewport') : $('body');
		},

		machinifyLog: function(){
			urf = window.WH.UserReviewForm.prototype;
			WH.maEvent('opti_testimonial', {
				category: 'opti',
				pagetitle: mw.config.get('wgTitle'),
				testemail: $('#email').val(),
				testdetail: $('#review').val(),
				testfirst: $('#first-name').val(),
				testlast: $('#last-name').val(),
				testarticleid: mw.config.get('wgArticleId'),
				testsource: WH.isMobileDomain ? 'mobile' : 'desktop',
				testwithpageloadstat: 'yes',
				testdetailunstripped: '',
			}, false);
		},

		validateInput: function(key, object) {
			$(object).css('border-color', '#eee');
			if ($(object).val().length < 1) {
				$(object).css('border-color', 'red');
				return false;
			}
			return true;
		},
		postUserReview: function () {
			valid = true;
			urf = window.WH.UserReviewForm.prototype;
			$('.required').each( function(key, object) {
				currValid = urf.validateInput(key, object);
				valid = valid && currValid;
			});

			if (valid) {
				if(mw.config.get("wgUserId") == null) {
					urf.initSocialLoginForm();
				}
				$.post(urf.userReviewEndpoint,
					{
						action: 'post_review',
						articleId: wgArticleId,
						firstName: $('#first-name').val(),
						lastName:$('#last-name').val(),
						review:$('#review').val(),
						rating:$('.ur_helpful_icon_star.mousedone').length,
						image:$('#urf_uci_image').val()
					},
					function(result) {
						$('#urf-content-container').fadeOut('fast',function() {
							if(mw.config.get("wgUserId") == null) {
								$('#urf-social-login').fadeIn('fast');
							} else {
								$('#urf-thanks').fadeIn('fast');
							}
						});
						if(result.success) {
							urf.submittedReviewId = result.success.id;
						}
					},
					'json'
				);
				urf.machinifyLog();
			} else {
				$('#urf-submit').prop('disabled', false);
			}
		},

		loadUserReviewForm: function () {
			urf = window.WH.UserReviewForm.prototype;
			$.get(urf.userReviewEndpoint,
				{action:'get_form'},
				function(result) {
					if ($('#urf_form_container').length < 1) {
						$('body').append(result.html);
					}
					urf.starBehavior();
					$('#urf-popup').magnificPopup({
						fixedContentPos: false,
						fixedBgPos: true,
						showCloseBtn: false,
						overflowY: 'auto',
						preloader: false,
						type: 'inline',
						closeBtnInside: true,
						callbacks: {
							beforeClose: function() {
								if(urf.hasLoggedIn) {
									window.location.reload();
								}
							}
						}
					});
					$('.urf-close').click(urf.hideUserReviewForm);
					$('#urf-submit').click(function() {
						$('#urf-submit').prop('disabled', true);
						urf.postUserReview();
					});
					scrollingElement = urf.getScrollingElement();
					scrollingElement.addClass('modal-open');
					$('#urf-popup').trigger('click');
				},
				'json'
			);
		},

		setUCIImage: function(imageName) {
			$("#urf_uci_image").val(imageName);
		},

		hideUserReviewForm: function  () {
			scrollingElement = urf.getScrollingElement();
			scrollingElement.removeClass('modal-open');
			$.magnificPopup.close();
		},

		starBehavior: function() {
			$(".ur_star_container").each(function(index){
				$(this).bind({
					mouseenter: function () {
						for (var j = 1; j <= index+1; j++){
							$("#ur_star_section #ur_star" + j + " > div:eq(1)").addClass("mousevote");
						}
					},
					mouseleave: function () {
						for (var j = 1; j <= index+1; j++){
							$("#ur_star_section #ur_star" + j + " > div:eq(1)").removeClass("mousevote");
						}
					},
					click: function () {
						$(".ur_star_container .ur_helpful_icon_star").removeClass("mousedone mousevote");
						for (var j = 1; j <= index+1; j++){
							$("#ur_star_section #ur_star" + j + " > div:eq(1)").addClass("mousedone");
						}
					}
				})
			});

		},

		// Social login methods
		// Example adapted from qa_widget.js

		isSocialAuthLoaded: function() {
			return typeof window.WH.social != 'undefined';
		},

		initSocialLoginForm: function() {
			var urf = window.WH.UserReviewForm.prototype;

			var whLoginDone = function(data) {
				// Called after the social signup/login is complete
				urf.hasLoggedIn = true;

				$("#urf-social-login-done .urf-social-avatar").attr("src", data.user.avatarUrl);
				$("#urf-social-login-done .urf-user-link").attr("href", "/User:"+data.user.username).html(data.user.realName);

				var properties = {
					category: 'account_signup',
					type: data.type,
					prompt_location: 'stories'
				};
				WH.maEvent('account_signup', properties, false);

				//now we have the new userid, so we need to associate it with the submitted story
				$.ajax({
					type: "GET",
					url: 'https://' + window.location.hostname + urf.userReviewEndpoint,
					data: {action: 'update', us_id: urf.submittedReviewId, us_user_id: data.user.userId},
					dataType: 'jsonp',
					jsonpCallback: 'wh_jsonp_ur'
				}).done(function (response) {
					$("#urf-social-login .urf-social-close").on("click", function(e){
						e.preventDefault();
						window.location.reload();
					});
					
				});
				
				$("#urf-social-login-before").hide();
				$("#urf-social-login-done").show();
			};

			var whLoginFail = function() {
				// Called if the social signup/login fails
			};

			var initFacebookButtonWrapper = function() {
				urf.initFacebookButton(whLoginDone, whLoginFail);
			};

			var initGoogleButtonWrapper = function() {
				urf.initGoogleButton(whLoginDone, whLoginFail);
			};

			if (WH.isMobileDomain && !urf.isSocialAuthLoaded()) {
				mw.loader.using('ext.wikihow.socialauth', function() {
					WH.social.fb.addInitCallback(initFacebookButtonWrapper);
					WH.social.fb.init()
					WH.social.g.addInitCallback(initGoogleButtonWrapper);
					WH.social.g.init()
				});
			} else {
				initFacebookButtonWrapper();
				initGoogleButtonWrapper();
			}
		},

		initFacebookButton: function(done, fail) {
			var urf = window.WH.UserReviewForm.prototype;

			if (!urf.isSocialAuthLoaded() || typeof FB == 'undefined') {
				return;
			}

			var socialLoginComplete = function(response) {
				if (response.status === 'connected') {
					window.WH.social.fb.autoLogin(response.authResponse, done, fail);
				}
			};

			var $button = $('#urf-social-login .facebook_button');
			$button.click(function() {
				FB.login(socialLoginComplete, { scope: window.WH.social.fb.SCOPE });
			});
		},

		initGoogleButton: function(done, fail) {
			var urf = window.WH.UserReviewForm.prototype;

			if (!urf.isSocialAuthLoaded() || typeof window.WH.social.g.authInstance == 'undefined') {
				return;
			}

			var socialLoginComplete = function(googleUser) {
				window.WH.social.g.autoLogin(googleUser, done, fail);
			};

			var $button = $('#urf-social-login .google_button')[0];
			window.WH.social.g.authInstance.attachClickHandler(
				$button, { 'scope': window.WH.social.g.SCOPE }, socialLoginComplete
			);
		}

	};
}( mediaWiki , jQuery ) );
