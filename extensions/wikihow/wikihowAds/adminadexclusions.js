$('document').ready(function() {
	'use strict';

	$(document).on('click', '#adexclusion_list', function(e) {
		e.preventDefault();
		if (confirm('Are you sure you want to delete all titles from all languages from the ad exclusion table?')) {
			$.ajax({
				url: '/Special:AdminAdExclusions',
				dataType: 'json',
				data: {
					action: 'delete'
				}
			});
		}
	});

	$('#adexclusions').submit(function(e) {
		e.preventDefault();

		if ($('#adexclusions input').hasClass('disabled'))
			return;

		var urls = $('#urls').val().trim();

		if (urls === '') {
			alert('You must enter urls');
			return;
		}

		$('#adexclusions input').addClass('disabled');
		$('#adexclusions_results').html('');
		$('#adexclusions_purging').html('');

		$.ajax({
			url: '/Special:AdminAdExclusions',
			dataType: 'json',
			data: {
				urls: urls,
				submitted: true
			},
			success: function(data) {
				$('#adexclusions input').removeClass('disabled');

				if (data.success) {
					$('#adexclusions_results').html('• The urls have been added to the exclusion list.');
				} else {
					var results = '• We were not able to process the following urls:<br />';
					for (var i = 0; i < data.errors.length; i++) {
						results += data.errors[i] + '<br />';
					}

					$('#adexclusions_results').html(results);
				}
				purgeCache(data);
			}
		});

		function purgeCache(data) {
			var count = { ok: 0, error: 0, ajax: data.articleGroups.length };
			if (!count.ajax) return; // keeps track of remaining AJAX calls (one per lang)

			$('#adexclusions_purging').html('• Purging article cache...');

			$.each(data.articleGroups, function(idx, group) {
				$.ajax({
					url: group.apiUrl,
					type: 'POST',
					dataType: 'json',
					xhrFields: { withCredentials: true },
					data: {
						origin: window.location.origin,
						format: 'json',
						action: 'purge',
						pageids: group.articleIds.join('|')
					}
				}).done(function(data) {
					$.each(data.purge, function(idx, apiResult) {
						if (apiResult.hasOwnProperty('purged')) {
							count.ok += 1;
							console.log("Purged: (" + group.langCode + ")", apiResult.title);
						} else {
							count.error += 1;
							console.log("Failed to purge: (" + group.langCode + ")", apiResult.pageid);
						}
					});
				}).always(function() {
					if ((count.ajax -= 1) === 0) { // All AJAX requests are complete
						var msg = "• Purged " + count.ok + " articles across " + data.articleGroups.length + " languages.";
						if (count.error) {
							msg += " (There were " + count.error + " errors. More details in the console)";
						}
						$('#adexclusions_purging').html(msg);
					}
				});

			});
		}

	});
});
