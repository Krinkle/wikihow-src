(function($, mw) {
	window.WH = window.WH || {};
	window.WH.LoginPopin = {

		popModal: function () {
			var url = "/extensions/wikihow/common/jquery.simplemodal.1.4.4.min.js";
			$.getScript(url, function() {
				$.get("/Special:BuildWikihowModal?modal=login&returnto="+wgTitle.replace(/ /g,'-'), function(data) {
					$.modal(data, {
						zIndex: 100000007,
						maxWidth: 300,
						minWidth: 300,
						overlayCss: { "background-color": "#000" }
					});
					WH.LoginPopin.getready();
				});
			});
		},

		getready: function() {
			$('#wh_modal_close').click(function() {
				$.modal.close();
			});

			$('.userlogin #wpName1').val(wfMsg('usernameoremail'))
				.css('color','#ABABAB')
				.click(function() {
					if ($(this).val() == wfMsg('usernameoremail')) {
						$(this).val(''); //clear field
						$(this).css('color','#333'); //change font color
					}
				});

			//switch to text so we can display "Password"
			if (!($.browser.msie && $.browser.version <= 8.0)) {
				if ($('.userlogin #wpPassword1').get(0)) $('.userlogin #wpPassword1').get(0).type = 'text';
			}

			$('.userlogin #wpPassword1').val(wfMsg('password'))
				.css('color','#ABABAB')
				.focus(function() {
					if ($(this).val() == wfMsg('password')) {
						$(this).val('');
						$(this).css('color','#333'); //change font color
						$(this).get(0).type = 'password'; //switch to dots
					}
				});

			$('#wpName1').blur();
		}

	}

	$(document).ready( function() {
		WH.LoginPopin.popModal();
	} );

})(jQuery, mw);
