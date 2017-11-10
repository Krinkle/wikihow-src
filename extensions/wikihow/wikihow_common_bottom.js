(function(mw, $) {
'use strict';

// ratings section
WH.ratings = {};

// DEPRECATED
// TODO: Remove this function
WH.ratings.ratingReason = function(reason, itemId, type, rating, name, email, detail, ratingId) {
	if (!reason && !detail) {
		return;
	}

	var postData = {
		'item_id': itemId,
		'type': type,
		'ratingId': ratingId,
		'rating': rating
	};
	var requestUrl = '/Special:RatingReason';
	if (detail && typeof detail != 'undefined') {
		postData.detail = detail;
	}
	if (reason && typeof reason != 'undefined') {
		postData.reason = reason;
	}
	if (name && typeof name != 'undefined') {
		postData.name = name;
	}
	if (email && typeof email != 'undefined') {
		postData.email = email;
	}
	if (type === 'article' && mw.config.get('wgArticleId')) {
		postData.page_id = mw.config.get('wgArticleId');
	}
	$.ajax({
		type: 'POST',
		url: requestUrl,
		data: postData
	}).done(function(data) {
		data = '<div class="' + type + '_rating_result">' + data + '</div>';
		$('#' + type + '_rating').html(data);
	});
};
WH.ratings.gRated = false;
WH.ratings.rateItem = function(r, itemId, type, source) {
	if (!WH.ratings.gRated) {
		if (window.mw) {
			mw.loader.using(
				['ext.wikihow.ratingreason.mh_style', 'ext.wikihow.ratingreason.mh_style.styles'],
				function() {
					WH.ratings.bindInputFields(WH.ratings.parentElem);
				}
			);
		}

		var postData = {
			'action': 'rate_page',
			'page_id': itemId,
			'rating': r,
			'type': type,
			'source': source
		};

		var ratingsData = '';

		var displayMethodHelpfulnessBottomForm = r == 1 && WH.displayMethodHelpfulness && type != 'sample';

		$.ajax({
			type: 'POST',
			url: '/Special:RateItem',
			data: postData
		}).done(function(data) {
			ratingsData = '<div class="article_rating_result">' + data + '</div>';

			if (WH.RatingSidebar) {
				if (source == 'sidebar') {
					WH.RatingSidebar.showResult(r);
					$('#article_rating').slideUp();
				}
				else if (source == 'desktop') {
					WH.RatingSidebar.disappear();
				}
			}

			var scrollDown = source != 'sidebar' && !displayMethodHelpfulnessBottomForm;

			if (scrollDown) {
				setTimeout(function () {
					$('#article_rating').html(ratingsData);
					$('#article_rating').css('max-width', 'none');
					$('body').scrollTo('#article_rating');
				}, 1);
			}

			if (type == 'sample') {
				$('#sample_rating').html(ratingsData);
			}
		});

		if (displayMethodHelpfulnessBottomForm && source != 'sidebar') {
			// User clicked 'Yes' and the article has methods
			var helpfulnessData = {
				'action': 'cta',
				'type': 'bottom_form',
				'aid': mw.config.get('wgArticleId'),
				'methods': JSON.stringify(WH.methods),
				'platform': source
			};

			$.ajax({
				type: 'POST',
				url: '/Special:MethodHelpfulness',
				data: helpfulnessData,
				dataType: 'json'
			}).done(function(result) {
				var mh = $('<div>', {
					'class': 'article_rating_result'
				});

				if (window.mw) {
					mw.loader.using([result.resourceModule], function () {
						mh.html(result.cta);
						// A 1 ms delay seems to prevent flickering on some devices
						setTimeout(function () {
							$('#article_rating').html(mh);
							$('body').scrollTo('#article_rating');
						}, 1);
					});
				}
			}).fail(function (/*result*/) {
				$('#article_rating').html(ratingsData);
				$('#article_rating').css('max-width', 'none');
				$('body').scrollTo('#article_rating');
			});
		}
	}
	WH.ratings.gRated = true;
};

WH.displayMethodHelpfulness = false;
WH.methods = [];

WH.checkMethods = function() {
	var doMethodCheck = mw.config.get('wgContentLanguage') == 'en';

	if (doMethodCheck) {
		// Get method names
		var methodSelector = '#method-title-info>.mti-title';

		$(methodSelector).each(function () {
			WH.methods.push($(this).data('title'));
		});

		WH.displayMethodHelpfulness = WH.methods.length > 1;
	}
};

WH.injectMethodThumbs = function() {
	var tmplElem = $('.mh-method-thumbs-template');

	if (!tmplElem.length) {
		return;
	}

	if (!WH.displayMethodHelpfulness || !WH.methodThumbsCTAActive) {
		tmplElem.remove();
		return;
	}

	var platform = WH.isMobileDomain ? 'mobile' : 'desktop';

	// TODO: Remove this when/if we want a desktop CTA as well
	if (platform !== 'mobile') {
		tmplElem.remove();
		return;
	}

	var params = {
		questionText: mw.message('mhmt-question').plain()
	};

	var helpedText = mw.message('mhmt-helped').plain();
	var thanksText = mw.message('mhmt-thanks').plain();
	var cheerList = mw.message('mhmt-cheer').plain().split("\n");
	var oopsList = mw.message('mhmt-oops').plain().split("\n");

	$('.section.steps:not(:has(h2>span#Steps))').each(function (index) {
		params.methodIndex = index;
		params.currentMethod = WH.methods[index];
		params.cheerText = cheerList[Math.floor(Math.random()*cheerList.length)] + ' ' + helpedText;
		params.oopsText = oopsList[Math.floor(Math.random()*oopsList.length)] + ' ' + thanksText;
		var cta = Mustache.render(unescape(tmplElem.html()), params);
		$(this).find('.steps_list_2:last').append(cta);
	});

	tmplElem.remove();
};

/**
 * Late loading of printable styling for @media 'print' to prevent breaking
 * the site on IE9.
 */
WH.loadPrintModule = function () {
	if (!WH.isMobileDomain &&
		window.location.href.indexOf('printable=yes') == -1 &&
		!($.browser.msie && $.browser.version < 10)
	) {
		mw.loader.using(['ext.wikihow.printable']);
		WH.bindPrintEvents();
	}
};

window.WH.beforePrintEventCount = 0;

WH.bindPrintEvents = function () {
	if (window.location.href.indexOf('printable=yes') > 0) {
		WH.maEvent('print_article', { category: 'print_article' }, false);
	}

	window.onbeforeprint = WH.beforePrint;

	var mediaQueryList = window.matchMedia('print');
	mediaQueryList.addListener(function (mql) {
		if (mql.matches) {
			WH.beforePrint();
		}
		// Add an else here if you want to handle after-print
	});
};

WH.beforePrint = function () {
	window.WH.beforePrintEventCount += 1;

	// if (window.WH.beforePrintEventCount == 1 && mw.config.get('wgArticleId') > 0) {
		// // Track in machinify
		// WH.maEvent('print_event', { category: 'print_article' }, false);
	// }

	return false;
};

$(document).ready(function() {
	if ($('.mwimg-caption-fade').length < 1) {
		return;
	}

	$(window).on('scroll', function fadeScrollHandler() {
		var fadedIn = false;

		$('.mwimg-caption-fade').each( function() {
			if ($(window).scrollTop() + 300 >= $(this).parent().offset().top) {
				var fadeTime = $(this).data('fadetime');
				$(this).fadeTo(fadeTime, 1);
				$(this).removeClass('mwimg-caption-fade');
				fadedIn = true;
			}
		});

		// if there are no more mwimg-caption-fade classes, unbind the handler
		if ($('.mwimg-caption-fade').length < 1) {
			$(window).off('scroll', fadeScrollHandler);
		}
	});
});

$(document).ready(function() {
	$('.aritem').on('click', function() {
		var type = 'desktop';
		if (WH.isMobileDomain) {
			type = 'mobile';
		}

		var rating = 0;
		if ($(this).attr('id') == 'gatAccuracyYes') {
			rating = 1;
		}

		var pageId = $(this).attr('pageid');
		WH.ratings.rateItem(rating, pageId, 'article_mh_style', type);
	});

	$('#article_rating').on('change', '.ar_public', function() {
		if ($(this).val() == 'yes') {
			$('#ar_public_info').show();
		} else {
			$('#ar_public_info').hide();
		}
	});

//	if (!mw.user.isAnon()) {
//		if ($('#servedtime').length) {
//			var time = parseInt($('#servedtime').html());
//			WH.ga.sendEvent('servedtime', 'appserver', 'milliseconds', time, 1);
//			ga('send', 'timing', 'appserver', 'servedtime', time);
//		}
//	}

	WH.checkMethods();

	if (WH.methodThumbsCTAActive === undefined) {
		// Set to false to disable Method Helpfulness per-method CTA
		// Alternatively, this variable can be set elsewhere with more complex
		// logic if necessary.
		WH.methodThumbsCTAActive = true;
	}

	if (WH.displayMethodHelpfulness) {
		WH.injectMethodThumbs();
	}

	WH.loadPrintModule();
});

// strips step text of extra the text from script tags
function stripScripts(s) {
	var div = $('<div>');
	div.innerHTML = s;
	var scripts = div.find('script');
	var i = scripts.length;

	while (i-- > 0) {
	  scripts[i].parentNode.removeChild(scripts[i]);
	}

	var noscripts = div.find('noscript');
	i = noscripts.length;

	while (i-- > 0) {
	  noscripts[i].parentNode.removeChild(noscripts[i]);
	}

	var rptimg = div.find('rpt_img');
	i = rptimg.length;
	while (i-- > 0) {
		rptimg[i].parentNode.removeChild(rptimg[i]);
	}

	return div.innerHTML;
}

// switch class for given element
function switchClass(elem, currentClass, replacementClass) {
	$(elem).removeClass(currentClass).addClass(replacementClass);
}

// find all of buttons of current class and switches out their class and text with their replacements
function switchButtons(currentClass, replacementClass, replacementText) {
	var buttons = $('.' + currentClass);
	for (i = 0; i < buttons.length; i++) {
		buttons[i].innerHTML = replacementText;
		switchClass(buttons[i], currentClass, replacementClass);
	}
}

//callback function to reset pause button to '❚► Listen to this step' when text is finished being spoken
function voiceEndCallback() {
	switchButtons('pauseSpeech', 'speakButton', "❚► Listen to this step");
}

//initializes text to speech. Adds buttons to end of each step
function initTextToSpeech() {
	responsiveVoice.setDefaultVoice('UK English Female');
	responsiveVoice.speak('');
	var speakButtons = $('.step');
	for (i = 0; i < speakButtons.length; i++) {
		var textForSpeech = WH.prepareTextForSpeech(speakButtons[i].innerHTML);
		var start = '<button class=\'button speakButton\' onClick="WH.speakStep(this, \'' + textForSpeech +'\');">❚► Listen to this step</button>';
		$(speakButtons[i]).append('<div><br>' + start + '</div');
	}
}

WH.prepareTextForSpeech = function(textForSpeech) {
	var textForSpeech = stripScripts(textForSpeech);

	//removes html tags
	textForSpeech = textForSpeech.replace(/<\/?[^>]+(>|$)/g, '');

	//removes new lines and extra white space so it fits properly in function call
	textForSpeech = textForSpeech.replace(/(\r\n|\n|\r)/gm, '');

	//strips text of reference tags like [1] so they aren't read aloud.
	textForSpeech = textForSpeech.replace(/ *\[[^\]]*]/g, '');

	//[sc] computer voice sounds like an idiot when saying things with \' in it
	//replaces apostrophes with \' so the the string can be made without it prematurely closing it.
	// textForSpeech = textForSpeech.replace(/'/g, "\\'");

	//removes double quote marks because they will prematurely close out function in html. ex: onClick='function(here is something ' quoted text class='button'
	textForSpeech = textForSpeech.replace(/"/g, '');

	return textForSpeech;
}

//loads ResponsiveVoice script and adds 'Listen to this step' button on each step if they have a Text_to_Speech div
WH.enableTextToSpeech = function() {
	if ( $('.Text_To_Speech').length ) {
		if ($.isReady) {
			initTextToSpeech();
		} else {
			responsiveVoice.addEventListener('OnReady', initTextToSpeech);
		}
	}
};

// upon click, pause, play, or resume voice playback depending upon the current text of the button
WH.speakStep = function (elem, stepText) {
	var classList = $(elem).attr('class').split(/\s+/);
	$.each(classList, function(index, curClass) {
		if (curClass === 'pauseSpeech') {
			elem.innerHTML = "❚► Resume this Step";
			switchClass(elem, 'pauseSpeech', 'resumeSpeech');
			responsiveVoice.pause();
		} else if (curClass === 'speakButton') {
			switchButtons('pauseSpeech', 'speakButton', "❚► Listen to this step");
			switchButtons('resumeSpeech', 'speakButton', "❚► Listen to this step");

			var parameters = { onend: voiceEndCallback};
			responsiveVoice.speak(stepText, 'UK English Female', parameters);

			elem.innerHTML = "❚❚ Pause this step";
			switchClass(elem, 'speakButton', 'pauseSpeech');
		} else if (curClass === 'resumeSpeech') {
			elem.innerHTML = "❚❚ Pause this step";
			switchClass(elem, 'resumeSpeech', 'pauseSpeech');
			responsiveVoice.resume();
		}
	});
};


/**
 * Display a notice if we detect an ad blocker. Adapted from:
 * https://www.christianheilmann.com/2015/12/25/detecting-adblock-without-an-extra-http-overhead/
 * https://marthijnhoiting.com/detect-if-someone-is-blocking-google-analytics-or-google-tag-manager/
 * http://www.detectadblock.com/
 */
WH.loadAdblockNotice = function() {
	// Check to see if adblock notice is loaded in html.
	// If it isn't, it's not a target page
	if (!$('#ab_notice').length) return;

	// Only for anons
	if (mw.user.getId() === 0) {
		var test = document.createElement('div');
		test.innerHTML = '&nbsp;';
		test.className = 'adsbox';
		document.body.appendChild(test);
		window.setTimeout(function() {
			// testing offsetHeight as a proxy for adBlock
			// testing of existing of element id for safari content blocking (and others)
			// testing existence of ga as a proxy for detecting ghostery and ublock
			if (test.offsetHeight === 0 || !document.getElementById('vDxPZmfJyISu') || !(window.ga && ga.create)) {
				$('#ab_notice').show();
				$('.ad_label_method').hide();
			}
			test.remove();
		}, 100);
	}
};

function onLoadWikihowCommonBottom() {
	WH.loadAdblockNotice();
}

$(window).load(onLoadWikihowCommonBottom);

}(mediaWiki, jQuery));
