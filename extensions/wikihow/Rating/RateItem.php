<?php

use MethodHelpfulness\ArticleMethod;

/**
 * AJAX call class to actually rate an item.
 * Currently we can rate: articles and samples
 */
class RateItem extends UnlistedSpecialPage {

	public function __construct() {
		global $wgHooks;
		parent::__construct( 'RateItem' );
		$wgHooks['ArticleDelete'][] = array("RateItem::clearRatingsOnDelete");
	}

	public function isMobileCapable() {
		return true;
	}

	/**
	 *
	 * This function can only get called when an article gets deleted
	 *
	 **/
	public static function clearRatingsOnDelete($wikiPage, $user, $reason) {
		$ratingTool = new RatingArticle();
		$ratingTool->clearRatings($wikiPage->getId(), $user, "Deleting page");
		$starRatingTool = new RatingStar();
		$starRatingTool->clearRatings($wikiPage->getId(), $user, "Deleting page");
		return true;
	}

	public function execute($par) {
		$req = $this->getRequest();
		$out = $this->getOutput();
		$user = $this->getUser();

		$ratType = $req->getVal("type", 'article_mh_style');
		$ratId = $req->getVal("page_id");
		$ratUser = $user->getID();
		$ratUserext = $user->getName();
		$ratRating = $req->getVal('rating');
		$source = $req->getVal('source');
		$out->setArticleBodyOnly(true);

		// disable ratings more than 5, less than 1
		if ($ratRating > 5 || $ratRating < 0) return;
		if (!$ratId) return;

		$rateItem = new RateItem();
		$ratingTool = $rateItem->getRatingTool($ratType);

		print $ratingTool->addRating($ratId, $ratUser, $ratUserext, $ratRating, $source);

		if ($ratType == 'article_mh_style' && $ratRating == "1") {
			RatingRedis::incrementRating();
		}
	}

	public static function showForm($type) {
		$rateItem = new RateItem();
		$ratingTool = $rateItem->getRatingTool($type);

		return $ratingTool->getRatingForm();
	}

	public static function showSidebarForm($type, $class = '') {
		$rateItem = new RateItem();
		$ratingTool = $rateItem->getRatingTool($type);

		return $ratingTool->getSidebarRatingForm($class);
	}

	public function showMobileForm($type) {
		$rateItem = new RateItem();
		$ratingTool = $rateItem->getRatingTool($type);

		$result = $ratingTool->getMobileRatingForm();
		return $result;
	}

	public function getRatingTool($type) {
		switch (strtolower($type)) {
			case 'article': // LEGACY from before 10/27/15
				$rTool = new RatingArticle();
				break;
			case 'sample': // LEGACY from before 10/27/15
				$rTool = new RatingSample();
				break;
			case 'article_mh_style':
				$rTool = new RatingArticleMHStyle();
				break;
			case 'star':
				$rTool = new RatingStar();
				break;
		}

		$rTool->setContext($this->getContext());
		return $rTool;
	}

}
