<?php

class RatingArticleMHStyle extends RatingArticle {
	public function __construct() {
		parent::__construct();
	}

	function getRatingResponsePlatform($itemId, $rating, $ratingId, $isMobile) {
		$tmpl = new EasyTemplate(dirname(__FILE__));
		$title = Title::newFromID($itemId);
		$tmpl->set_vars(
			array(
				'rating' => $rating,
				'titleText' => $title->getText(),
				'ratingId' => $ratingId,
				'isMobile' => $isMobile
		));

		$template = 'rating_mh_style.tmpl.php';
		if ( SpecialTechFeedback::isTitleInTechCategory( $title ) ) {
			$template = 'rateitem_response_tech.tmpl.php';
		}
		return $tmpl->execute( $template );
	}

	function getRatingResponseMobile($itemId, $rating, $ratingId) {
		return $this->getRatingResponsePlatform($itemId, $rating, $ratingId, true);
	}

	function getRatingResponseDesktop($itemId, $rating, $ratingId) {
		return $this->getRatingResponsePlatform($itemId, $rating, $ratingId, false);
	}
}

