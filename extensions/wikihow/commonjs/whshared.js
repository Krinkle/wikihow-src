/*jslint browser: true, white:true, sloppy:true*/
WH.shared = (function () {
    'use strict';

    var TOP_MENU_HEIGHT = 52,
    scrollLoadItems = [],
    scrollLoadingHandler,
    autoPlayVideo,
    autoLoad = false,
    getNow = Date.now || function() { return new Date().getTime(); },
	nv = navigator.userAgent,
	webpSupport = nv.match(/Linux/) && nv.match(/Android/) ||
		nv.match(/Opera/) ||
		nv.match(/Chrome/) && !nv.match(/Edge/),
    videoRoot = 'http://vid1.whstatic.com';
    if (window.location.protocol === 'https:') {
        // set videoRoot to more expensive cloudfront bucket if on https
        videoRoot = '//d5kh2btv85w9n.cloudfront.net';
    }

    function throttle(func, wait) {
        var timeout = null;
        var previous = 0;
        var later = function() {
            previous = 0;
            timeout = null;
            func.apply();
        };
        var throttled = function() {
            var now = getNow();
            var remaining = wait - (now - previous);
            if (remaining <= 0 || remaining > wait) {
                if (timeout) {
                    clearTimeout(timeout);
                    timeout = null;
                }
                previous = now;
                func.apply();
            } else if (!timeout) {
                timeout = setTimeout(later, remaining);
            }
        };
        return throttled;
    }

    /*
     *  check if either the top or the bottom of the element is in view
     *  taking into account header + 50% of the screen size to load before you actually see things
     *  @param rect - the result of calling  of getBoundingClientRect() on the target element
     *  @param viewportHeight - the current viewport height
     */
    function isInViewport(rect, viewportHeight) {
        var screenTop = TOP_MENU_HEIGHT,
            offset = viewportHeight * 2;
        screenTop -= offset;
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

    function getBoundingRect(item) {
        var rect = item.element.getBoundingClientRect();
        var result = {top:rect.top, bottom:rect.bottom};
        var diff = item.lastTop - rect.top;
        if (diff == 0) {
            if (window.scrollY != item.lastY) {
                result.top = result.top - window.scrollY;
                result.bottom = result.bottom - window.scrollY;
            }
        }
        item.lastTop = rect.top;
        item.lastY = window.scrollY;

        return result;
    }

    function updateVisibility() {
        var unloadedItems = false,
            viewportHeight = (window.innerHeight || document.documentElement.clientHeight);

        for (var i = 0; i < scrollLoadItems.length; i+=1) {
            var item = scrollLoadItems[i];
            if (item.isLoaded) {
                continue;
            }
            var rect = getBoundingRect(item);
            if (isInViewport(rect, viewportHeight)) {
                item.load();
            }
            unloadedItems = true;
        }

        if (!unloadedItems && scrollLoadItems.length) {
            window.removeEventListener('scroll', scrollLoadingHandler);
        }
    }

    function supportsAutoplay() {
        var el = window.document.createElement('video');
        el.setAttribute('muted', '');
        el.setAttribute('playsinline', '');
        el.setAttribute('webkit-playsinline', '');
        el.muted = true;
        el.playsinline = true;
        el.webkitPlaysinline = true;
        el.setAttribute('height', '0');
        el.setAttribute('width', '0');
        el.style.position = 'fixed';
        el.style.top = 0;
        el.style.width = 0;
        el.style.height = 0;
        el.style.opacity = 0;

        try {
            var promise = el.play();
            if (promise && promise.catch) {
                promise.then(function() {
                    // do nothing
                }).catch( function() {
                    // do nothing
                });
            }
        } catch(ignore) {
            // ignore errors since they are allowed
        }
        return !el.paused;
    }

    function setupLoader(item) {
        // add event listener if this is supported
        if (document.addEventListener && item.finishedLoadingEvent) {
            if (item.alt) {
                item.element.alt = item.alt;
            }
            var loader = document.createElement("div");
            loader.className = 'loader';
            for (var i = 0; i < 3; i++) {
                var loaderDot = document.createElement("div");
                loaderDot.className = 'loader-dot';
                loader.appendChild(loaderDot);
            }

            var loadingContainer = document.createElement("div");
            loadingContainer.className = 'loading-container';
            loadingContainer.appendChild(loader);
            item.element.parentElement.appendChild(loadingContainer);
            item.element.addEventListener(item.finishedLoadingEvent, function() {
				this.parentElement.removeChild(loadingContainer);
            } );
        }
    }

    function ScrollLoadElement(element) {
        this.lastTop = element.getBoundingClientRect().top;
        this.lastY = window.scrollY;
        this.isLoaded = false;
        this.isVisible = false;
        this.element = element;
        this.load = function() {};
    }

    function ScrollLoadImage(element) {
        ScrollLoadElement.call(this, element);
        this.alt = element.alt;
        element.alt = '';
        this.finishedLoadingEvent = 'load';
        this.src = element.getAttribute('data-src');
		if (webpSupport && this.src && this.src.split('.').pop() == 'jpg' && this.src.match(/images(_[a-z]{2})?\/thumb\//) && !this.src.match(/(\.[a-zA-Z]+){2}$/) ) {
			this.src = this.src + '.webp';
		}

        this.load = function() {
            this.element.setAttribute('src', this.src);
            this.isLoaded = true;
            setupLoader(this);
        };
    }

    function ScrollLoadVideo(element) {
        ScrollLoadElement.call(this, element);
        this.finishedLoadingEvent = 'loadeddata';
        this.isPlaying = false;
        this.src = videoRoot + this.element.getAttribute('data-src');
        this.poster = this.element.getAttribute('data-poster');
		if (this.poster && this.poster.split('.').pop() == 'jpg' &&  webpSupport) {
			this.poster = this.poster + '.webp';
		}
        this.noAutoplay = this.element.getAttribute('data-noautoplay');
        this.play = function() {
            this.element.play();
            this.isPlaying = true;
        };
        this.pause = function() {
            this.element.pause();
            this.isPlaying = false;
        };
        this.load = function() {
            this.element.setAttribute('poster', this.poster);
            if (autoPlayVideo && !this.noAutoplay && (window.WH.isMobile == 0 || window.wgIsMainPage === true)) {
                this.element.setAttribute('src', this.src);
                this.play();
			} else {
				this.finishedLoadingEvent = null;
			}
            this.isLoaded = true;
            if (!this.noAutoplay) {
                setupLoader(this);
            }
        };
    }

    function addScrollLoadItem(id) {
        var el = document.getElementById(id);
        var item = null;
        if (el.nodeName.toLowerCase() === 'img') {
            item = new ScrollLoadImage(el);
        } else if (el.nodeName.toLowerCase() === 'video') {
            item = new ScrollLoadVideo(el);
        }
        if (item) {
            scrollLoadItems.push(item);
        }

        // set padding top on the parent spacer element
        var width = el.getAttribute('data-width') || el.getAttribute('width');
        var height = el.getAttribute('data-height') || el.getAttribute('height');
		if (width > 0) {
			el.parentElement.style['paddingTop'] = ((height / width) * 100) + '%';
		}

		updateVisibility();
        if (autoLoad) {
            item.load();
        }
    }

    autoPlayVideo = supportsAutoplay();
    scrollLoadingHandler = throttle(updateVisibility, 500);
    if (window.addEventListener) {
        window.addEventListener('scroll', scrollLoadingHandler);
    } else {
        autoLoad = true;
    }

    return {
        'throttle' : throttle,
        'TOP_MENU_HEIGHT' : TOP_MENU_HEIGHT,
        'autoPlayVideo' : autoPlayVideo,
        'webpSupport' : webpSupport,
        'addScrollLoadItem' : addScrollLoadItem,
        'videoRoot' : videoRoot
    };

}());
