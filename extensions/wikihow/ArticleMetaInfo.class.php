<?

/**
 * Controls the html meta descriptions that relate to Google and Facebook
 * in the head of all article pages.
 *
 * Follows something like the active record pattern.
 */
class ArticleMetaInfo {
	static $dbr = null,
		$dbw = null;

	static $wgTitleAMIcache = null;
	static $wgTitleAmiImageName = null;

	var $title = null,
		$articleID = 0,
		$namespace = NS_MAIN,
		$titleText = '',
		$wikitext = '',
		$cachekey = '',
		$isMaintenance = false,
		$row = null;

	// Reuben added an exclusion list from Chris because we found that
	// wikiVideo wasn't doing well in terms of CTR from Google Index, and
	// video thumbnails seemed to be the common factor. We're excluding
	// OpenGraph image data and pinterest data from certain pages as a
	// test to see if (1) we can remove the video thumbnails from the
	// Google Index for these articles, and (2) if this helps CTR / page
	// views for these articles.
	//
	// Articles with an even article ID had this og:image:
	//    <meta property="og:image" content=""/>
	// Articles with an odd article ID had no og:image meta tag.
	static $opengraphArticleExclusions = array(
		1068179, // Bake Chicken Breast -- added new Done image and renamed old Done image from html
		65596, // Remove Ink from Clothes -- changed wikitext
		18474, // Hard Boil an Egg -- reverted by wikiphoto
		14904, // Keep a Cut Apple from Turning Brown
		109431, // Make a Simple Remedy for Sore Throat -- reverted by wikiphoto
		23439, // Curl Hair with Straighteners
		31411, // Make a Milkshake -- images in last steps
		22782, // Remove Gum from Clothes -- added new Done image at end
		24394, // Remove Yellow Armpit Stains -- reverted by wikiphoto
		237241, // Do Long Division
		14791, // Smoke a Cigarette
		2365, // Cook Pasta
		22205, // Write a Check
		5014, // Play Poker
		118572, // Make Peanut Butter
		2538648, // Defrost Ground Beef
		13331, // Remove Red Wine from Fabric
		5358, // Ease a Toothache
		22960, // Recover after Wisdom Teeth Surgery
		527276, // Turn Your Computer Screen Upside Down
		64180, // Cook Pork Chops
		279762, // Make Tea
		3467, // Apply False Eyelashes
		235416, // Get Super Glue Off Skin
		27799, // Make Eyes Look Bigger
		26355, // Take Care of a Betta Fish
		344563, // Dry Roses
		143190, // Clear Google Search History
		150727, // Cook Lobster Tails
		3232181, // Do Winged Eyeline
		66040, // Make Garlic Bread
		494467, // Make Santa Cookies -- deindexed, added new Done image
		2896745, // Delete Apps -- deindexed, added new Done image
	);

	const MAX_DESC_LENGTH = 240;
	const SHORT_DESC_LENGTH = 160;

	const DESC_STYLE_NOT_SPECIFIED = -1;
	const DESC_STYLE_ORIGINAL = 0;
	const DESC_STYLE_INTRO = 1;
	const DESC_STYLE_DEFAULT = 1; // SAME AS ABOVE
	const DESC_STYLE_STEP1 = 2;
	const DESC_STYLE_EDITED = 3;
	const DESC_STYLE_INTRO_NO_TITLE = 4;
	const DESC_STYLE_FACEBOOK_DEFAULT = 4; // SAME AS ABOVE
	const DESC_STYLE_HELPFUL = 5;
	const DESC_STYLE_SIMPLE_EASY = 6;
	const DESC_STYLE_SIMPLE = 7;
	const DESC_STYLE_EASY = 8;
	const DESC_STYLE_SHORT = 9;

	public function __construct($title, $isMaintenance = false) {
		$this->title = $title;
		$this->articleID = $title->getArticleID();
		$this->namespace = $title->getNamespace();
		$this->titleText = $title->getText();
		$this->isMaintenance = $isMaintenance;
		$this->cachekey = wfMemcKey('metadata2', $this->namespace, $this->articleID);
	}

	public function updateLastVideoPath( $videoPath ) {
		if ( !$videoPath ) {
			return;
		}
		$this->loadInfo();
		if ( $this->row['ami_video'] != $videoPath ) {
			$this->row['ami_video'] = $videoPath;
			$this->saveInfo();
		}
	}

	public function updateSummaryVideoPath( $videoPath ) {
		if ( !$videoPath ) {
			return;
		}
		$this->loadInfo();
		if ( $this->row['ami_summary_video'] != $videoPath ) {
			$this->row['ami_summary_video'] = $videoPath;
			$this->saveInfo();
		}
	}

	public function getVideo() {
		$this->loadInfo();
		return $this->row['ami_video'];
	}

	public static function getGif( $title ) {
		$result = '';
		$ami = new ArticleMetaInfo( $title );
		$video = $ami->getVideo();
		if ( !$video ) {
			return $result;
		}

		$video = end( explode( '/', substr( $video, 0, -3 ) . 'gif' ) );
		$file = RepoGroup::singleton()->findFile( $video );
		if ( !$file ) {
			return $result;
		}
		$result = $file->getUrl();
		return $result;
	}

	public static function getVideoSrc( $title ) {
		$result = '';
		$ami = new ArticleMetaInfo( $title );
		$result = $ami->getVideo();
		return $result;
	}

	/**
	 * Refresh the metadata after the article edit is patrolled, good revision is updated
	 * and before squid is purged. See GoodRevision::onMarkPatrolled for more details.
	 */
	public static function refreshMetaDataCallback($article) {
		$title = $article->getTitle();
		if ($title
			&& $title->exists()
			&& $title->getNamespace() == NS_MAIN)
		{
			$meta = new ArticleMetaInfo($title, true);
			$meta->refreshMetaData();
		}
		return true;
	}

	/**
	 * Refresh all computed data about the meta description stuff
	 */
	public function refreshMetaData($style = self::DESC_STYLE_NOT_SPECIFIED) {
		$this->loadInfo();
		$this->updateImage();
		$this->populateDescription($style);
		$this->populateFacebookDescription();
		$this->saveInfo();
	}

	/**
	 * Return the image dimensions, or an empty array if we cannot get either one
	 * load them from db if necessary but try to get them from memcached first
	 */
	public function getImageDimensions() {
		//  load the image from memcached (or from the ami db table as a backup)
		$this->loadInfo();

		// the data will likely be in memcached..but if it is not we have to load it ourselves
		if ( $this->row && @$this->row['ami_img_width'] === null && @$this->row['ami_img_height'] === null ) {
			// update the row with the image dimensions (from wfFindFile)
			if ( $this->updateImageDimensions() ) {
				// we will save the image but we only want to update memcached
				$updateDB = false;
				$this->saveInfo( $updateDB );
			}
		}

		// if we still don't have the dimensions bail out
		if ( !@$this->row['ami_img_width'] || !@$this->row['ami_img_height'] ) {
			return array();
		}

		return array( 'width' => $this->row['ami_img_width'], 'height' => $this->row['ami_img_height'] );
	}

	/**
	 * Return the image meta info for the article record
	 */
	public function getImage() {
		$this->loadInfo();
		// if ami_img == NULL, this field needs to be populated
		if ($this->row && $this->row['ami_img'] === null) {
			if ($this->updateImage()) {
				$this->saveInfo();
			}
		}
		return @$this->row['ami_img'];
	}

	/**
	 * Update the image meta info dimensions for the article record
	 */
	private function updateImageDimensions() {
		$amiImg = $this->row['ami_img'];
		// the ami_image is a string that is a path to the image..
		// it begins with /images/x/xx/ which is 13 characters long
		// so we will try to find the file with this name
		if ( $this->row && $amiImg && strlen( $amiImg ) > 13 ) {
			$imageFile = wfFindFile( substr( $amiImg, 13 ) );

			// only update if we found the image and we have width and height
			if ( $imageFile && $imageFile->getWidth() > 0 && $imageFile->getHeight() > 0 ) {
				$this->row['ami_img_width'] = $imageFile->getWidth();
				$this->row['ami_img_height'] = $imageFile->getHeight();
				return true;
			}
		}
		return false;
	}

	/**
	 * Update the image meta info for the article record
	 */
	private function updateImage() {
		$url = WikihowShare::getShareImage($this->title);
		$this->row['ami_img'] = $url;
		return true;
	}

	/**
	 * Grab the wikitext for the article record
	 */
	private function getArticleWikiText() {
		// cache this if it was already pulled
		if ($this->wikitext) {
			return $this->wikitext;
		}

		if (!$this->title || !$this->title->exists()) {
			//throw new Exception('ArticleMetaInfo: title not found');
			return '';
		}

		$good = GoodRevision::newFromTitle($this->title, $this->articleID);
		$revid = $good ? $good->latestGood() : 0;

		$dbr = $this->getDB();
		$rev = Revision::loadFromTitle($dbr, $this->title, $revid);
		if (!$rev) {
			//throw new Exception('ArticleMetaInfo: could not load revision');
			return '';
		}

		$this->wikitext = $rev->getText();
		return $this->wikitext;
	}

	/**
	 * Populate Facebook meta description.
	 */
	private function populateFacebookDescription() {
		$fbstyle = self::DESC_STYLE_FACEBOOK_DEFAULT;
		return $this->populateDescription($fbstyle, true);
	}

	/**
	 * Add a meta description (in one of the styles specified by the row) if
	 * a description is needed.
	 */
	private function populateDescription($forceDesc = self::DESC_STYLE_NOT_SPECIFIED, $facebook = false) {
		$this->loadInfo();

		if (!$facebook &&
			(self::DESC_STYLE_NOT_SPECIFIED == $forceDesc
			 || self::DESC_STYLE_EDITED == $this->row['ami_desc_style']))
		{
			$style = $this->row['ami_desc_style'];
		} else {
			$style = $forceDesc;
		}

		if (!$facebook) {
			$this->row['ami_desc_style'] = $style;
			list($success, $desc) = $this->buildDescription($style);
			$this->row['ami_desc'] = $desc;
		} else {
			list($success, $desc) = $this->buildDescription($style);
			$this->row['ami_facebook_desc'] = $desc;
		}

		return $success;
	}

	/**
	 * Sets the meta description in the database to be part of the intro, part
	 * of the first step, or 'original' which is something like "wikiHow
	 * article on How to <title>".
	 */
	private function buildDescription($style) {
		if (self::DESC_STYLE_ORIGINAL == $style) {
			return array(true, '');
		}
		if (self::DESC_STYLE_EDITED == $style) {
			return array(true, $this->row['ami_desc']);
		}
		$descLength = self::MAX_DESC_LENGTH;

		$wikitext = $this->getArticleWikiText();
		if (!$wikitext) return array(false, '');

		if (self::DESC_STYLE_INTRO == $style
			|| self::DESC_STYLE_INTRO_NO_TITLE == $style)
		{
			// grab intro
			$desc = Wikitext::getIntro($wikitext);

			// append first step to intro if intro maybe isn't long enough
			if (strlen($desc) < 2 * self::MAX_DESC_LENGTH) {
				list($steps, ) = Wikitext::getStepsSection($wikitext);
				if ($steps) {
					$desc .= ' ' . Wikitext::cutFirstStep($steps);
				}
			}
		} elseif (self::DESC_STYLE_STEP1 == $style) {
			// grab steps section
			list($desc, ) = Wikitext::getStepsSection($wikitext);

			// pull out just the first step
			if ($desc) {
				$desc = Wikitext::cutFirstStep($desc);
			} else {
				$desc = Wikitext::getIntro($wikitext);
			}
		} elseif (self::DESC_STYLE_SIMPLE == $style) {
			$desc = "Simple, step-by-step guide on " . wfMessage('howto', $this->titleText)->text() . ". ";
			$desc .= Wikitext::getIntro($wikitext);
			$descLength = self::SHORT_DESC_LENGTH;
		} elseif (self::DESC_STYLE_EASY == $style) {
			$desc = "Easy step-by-step guide on " . wfMessage('howto', $this->titleText)->text() . ". ";
			$desc .= Wikitext::getIntro($wikitext);
			$descLength = self::SHORT_DESC_LENGTH;
		} elseif (self::DESC_STYLE_SIMPLE_EASY == $style) {
			$desc = "Simple, easy-to-follow instructions on " . wfMessage('howto', $this->titleText)->text() . ". ";
			$desc .= Wikitext::getIntro($wikitext);
			$descLength = self::SHORT_DESC_LENGTH;
		} elseif (self::DESC_STYLE_SHORT == $style) {
			// grab intro
			$desc = Wikitext::getIntro($wikitext);
			$descLength = self::SHORT_DESC_LENGTH;
		} elseif (self::DESC_STYLE_HELPFUL == $style) {
			//this one needs to go first b/c if it doesn't meet the
			//conditions, it falls back to the default
			$data = PageHelpfulness::getRatingData($this->articleID);
			$current = array_shift($data);
			if ($current != null && $current->total >= 12 && $current->percent >= 61) {
				$desc = "Step-by-step guide on " . wfMessage('howto', $this->titleText)->text() . ".";
				list($steps, ) = Wikitext::getStepsSection($wikitext);
				if($steps) {
					$numSteps = Wikitext::countSteps($steps);
					$numPhotos = Wikitext::countImages($wikitext);

					if ($numPhotos > $numSteps/2) {
						$desc .= " With pictures.";
					}
				}

				$desc .= " Rated ";
				if ($current->percent >= 81 ) {
					$desc .= "exceptionally helpful ";
				} else {
					$desc .= "very helpful ";
				}
				$desc .= "by {$current->total} readers.";
				$descLength = self::SHORT_DESC_LENGTH;
			} else {
				// grab intro
				$desc = Wikitext::getIntro($wikitext);

				// append first step to intro if intro maybe isn't long enough
				if (strlen($desc) < 2 * self::MAX_DESC_LENGTH) {
					list($steps, ) = Wikitext::getStepsSection($wikitext);
					if ($steps) {
						$desc .= ' ' . Wikitext::cutFirstStep($steps);
					}
				}
			}
		} else {
			//throw new Exception('ArticleMetaInfo: unknown style');

			return array(false, '');
		}

		$desc = Wikitext::flatten($desc);
		$howto = wfMessage('howto', $this->titleText)->text();
		if ($desc) {
			if (!in_array($style, array(self::DESC_STYLE_INTRO_NO_TITLE, self::DESC_STYLE_HELPFUL, self::DESC_STYLE_SIMPLE, self::DESC_STYLE_EASY, self::DESC_STYLE_SIMPLE_EASY) )) {
				$desc = $howto . '. ' . $desc;
			}
		} else {
			$desc = $howto;
		}

		$desc = self::trimDescription($desc, $descLength);
		return array(true, $desc);
	}
	private static function trimDescription($desc, $maxLength = self::MAX_DESC_LENGTH) {
		// Chop desc length at MAX_DESC_LENGTH, and then last space in
		// description so that '...' is added at the end of a word.
		$desc = mb_substr($desc, 0, $maxLength);
		$len = mb_strlen($desc);
		// TODO: mb_strrpos method isn't available for some reason
		$pos = strrpos($desc, ' ');

		if ($len >= $maxLength && $pos !== false) {
			$toAppend = '...';
			if ($len - $pos > 20)  {
				$pos = $len - strlen($toAppend);
			}
			$desc = mb_substr($desc, 0, $pos) . $toAppend;
		}

		return $desc;
	}

	/**
	 * Load and return the <meta name="description" ... descriptive text.
	 */
	public function getDescription() {
		// return copy of description already found
		if ($this->row && $this->row['ami_desc']) {
			return $this->row['ami_desc'];
		}

		$this->loadInfo();

		// needs description
		if ($this->row
			&& $this->row['ami_desc_style'] != self::DESC_STYLE_ORIGINAL
			&& !$this->row['ami_desc'])
		{
			if ($this->populateDescription()) {
				$this->saveInfo();
			}
		}

		return @$this->row['ami_desc'];
	}

	/**
	 * Return the description style used.  Can be compared against the
	 * self::DESC_STYLE_* constants.
	 */
	public function getStyle() {
		$this->loadInfo();
		return $this->row['ami_desc_style'];
	}

	/**
	 * Returns the description in the "intro" style.  Note that this function
	 * is not optimized for caching and should only be called within the
	 * admin console.
	 */
	public function getDescriptionDefaultStyle() {
		$this->loadInfo();
		list($success, $desc) = $this->buildDescription(self::DESC_STYLE_DEFAULT);
		return $desc;
	}

	/**
	 * Set the meta description to a hand-edited one.
	 */
	public function setEditedDescription($desc, $customNote) {
		$this->loadInfo();
		$this->row['ami_desc_style'] = self::DESC_STYLE_EDITED;
		$this->row['ami_desc'] = self::trimDescription($desc);
		$this->row['ami_edited_note'] = $customNote;
		$this->refreshMetaData();
	}

	public function dbListEditedDescriptions() {
		$dbr = self::getDB();
		$res = $dbr->select('article_meta_info',
			['ami_id', 'ami_desc', 'ami_edited_note'],
			['ami_desc_style' => self::DESC_STYLE_EDITED, "ami_desc != ''"],
			__METHOD__);
		$results = [];
		foreach ($res as $row) {
			$results[] = (array)$row;
		}
		return $results;
	}

	/**
	 * Set the meta description to a hand-edited one.
	 */
	public function resetMetaData() {
		$this->loadInfo();
		$this->row['ami_desc_style'] = self::DESC_STYLE_DEFAULT;
		$this->row['ami_desc'] = '';
		$this->refreshMetaData();
	}

	/**
	 * Load and return the <meta name="description" ... descriptive text.
	 */
	public function getFacebookDescription() {
		// return copy of description already found
		if ($this->row && $this->row['ami_facebook_desc']) {
			return $this->row['ami_facebook_desc'];
		}

		$this->loadInfo();

		// needs FB description
		if ($this->row && !$this->row['ami_facebook_desc']) {
			if ($this->populateFacebookDescription()) {
				$this->saveInfo();
			}
		}

		return @$this->row['ami_facebook_desc'];
	}

	/**
	 * Retrieve the meta info stored in the database.
	 */
	/*public function getInfo() {
		$this->loadInfo();
		return $this->row;
	}*/

	/* DB schema
	 *
	 CREATE TABLE article_meta_info (
	   ami_id int unsigned not null,
	   ami_namespace int unsigned not null default 0,
	   ami_title varchar(255) not null default '',
	   ami_updated varchar(14) not null default '',
	   ami_desc_style tinyint(1) not null default 1,
	   ami_desc varchar(255) not null default '',
	   ami_facebook_desc varchar(255) not null default '',
	   ami_video varchar(255) not null default '',
	   ami_summary_video varchar(255) not null default '',
	   ami_img varchar(255) default null,
	   primary key (ami_id)
	 ) DEFAULT CHARSET=utf8;
	 *
	 alter table article_meta_info add column ami_facebook_desc varchar(255) not null default '' after ami_desc;
	 alter table article_meta_info add column ami_summary_video varchar(255) not null default '' after ami_video;
	 *
	 */

	/**
	 * Create a database handle.  $type can be 'read' or 'write'
	 */
	private function getDB($type = 'read') {
		if ($type == 'write') {
			if (self::$dbw == null) self::$dbw = wfGetDB(DB_MASTER);
			return self::$dbw;
		} elseif ($type == 'read') {
			if (self::$dbr == null) self::$dbr = wfGetDB(DB_SLAVE);
			return self::$dbr;
		} else {
			throw new Exception('unknown DB handle type');
		}
	}

	/**
	 * Load the meta info record from either DB or memcache
	 */
	private function loadInfo() {
		global $wgMemc;

		if ($this->row) return;

		$res = null;
		// Don't pull from cache if maintenance is being performed
		if (!$this->isMaintenance) {
			$res = $wgMemc->get($this->cachekey);
		}

		if (!is_array($res)) {
			$articleID = $this->articleID;
			$namespace = NS_MAIN;
			$dbr = $this->getDB();
			$sql = 'SELECT * FROM article_meta_info WHERE ami_id=' . $dbr->addQuotes($articleID) . ' AND ami_namespace=' . intval($namespace);
			$res = $dbr->query($sql, __METHOD__);
			$this->row = $dbr->fetchRow($res);

			if (!$this->row) {
				$this->row = array(
					'ami_id' => $articleID,
					'ami_namespace' => intval($namespace),
					'ami_desc_style' => self::DESC_STYLE_DEFAULT,
					'ami_desc' => '',
					'ami_facebook_desc' => '',
				);
			} else {
				foreach ($this->row as $k => $v) {
					if (is_int($k)) {
						unset($this->row[$k]);
					}
				}
			}
			$wgMemc->set($this->cachekey, $this->row);
		} else {
			$this->row = $res;
		}
	}

	/**
	 * Save article meta info to both DB and memcache
	 * params:
	 * updateDB: allows you to only save to memcached
	 * this is useful if you have extra data you want to save in memcached but not in the db table
	 * for example the image dimensions
	 */
	private function saveInfo( $updateDB = true ) {
		global $wgMemc;

		if (empty($this->row)) {
			throw new Exception(__METHOD__ . ': nothing loaded');
		}
		$imgWidth = null;
		$imgHeight = null;

		$this->row['ami_updated'] = wfTimestampNow(TS_MW);

		if (!isset($this->row['ami_title'])) {
			$this->row['ami_title'] = $this->titleText;
		}
		if (!isset($this->row['ami_id'])) {
			$articleID = $this->articleID;
			$this->row['ami_id'] = $articleID;
		}
		if (!isset($this->row['ami_namespace'])) {
			$namespace = $this->namespace;
			$this->row['ami_namespace'] = $namespace;
		}
		if (!isset($this->row['ami_desc_style']) || is_null($this->row['ami_desc_style'])) {
			$this->row['ami_desc_style'] = self::DESC_STYLE_DEFAULT;
		}

		if ( isset( $this->row['ami_img_width'] ) ) {
			$imgWidth = $this->row['ami_img_width'];
			unset( $this->row['ami_img_width'] );
		}

		if ( isset( $this->row['ami_img_height'] ) ) {
			$imgHeight = $this->row['ami_img_height'];
			unset( $this->row['ami_img_height'] );
		}

		if ( $updateDB == true ) {
			$dbw = $this->getDB('write');
			$sql = 'REPLACE INTO article_meta_info SET ' . $dbw->makeList($this->row, LIST_SET);
			$res = $dbw->query($sql, __METHOD__);
		}

		if ( $imgWidth > 0 && $imgHeight > 0 ) {
			// put the image dimensions into memcache
			$this->row['ami_img_width'] = $imgWidth;
			$this->row['ami_img_height'] = $imgHeight;
		}
		$wgMemc->set($this->cachekey, $this->row);
	}

	private static function getMetaSubcategories($title, $limit = 3) {
		$results = array();
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			array('categorylinks', 'page'),
			array('page_namespace', 'page_title'),
			array('page_id=cl_from', 'page_namespace' => NS_CATEGORY, 'cl_to' => $title->getDBKey()),
			__METHOD__,
			array('ORDER BY' => 'page_counter desc', 'LIMIT' => ($limit + 1) )
		);
		$requests = wfMessage('requests')->text();
		$count = 0;
		foreach ($res as $row) {
			if ($count++ == $limit) break;
			$t = Title::makeTitle($row->page_namespace, $row->page_title);
			if (strpos($t->getText(), $requests) === false) {
				$results[] = $t->getText();
			}
		}
		return $results;
	}

	// Add these meta properties that the Facebook graph protocol wants
	// https://developers.facebook.com/docs/opengraph/
	static function addFacebookMetaProperties($titleText) {
		global $wgOut, $wgTitle, $wgRequest;

		$action = $wgRequest->getVal('action', '');
		if ($wgTitle->getNamespace() != NS_MAIN
			|| $wgTitle->getText() == wfMessage('mainpage')->text()
			|| (!empty($action) && $action != 'view'))
		{
			return;
		}

		if ( !wfRunHooks( 'ArticleMetaInfoAddFacebookMetaProperties', array() ) ) {
			return;
		}
		$url = $wgTitle->getFullURL('', false, PROTO_CANONICAL);

		$ami = self::getAMICache();
		$fbDesc = $ami->getFacebookDescription();

		$img = $ami->getImage();

		// if this was shared via thumbs up, we want a different description.
		// url will look like this, for example:
		// https://www.wikihow.com/Kiss?fb=t
		if ($wgRequest->getVal('fb', '') == 't') {
			$fbDesc = wfMessage('article_meta_description_facebook', $wgTitle->getText())->text();
			$url .= "?fb=t";
		}


		// If this url isn't a facebook action, make sure the url is formatted appropriately 
		if ($wgRequest->getVal('fba','') == 't') {
			$url .= "?fba=t";
		} else {
			// If this url isn't a facebook action, add 'How to ' to the title
			$titleText = wfMessage('howto', $titleText)->text();
		}

		$props = array(
			array( 'property' => 'og:title', 'content' => $titleText ),
			array( 'property' => 'og:type', 'content' => 'article' ),
			array( 'property' => 'og:url', 'content' => $url ),
			array( 'property' => 'og:site_name', 'content' => 'wikiHow' ),
			array( 'property' => 'og:description', 'content' => $fbDesc ),
		);
		if ($img) {
			// Note: we can add multiple copies of this meta tag at some point
			// Note 2: we don't want to use pad*.whstatic.com because we want
			//   these imgs to refresh reasonably often as the page refreshes
			// Note 3: we use a static string for www.wikihow.com here since
			//   the non-English languages need to refer to English
			if ($img) {
				$img = 'https://www.wikihow.com' . $img;
			}
			if (!self::isImageExclusionArticle()) {
				$props[] = array( 'property' => 'og:image', 'content' => $img );
				$dim = $ami->getImageDimensions();
				if ( @$dim['width'] && @$dim['height'] ) {
					$props[] = array( 'property' => 'og:image:width', 'content' => $dim['width'] );
					$props[] = array( 'property' => 'og:image:height', 'content' => $dim['height'] );
				}
			} else {
				global $wgTitle;
				$articleID = $wgTitle ? $wgTitle->getArticleID() : 0;
				if ($articleID % 2 == 0) {
					$props[] = array( 'property' => 'og:image', 'content' => '' );
				}
			}
		}

		foreach ($props as $prop) {
			//ENT_HTML5 was added in php 5.4.0 (we aren't there yet)
			//$wgOut->addHeadItem($prop['property'], '<meta property="' . $prop['property'] . '" content="' . htmlspecialchars($prop['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"/>' . "\n");
			$wgOut->addHeadItem($prop['property'], '<meta property="' . $prop['property'] . '" content="' . htmlspecialchars($prop['content'], ENT_QUOTES, 'UTF-8') . '"/>' . "\n");
		}
	}

	static function isImageExclusionArticle() {
		global $wgTitle, $wgLanguageCode;
		if (!$wgTitle || ($wgLanguageCode == "en" && in_array($wgTitle->getArticleID(), self::$opengraphArticleExclusions))) {
			return true;
		} else {
			return false;
		}
	}

	static function getCurrentTitleMetaDescription() {
		global $wgTitle;
		static $titleTest = null;

		$return = '';
		if ($wgTitle->getNamespace() == NS_MAIN && $wgTitle->getFullText() == wfMessage('mainpage')->text()) {
			$return = wfMessage('mainpage_meta_description')->text();
		} elseif ($wgTitle->getNamespace() == NS_MAIN) {
			$desc = '';
			if (!$titleTest) {
				$titleTest = TitleTests::newFromTitle($wgTitle);
				if ($titleTest) {
					$desc = $titleTest->getMetaDescription();
				}
			}
			if (!$desc) {
				$ami = self::getAMICache();
				$desc = $ami->getDescription();
			}
			if (!$desc) {
				$return = wfMessage('article_meta_description', $wgTitle->getText() )->text();
			} else {
				$return = $desc;
			}
		} elseif ($wgTitle->getNamespace() == NS_CATEGORY) {
			// get keywords
			$subcats = self::getMetaSubcategories($wgTitle, 3);
			$keywords = implode(", ", $subcats);
			if ($keywords) {
				$return = wfMessage('category_meta_description', $wgTitle->getText(), $keywords)->text();
			} else {
				$return = wfMessage('subcategory_meta_description', $wgTitle->getText(), $keywords)->text();
			}
		} elseif ($wgTitle->getNamespace() == NS_USER) {
			$desc = ProfileBox::getMetaDesc();
			$return = $desc;
		} elseif ($wgTitle->getNamespace() == NS_IMAGE) {
			$articles = ImageHelper::getLinkedArticles($wgTitle);
			if (count($articles) && $articles[0]) {
				$articleTitle = wfMessage('howto', $articles[0])->text();
				if (preg_match('@Step (\d+)@', $wgTitle->getText(), $m)) {
					$imageNum = '#' . $m[1];
				} else {
					$imageNum = '';
				}
				$return = wfMessage('image_meta_description', $articleTitle, $imageNum)->text();
			} else {
				$return = wfMessage('image_meta_description_no_article', $wgTitle->getText())->text();
			}
		} elseif ($wgTitle->getNamespace() == NS_SPECIAL && $wgTitle->getText() == "Popularpages") {
			$return = wfMessage('popularpages_meta_description')->text();
		}

		return $return;
	}

	static function getCurrentTitleMetaKeywords() {
		global $wgTitle;

		$return = '';
		if ($wgTitle->getNamespace() == NS_MAIN && $wgTitle->getFullText() == wfMessage('mainpage')->text()) {
			$return = wfMessage('mainpage_meta_keywords')->text();
		} elseif ($wgTitle->getNamespace() == NS_MAIN ) {
			$return = wfMessage('article_meta_keywords', htmlspecialchars($wgTitle->getText()) )->text();
		} elseif ($wgTitle->getNamespace() == NS_CATEGORY) {
			$subcats = self::getMetaSubcategories($wgTitle, 10);
			$return = implode(", ", $subcats);
			if (!trim($return)) {
				$return = wfMessage( 'category_meta_keywords_default', htmlspecialchars($wgTitle->getText()) )->text();
			}
		} elseif ($wgTitle->getNamespace() == NS_SPECIAL && $wgTitle->getText() == "Popularpages") {
			$return = wfMessage('popularpages_meta_keywords')->text();
		}

		return $return;
	}

	static function addTwitterMetaProperties() {
		global $wgTitle, $wgRequest, $wgOut;

		$action = $wgRequest->getVal('action', 'view');

		if($wgTitle->getNamespace() != NS_MAIN || $action != "view")
			return;

		if ( !wfRunHooks( 'ArticleMetaInfoShowTwitterMetaProperties', array() ) ) {
			return;
		}

		$isMainPage = $wgTitle
			&& $wgTitle->getNamespace() == NS_MAIN
			&& $wgTitle->getText() == wfMessage('mainpage')->inContentLanguage()->text()
			&& $action == 'view';

		if (!self::$wgTitleAMIcache) {
			self::$wgTitleAMIcache = new ArticleMetaInfo($wgTitle);
		}
		$ami = self::$wgTitleAMIcache;

		if($isMainPage)
			$twitterTitle = "wikiHow";
		else
			$twitterTitle = wfMessage('howto', $ami->titleText)->text();

		if($isMainPage)
			$twitterDesc = "wikiHow - How to do anything";
		else
			$twitterDesc = $ami->getFacebookDescription();

		if($isMainPage)
			$twitterImg = "/images/7/71/Wh-logo.jpg";
		else
			$twitterImg = $ami->getImage();

		// Note: we use a static string here since the non-English
		//   languages need to refer to the canonical English domain
		//   for vast majority of images
		if ($twitterImg) {
			$twitterImg = 'https://www.wikihow.com' . $twitterImg;
		}

		$wgOut->addHeadItem('tcard', '<meta name="twitter:card" content="summary_large_image"/>' . "\n");
		if (!self::isImageExclusionArticle()) {
			$wgOut->addHeadItem('timage', '<meta name="twitter:image:src" content="' . $twitterImg . '"/>' . "\n");
		}
		$wgOut->addHeadItem('tsite', '<meta name="twitter:site" content="@wikihow"/>' . "\n");
		$wgOut->addHeadItem('tdesc', '<meta name="twitter:description" content="' . htmlspecialchars($twitterDesc) . '"/>' . "\n");

		$wgOut->addHeadItem('ttitle', '<meta name="twitter:title" content="' . htmlspecialchars($twitterTitle) . '"/>' . "\n");
		$wgOut->addHeadItem('turl', '<meta name="twitter:url" content="' . $wgTitle->getFullURL('', false, PROTO_CANONICAL) . '"/>' . "\n");

		if ( class_exists( "IOSHelper" ) && $wgTitle->exists() ) {
			$wgOut->addHeadItem('tappname', '<meta name="twitter:app:name:iphone" content="' . IOSHelper::getAppName() . '"/>' . "\n");
			$wgOut->addHeadItem('tappid', '<meta name="twitter:app:id:iphone" content="' . IOSHelper::getAppId() . '"/>' . "\n");
			$wgOut->addHeadItem('tappurl', '<meta name="twitter:app:url:iphone" content="' . IOSHelper::getArticleUrl( $wgTitle ) . '"/>' . "\n");
		}
	}

	public static function getAMICache() {
		global $wgTitle;
		if (!self::$wgTitleAMIcache) {
			self::$wgTitleAMIcache = new ArticleMetaInfo($wgTitle);
		}
		return self::$wgTitleAMIcache;
	}

	static function addAndroidAppMetaInfo() {
		global $wgTitle, $wgOut;

		$ami = self::getAMICache();
		$img = $ami->getImage();
		if ($img) {
			$img = 'https://www.wikihow.com' . $img;
			$props[] = array( 'name' => 'wh_an:image', 'content' => $img );
		} else {
			$props[] = array( 'name' => 'wh_an:image', 'content' => "" );
		}

		$props[] = array( 'name' => 'wh_an:ns', 'content' => $wgTitle->getNamespace() );

		foreach ($props as $prop) {
			//ENT_HTML5 was added in php 5.4.0 (we aren't there yet)
			//$wgOut->addHeadItem($prop['name'], '<meta name="' . $prop['name'] . '" content="' . htmlspecialchars($prop['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"/>' . "\n");
			$wgOut->addHeadItem($prop['name'], '<meta name="' . $prop['name'] . '" content="' . htmlspecialchars($prop['content'], ENT_QUOTES, 'UTF-8') . '"/>' . "\n");
		}
	}

	/**
	 * Gets the name of the meta image associated with this article
	 * uses static var for wgTitle for speed
	 * and looks up from the DB for non wgTitle
	 *
	 * @return String|null the name of the title image of wgTitle
	 */
	public static function getArticleImageName( $title = null ) {
		global $wgTitle;
		$usingWgTitle = false;

		if ( $wgTitle == $title ) {
			$usingWgTitle = true;
		}

		if ( $title == null ) {
			$title = $wgTitle;
			$usingWgTitle = true;
		}

		// get the static var if exists
		if ( $usingWgTitle && self::$wgTitleAmiImageName ) {
			return self::$wgTitleAmiImageName;
		}

		if ( !$usingWgTitle ) {
			// load from ami db table
			$dbr = wfGetDB(DB_SLAVE);
			$table = 'article_meta_info';
			$var = 'ami_img';
			$cond = array( 'ami_id'  => $title->getArticleID() );
			$imageName = $dbr->selectField( $table, $var, $cond, __METHOD__ );

			return $imageName;
		}

		// get from the AMI class object itself which can regenerate it if missing from db table
		$ami = self::getAMICache();
		if ( !$ami ) {
			return null;
		}

		$amiImage = $ami->getImage();
		self::$wgTitleAmiImageName = $amiImage;
		return $amiImage;

	}

	// gets the article id and defaults to wgtitle
	private static function getArticleID( $title ) {
		if ( !$title ) {
			global $wgTitle;
			$title = $wgTitle;
		}
		return $title->getArticleID();
	}

	public static function getRelatedThumb( $title, $width, $height ) {
		$usePageId = false;
		$crop = true;
		return self::getTitleImageThumb( $title, $width, $height, $usePageId, $crop );
	}

	// get the thumbnail object for the title image of an article
	// defaults to the width of desktop images
	// has optional param to use the page id to generate the new style watermarks
	public static function getTitleImageThumb( $title = null, $width = 728, $height = -1, $usePageId = true, $crop = false ) {

		$pageId = null;
		if ( $usePageId ) {
			$pageId = self::getArticleID( $title );
		}

		// this is the path to the image
		$imageName = self::getArticleImageName( $title );
		if ( !$imageName ) {
			return null;
		}

		// get the final part of the image path which is it's name
		$imageName = end( explode( '/', $imageName ) );
		if ( !$imageName ) {
			return null;
		}

		// get the file from the image name
		$file = RepoGroup::singleton()->findFile( $imageName );
		if ( !$file ) {
			return null;
		}

		if ( $pageId ) {
			$thumb = $file->getThumbnail( $width, $height, true, $crop, false, $pageId );
			return $thumb;
		}
		// get the thumbnail
		$thumb = $file->getThumbnail( $width, $height, true, $crop );

		return $thumb;
	}
}

