(function ($) {
	'use strict';

	window.WH = WH || {};

	window.WH.PrintView = {
		popModal: function () {
			$.get('/Special:BuildWikihowModal?modal=printview', function(data) {
				$.modal(data, {
					zIndex: 100000007,
					maxWidth: 360,
					minWidth: 360,
					overlayCss: { "background-color": "#000" }
				});

				WH.PrintView.prep();

				console.log('wut wut');
			});
		},

		prep: function () {
			$('#wh_modal_close').click(function () {
				$.modal.close()
				return false;
			});

			$('#wh_modal_btn_text_only').click(function () {
				window.location.href = '/' + wgPageName + '?printable=yes';
				return false;
			});

			$('#wh_modal_btn_incl_imgs').click(function () {
				window.location.href = '/' + wgPageName + '?printable=yes&images=1';
				return false;
			});
		}
	};
}(jQuery));

