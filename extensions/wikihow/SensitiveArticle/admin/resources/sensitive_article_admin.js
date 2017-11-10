(function($, mw)
{
	'use strict';

	$(document).ready(function()
	{
		var $div = $('#sensitive_article_admin');

		// A "Save" button was clicked
		$div.on('click', 'button.save_btn', function()
		{
			var $tr = $(this).closest('tr');
			$tr.find('button').attr('disabled', true);

			$.post('/Special:SensitiveArticleAdmin', {
				action: 'upsert',
				id: $tr.data('id'),
				name: $tr.find(':text').val(),
				enabled: $tr.find(':checkbox').prop('checked')
			})
			.done(function(data) {
				$div.html(data);
			});
		});

		// A checkbox or input field changed
		$div.on('change keyup', 'td > input', function()
		{
			var $tr = $(this).closest('tr');
			var inputIsEmpty = $tr.find(':text').val().trim() === '';
			$tr.find('button').prop('disabled', inputIsEmpty);
		});

	});

}(jQuery, mediaWiki));
