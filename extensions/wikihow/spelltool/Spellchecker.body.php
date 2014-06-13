<?
class Spellchecker extends UnlistedSpecialPage {
	
	var $skipTool;
	const SPELLCHECKER_EXPIRED = 3600; //60*60 = 1 hour
	const SPCH_AVAIL_IDS_KEY = 'spch_avail_ids2';
	const SPCH_IDS_LAST_CHECKED_KEY = 'spch_avail_ids_last_checked';

	function __construct() {
		global $wgHooks;
		parent::__construct('Spellchecker');
		$wgHooks['getToolStatus'][] = array('Misc::defineAsTool');
	}

	function execute($par) {
		global $wgRequest, $wgUser, $wgHooks, $wgDebugToolbar;

		$wgHooks['getBreadCrumbs'][] = array('Spellchecker::getBreadCrumbsCallback');

		$out = $this->getContext()->getOutput();
		
		if ($wgUser->isBlocked()) {
			$out->blockedPage();
			return;
		}

		$out->setRobotpolicy( 'noindex,nofollow' );

		$maintenanceMode = false;
		if ($maintenanceMode) {
			$this->displayMaintenanceMessage($out);
			return;
		}


		wfLoadExtensionMessages("Spellchecker");
		$this->skipTool = new ToolSkip("spellchecker", "spellchecker", "sc_checkout", "sc_checkout_user", "sc_page");

		if ( $wgRequest->getVal('getNext') ) {
			$out->disable();
			$articleName = $wgRequest->getVal('a', "");
			
			$result = self::getNextArticle($articleName);

			// if debug toolbar pass logs back in response
			if ($wgDebugToolbar) {
				$result['debug']['log'] = MWDebug::getLog();
			}

			print_r(json_encode($result));
			return;
		}
		else if ( $wgRequest->getVal('deleteCache') ) {
			if (in_array('staff', $wgUser->getGroups())) {
				self::deleteSpellCheckerCacheKeys();
			}
		}
		else if ($wgRequest->wasPosted()) {
			$out->setArticleBodyOnly(true);
			if ( $wgRequest->getVal('submit')) {
				//user has edited the article from within the Spellchecker tool
				$out->disable();
				$this->submitEdit();
				$result = self::getNextArticle();
				// if debug toolbar pass logs back in response
				if ($wgDebugToolbar) {
					$result['debug']['log'] = MWDebug::getLog();
				}
				print_r(json_encode($result));
				return;
			}
		}

		$out->setHTMLTitle(wfMsg('spellchecker'));
		$out->setPageTitle(wfMsg('spellchecker'));

		if ($wgDebugToolbar) {
			$out->addScript(HtmlSnips::makeUrlTags('js', array('consoledebug.js'), 'extensions/wikihow/debug', false));
		}

		$out->addJSCode('mt');	// Mousetrap
		$out->addHTML(QuickNoteEdit::displayQuickEdit());	// Quick Edit
		$out->addModules('ext.wikihow.spellchecker');	// Spellchecker js and mw messages
		$out->addHTML(HtmlSnips::makeUrlTags('css', array('spellchecker.css'), 'extensions/wikihow/spelltool', false));

		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$out->addHTML($tmpl->execute('Spellchecker.tmpl.php'));

		$indi = new SpellcheckerStandingsIndividual();
		$indi->addStatsWidget();

		$group = new SpellcheckerStandingsGroup();
		$group->addStandingsWidget();
	}

	function getBreadCrumbsCallback(&$breadcrumb) {
		$mainPageObj = Title::newMainPage();
		$spellchecker = Title::newFromText("Spellchecker", NS_SPECIAL);
		$sep = wfMsgHtml( 'catseparator' );
		$breadcrumb = "<li class='home'><a href='{$mainPageObj->getLocalURL()}'>Home</a></li><li>{$sep} <a href='{$spellchecker->getLocalURL()}'>{$spellchecker->getText()}</a></li>";
		return true;
	}

	public static function deleteSpellCheckerCacheKeys() {
		global $wgMemc;
		$wgMemc->delete(wfMemcKey(Spellchecker::SPCH_AVAIL_IDS_KEY));
		$wgMemc->delete(wfMemcKey(Spellchecker::SPCH_IDS_LAST_CHECKED_KEY));
	}

	private function getIds() {
		global $wgMemc;
		$key = wfMemcKey(self::SPCH_AVAIL_IDS_KEY);
		$ids = $wgMemc->get($key);
		if (empty($ids)) {
			$ids = array();
		}

		MWDebug::log("size of ids: " . sizeof($ids));
		// Get 500 more if we drop below 100 available ids to edit
		if (sizeof($ids) < 100) {
			// In case there isn't an array returned from memcache


			// Only get ids once every 30 minutes max
			$lastCheckedKey = wfMemcKey(self::SPCH_IDS_LAST_CHECKED_KEY);
			$lastChecked = $wgMemc->get($lastCheckedKey);

			MWDebug::log("lastChecked: " . wfTimestamp(TS_MW, $lastChecked));
			MWDebug::log("10 min cutoff: " . wfTimestamp(TS_MW, strtotime("-10 minutes")));

			if (!$lastChecked || intVal($lastChecked) < strtotime("-10 minutes")) {
				MWDebug::log("getting new spellchecker ids. Last check was : " . date($lastChecked));
				$dbr = wfGetDB(DB_SLAVE);
				$expired = wfTimestamp(TS_MW, time() - Spellchecker::SPELLCHECKER_EXPIRED);
				$res = $dbr->select('spellchecker',
					'sc_page',
					array('sc_exempt' => 0, 'sc_errors' => 1, 'sc_dirty' => 0, "sc_checkout < '{$expired}'"),
					__METHOD__,
					array("LIMIT" => 500, "ORDER BY" => "RAND()"));

				$newIds = array();
				while ($row = $dbr->fetchObject($res)) {
					$newIds[] = $row->sc_page;
				}

				$ids = array_unique(array_merge($ids, $newIds));
				shuffle($ids);

				$lastChecked = time();
				$wgMemc->set($lastCheckedKey, $lastChecked);
				MWDebug::log('setting ids in memcache from getIds(). ids to be set' .  print_r($ids, true));
				$wgMemc->set($key, $ids);
			}
 		}
		return $ids;
	}

	private function getNextId() {
		global $wgMemc;

		$ids = $this->getIds();
		MWDebug::log("ids before pop: " . print_r($ids, true));
		$id = array_pop($ids);
		MWDebug::log("id popped: " . $id);
		MWDebug::log("ids after pop: " . print_r($ids, true));

		$key = wfMemcKey(self::SPCH_AVAIL_IDS_KEY);
		MWDebug::log('setting ids in memcache from getNextId(). ids to be set' . print_r($ids, true));
		$wgMemc->set($key, $ids);

		return $id;
	}

	function getNextArticle($articleName = '') {
		global $wgOut;
		
		$dbr = wfGetDB(DB_SLAVE);

		$title = Title::newFromText($articleName);
		if($title && $title->getArticleID() > 0) {
			$articleId = $title->getArticleID();
		}
		else {
			$articleId = $this->getNextId();
        }

		if ($articleId) {
			$sql = "SELECT * from `spellchecker_page` JOIN `spellchecker_word` ON sp_word = sw_id WHERE sp_page = {$articleId}"; 
			$res =  $dbr->query($sql, __METHOD__);

			$wordMap = array();
			while ($row = $dbr->fetchObject($res)) {
				$word = $row->sw_word;
				$wordMap[] = array('misspelled' => $word, 'correction' => "", 'key' => $row->sp_key, 'key_count' => $row->sp_key_count);
			}

			if (sizeof($wordMap) > 0) {
				$title = Title::newFromID($articleId);
				if ($title) {
					$revision = Revision::newFromTitle($title, $title->getLatestRevID());
					if ($revision) {
						$content['title'] = "<a href='{$title->getFullURL()}' target='new'>" . wfMsg('howto', $title->getText()) . "</a>";
						$content['articleId'] = $title->getArticleID();
						$content['words'] = $wordMap;

						$popts = $wgOut->parserOptions();
						$popts->setTidy(true);
						$parserOutput = $wgOut->parse($revision->getText(), $title, $popts);
						$magic = WikihowArticleHTML::grabTheMagic($revision->getText());
						$html = WikihowArticleHTML::processArticleHTML($parserOutput, array('no-ads' => true, 'ns' => NS_MAIN, 'magic-word' => $magic));
						$content['html'] = $html;
						$content['qeurl'] = QuickEdit::getQuickEditUrl($title);

						$this->skipTool->useItem($articleId);
						return $content;
					}
				}
			}
		}
		//return error message
        $content['lastQuery'] = $dbr->lastQuery();
		$content['error'] = wfMessage('spch-error-noarticles')->text();

		return $content;
	}

	/*
	 *
	 * Processes an article submit
	 *
	 */
	private function submitEdit() {
		global $wgRequest, $wgUser;
		$user = $this->getContext()->getUser();
		$t = Title::newFromID($wgRequest->getVal('articleId'));
		if ($t && $t->exists() && $t->userCan('edit', false)) {
			$a = WikiPage::factory($t);
			if ($a && $a->exists()) {
				$text = ContentHandler::getContentText($a->getContent());
				$result = $this->replaceMisspelledWords($text);
				if ($result['replaced']) {
					//save the edit
					$summary = wfMessage('spch-edit-summary')->text();
					$content = ContentHandler::makeContent( $text, $t );
					$a->doEditContent($content, $summary, EDIT_UPDATE);
					wfRunHooks("Spellchecked", array($wgUser, $t, '0'));
				}

				// Add a log entry
				$log = new LogPage( 'spellcheck', false ); // false - dont show in recentchanges, it'll show up for the doEdit call
				$msg = wfMsgHtml('spch-edit-message', "[[{$t->getText()}]]");
				$entryType = $result['replaced'] ? 'edit' : '';
				$log->addEntry($entryType, $t, $msg, null);

				// Remove article from spellchecker queue if the user
				// whitelisted or replaced at least one word
				if ($result['replaced'] || $result['whitelisted']) {
					// Set this to no errors so it doesn't get sucked back into the spell checker queue until another edit
					$dbw = wfGetDB(DB_MASTER);
					$dbw->update('spellchecker', array('sc_errors' => 0), array('sc_page' => $t->getArticleID()));

				}
				$this->skipTool->unUseItem($a->getID());
			}

		}
	}

	private function replaceMisspelledWords(&$text) {
		$request = $this->getRequest();
		$words = $request->getArray('words');
		$whitelistWords = array();
		$replaced = false;
		$whitelisted = false;
		foreach ($words as $word) {
			if ($word['misspelled'] != $word['correction'] && $word['correction'] != "") {
				$replacementKey = str_replace($word['misspelled'], $word['correction'], $word['key']);
				$text = str_replace($word['key'], $replacementKey, $text);
				$replaced = true;
			} else if ($word['misspelled'] == $word['correction']) {
				$whitelistWords[] = $word['misspelled'];
				$whitelisted = true;
			}
		}

		if (sizeof($whitelistWords) > 0) {
			wikiHowDictionary::batchAddWordsToWhitelist($whitelistWords);
		}
		return array('replaced' => $replaced, 'whitelisted' => $whitelisted);
	}
	
	static function markAsDirty($id) {
		$dbw = wfGetDB(DB_MASTER);
		
		$sql = "INSERT INTO spellchecker (sc_page, sc_timestamp, sc_dirty, sc_errors, sc_exempt) VALUES (" . 
					$id . ", " . wfTimestampNow() . ", 1, 0, 0) ON DUPLICATE KEY UPDATE sc_dirty = '1', sc_timestamp = " . wfTimestampNow();
		$dbw->query($sql, __METHOD__);
	}
	
	static function markAsIneligible($id) {
		$dbw = wfGetDB(DB_MASTER);
		
		$dbw->update('spellchecker', array('sc_errors' => 0, 'sc_dirty' => 0), array('sc_page' => $id), __METHOD__);
	}

	/**
	 * @param $out
	 */
	private function displayMaintenanceMessage($out)
	{
		wfLoadExtensionMessages("Spellchecker");

		$out->setHTMLTitle(wfMsg('spellchecker'));
		$out->setPageTitle(wfMsg('spellchecker'));

		$out->addWikiText("This tool is temporarily down for maintenance. Please check out the [[Special:CommunityDashboard|Community Dashboard]] for other ways to contribute while we iron out a few issues with this tool. Happy editing!");
		return;
	}

}

class Spellcheckerwhitelist extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct('Spellcheckerwhitelist');
	}

	function execute($par) {
		global $IP, $wgOut, $wgUser, $wgHooks;
		
		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}
		
		if ($wgUser->getID() == 0) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		
		$isStaff = in_array('staff', $wgUser->getGroups());
		
		wfLoadExtensionMessages("Spellchecker");

		
		$dbr = wfGetDB(DB_SLAVE);
		
		$wgOut->addWikiText(wfMsg('spch-whitelist-inst'));
		
		$words = array();
		$res = $dbr->select(wikiHowDictionary::WHITELIST_TABLE, "*", '', __METHOD__);
		while($row = $dbr->fetchObject($res)) {
			$words[] = $row;
		}
		asort($words);
		
		$res = $dbr->select(wikiHowDictionary::CAPS_TABLE, "*", '', __METHOD__);

		$caps = array();
		while($row = $dbr->fetchObject($res)) {
			$caps[] = $row->sc_word;
		}
		asort($caps);
		
		$wgOut->addHTML("<ul>");
		foreach($words as $word) {
			if($word->{wikiHowDictionary::WORD_FIELD} != "")
				$wgOut->addHTML("<li>" . $word->{wikiHowDictionary::WORD_FIELD} );
			if($isStaff && $word->{wikiHowDictionary::USER_FIELD} > 0) {
				$user = User::newFromId($word->{wikiHowDictionary::USER_FIELD});
				$wgOut->addHTML(" (" . $user->getName() . ")");
			}
			$wgOut->addHTML("</li>");
		}
		
		foreach($caps as $word) {
			if($word != "")
				$wgOut->addHTML("<li>" . $word . "</li>");
		}
		
		$wgOut->addHTML("</ul>");
		
		$wgOut->setHTMLTitle(wfMsg('spch-whitelist'));
		$wgOut->setPageTitle(wfMsg('spch-whitelist'));
	}
}

class SpellcheckerArticleWhitelist extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct('SpellcheckerArticleWhitelist');
	}

	function execute($par) {
		global $IP, $wgOut, $wgUser, $wgRequest;
		
		if($wgUser->getID() == 0 || !($wgUser->isSysop() || in_array( 'newarticlepatrol', $wgUser->getRights() ))) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		
		wfLoadExtensionMessages("Spellchecker");
		
		$this->skipTool = new ToolSkip("spellchecker", "spellchecker", "sc_checkout", "sc_checkout_user", "sc_page");

		$message = "";
		if ( $wgRequest->wasPosted() ) {
			$articleText = $wgRequest->getVal('articleName');
			$title = Title::newFromText($articleText);

			if($title && $title->getArticleID() > 0) {
				if($this->addArticleToWhitelist($title))
					$message = $title->getText() . " was added to the article whitelist.";
				else
					$message = $articleText . " could not be added to the article whitelist.";
			}
			else
				$message = $articleText . " could not be added to the article whitelist.";
		}
		
		$tmpl = new EasyTemplate( dirname(__FILE__) );
		
		$tmpl->set_vars(array('message' => $message));

		$wgOut->addHTML($tmpl->execute('ArticleWhitelist.tmpl.php'));
				
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select("spellchecker", "sc_page", array("sc_exempt" => 1));
		
		$wgOut->addHTML("<ol>");
		while($row = $dbr->fetchObject($res)) {
			$title = Title::newFromID($row->sc_page);
			
			if($title)
				$wgOut->addHTML("<li><a href='" . $title->getFullURL() . "'>" . $title->getText() . "</a></li>");
		}
		$wgOut->addHTML("</ol>");
		
		$wgOut->setHTMLTitle(wfMsg('spch-articlewhitelist'));
		$wgOut->setPageTitle(wfMsg('spch-articlewhitelist'));
	}

	function addArticleToWhitelist($title) {
		$dbw = wfGetDB(DB_MASTER);
		
		$sql = "INSERT INTO spellchecker (sc_page, sc_timestamp, sc_dirty, sc_errors, sc_exempt) VALUES (" . 
					$title->getArticleID() . ", " . wfTimestampNow() . ", 0, 0, 1) ON DUPLICATE KEY UPDATE sc_exempt = '1', sc_errors = 0, sc_timestamp = " . wfTimestampNow();
		return $dbw->query($sql);
	}
}

class wikiHowDictionary {
	const DICTIONARY_LOC	= "/maintenance/spellcheck/custom.pws";
	const WHITELIST_TABLE	= "spellchecker_whitelist";
	const CAPS_TABLE		= "spellchecker_caps";
	const WORD_TABLE		= "spellchecker_word";
	const WORD_FIELD		= "sw_word";
	const USER_FIELD		= "sw_user";
	const VOTES_FIELD		= "sw_votes";
	const ACTIVE_FIELD		= "sw_active";
	const MIN_VOTES			= 5;
	
	/***
	 * 
	 * Takes the given word and, if allowed, adds it
	 * to the temp table in the db to be added
	 * to the dictionary at a later time
	 * (added via cron on the hour)
	 * 
	 */
	static function addWordToWhitelist($word) {
		global $wgUser, $wgMemc;

		$word = strtolower(trim($word));
		$userId = $wgUser->getId();
		// Admins get 2 votes, everyone else gets 1
		$votes = in_array('sysop', $wgUser->getGroups()) ? 2 : 1;


		//now check to see if the word can be added to the library
		//only allow a-z and apostrophe
		//check for numbers
		if ( preg_match("@[^a-z']@", $word) ) {
			return false;
		}

		$dbw = wfGetDB(DB_MASTER);
		$word = $dbw->strencode($word);
		$sql = "INSERT INTO "
			. self::WHITELIST_TABLE
			. " (" . self::WORD_FIELD . "," . self::USER_FIELD . "," . self::ACTIVE_FIELD . "," . self::VOTES_FIELD . ") "
			. " VALUES "
			. " ('$word', $userId, 0, $votes)"
			. " ON DUPLICATE KEY UPDATE"
		 	. " " . self::VOTES_FIELD . " = " . self::VOTES_FIELD . " + $votes";
		$dbw->query($sql);

		$key = wfMemcKey('spellchecker_whitelist');
		$wgMemc->delete($key);
		
		return true;
	}

	static function batchAddWordsToWhitelist($words) {
		global $wgUser, $wgMemc;

		$dbw = wfGetDB(DB_MASTER);
		$userId = $wgUser->getId();
		// Admins get 2 votes, everyone else gets 1
		$votes = in_array('sysop', $wgUser->getGroups()) ? 2 : 1;

		$wordsToAdd = array();
		foreach ($words as $word) {
			$word = strtolower(trim($word));

			//now check to see if the word can be added to the library
			//only allow a-z and apostrophe
			//check for numbers
			if ( preg_match("@[^a-z']@", $word) ) {
				continue;
			}
			$word = $dbw->strencode($word);
			$wordsToAdd[] = $word;
		}


		if (!empty($wordsToAdd)) {
			$table = self::WHITELIST_TABLE;

			$keys = array(self::WORD_FIELD, self::VOTES_FIELD);
			$keys = "(" . implode(",", $keys) . ")";

			$values = array();
			foreach ($wordsToAdd as $word) {
				$values[] = "('" . $word . "', $votes)";
			}
			$values = implode(",", $values);

			$sql = "INSERT IGNORE INTO $table $keys VALUES $values";
			$sql .= " ON DUPLICATE KEY UPDATE " . self::VOTES_FIELD . "= $votes + " . self::VOTES_FIELD;
			$dbw->query($sql);

			$key = wfMemcKey('spellchecker_whitelist');
			$wgMemc->delete($key);
		}

		return true;
	}

	static function addWordsToWhitelist($words) {
		$success = true;
		
		foreach($words as $word) {
			$success = wikiHowDictionary::addWordToWhitelist($word) && $success;
		}
		
		return $success;
	}
	
	static function invalidateArticlesWithWord(&$dbr, &$dbw, $word) {
		//now go through and check articles that contain that word.
		$sql = "SELECT * FROM `" . self::WORD_TABLE . "` JOIN `spellchecker_page` ON `sp_word` = `sw_id` WHERE sw_word = " . $dbr->addQuotes($word);
		$res = $dbr->query($sql, __METHOD__);

		while($row = $dbr->fetchObject($res)) {
			$page_id = $row->sp_page;
			$dbw->update('spellchecker', array('sc_dirty' => "1"), array('sc_page' => $page_id), __METHOD__);
		}
	}

	/***
	 * 
	 * Gets a link to the pspell library
	 * 
	 */
	static function getLibrary() {
		global $IP;
		$pspell_config = pspell_config_create("en", 'american');
		pspell_config_mode($pspell_config, PSPELL_FAST);
		//no longer using the custom dictionary
		//pspell_config_personal($pspell_config, $IP . wikiHowDictionary::DICTIONARY_LOC);
		$pspell_link = pspell_new_config($pspell_config);

		return $pspell_link;
	}
	
	/***
	 * 
	 * Checks the given word using the pspell library
	 * and our internal whitelist
	 * 
	 * Returns: -1 if the word is ok
	 *			id of the word in the spellchecker_word table
	 * 
	 */
	function spellCheckWord(&$dbw, $word, &$pspell, &$wordArray) {


		// Ignore upper-case
		if (strtoupper($word) == $word) {
			return -1;
		}

		//check against our internal whitelist
		// only check lowercase
		if($wordArray[strtolower($word)] === true) {
			return -1;
		}



		//if only the first letter is capitalized, then
		//uncapitalize it and see if its in our list
//		$regWord = lcfirst($word);
//		if($wordArray[$regWord] === true) {
//			return -1;
//		}

		// Skip word if it has any special characters
		if (strpos($word, "''") !== false
			/*|| preg_match("@^'|'$@", $word)*/
			|| preg_match("/[\[\]\{\}!@#$%^&*(()-+=_:;<>?\"]+/m", $word)) {
			return -1;
		}

		// Ignore numbers
		//if (preg_match('/^[A-Z]*$/',$word)) return;
		if (preg_match('/[0-9]/',$word)) {
			return - 1;
		}
		
		// Return dictionary words
		if (pspell_check($pspell,$word)) {
			return -1;
		}
		

		$suggestions = pspell_suggest($pspell,$word);
		$corrections = "";
		if (sizeof($suggestions) > 0) {
			if (sizeof($suggestions) > 5) {
				$corrections = implode(",", array_splice($suggestions, 0, 5));
			} else {
				$corrections = implode(",", $suggestions);
			}
		} 
		
		//first check to see if it already exists
		$id = $dbw->selectField(self::WORD_TABLE, 'sw_id', array('sw_word' => $word), __METHOD__);
		if ($id === false) {
			$dbw->insert(self::WORD_TABLE, array('sw_word' => $word, 'sw_corrections' => $corrections), __METHOD__);
			$id = $dbw->insertId();
		}

		return $id;

	}
	
	/******
	 * 
	 * Returns an array of words that make up our internal whitelist.
	 * 
	 ******/
	static function getWhitelistArray() {
		global $wgMemc;
		
		$key = wfMemcKey('spellchecker_whitelist');
		$wordArray = $wgMemc->get($key);
		
		if(!is_array($wordArray)) {
			$dbr = wfGetDB(DB_SLAVE);
			$res = $dbr->select(wikiHowDictionary::WHITELIST_TABLE,
				'*', array('sw_votes > '. self::MIN_VOTES), __METHOD__);
			
			$wordArray = array();
			foreach($res as $word) {
				$wordArray[$word->sw_word] = true;
			}
			
			$wgMemc->set($key, $wordArray);
		}
		
		return $wordArray;
		
		
	}
	
	/***
	 * 
	 * Returns a string with all the CAPS words in them
	 * to compare against words that are in articles
	 * 
	 */
	static function getCaps() {
		$dbr = wfGetDB(DB_SLAVE);
		
		$res = $dbr->select(self::CAPS_TABLE, "*", '', __METHOD__);
		
		$capsString = "";
		while($row = $dbr->fetchObject($res)) {
			$capsString .= " " . $row->sc_word . " ";
		}
		
		return $capsString;
	}
}

class ProposedWhitelist extends UnlistedSpecialPage {
	
	function __construct() {
		parent::__construct('ProposedWhitelist');
	}
	
	function execute($par) {
		global $wgOut, $wgUser, $wgRequest;
		
		if($wgUser->getID() == 0 || !($wgUser->isSysop() || in_array( 'newarticlepatrol', $wgUser->getRights() ) || $wgUser->getName() == "Gloster flyer" || $wgUser->getName() == "Byankno1" )) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		
		wfLoadExtensionMessages('Spellchecker');
		
		$wgOut->setHTMLTitle('Spellchecker Proposed Whitelist');
		$wgOut->setPageTitle('Spellchecker Proposed Whitelist');
		
		$wgOut->addHTML("<style type='text/css' media='all'>/*<![CDATA[*/ @import '" . wfGetPad('/extensions/min/f/extensions/wikihow/spellchecker/spellchecker.css?') . WH_SITEREV . "'; /*]]>*/</style> ");
		
		$dbr = wfGetDB(DB_SLAVE);
		
		$wgOut->addHTML("<p>" . wfMsgWikiHtml('spch-proposedwhitelist') . "</p>");
		
		if ($wgRequest->wasPosted()) {
			$wordsAdded = array();
			$wordsRemoved = array();
			$dbw = wfGetDB(DB_MASTER);
			foreach ($wgRequest->getValues() as $key=>$value) {
				$wordId = intval(substr($key, 5)); // 5 = "word-"
				$word = $dbr->selectField(wikiHowDictionary::WHITELIST_TABLE, 'sw_word', array('sw_id' => $wordId), __METHOD__);
				$msg = "";
				switch($value) {
					case "lower":
						$lWord = lcfirst($word);
						$lWordId = $dbr->selectField(wikiHowDictionary::WHITELIST_TABLE, 'sw_id', array('sw_word' => $lWord), __METHOD__);
						
						if($lWordId == $wordId) {
							//submitting the same word as was entered
							$dbw->update(wikiHowDictionary::WHITELIST_TABLE, array('sw_active' => 1), array('sw_id' => $wordId) );
							$msg = "Accepted {$word} into the whitelist";
						} else {
							//they've chosen to make it lowercase, when it wasn't to start
							if($lWordId === false) {
								//doesn't exist yet
								$dbw->insert(wikiHowDictionary::WHITELIST_TABLE, array(wikiHowDictionary::WORD_FIELD => $lWord, wikiHowDictionary::USER_FIELD => $wgUser->getID(), wikiHowDictionary::ACTIVE_FIELD => 1), __METHOD__, "IGNORE");
							}
							else {
								//already exists, so update it
								$dbw->update(wikiHowDictionary::WHITELIST_TABLE, array('sw_active' => 1), array('sw_id' => $lWordId) );
							}
							
							$dbw->delete(wikiHowDictionary::WHITELIST_TABLE, array('sw_id' => $wordId));
							$msg = "Put {$lWord} into whitelist as lowercase. Removed uppercase version.";
						}
						
						wikiHowDictionary::invalidateArticlesWithWord($dbr, $dbw, $lWord);
						$wordsAdded[] = $lWord;
						break;
					case "reject":
						$dbw->delete(wikiHowDictionary::WHITELIST_TABLE, array('sw_id' => $wordId));
						$msg = "Removed {$word} from the whitelist";
						$wordsRemoved[] = $word;
						break;
					case "caps":
						$uWord = ucfirst($word);
						$uWordId = $dbr->selectField(wikiHowDictionary::WHITELIST_TABLE, 'sw_id', array('sw_word' => $uWord), __METHOD__);
						if($uWordId == $wordId) {
							//submitting the same word as was entered
							$dbw->update(wikiHowDictionary::WHITELIST_TABLE, array('sw_active' => 1), array('sw_id' => $wordId) );
							$msg = "Accepted {$word} into the whitelist";
						} else {
							//they've chosen to make it lowercase, when it wasn't to start
							if($uWordId === false) {
								//doesn't exist yet
								$dbw->insert(wikiHowDictionary::WHITELIST_TABLE, array(wikiHowDictionary::WORD_FIELD => $uWord, wikiHowDictionary::USER_FIELD => $wgUser->getID(), wikiHowDictionary::ACTIVE_FIELD => 1), __METHOD__, "IGNORE");
							}
							else {
								//already exists, so update it
								$dbw->update(wikiHowDictionary::WHITELIST_TABLE, array('sw_active' => 1), array('sw_id' => $uWordId) );
							}
							
							$dbw->delete(wikiHowDictionary::WHITELIST_TABLE, array('sw_id' => $wordId));
							$msg = "Put {$uWord} into whitelist as uppercase. Removed lowercase version.";
						}
						wikiHowDictionary::invalidateArticlesWithWord($dbr, $dbw, $uWord);
						$wordsAdded[] = $uWord;
						break;
					case "ignore":
					default:
						break;
				}
				
				if($msg != "") {
					$log = new LogPage( 'whitelist', false ); // false - dont show in recentchanges
					$t = Title::newFromText("Special:ProposedWhitelist");
					$log->addEntry($value, $t, $msg);
				}
			}
			
			if(count($wordsAdded) > 0) 
				$wgOut->addHTML("<p><b>" . implode(", ", $wordsAdded) . "</b> " . ((count($wordsAdded)>1)?"were":"was") . " added to the whitelist.</p>");
			if(count($wordsRemoved) > 0)
				$wgOut->addHTML("<p><b>" . implode(", ", $wordsRemoved) . "</b> " . ((count($wordsRemoved)>1)?"were":"was") . " were removed from the whitelist.</p>");
						
		}	
		
		//show table
		list( $limit, $offset ) = wfCheckLimits(50, '');
		
		$res = $dbr->select(wikiHowDictionary::WHITELIST_TABLE, '*', array(wikiHowDictionary::ACTIVE_FIELD => 0), __METHOD__, array("LIMIT" => $limit, "OFFSET" => $offset));
		$num = $dbr->numRows($res);

		$words = array();
		foreach($res as $item) {
			$words[] = $item;
		}
		
		$paging = wfViewPrevNext( $offset, $limit, "Special:ProposedWhitelist", "", ( $num < $limit ) );

		$wgOut->addHTML("<p>{$paging}</p>");
		
		//ok, lets create the table
		$wgOut->addHTML("<form name='whitelistform' action='/Special:ProposedWhitelist' method='POST'>");

		$wgOut->addHTML("<table id='whitelistTable' cellspacing='0' cellpadding='0'><thead><tr>");
		$wgOut->addHTML("<td>Word</td><td class='wide'>Correctly spelled,<br /> always require that the first letter be capitalized</td><td class='wide'>Correctly spelled,<br /> do not require first letter to be capitalized</td><td>Reject - not a word</td><td>I'm not sure</td></tr></thead>");

		/////SAMPLES
		$wgOut->addHTML("<tr class='sample'><td class='word'>kiittens</td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='caps' name='sample-1'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='lower' name='sample-1'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' checked='checked' value='reject' name='sample-1'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='ignore' name='sample-1'></td>");
		$wgOut->addHTML("</tr>");
		$wgOut->addHTML("<tr class='sample'><td class='word'>hawaii</td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' checked='checked' value='caps' name='sample-2'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='lower' name='sample-2'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='reject' name='sample-2'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='ignore' name='sample-2'></td>");
		$wgOut->addHTML("</tr>");
		$wgOut->addHTML("<tr class='sample'><td class='word'>Dude</td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='caps' name='sample-3' /></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' checked='checked' value='lower' name='sample-3' /></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='reject' name='sample-3' /></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='ignore' name='sample-3' /></td>");
		$wgOut->addHTML("</tr>");
		
		if(count($words) == 0) {
			//no words waiting to be approved
			$wgOut->addHTML("<tr><td colspan='5' class='word'>No words to approve right now. Please check back again later</td></tr>");
		} 
		else {
			foreach($words as $word) {
				$firstLetter = substr($word->{wikiHowDictionary::WORD_FIELD}, 0, 1);
				$wgOut->addHTML("<tr><td class='word'>" . $word->{wikiHowDictionary::WORD_FIELD} . " [<a target='_blank' href='http://www.google.com/search?q=" . $word->{wikiHowDictionary::WORD_FIELD} . "'>?</a>]</td>");
				$wgOut->addHTML("<td><input type='radio' value='caps' name='word-" . $word->sw_id . "'></td>");
				$wgOut->addHTML("<td><input type='radio' value='lower' name='word-" . $word->sw_id . "'></td>");
				$wgOut->addHTML("<td><input type='radio' value='reject' name='word-" . $word->sw_id . "'></td>");
				$wgOut->addHTML("<td><input type='radio' value='ignore' name='word-" . $word->sw_id . "' checked='checked'></td>");
				$wgOut->addHTML("</tr>");
			}
			
			$wgOut->addHTML("<tr><td colspan='5'><input type='button' onclick='document.whitelistform.submit();' value='Submit' class='guided-button' /></td></tr>");
		}
		
		$wgOut->addHTML("</table></form>");
	}
}
