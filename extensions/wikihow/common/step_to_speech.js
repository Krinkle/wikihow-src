(function($) {
	"use strict";
	window.WH = window.WH || {};
	window.WH.StepToSpeech = {

		talking: false,

		speak: function() {
			WH.StepToSpeech.findTopStepInView(true);
		},

		isStepReadable: function() {
			return WH.StepToSpeech.findTopStepInView(false);
		},

		findTopStepInView: function(read) {
			var step = '';

			$('.steps_list_2 > li .step_num').each(function() {
				if (WH.StepToSpeech.inView(this)) {
					//first step in view!
					step = $(this).parent().find('.step');
					return false; //.each break
				}
				else if (this.getBoundingClientRect().top > WH.StepToSpeech.getViewTop()) {
					//previous step
					step = $(this).parent().prev().find('.step');
					return false; //.each break
				}
			});

			//just checking
			if (!read) return step.length;

			if (step.length > 0) {
				$(step).find('script').remove();
				var step_text = WH.prepareTextForSpeech($(step).text());
				WH.StepToSpeech.readTopStepInView(step_text);
			}
		},

		readTopStepInView: function(step_text) {
			responsiveVoice.speak(
				step_text,
				'UK English Female',
				{onstart: WH.StepToSpeech.talkingStart, onend: WH.StepToSpeech.talkingStop}
			);
		},

		talkingStart: function() {
			WH.StepToSpeech.talking = true;
		},

		talkingStop: function() {
			WH.StepToSpeech.talking = false;
		},

		pause: function() {
			responsiveVoice.pause();
		},

		resume: function() {
			responsiveVoice.resume();
		},

		//copied from defer.js & tweaked
		inView: function (el) {
			var box = el.getBoundingClientRect(),
				bottom = (window.innerHeight || document.documentElement.clientHeight);
			var offset = 80;
			return (box.bottom >= offset && box.top <= bottom);
		},

		getViewTop: function() {
			return (window.innerHeight || document.documentElement.clientHeight);
		}
	}

})($);