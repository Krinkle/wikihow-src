WH.mobileads = (function () {
	var TOP_MENU_HEIGHT = 80;
	var adData;
	var scrollHandler;
	var scrollHandlerRegistered = false;
	var bodyAds = [];

	/**
	 * gets the ad data from json written to the page
	 * @return {json data} the json data which is the ad data
	 */
	function getAdData() {
		var el = document.getElementById("wh_ad_data");
		var data = JSON.parse(el.innerHTML);
		return data;
	}

	/**
	 * calculates the ad slot based on ad position and ad size
	 * @param {string} adPosition - either intro, method, related or footer
	 * @return {string} the list of channels as a string for adsense
	 */
	function getAdSlot(adPosition) {
		if (window.isBig) {
			slot = adData.slots.large[adPosition];
		} else {
			slot = adData.slots.small[adPosition];
		}
		return slot;
	}

	function getMaxNumAds(adPosition) {
		if (adPosition == 'method') {
			return 3;
		}

		if (adPosition == 'related' && window.isBig) {
			return 3;
		}

		return 1;
	}

	/**
	 * calculates the channels for the given ad position based on
	 * the ad position and other global variables such as screen size
	 * @param {string} type - either intro, method, or related for now
	 * @return {string} the list of channels as a string for adsense
	 */
	function getAdChannels(type, target) {
		var channels = adData.channels.base;

		var adPosition = type;

		if (target == "wh_ad_method1") {
			channels += "+4557351379";
		}

		// special channel for old android
		if (window.isOldAndroid) {
			channels += "+8151239771";
		}

		// special channel for very large screens
		if (window.isBig && (screen.width < 738 || (window.isLandscape && screen.height < 738))) {
			channels +=  "+7355504173";
		}

		if (!window.isBig) {
			channels += adData.channels.small[adPosition];
		}

		if (window.intlAds == true) {
			channels = "";
		}

		return channels;
	}

	function getIntroAdWidth(type) {
		var width = document.documentElement.clientWidth;

		// 320 or more is ideal ad width, 352 is 320 + 16 padding each side
		if (document.documentElement.clientWidth <= 352) {
			width = document.documentElement.clientWidth;
			var ad1 = document.getElementById("wh_ad_intro");
			var curClass = ad1.className;
			ad1.className = curClass + " wh_ad1_full";
			return width;
		}

		if (window.isBig) {
			width = width - 95;
		} else {
			width = width - 30;
		}

		return width;
	}

	function getAdWidth(type) {

		if (isOldAndroid && !window.isBig) {
			return 250;
		}

		var width = document.documentElement.clientWidth;

		if (type == 'intro') {
			width = getIntroAdWidth(type);
		}

		if (type == 'method') {
			width = width - 20;
		}

		if (type == 'related') {
			width = width - 14;
		}

		if (type == 'footer') {
			width = width;
		}

		return width;
	}

	function getAdHeight(type) {
		var height = 0;
		if (type == 'intro') {
			height = 120;
		} else if (type == 'method') {
			if (isBig) {
				height = 300;
			} else {
				height = 250;
			}
		} else if (type == 'related') {
			height = 280;
		} else if (type == 'footer') {
			height = 100;
		}
		return height;
	}

	function getAdCss(type) {
		var width = getAdWidth(type);
		var height = getAdHeight(type);
		var css = 'display:inline-block;width:'+width+'px;height:'+height+'px;';
		return css;
	}

	function insertAd(ad) {
		var client = "ca-pub-9543332082073187";
		var i = window.document.createElement('ins');
		i.setAttribute('data-ad-client', client);
        var adType = ad.type;
        var slot = getAdSlot(adType);
		i.setAttribute('data-ad-slot', slot);
		i.setAttribute('class', 'adsbygoogle');
		var css = getAdCss(ad.type);
		i.style.cssText = css;
		window.document.getElementById(ad.target).appendChild(i);
	}
	/**
	 * updates the target <ins> element with the ad channels
	 * then calls the google js function to load the ad
	 * @param {string} type - the ad type
	 */
	function loadAd(ad) {
		// first create and add the ins element
		insertAd(ad);
		var chans = getAdChannels(ad.type, ad.target);
		var maxNumAds = getMaxNumAds(ad.type);

		(adsbygoogle = window.adsbygoogle || []).push({
			params: {
				google_max_num_ads: maxNumAds,
				google_ad_region: "test",
				google_override_format: true,
				google_ad_channel: chans
			}
		});
	}

	function adTestingActive() {
		return true;
	}

	function getAdTestGroup(split) {
		var type = null;

		// check if ad testing is on
		if (adTestingActive() == false) {
			return type;
		}

		var r = Math.random();

		if (r > split) {
			type = 1;
		} else {
			type = 0;
		}

		return type;
	}

	function isInViewport(rect, viewportHeight) {
		var screenTop = TOP_MENU_HEIGHT;

		var offset = viewportHeight * 1.25;
		screenTop = 0 - offset;
		viewportHeight = viewportHeight + offset;
		if (rect.top >= screenTop && rect.top <= viewportHeight) {
			return true;
		}
		if (rect.bottom >= screenTop && rect.bottom <= viewportHeight) {
			return true;
		}
		if (rect.top <= screenTop && rect.bottom >= viewportHeight) {
			return true;
		}
		return false;
	}

	function updateVisibility() {
		var unloadedAds = false;
		var viewportHeight = (window.innerHeight || document.documentElement.clientHeight);
		for (var i = 0; i < bodyAds.length; i++) {
			var ad = bodyAds[i];
			if (ad.isLoaded == false) {
				var rect = ad.adElement.getBoundingClientRect();
				// special case, we can specify a different element to trigger ad loading
				if (ad.viewTargetElement) {
					rect = ad.viewTargetElement.getBoundingClientRect();
				}
				if (isInViewport(rect, viewportHeight)) {
					ad.load();
				}
				unloadedAds = true;;
			}
		}
		if (!unloadedAds) {
			window.removeEventListener('scroll', scrollHandler);
			scrollHandlerRegistered = false;
		}
	}
	function registerScrollHandler() {
		if (scrollHandlerRegistered) {
			return;
		}
		scrollHandler = WH.shared.throttle(updateVisibility, 500);
		window.addEventListener('scroll', scrollHandler);
		scrollHandlerRegistered = true;
	}

	// any things to change when the page is loaded (like for ab test cleanup)
	function docLoad() {
		updateVisibility();
	}

	function start() {
		// set up ab testing data
		adData = getAdData();

		document.addEventListener('DOMContentLoaded', function() {docLoad();}, false);
		registerScrollHandler();
	};

	function Ad(target) {
		this.target = target;
		this.adElement = document.getElementById(target);
		this.type = this.adElement.getAttribute('data-type');
		this.isLoaded = false;
		this.scrollLoad = this.adElement.getAttribute('data-scroll-load') == 1;
		this.loadClass = this.adElement.getAttribute('data-load-class');
		this.stickyFooter = this.adElement.getAttribute('data-sticky-footer') == 1;
		this.viewTargetElement = null;
		if (this.stickyFooter) {
			var sections = document.getElementsByClassName('section');
			var stepsPassed = false;
			for (var i = 0; i < sections.length; i++) {
				var section = sections.item(i);
				if (section.classList.contains('steps')) {
					stepsPassed = true;
					continue;
				}
				if (stepsPassed) {
					this.viewTargetElement = section;
					break;
				}
			}
		}
		this.load = function() {
			loadAd(this);
			if (this.loadClass) {
				var curClass = this.adElement.className;
				this.adElement.className = curClass + " " + this.loadClass;
			}
			this.isLoaded = true;
		}
		if (this.scrollLoad == false) {
			this.load();
		}
	}

	function add(target) {
		bodyAds.push(new Ad(target));
		registerScrollHandler();
	}

	return {
		'start':start,
		'add' : add,
	};
})();
WH.mobileads.start();
