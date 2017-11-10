(function(mw, $) {
'use strict';

/* Common js for both our desktop and mobile sites
 * it is now loaded by the resource loader in it's own module
 * it is loaded before the javascript in whjs (groups config)
 */
window.WH = window.WH || {};
WH.isMobileDomain = window.location.hostname.match(/\bm\./) !== null;
WH.isAndroidAppRequest = window.location.search.match(/wh_an=1/) !== null;

WH.ga = {};

/**
 * Used to track events. If Google Analytics is not initialized when this function is called,
 * then no event will be sent.
 *
 * @param String  category   The name you supply for the group of objects you want to track.
 * @param String  action     Commonly used to define the type of user interaction for the web object.
 * @param String  label      An optional string to provide additional dimensions to the event data.
 * @param String  value      An integer that you can use to provide numerical data about the user event.
 * @param boolean nonInter   When true, the event hit will not be used in bounce-rate calculation.
 */
WH.ga.sendEvent = function(category, action, label, value, nonInter, hitCallbackFunction) {
	if (typeof(ga) == typeof(Function)) {
		var fieldName = {
			nonInteraction: nonInter ? 1 : 0
		};
		if (hitCallbackFunction) {
			fieldName.hitCallback = hitCallbackFunction;
		}
		if (typeof value != 'undefined' && value !== null) {
			ga('send', 'event', category, action, label, value, fieldName);
		} else if (typeof(label) != 'undefined' && label !== null) {
			ga('send', 'event', category, action, label, fieldName);
		} else {
			ga('send', 'event', category, action, fieldName);
		}
	}
};

/**
 * Load Google Analytics
 * @param  String siteVersion Either 'desktop' or 'mobile'
 */
WH.ga.loadGoogleAnalytics = function(siteVersion, propertyId, extraPropertyIds) {
	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m);
	})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

	// Do the main GA ping
	ga('create', propertyId, 'auto', { 'allowLinker': true });
	ga('linker:autoLink', [/^.*wikihow\.(com|cz|it|jp|vn)$/]);
	ga('send', 'pageview');

	// ... and extra events if we got any
	for (var id in extraPropertyIds) {
		var name = extraPropertyIds[id];

		ga('create', {
			trackingId: id,
			cookieDomain: 'auto',
			name: name,
			allowLinker: true
		});

		ga(name + '.send', 'pageview');
	}

	//var load_t_delta = (new Date()).getTime() - WH.timeStart;
	//setTimeout('ga("send","event","Adjusted Bounce","60 seconds on page (' + siteVersion + ')")',
		//Math.max(60000 - load_t_delta, 0));
	//var pv_user_type = mw.config.get('wgUserName') === null ? 'anon' : 'logged_in';
	//ga('send', 'event', 'pvs-' + siteVersion[0], pv_user_type, {
		//'nonInteraction': 1
	//});

	WH.ga.processPendingEvents();
};

/**
 * Process pending Google Analytics events
 */
WH.ga.processPendingEvents = function() {
	// Process pending analytics events
	var cookieName = mw.config.get('wgCookiePrefix') + 'GAPendingEvents';
	var eventsJson = $.cookie(cookieName);
	if (eventsJson) {
		try {
			var events = JSON.parse(eventsJson);
			for (var i = 0; i < events.length; i++) {
				var e = events[i];
				WH.ga.sendEvent(e.category, e.action, e.label, e.value, e.nonInter);
			}
		} catch (exception) {
			if (typeof console.log != 'undefined') {
				console.log(exception);
			}
		}
		$.removeCookie(cookieName, {
			'path': '/',
			'domain': '.' + mw.config.get('wgCookieDomain')
		});
	}
};

// TODO: (Reuben, Jan 2017) maybe this method should be in wikihow_common_bottom.js.
// Doesn't seem like it really needs to be here, since it's only used by a couple
// randomish tools.
WH.xss = {
	addToken: function() {
		$.ajaxSetup({
			beforeSend: function(xhr /*, settings*/) {
				xhr.setRequestHeader('X-CSRF-TOKEN', mw.user.sessionId());
			}
		});
	}
};

// NOTE: using this method is deprecated (March 2017)
// You should call WH.maEvent directly now. We no longer use the
// old "whEvent" system, so this method just called into machinify.
WH.whEvent = function(category, action, group, label, version, fromMAEvent) {
	var c, a, g, l, v;

	// required parameters
	if (typeof category != 'undefined' && category !== null) {
		c = category;
	} else {
		return;
	}

	if (typeof action != 'undefined' && action !== null) {
		a = action;
	} else {
		return;
	}

	// optional parameters
	g = group || '';
	l = label || '';
	v = version || '';

	WH.maEvent(action, {category: c, group: g, label: l, version: v}, true);
};

// Machinify event system
// eventName should be a string identifying the event.
// eventProps should be a dict with the data to log set in it.
// noLogToWHEvent is an ignored/unused param
WH.maEventInitialized = false;
WH.maEvent = function(eventName, eventProps, noLogToWHEvent) {
	if (typeof MachinifyAPI == 'undefined') {
		if (typeof console != 'undefined') {
			console.log('error: Machinify API called before it was initialized! (Maybe a loading order issue?) maEvent=' + eventName);
		}
		return;
	}

	if (!WH.maEventInitialized) {
		// wikiHow Machinify key
		MachinifyAPI.setApiKey('69Me1Jf95c9f9604b77bd0cb6cedc346');

		var deviceID = $.cookie('whv');
		if (deviceID) {
			MachinifyAPI.setDeviceID(deviceID);
		}

		var platform;
		if (WH.isAndroidAppRequest) {
			platform = 'android_app';
		} else if (WH.isMobileDomain) {
			platform = 'mobile_web';
		} else {
			platform = 'desktop';
		}

		MachinifyAPI.setDeviceProperties( {platform: platform} );
		MachinifyAPI.delayAllEvents(); // send events synchronously (-ish).
		MachinifyAPI.setUserProperties(); // no one is signed in.
		// MachinifyAPI.sendEvent('launch'); // send a launch event; no special properties.

		WH.maEventInitialized = true;
	}

	if (typeof eventProps != 'object') {
		eventProps = {};
	}
	var isDev = (window.location.href.indexOf(".wikidogs.") >= 0) ? 1 : 0;
	eventProps.isDev = isDev;
	var uid = mw.config.get('wgUserId');
	uid = uid || 0; // user id
	eventProps.anon = (uid <= 0);
	var u = mw.config.get('wgUserName');
	u = u || ''; // user name
	if (u) eventProps.username = u;

	if (isDev) {
		console.log(eventName);
		console.log(eventProps);
	}

	MachinifyAPI.sendEvent(eventName, eventProps);
};

/**
 * Use this method to add your own throttled scroll event handler
 */
WH.addThrottledScrollHandler = function(handler) {
	WH.scrollHandlers = WH.scrollHandlers || [];
	WH.scrollHandlers.push(handler);
};

/**
 * Rate limiting adopted from:
 *   http://stackoverflow.com/a/9617517
 * additional reference here:
 *   setTimeout example: https://developer.mozilla.org/en-US/docs/Web/Events/resize#Example
 *   requestAnimationFrame: https://developer.mozilla.org/en-US/docs/Web/Events/scroll
 */
var scrollTimer = null, lastScrollFireTime = 0;
function handleScrollEvent() {
	var now = +(new Date());
	var MIN_SCROLL_TIME = 50;

	var execHandlers = function () {
		if (typeof WH.scrollHandlers != 'undefined') {
			for (var i = 0; i < WH.scrollHandlers.length; i++) {
				try {
					(WH.scrollHandlers[i])();
				} catch(e) {
					//console.log('handleScrollEvent exception', e);
				}
			}
		}
	};

	if (!scrollTimer) {
		if (now - lastScrollFireTime < 3 * MIN_SCROLL_TIME) {
			execHandlers();
			lastScrollFireTime = now;
		}

		scrollTimer = setTimeout(function () {
			scrollTimer = null;
			lastScrollFireTime = +(new Date());
			execHandlers();
		}, MIN_SCROLL_TIME);
	}
}

/**
 * We throttle scroll events because many, many events can be generated, and
 * it's safe to skip ones that happen in quick succession.
 *
 * See also the mobile code we use to throttle this, in scroll_handler.js
 *
 * Reference of this throttling code:
 *   https://developer.mozilla.org/en-US/docs/Web/Events/resize#Example
 * Mobile rate limiting was originally adopted from, but no longer:
 *   http://stackoverflow.com/a/9617517
 * additional reference here- same thing using requestAnimationFrame:
 *   https://developer.mozilla.org/en-US/docs/Web/Events/scroll
 */
/* NOTE: I experimented with this version but decided the mobile code performed
 * a little better, so using that instead.
var scrollTimeout = null;
function handleScrollEvent() {
	// ignore resize events as long as an actualScrollHandler execution is waiting to execute
	if ( !scrollTimeout ) {
		scrollTimeout = setTimeout(function() {
			scrollTimeout = null;

			if (typeof WH.scrollHandlers != 'undefined') {
				for (var i = 0; i < WH.scrollHandlers.length; i++) {
					(WH.scrollHandlers[i])();
				}
			}

			// The actualResizeHandler will execute at a rate of 15fps, since 1000ms / 66ms = 15fps
		}, 66);
	}
}
*/
//disable dragging images off the page
$('img').on('dragstart', false);

// set up scroll event handlers for normal platforms and iOS devices
var $jqWindow = $(window);
$jqWindow.scroll(handleScrollEvent);

var isIOS = navigator.userAgent.match(/(iPod|iPhone|iPad)/gi) !== null;
if (isIOS) {
	$jqWindow.bind('touchmove', handleScrollEvent);
	$jqWindow.bind('touchstart', handleScrollEvent);
	$jqWindow.bind('touchend', handleScrollEvent);
}

}(mediaWiki, jQuery));
