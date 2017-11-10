(function($, mw) {
	"use strict";
	window.WH = window.WH || {};
	window.WH.UserReview = {

		ur_div: WH.isMobileDomain ? 'ur_mobile' :'userreviews',

		init: function () {
			//show a few more reviews
			$(".ur_more").on("click", function (e) {
				e.preventDefault();
				$(this).hide();
				//expand all reviews
				$("#"+WH.UserReview.ur_div+" .ur_review").show();
				//expand all review texts
				$(".ur_review_show").hide();
				$(".ur_review_more").show().css("display", "inline");
				$(".ur_ellipsis").hide();
				//are there any left to show?
				if ($("#"+WH.UserReview.ur_div+" .ur_review:hidden").length > 0) {
					//$(".ur_even_more").show().css("display", "block");
				} else {
					$(".ur_hide").show().css("display", "block");
				}
			});
			//show all the reviews - NOT USING RIGHT NOW
			/*$(".ur_even_more").on("click", function (e) {
				e.preventDefault();
				$(this).hide();
				//expand all reviews
				$("#"+WH.UserReview.ur_div+" .ur_review").show();
				$(".ur_hide").show().css("display", "block");
			});*/
			//show the rest of the text of this review
			$(".ur_review_show").on("click", function (e) {
				e.preventDefault();
				$(this).hide();
				$(".ur_review_more", $(this).parent()).show();
				$(".ur_ellipsis", $(this).parent()).hide();
			});
			//expand all the reviews in the sidebar
			$(".sp_intro_user").on("click", function (e) {
				if(!WH.isMobileDomain) {
					e.preventDefault();
					$(".ur_review_show").hide();
					$(".ur_review_more").show();
					$(".ur_ellipsis").hide();
					$("#" + WH.UserReview.ur_div + " .ur_review").show();
					$(".ur_more").hide();
					//$(".ur_even_more").hide();
					$(".ur_hide").show().css("display", "block");
				}
			});
			//hide all but the first review
			$(".ur_hide").on("click", function (e) {
				e.preventDefault();
				$("#"+WH.UserReview.ur_div+" .ur_review:gt(0)").hide();
				$(this).hide();
				$(".ur_more").show().css("display", "block");
			});
			$(".ur_share").on("click", function(e) {
				e.preventDefault();
				if(WH.isMobileDomain) {
					mw.loader.using('ext.wikihow.UserReviewForm.mobile', function () {
						window.WH.UserReviewForm.prototype.loadUserReviewForm();
					});
				} else {
					mw.loader.using('ext.wikihow.UserReviewForm', function () {
						window.WH.UserReviewForm.prototype.loadUserReviewForm();
					});
				}
				$(this).hide();
			});
		}
	}

	WH.UserReview.init();
}($, mw));
