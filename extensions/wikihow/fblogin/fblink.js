(function($) {
	'use strict';

	if (typeof window.WH == 'undefined' || typeof window.WH.social == 'undefined') {
		return;
	}

	$('#fl_button_save').live('click', function(e) {
		e.preventDefault();
		window.WH.social.fb.doFBLogin(function(response) {
			var token = response.authResponse.accessToken;
			var data = {
				a: 'link',
				token: token,
				editToken: $("#edit_token").val()
			};

			$.ajax({
				type: 'POST',
				dataType: 'json',
				url: '/Special:FBLink',
				data: data
			})
			.done(function() {
				window.location.reload();
			})
			.fail(function(jqXHR) {
				var obj = JSON.parse(jqXHR.responseText);
				alert(obj.error);
			});

		});
	});

	$('.fl_button_cancel').live('click', function() {
		$('#dialog-box').dialog('close');
	});

}(jQuery));
