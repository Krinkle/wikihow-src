(function($, mw) {
	"use strict";
	window.WH = window.WH || {};
	window.WH.MobileTabs = {
		defaultClass: "tab_default",

		init: function () {
			//show a few more reviews
			$(".mobile_tab").on("click", function (e) {
				e.preventDefault();
				if(!$(this).hasClass("active")) {
					$(".mobile_tab").removeClass("active").addClass("inactive");
					$(this).addClass("active").removeClass("inactive");

					//get the class for these tabs
					var className;
					if($(this).hasClass("mobile_tab_default")) {
						className = "tab_default";
					} else {
						className = $(this).attr("id").substring(7); //7 = mobile_
					}

					$(".tabbed_content").hide();
					$("." + className).show();
				}
			});
		}
	}

	WH.MobileTabs.init();
}($, mw));
