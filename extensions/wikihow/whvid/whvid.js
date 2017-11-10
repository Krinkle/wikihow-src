WH.video = (function () {
	'use strict';
	var videos = [];
	var VISIBILITY_PERCENT = 75;
	var TOP_MENU_HEIGHT = 80;
	var autoPlayVideo = false;
	var cdnRoot = 'http://vid1.whstatic.com';
	var mobile = false;
	var imageFallback = false;

	function visibilityChanged(video) {
		if (video.isVisible == true && video.isPlaying == false) {
			video.play();
		} else if (video.isPlaying == true) {
			video.pause();
		}
	}

	function updateItemVisibility(item) {
		var wasVisible = item.isVisible;
		var viewportHeight = (window.innerHeight || document.documentElement.clientHeight);
		var rect = item.element.getBoundingClientRect();

		if (!isInViewport(rect, viewportHeight, false)) {
			item.isVisible = false;
		} else {
			var visiblePercent = getVisibilityPercent(rect, viewportHeight) * 100;
			item.isVisible = visiblePercent >= VISIBILITY_PERCENT;
		}
		// check viewport size + additional 20% so we load before the video is in view
		if ( item.isLoaded == false && isInViewport(rect, viewportHeight, true)) {
			item.load();
		}
		if (item.isVisible != wasVisible && item.autoplay) {
			visibilityChanged(item);
		}
	}

	// this function is only called if we know the element is in view
	// therefore we can do fewer checks on the element in order to calculate
	// what percentage is visible
	// @param rect - the result of calling  of getBoundingClientRect() on the target element
	// @param viewportHeight - the current viewport height
	function getVisibilityPercent(rect, viewportHeight) {

		if (rect.height == 0) return 0;

		// if the element is larger than the viewport and the full thing is in view
		// we will call that 100% for purposes of playing the videos
		if (rect.top < TOP_MENU_HEIGHT && rect.bottom > viewportHeight) {
			return 1;
		}
		// if top is in view
		if (rect.top > TOP_MENU_HEIGHT && rect.top < viewportHeight) {
			if (rect.bottom < viewportHeight ) {
				// if bottom is also in view.. then the entire thing is in view
				return 1;
			} else {
				// bottom must be below the bottom of the viewport
				return (viewportHeight - rect.top) / rect.height;
			}
		} else if (rect.bottom > TOP_MENU_HEIGHT ) {
			// if the top is not in view .. then the bottom must be in view
			return (rect.bottom - TOP_MENU_HEIGHT)  / rect.height;
		}
	}

	// this is registered by the scroll handler
	function updateVisibility() {
		for (var i = 0; i < videos.length; i++ ) {
			updateItemVisibility(videos[i]);
		}
	}

	// check if either the top or the bottom of the video element is in view
	// taking into account the 40px header and 40px TOC
	// of loading the video, in which case we add 20% to the size of the viewport
	// so the videos load before you scroll to them
	// @param rect - the result of calling  of getBoundingClientRect() on the target element
	// @param viewportHeight - the current viewport height
	// @param forLoading - adds 20% to viewport size
	function isInViewport(rect, viewportHeight, forLoading) {
		var screenTop = TOP_MENU_HEIGHT;

		if (rect.height == 0) return false;

		if (forLoading) {
			var offset = viewportHeight * 0.2;
			screenTop = 0 - offset;
			viewportHeight = viewportHeight + offset;
		}
		if (rect.top >= screenTop && rect.top <= viewportHeight) {
			return true;
		}
		if (rect.bottom >= screenTop && rect.bottom <= viewportHeight) {
			return true;
		}
		return false;
	}

	function videoControlSetup(video) {
		if (video.playButton) {
			video.playButton.addEventListener('click', function() {
				video.toggle();
			});
			if (video.summaryVideo && "onloadstart" in window) {
				video.element.addEventListener( 'loadstart', function() {
					setTimeout(function(){
						if ( !video.isPlaying ) {
							video.playButton.style.visibility = 'visible';
						}
					}, 200);
				});
			} else {
				video.playButton.style.visibility = 'visible';
			}
		}
		if (video.summaryOutro) {
			video.element.addEventListener('ended', function() {
				video.element.load();
			});
		}
		video.element.addEventListener('play', function() {
			if (video.playButton) {
				video.playButton.style.visibility = 'hidden';
			}
			if (video.summaryOutro) {
				video.element.poster = video.summaryOutro;
			}
		});
		video.element.addEventListener('pause', function() {
			if (video.playButton) {
				video.playButton.style.visibility = 'visible';
			}
		});
		if (video.seek) {
			// Event listener for the seek bar
			video.seek.addEventListener("change", function() {
				// Calculate the new time
				var time = video.element.duration * (video.seek.value / 100);

				// Update the video time
				video.element.currentTime = time;
			});
			// Update the seek bar as the video plays
			video.element.addEventListener("timeupdate", function() {
				// Calculate the slider value
				var value = (100 / video.element.duration) * video.element.currentTime;

				// Update the slider value
				video.seek.value = value;
			});
			// Pause the video when the slider handle is being dragged
			video.seek.addEventListener("mousedown", function() {
				video.pause();
			});
			// Play the video when the slider handle is dropped
			video.seek.addEventListener("mouseup", function() {
				video.play();
			});
		}
		// Event listener for the volume bar
		if (video.volumeBar) {
			video.volumeBar.addEventListener("change", function() {
				  // Update the video volume
				  video.volume = volumeBar.value;
			});
		}
	}

	function Video(mVideo) {
		this.isLoaded = false;
		this.isVisible = false;
		this.isPlaying = false;
		this.pausedQueued = false;
		this.element = mVideo;
		this.summaryVideo = false;
		this.controls = null;
        this.poster = this.element.getAttribute('data-poster');
        this.summaryOutro = this.element.getAttribute('data-summary-outro');
		if (this.poster && this.poster.split('.').pop() == 'jpg' &&  WH.shared.webpSupport) {
			this.poster = this.poster + '.webp';
		}
		this.summaryVideo = this.element.getAttribute('data-summary') == 1;
		this.autoplay = !this.summaryVideo;
		for (var i = 0; i < this.element.parentNode.children.length; i++) {
			var el = this.element.parentNode.children[i];
			if (el.className == 'm-video-controls') {
				this.controls = el;
				for (var j = 0; j < this.controls.children.length; j++) {
					var child = this.controls.children[j];
					if (child.className == 'm-video-play') {
						this.playButton = child;
					} else if (child.className == 'm-video-play-old') {
						this.playButton = child;
					} else if (child.className == 'm-video-seek') {
						this.seek = child;
					} else if (child.className == 'm-video-volume') {
						this.volumeBar = child;
					}
				}
			}
		}
		this.play = function() {
			var video = this;
			this.playPromise = this.element.play();
			if (this.playPromise !== undefined) {
				this.playPromise.then(function(value) {
					video.isPlaying = true;
				}).catch(function() {});
			} else {
				this.isPlaying = true;
			}
			if (this.summaryVideo) {
				this.element.setAttribute('controls', 'true');
			}
		};
		this.pause = function() {
			var video = this;
			if (this.playPromise !== undefined && !this.pausedQueued) {
				this.pausedQueued = true;
				this.playPromise.then(function(value) {
					video.element.pause();
					video.pausedQueued = false;
					video.isPlaying = false;
				}).catch(function() {});
			} else {
				this.element.pause();
				this.isPlaying = false;
				this.pausedQueued = false;
			}
		};
		this.toggle = function() {
			if (!this.isLoaded) {
				this.load();
				if (this.summaryVideo) {
					//this.element.volume = "1";
					this.element.removeAttribute('muted');
				}
			}
			if (this.isPlaying) {
				this.pause();
			} else {
				this.play();
			}
		}
		this.load = function() {
			var videoUrl = cdnRoot + this.element.getAttribute('data-src');
			this.element.setAttribute('src', videoUrl);
			this.element.setAttribute('poster', this.poster);
			this.isLoaded = true;
		};

		var video = this;
		this.element.addEventListener('click', function() {
			video.toggle();
		});

		if (this.controls) {
			videoControlSetup(video);
		}
	}

	function Gif(mVideo) {
		this.isLoaded = false;
		this.isVisible = false;
		this.isPlaying = false;
		this.gifSrc = mVideo.getAttribute('data-gifsrc');
		this.gifFirstSrc = mVideo.getAttribute('data-giffirstsrc');
		this.play = function() {
			this.element.setAttribute('src', this.gifSrc);
			this.isPlaying = true;
		};
		this.pause = function() {
			// we could switch back to the static image for a fake pause effect
			// but for now do nothing
		};
		this.load = function() {
			// this will pre load the gif
			var image = new Image();
			image.src = this.gifSrc;
			this.isLoaded = true;
		}

		// set height of parent so no flicker when we replace the element
		mVideo.parentNode.parentNode.style.minHeight = mVideo.offsetHeight + "px";

		// create an img element to show the gif
		var image = window.document.createElement('img');
		image.setAttribute('class', 'whvgif whcdn');
		image.setAttribute('src', mVideo.getAttribute('data-giffirstsrc'));
		mVideo.parentNode.replaceChild(image, mVideo);
		this.element = image;
	}

	function start() {
		if (window.WH.isMobile) {
			mobile = true;
		}
        if (WH.shared) {
            autoPlayVideo = WH.shared.autoPlayVideo;
        }
		var isHTML5Video = (typeof(document.createElement('video').canPlayType) != 'undefined');
		if (!isHTML5Video) {
			imageFallback = false;
		}

		if (window.location.href.indexOf("gif=1") > 0) {
			autoPlayVideo = false;
		}
		if (window.location.protocol === 'https:') {
			// set cdnRoot to more expensive cloudfront bucket if on https
			cdnRoot = '//d5kh2btv85w9n.cloudfront.net';
		}
		// we can use the dev bucket for testing if the video is in the dev s3 account (uncommon)
		//cdnRoot= '//d2mnwthlgvr25v.cloudfront.net'
        if (WH.shared) {
            window.addEventListener('scroll', WH.shared.throttle(updateVisibility, 100));
        }
	}

	function add(mVideo) {
		var item = null;
		var summaryVideo = mVideo.getAttribute('data-summary') == 1;
		if (imageFallback) {
			var newId = "img-" + mVideo.id;
			var src = mVideo.getAttribute('poster');
			mVideo.parentElement.innerHTML = "<img id='" + newId + "' src='"+ src + "'></img>";
		} else if (autoPlayVideo || summaryVideo) {
			item = new Video(mVideo);
			videos.push(item);
			updateItemVisibility(item);
		} else {
			item = new Gif(mVideo);
			videos.push(item);
			updateItemVisibility(item);
		}
	}

	return {
		'start':start,
		'add': add,
	};
})();
WH.video.start();
