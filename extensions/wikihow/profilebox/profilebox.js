( function($, mw) {
	"use strict";
	window.WH = window.WH || {};
	window.WH.ProfileBox = {

		url_ajax: '/Special:ProfileBox?type=ajax&pagename=' + mw.config.get('wgPageName'),
		data: {},

		init: function() {
			this.addHandlers();
		},

		addHandlers: function() {
			$('#remove_user_page').click($.proxy(function() {
				this.removeUserPage();
				return false;
			},this));

			$('.view_toggle').on('click', function() {
				WH.ProfileBox.viewToggle(this);
				return false;
			});
		},

		removeUserPage: function() {
			var conf = confirm("Are you sure you want to permanently remove your "+mw.message('profilebox_name').text()+"?");
			if (conf == true) {
				var url = '/Special:ProfileBox?type=remove';

				$.get(url, function(data) {
					gatTrack("Profile","Remove_profile","Remove_profile");
					$('#profileBoxID').hide();
					$('#pb_aboutme').hide();
				});
			}
			return false;
		},

		viewToggle: function(obj) {
			var section = $(obj).parent().attr('id');
			var section_class = $(obj).hasClass('more') ? 'more' : 'less';
			var section_class_other = section_class == 'more' ? 'less' : 'more';
			var data = this.dataForSection(section, section_class);

			if (!data) {
				$.getJSON(
					this.url_ajax + '&element='+section+'_'+section_class,
					$.proxy(function(vars) {
						this.data[section][section_class] = vars;
						this.render(section, vars);
					},this)
				);
			}
			else {
				this.render(section, data);
			}

			//switch view more/less link
			$(obj).fadeOut(function() {
				$(this)
					.html(mw.message('pb-view'+section_class_other).text())
					.removeClass(section_class)
					.addClass(section_class_other)
					.fadeIn();
			});
		},

		dataForSection: function(section, section_class) {
			if (!this.data[section]) this.data[section] = {};
			if (!this.data[section][section_class]) this.data[section][section_class] = '';
			return this.data[section][section_class];
		},

		render: function(section, vars) {
			var template = '{{#.}}'+$('#'+section+'_item').html()+'{{/.}}';
			var htmlString = Mustache.render(unescape(template), vars);
			var html = $('<textarea/>').html(htmlString).text();
			$('#pb-'+section+' tbody').html(html);
		}
	}

	$(document).ready(function() {
		WH.ProfileBox.init();
	});

}(jQuery, mediaWiki) );
