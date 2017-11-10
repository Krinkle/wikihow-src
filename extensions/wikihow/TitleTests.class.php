<?php
//
// Class used to manage title tests, to display the correct title and meta
// description data based on which test is being run.
//

/*db schema:
CREATE TABLE title_tests(
	tt_pageid INT UNSIGNED NOT NULL,
	tt_page VARCHAR(255) NOT NULL,
	tt_test INT(2) UNSIGNED NOT NULL,
	tt_custom TEXT DEFAULT NULL,
	tt_custom_note TEXT DEFAULT NULL,
	PRIMARY KEY (tt_pageid)
);
*/

class TitleTests {

	const TITLE_DEFAULT = -1;
	const TITLE_CUSTOM = 100;
	const TITLE_SITE_PREVIOUS = 101;

	const MAX_TITLE_LENGTH = 66;

	var $title;
	var $row;
	var $cachekey;

	// Flag can be set to avoid using memcache altogether
	static $forceNoCache = false;

	// Constructor called by factory method
	protected function __construct($title, $row) {
		$this->title = $title;
		$this->row = $row;
	}

	private static function getCachekey($pageid) {
		return !self::$forceNoCache ? wfMemcKey('titletests', $pageid) : '';
	}

	// Create a new TitleTest object using pageid
	public static function newFromTitle($title) {
		global $wgMemc;

		if (!$title || !$title->exists()) {
			// cannot create class
			return null;
		}

		$pageid = $title->getArticleId();
		$namespace = $title->getNamespace();
		if ($namespace != NS_MAIN || $pageid <= 0) {
			return null;
		}

		$cachekey = self::getCachekey($pageid);
		$row = $cachekey ? $wgMemc->get($cachekey) : false;
		if (!is_array($row)) {
			$dbr = wfGetDB(DB_SLAVE);
			$row = $dbr->selectRow(
				'title_tests',
				array('tt_test', 'tt_custom'),
				array('tt_pageid' => $pageid),
				__METHOD__);
			$row = $row ? (array)$row : array();
			if ($cachekey) $wgMemc->set($cachekey, $row);
		}

		$obj = new TitleTests($title, $row);
		return $obj;
	}

	public function getTitle() {
		$tt_test = isset($this->row['tt_test']) ? $this->row['tt_test'] : '';
		$tt_custom = isset($this->row['tt_custom']) ? $this->row['tt_custom'] : '';

		return self::genTitle($this->title, $tt_test, $tt_custom);
	}

	public function getDefaultTitle() {
		$wasEdited = $this->row['tt_test'] == self::TITLE_CUSTOM;
		$defaultPageTitle = self::genTitle($this->title, self::TITLE_DEFAULT, '');
		return array($defaultPageTitle, $wasEdited);
	}

	public function getOldTitle() {
		$isCustom = $this->row['tt_test'] == self::TITLE_CUSTOM;
		$testNum = $isCustom ? self::TITLE_CUSTOM : self::TITLE_SITE_PREVIOUS;
		$oldPageTitle = self::genTitle($this->title, $testNum, $this->row['tt_custom']);
		return $oldPageTitle;
	}

	private static function getWikitext($title) {
		$dbr = wfGetDB(DB_SLAVE);
		$wikitext = Wikitext::getWikitext($dbr, $title);
		$stepsText = '';
		if ($wikitext) {
			list($stepsText, ) = Wikitext::getStepsSection($wikitext, true);
		}
		return array($wikitext, $stepsText);
	}

	private static function getTitleExtraInfo($wikitext, $stepsText, $title) {
		$numSteps = Wikitext::countSteps($stepsText);
		$numPhotos = Wikitext::countImages($wikitext);
		$numVideos = Wikitext::countVideos($wikitext);

		// for the purpose of title info, we are counting videos as images
		// since we default to showing images with the option of showing video under them
		$numPhotos = intval($numPhotos) + intval($numVideos);

		$showWithPictures = false;
		if ($numSteps >= 5 && $numSteps <= 25) {
			if ($numPhotos > ($numSteps / 2) || $numPhotos >= 6) {
				$showWithPictures = true;
			}
		} else {
			if ($numPhotos > ($numSteps / 2)) {
				$showWithPictures = true;
			}
		}

		return array($numSteps, $showWithPictures);
	}

	private static function makeTitleInner($howto, $numSteps, $withPictures) {
		global $wgLanguageCode;

		if ($wgLanguageCode == 'en') {
			$ret = $howto
				. ($numSteps > 0 && $numSteps <= 15 ? ($numSteps == 1 ? ": 1 Step" : ": $numSteps Steps") : "")
				. ($withPictures ? " (with Pictures)" : "");
		} else {
			if (wfMessage('title_inner', $howto, $numSteps, $withPictures)->isBlank()) {
				$inner = $howto;
			} else {
				$inner = wfMessage('title_inner', $howto, $numSteps, $withPictures)->text();
			}
			$ret = preg_replace("@ +$@", "", $inner);
		}
		return $ret;
	}

	private static function makeTitleWays($ways, $titleTxt) {
		global $wgLanguageCode;

		if ($wgLanguageCode == "en") {
			$ret = $ways . " Ways to " . $titleTxt;
		} else {
			if (wfMessage('title_ways', $ways, $titleTxt)->isBlank()) {
				$ret = $titleTxt;
			} else {
				$ret = wfMessage('title_ways', $ways, $titleTxt)->text();
			}
		}
		return($ret);
	}

	private static function genTitle($title, $test, $custom) {
		global $wgLanguageCode;
		// MediaWiki:max_title_length is used for INTL
		$maxTitleLength = (int)wfMessage("max_title_length")->plain();
		if (!$maxTitleLength) {
			$maxTitleLength = self::MAX_TITLE_LENGTH;
		}
		$titleTxt = $title->getText();
		$howto = wfMessage('howto', $titleTxt)->text();

		list($wikitext, $stepsText) = self::getWikitext($title);
		switch ($test) {
		case self::TITLE_CUSTOM: // Custom
			$title = $custom;
			break;

		case self::TITLE_SITE_PREVIOUS: // How to XXX: N Steps (with Pictures) - wikiHow
			list($numSteps, $withPictures) = self::getTitleExtraInfo($wikitext, $stepsText, $title);
			$inner = self::makeTitleInner($howto, $numSteps, $withPictures);
			$title = wfMessage('pagetitle', $inner)->text();
			break;

		default: // How to XXX: N Steps (with Pictures) - wikiHow

			$methods = Wikitext::countAltMethods($stepsText);

			$mw = MagicWord::get( 'parts' );
			$hasParts = ($mw->match($wikitext));

			if ($methods >= 3 && !$hasParts) {
				$inner = self::makeTitleWays($methods, $titleTxt);
				$title = wfMessage('pagetitle', $inner)->text();
				if (strlen($title) > $maxTitleLength) {
					$title = $inner;
				}
			} else {
				list($numSteps, $withPictures) = self::getTitleExtraInfo($wikitext, $stepsText, $title);
				$inner = self::makeTitleInner($howto, $numSteps, $withPictures);
				$title = wfMessage('pagetitle', $inner)->text();
				// first, try articlename + metadata + wikihow
				if (strlen($title) > $maxTitleLength) {
					// next, try articlename + metadata
					$title = $inner;
					if ($numSteps > 0 && strlen($title) > $maxTitleLength) {
						// next, try articlename + steps
						$title = self::makeTitleInner($howto, $numSteps, 0);
					}
					if (strlen($title) > $maxTitleLength) {
						// next, try articlename + wikihow
						$title = wfMessage('pagetitle', $howto)->text();
						if (strlen($title) > $maxTitleLength) {
							// lastly, set title just as articlename
							$title = $howto;
						}
					}
				}
			}
			break;
		}
		return $title;
	}

	public function getMetaDescription() {
		$tt_test = isset($this->row['tt_test']) ? $this->row['tt_test'] : '';
		return self::genMetaDescription($this->title, $tt_test);
	}

	private static function genMetaDescription($title, $test) {
		// no more tests -- always use site default for meta desription
		$ami = new ArticleMetaInfo($title);
		$desc = $ami->getDescription();
		return $desc;
	}

	/**
	 * Adds a new record to the title_tests db table.  Called by
	 * importTitleTests.php.
	 */
	public static function dbAddRecord(&$dbw, $title, $test) {
		global $wgMemc;
		if (!$title || $title->getNamespace() != NS_MAIN) {
			throw new Exception('TitleTests: bad title for DB call');
		}
		$pageid = $title->getArticleId();
		$dbw->replace('title_tests', 'tt_pageid',
			array('tt_pageid' => $pageid,
				'tt_page' => $title->getDBkey(),
				'tt_test' => $test),
			__METHOD__);
		$cachekey = self::getCachekey($pageid);
		if ($cachekey) $wgMemc->delete($cachekey);
	}

	/**
	 * Adds or replaces the current title with a custom one specified by
	 * a string from the admin. Note: must be a main namespace title.
	 */
	public static function dbSetCustomTitle(&$dbw, $title, $custom, $custom_note = '') {
		global $wgMemc;
		if (!$title || $title->getNamespace() != NS_MAIN) {
			throw new Exception('TitleTests: bad title for DB call');
		}
		$pageid = $title->getArticleId();
		$dbw->replace('title_tests', 'tt_pageid',
			array('tt_pageid' => $pageid,
				'tt_page' => $title->getDBkey(),
				'tt_test' => self::TITLE_CUSTOM,
				'tt_custom' => $custom,
				'tt_custom_note' => $custom_note),
			__METHOD__);
		$cachekey = self::getCachekey($pageid);
		if ($cachekey) $wgMemc->delete($cachekey);
	}

	/**
	 * List all "custom-edited" titles in one go
	 */
	public static function dbListCustomTitles(&$dbr) {
		$res = $dbr->select('title_tests',
			array('tt_pageid', 'tt_page', 'tt_custom', 'tt_custom_note'),
			array('tt_test' => self::TITLE_CUSTOM),
			__METHOD__);
		$pages = array();
		foreach ($res as $row) {
			$pages[] = (array)$row;
		}
		return $pages;
	}

	/**
	 * Remove a title from the list of tests
	 */
	public static function dbRemoveTitle(&$dbw, $title) {
		self::dbRemoveTitleID( $dbw, $title->getArticleId() );
	}

	public static function dbRemoveTitleID(&$dbw, $pageid) {
		global $wgMemc;
		$dbw->delete('title_tests',
			array('tt_pageid' => $pageid),
			__METHOD__);
		$cachekey = self::getCachekey($pageid);
		if ($cachekey) $wgMemc->delete($cachekey);
	}

}

