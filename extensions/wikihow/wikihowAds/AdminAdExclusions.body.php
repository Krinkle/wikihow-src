<?php

class AdminAdExclusions extends UnlistedSpecialPage {

	const EXCLUSION_TABLE = "adexclusions";

	public function __construct() {
		parent::__construct( 'AdminAdExclusions' );
	}

	public function execute($par) {
		$out = $this->getOutput();
		$req = $this->getRequest();
		$user = $this->getUser();

		$userGroups = $user->getGroups();
		if ($user->isBlocked() || !in_array('staff', $userGroups)) {
			$out->setRobotpolicy('noindex,nofollow');
			$out->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		$submitted = $req->getVal("submitted");
		$list = $req->getVal("list");
		$action = $req->getVal("action");

		if ($submitted == "true") {
			$out->setArticleBodyOnly(true);
			$urlList = $req->getVal("urls");
			$urlArray = explode("\n", $urlList);
			list($articleIds, $errors) = $this->addNewTitles($urlArray);

			if (count($errors) > 0) {
				$result['success'] = false;
				$result['errors'] = $errors;
			}
			else {
				$result['success'] = true;
			}

			// Return the list of articles to purge
			foreach ($articleIds as $langCode => $ids) {
				$result['articleGroups'][] = [
					'langCode' => $langCode,
					'apiUrl' => UrlUtil::getBaseURL($langCode) . '/api.php',
					'articleIds' => $ids
				];
			}

			echo json_encode($result);
		} elseif ($action == "delete"){
			$out->setArticleBodyOnly(true);
			$this->clearAllTitles();
		} elseif ($list == "true") {
			$this->getAllExclusions();
		} else {
			$out->setHTMLTitle("Ad Exclusions");
			$out->setPageTitle("Ad Exclusions");
			$out->addModules('ext.wikihow.ad_exclusions');
			$s = Html::openElement( 'form', array( 'action' => '', 'id' => 'adexclusions' ) ) . "\n";
			$s .= Html::element('p', array(''), "Input full URLs (e.g. http://www.wikihow.com/Kiss) for articles that should not have ads on them. Articles on the www.wikihow.com domain will have ads removed from all translations. Articles on other domains will only have ads removed from that article. Please only process 10 urls at a time.");
			$s .= Html::element('br');
			$s .= Html::element( 'textarea', array('id' => 'urls', 'cols' => 55, 'rows' => 5) ) . "\n";
			$s .= Html::element('br');
			$s .= Html::element( 'input',
					array( 'type' => 'submit', 'class' => "button primary", 'value' => 'Add articles' )
				) . "\n";
			$s .= Html::closeElement( 'form' );
			$s .= Html::element('div', array('id' => 'adexclusions_results'));
			$s .= Html::element('div', array('id' => 'adexclusions_purging'));

			$s .= Html::openElement('form', array('action' => "/Special:AdminAdExclusions", "method" => "post")) . "\n";
			$s .= Html::element('input', array('type' => 'hidden', 'name' => 'list', 'value' => 'true'));
			$s .= Html::element('input', array('type' => 'submit', 'class' => 'button secondary', 'id' => 'adexculsion_list', 'value' => 'Get all articles'));
			$s .= Html::element('a', array('class' => 'button secondary', 'id' => 'adexclusion_list'), 'Delete all titles');
			$s .= Html::closeElement('form');

			$out->addHTML($s);
		}

	}

	/*****
	 * Outputs a csv file that lists out
	 * all urls in all languages that have
	 * ads excluded from them.
	 ***/
	function getAllExclusions() {
		global $wgActiveLanguages;

		$out = $this->getOutput();
		$out->setArticleBodyOnly(true);

		$dbr = wfGetDB(DB_SLAVE);

		$ids = array();
		$this->getPageIdsForLanguage($dbr, $ids, "en");

		foreach ($wgActiveLanguages as $langCode) {
			$this->getPageIdsForLanguage($dbr, $ids, $langCode);
		}

		$pages = Misc::getPagesFromLangIds($ids);

		$date = date('Y-m-d');
		header('Content-type: application/force-download');
		header('Content-disposition: attachment; filename="adexclusions_' . $date . '.xls"');
		foreach ($pages as $page) {
			echo Misc::getLangBaseURL($page["lang"]) . "/" . $page["page_title"] . "\n";
		}
	}

	/****
	 *  Ads all page ids for the given language to the '$ids' array that
	 * have ads excluded from them based on the table.
	 ****/
	function getPageIdsForLanguage(&$dbr, &$ids, $langCode) {
		global $wgDBname;

		if ($langCode == "en")
			$dbr->selectDB($wgDBname);
		else
			$dbr->selectDB('wikidb_'.$langCode);

		$res = $dbr->select(AdminAdExclusions::EXCLUSION_TABLE, "ae_page", array(), __METHOD__);
		foreach ($res as $row) {
			$ids[] = array("lang" => $langCode, "id" => $row->ae_page);
		}
	}

	/*****
	 * Takes an array of full urls (can be on any wH domain)
	 * and adds them to the database of excluded articles.
	 * For urls on www.wikihow.com, it checks titus for any
	 * translations and adds those to the corresponding intl db.
	 * For urls on intl domains, it only adds that article to
	 * that db.
	 ****/
	function addNewTitles($articles): array {
		global $wgDBname;

		$dbw = wfGetDB(DB_MASTER);


		$articles = array_map("urldecode", $articles); // Article URLs submitted by the user
		$pages = Misc::getPagesFromURLs($articles);
		$artIDs = []; // All article IDs including translations, grouped by language code

		foreach ($pages as $page) {

			$langCode = $page['lang'];
			$pageId = $page['page_id'];
			$artIDs[$langCode][] = $pageId;

			// Don't show ads on this article in the current language
			self::addIntlArticle($dbw, $langCode, $pageId);

			if ($langCode == "en") {
				// Don't show ads on any translations of this article
				$artIDs = array_merge_recursive($artIDs, self::processTranslations($dbw, $pageId));
			}
		}

		// Find the ones that didn't work and tell user about them
		$errors = [];
		foreach ($articles as $article) {
			if (!array_key_exists($article, $pages)){
				$errors[] = $article;
			}
		}
		$dbw->selectDB($wgDBname);

		//reset memcache since we just changed a lot of values
		wikihowAds::resetAllAdExclusionCaches();

		return [ $artIDs, $errors ];
	}

	function clearAllTitles() {
		global $wgDBname, $wgActiveLanguages;

		$dbw = wfGetDB(DB_MASTER);
		$dbw->delete(AdminAdExclusions::EXCLUSION_TABLE, "*", [], __METHOD__); //en goes first
		foreach ($wgActiveLanguages as $langCode) {
			$dbw->selectDB('wikidb_' . $langCode);

			$dbw->delete(AdminAdExclusions::EXCLUSION_TABLE, "*", [], __METHOD__);
		}

		//reset memcache since we just changed a lot of values
		wikihowAds::resetAllAdExclusionCaches();
	}

	/****
	 * Given an article id for a title on www.wikihow.com, grabs the titus
	 * data for that article and adds all translations to corresponding
	 * list of excluded articles for those languages
	 ****/
	static function processTranslations(&$dbw, $englishId): array {
		global $wgActiveLanguages;

		$titusData = Pagestats::getTitusData($englishId);
		$articleIds = [];

		foreach ($wgActiveLanguages as $langCode) {
			$intl_id = "ti_tl_{$langCode}_id";
			if (property_exists($titusData->titus, $intl_id)) {
				$articleIds[$langCode][] = $titusData->titus->$intl_id;
				self::addIntlArticle($dbw, $langCode, $titusData->titus->$intl_id);
			}
		}
		return $articleIds;
	}

	/****
	 * Given an article id and a language code, adds the given
	 * article to the associated excluded article table in the
	 * correct language db.
	 ****/
	static function addIntlArticle(&$dbw, $langCode, $articleId) {
		global $wgDBname;

		if ($langCode == "en")
			$dbw->selectDB($wgDBname);
		else
			$dbw->selectDB('wikidb_'.$langCode);

		$sql = "INSERT IGNORE into " . AdminAdExclusions::EXCLUSION_TABLE . " VALUES ({$articleId})";
		$dbw->query($sql, __METHOD__);
	}

	/****
	 * Updates all ad exclusion translations based on the article ids
	 * that are in the English database
	 ****/
	public static function updateEnglishArticles() {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(AdminAdExclusions::EXCLUSION_TABLE, array('ae_page'));

		$dbw = wfGetDB(DB_MASTER);
		foreach ($res as $row) {
			self::processTranslations($dbw, $row->ae_page);
		}
	}

}
