<?php

/*
CREATE TABLE `image_feedback` (
  `ii_img_page_id` int(10) unsigned NOT NULL,
  `ii_wikiphoto_img` int(10) unsigned NOT NULL DEFAULT '0',
  `ii_page_id` int(10) unsigned NOT NULL,
  `ii_img_url` varchar(2048) NOT NULL,
  `ii_bad_votes` int(10) unsigned NOT NULL DEFAULT '0',
  `ii_bad_reasons` text NOT NULL,
  `ii_good_votes` int(10) unsigned NOT NULL DEFAULT '0',
  `ii_good_reasons` text NOT NULL,
  PRIMARY KEY (`ii_img_page_id`),
  UNIQUE KEY `ii_img_url` (`ii_img_url`(255)),
  KEY `ii_page_id` (`ii_page_id`),
  KEY `ii_votes` (`ii_bad_votes`),
  KEY `ii_good_votes` (`ii_good_votes`)
);
 */

/*
 *   Collects feedback on article images from users of wikihow
 */
class ImageFeedback extends UnlistedSpecialPage {
	const WIKIPHOTO_USER_NAME = 'wikiphoto';
	public static $allowAnonFeedback = null;

	function __construct() {
		parent::__construct('ImageFeedback');
	}

	function execute($par) {
		global $wgRequest, $wgOut, $wgUser, $wgServer;
		global $wgIsTitusServer, $wgIsDevServer, $wgIsToolsServer;

		if ($wgRequest->wasPosted()) {
			$action = $wgRequest->getVal('a');
			if (in_array('staff', $wgUser->getGroups()) && $action == 'reset_urls') {
				$this->resetUrls();
			} else {
				$this->handleImageFeedback();
			}
		} else {
			if (($wgIsTitusServer || $wgIsDevServer || $wgIsToolsServer) &&
				in_array( 'staff', $wgUser->getGroups() )
			) {
				$this->showAdminForm();
			}
		}
	}

	private function showAdminForm() {
		global $wgOut;
		EasyTemplate::set_path(dirname(__FILE__));
		$vars['ts'] = wfTimestampNow();
		$wgOut->addHtml(EasyTemplate::html('imagefeedback_admin'));
	}

	// The original function missed URLs from pages that had been deleted.
	// The function now checks if an article title has been deleted from the site.
	// If so, it searches the image_feedback table by URL rather than articleID.
	// The function also addresses the edge cases when an article ID or URL has changed.
	private function resetUrls() {
		global $wgRequest, $wgOut;
		$deletedNames = array();
		$urls = preg_split("@\n@", trim($wgRequest->getVal('if_urls')));
		$count = 0;
		$dbw = wfGetDB(DB_MASTER);

		foreach ($urls as $url) {
			if (!empty($url)) {
				$t = WikiPhoto::getArticleTitle($url);
				if ($t && $t->exists()) {
					$aids[] = $t->getArticleId();
					$aidsBackup[] = $dbw->addQuotes($t->getPrefixedURL());
					$count++;
				} else if ($t && $t->isDeletedQuick()) { //if the page was deleted
					$deletedNames[] = $dbw->addQuotes($t->getPrefixedURL());
					$count++;
				} else {
					$invalid[] = $url;
				}
			}
		}

		$numUrls = sizeof($aids);
		$affectedRows = 0;
		if ($numUrls) {
			$dbw->delete('image_feedback',
				array("ii_img_page_id" => $aids),
				__METHOD__);
			//edge case where the article ID has changed since the image was stored - has happened before
			//handle by deleting by URL
			$affectedRows += $dbw->affectedRows();
			if ($affectedRows != $numUrls) {
				$deletedNames = array_merge($deletedNames, $aidsBackup);
			}
		}

		//delete the URLs from deleted images (articleID no longer exists)
		$numDeleted = sizeof($deletedNames);
		if ($numDeleted) {
			$deletedNames = "(" . implode(",", $deletedNames) . ")";
			$dbw->delete('image_feedback',
				array("ii_img_url IN $deletedNames"),
				__METHOD__);
			$affectedRows += $dbw->affectedRows();
		}

		if (sizeof($invalid)) {
			$invalid = "These input urls are never existed:<br><br>" . implode("<br>", $invalid);
		}
		$wgOut->setArticleBodyOnly(true);
		$wgOut->addHtml("$affectedRows reset.$invalid");
	}

	private function handleImageFeedback() {
		global $wgRequest, $wgOut, $wgUser;

		$wgOut->setArticleBodyOnly(true);
		$dbw = wfGetDB(DB_MASTER);

		$reason = $wgRequest->getVal('reason');
		// Remove / chars from reason since this will be our delimeter in the ii_reason field
		$reason = $dbw->strencode(trim(str_replace("/", "", $reason)));
		// Add user who reported
		$reason = $wgUser->getName() . " says: $reason";

		$voteTypePrefix = $wgRequest->getVal('voteType') == 'good' ? 'ii_good' : 'ii_bad';

		$aid = $dbw->addQuotes($wgRequest->getVal('aid'));
		$imgUrl = substr(trim($wgRequest->getVal('imgUrl')), 1);
		$isWikiPhotoImg = 0;

		// Check if image is a wikiphoto image
		$t = Title::newFromUrl($imgUrl);
		if ($t && $t->exists()) {
			$r = Revision::newFromTitle($t);
			$userText = $r->getUserText();
			if (strtolower($r->getUserText()) == self::WIKIPHOTO_USER_NAME) {
				$isWikiPhotoImg = 1;
			}

			$url = substr($t->getLocalUrl(), 1);
			$voteField = $voteTypePrefix . "_votes";
			$reasonField = $voteTypePrefix . "_reasons";
			$sql = "INSERT INTO image_feedback
				(ii_img_page_id, ii_wikiphoto_img, ii_page_id, ii_img_url, $voteField, $reasonField) VALUES
				({$t->getArticleId()}, $isWikiPhotoImg, $aid, '$url', 1, '$reason')
				ON DUPLICATE KEY UPDATE
				$voteField = $voteField + 1, $reasonField = CONCAT($reasonField, '/$reason')";
			$dbw->query($sql, __METHOD__);
		}
	}

	public static function getImageFeedbackLink() {
		global $wgUser;

		if (self::isValidPage()) {
			$rptLink = "<a class='rpt_img' href='#'><span class='rpt_img_ico'></span>Helpful?</a>";
		} else {
			$rptLink = "";
		}
		return $rptLink;
	}

	public static function isValidPage() {
		global $wgUser, $wgTitle, $wgRequest;

		if (is_null(self::$allowAnonFeedback)) {
			// Allow anon feedback on ~5% of articles
			self::$allowAnonFeedback = mt_rand(1, 100) <= 5;
		}

		$allowAnonFeedback = self::$allowAnonFeedback;

		$ctx = MobileContext::singleton();
		$isMobileMode = $ctx->shouldDisplayMobileView();

		return $wgUser &&
			(!$wgUser->isAnon() || $allowAnonFeedback) &&
			!$isMobileMode &&
			$wgTitle &&
			$wgTitle->exists() &&
			$wgTitle->getNamespace() == NS_MAIN &&
			$wgRequest &&
			$wgRequest->getVal('create-new-article') == '' &&
			!self::isMainPage();
	}

	public static function isMainPage() {
		global $wgTitle;
		return $wgTitle && $wgTitle->getNamespace() == NS_MAIN &&
			$wgTitle->getText() == wfMessage('mainpage')->text();
	}
}
