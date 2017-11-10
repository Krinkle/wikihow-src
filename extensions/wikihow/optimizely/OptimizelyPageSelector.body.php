<?php

class OptimizelyPageSelector {

	// We will hash, and allow about this percent of articles
	public static function getOptimizelyTag() {
		global $wgIsDevServer, $wgIsAnswersDomain;

		$tag = "<script>if (typeof window['optimizely'] == 'undefined') { window['optimizely'] = [] } window['optimizely'].push(['addToSegment', 'wikihow_user_name', wgUserName]);</script>";
		if ( $wgIsAnswersDomain ) {
			$tag .= "<script async src=\"//cdn.optimizely.com/js/8223773184.js\"></script>";
		} elseif ( !$wgIsDevServer ) {
			$tag .= "<script async src=\"//cdn.optimizely.com/js/526710254.js\"></script>";
		} else {
			$tag .= "<script async src=\"//cdn.optimizely.com/js/539020690.js\"></script>";
		}
		return $tag;
	}

	/*
	 * Check if we enable optimizely for a user. We disable
	 * old users.
	 */
	public static function isUserEnabled($user) {
		// We enable Optimizely for anons and all users now
		return true;
	}

	/*
	 * Determine if we should show optimizely on this page
	 * @param articleName Name of the article we want to determine whether to show
	 */
	public static function isArticleEnabled($title) {
		global $wgLanguageCode;

		// Turn off optimizely if we didn't get an article name, or we aren't in English
		if (!$title) {
			return false;
		}
		if ($wgLanguageCode != 'en') {
			return false;
		}

		if ( class_exists( 'AlternateDomain' ) && AlternateDomain::onAlternateDomain() ) {
			return false;
		}

		// Turning off Optimizely on this specific article because of weird indexation issue:
		// https://dl.dropboxusercontent.com/s/lelzq6j1zfzqyb9/2016-09-14%20at%202.26%20PM%202x.png?dl=0
		// - per Elizabeth and Reuben, Sept 2016
		$articleName = $title->getText();
		if ($articleName == 'Gain Weight') {
			return false;
		}

		$isMobile = Misc::isMobileMode();
		if(!$isMobile && !ArticleTagList::hasTag("opti_desktop", $title->getArticleId())) {
			return false;
		}

		if($isMobile && !ArticleTagList::hasTag("opti_mobile", $title->getArticleId())) {
			return false;
		}

		// Put Optimizely on all article views -- main namespace and not
		return true;
	}
}
