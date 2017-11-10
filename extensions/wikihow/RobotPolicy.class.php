<?php

/*
TODO (Added by Alberto on Nov 9, 2016):

There are "index_info.ii_page" values which are missing from the "page" table:
select * from index_info where ii_page not in (select page_id from page);

One reason for this has to do with pages being moved. We could create a hook that removes
expired entries from the "index_info" table.
 */

$wgHooks['BeforePageDisplay'][] = array('RobotPolicy::setRobotPolicy');
// need to change to save complete so that new articles get proccessed correctly
$wgHooks['PageContentSaveComplete'][] = array('RobotPolicy::recalcArticlePolicy');
// no need to do demote since an edit happens with that
$wgHooks['NABMarkPatrolled'][] = array('RobotPolicy::recalcArticlePolicyBasedOnId');
$wgHooks['ArticleDelete'][] = array('RobotPolicy::onArticleDelete');
$wgHooks['TitleMoveComplete'][] = array('RobotPolicy::onTitleMoveComplete');

class RobotPolicy {

	const POLICY_INDEX_FOLLOW = 1;
	const POLICY_NOINDEX_FOLLOW = 2;
	const POLICY_NOINDEX_NOFOLLOW = 3;
	const POLICY_DONT_CHANGE = 4;

	const POLICY_INDEX_FOLLOW_STR = 'index,follow';
	const POLICY_NOINDEX_FOLLOW_STR = 'noindex,follow';
	const POLICY_NOINDEX_NOFOLLOW_STR = 'noindex,nofollow';

	const TABLE_NAME = "index_info";

	var $title, $wikiPage, $request;

	private function __construct($title, $wikiPage, $request = null) {
		$this->title = $title;
		$this->wikiPage = $wikiPage;
		$this->request = $request;
	}

	public static function setRobotPolicy($out) {
		$context = $out ? $out->getContext() : null;
		if ($context) {
			$title = $context->getTitle();
			$robotPolicy = self::newFromTitle($title, $context);
			list($policy, $policyText) = $robotPolicy->genRobotPolicyLong();

			switch ($policy) {
			case self::POLICY_NOINDEX_FOLLOW:
				$out->setRobotPolicyCustom(self::POLICY_NOINDEX_FOLLOW_STR, $policyText);
				break;
			case self::POLICY_NOINDEX_NOFOLLOW:
				$out->setRobotPolicyCustom(self::POLICY_NOINDEX_NOFOLLOW_STR, $policyText);
				break;
			case self::POLICY_INDEX_FOLLOW:
				$out->setRobotPolicyCustom(self::POLICY_INDEX_FOLLOW_STR, $policyText);
				break;
			case self::POLICY_DONT_CHANGE:
				$oldPolicy = $out->getRobotPolicy();
				$out->setRobotPolicyCustom($oldPolicy, $policyText);
				break;
			}
		}
		return true;
	}

	private static function newFromTitle($title, $context = null) {
		if (!$title) {
			return null;
		} elseif ($context) {
			$wikiPage = $title->exists() ? $context->getWikiPage() : null;
			return new RobotPolicy($title, $wikiPage, $context->getRequest());
		} else {
			$wikiPage = $title->exists() ? WikiPage::factory($title) : null;
			return new RobotPolicy($title, $wikiPage);
		}
	}

	public static function isIndexablePolicy(int $policy): bool {
		$indexablePolicies = [self::POLICY_INDEX_FOLLOW, self::POLICY_DONT_CHANGE];
		return in_array($policy, $indexablePolicies);
	}

	/**
	 * Determine whether current page view is indexable. This is based on the
	 * status of the article content (for example: whether it has a stub tag),
	 * and on the request parameters.
	 */
	public static function isIndexable($title, $context = null) {
		$policy = self::newFromTitle($title, $context);
		if ($policy && self::isIndexablePolicy($policy->genRobotPolicy())) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Find out if the title itself is indexable. This is different from the
	 * isIndexable method in that it does not consider factors related to the
	 * request itself, such as the printable or variant cgi params.
	 *
	 * Note: this method is a modified version of the isIndexable() method above.
	 */
	public static function isTitleIndexable($title, $context = null) {
		$robotPolicy = self::newFromTitle($title, $context);
		if ($robotPolicy) {
			list($policyNumber, $policyText) =  $robotPolicy->getRobotPolicyBasedOnTitle();
			if (self::isIndexablePolicy($policyNumber)) {
				return true;
			}
		}
		return false;
	}

	public static function getTitusPolicy(&$title) {
		$policyString = "";
		$timestamp = "";
		if ($title) {
			$dbr = wfGetDB(DB_SLAVE);
			$row = $dbr->selectRow(RobotPolicy::TABLE_NAME, array('ii_policy', 'ii_timestamp'), array('ii_page' => $title->getArticleID()), __METHOD__);

			if ($row) {
				$policy = $row->ii_policy;
				$timestamp = $row->ii_timestamp;
				switch ($policy) {
					case self::POLICY_NOINDEX_FOLLOW:
						$policyString = self::POLICY_NOINDEX_FOLLOW_STR;
						break;
					case self::POLICY_NOINDEX_NOFOLLOW:
						$policyString = self::POLICY_NOINDEX_NOFOLLOW_STR;
						break;
					case self::POLICY_INDEX_FOLLOW:
						$policyString = self::POLICY_INDEX_FOLLOW_STR;
						break;
					case self::POLICY_DONT_CHANGE:
						$policyString = self::POLICY_INDEX_FOLLOW_STR;
						break;
				}
			}
		}

		return array($policyString, $timestamp);
	}

	public function genRobotPolicyLong() {
		// First, we compute any indexation that isn't based based on
		// article ID but on request details or non-existence of article.
		// Note: these are generally "cheap" checks in terms of resources
		// and time to compute.
		if ($this->isPrintable()) {
			$policy = self::POLICY_NOINDEX_NOFOLLOW;
			$policyText = 'isPrintable';
		} elseif ($this->isVariant()) {
			$policy = self::POLICY_NOINDEX_FOLLOW;
			$policyText = 'isVariant';
		} elseif ($this->isNoRedirect()) {
			$policy = self::POLICY_NOINDEX_FOLLOW;
			$policyText = 'isNoRedirect';
		} elseif ($this->isOriginCDN()) {
			$policy = self::POLICY_NOINDEX_NOFOLLOW;
			$policyText = 'isOriginCDN';
		} elseif ($this->hasOldidParam()) {
			$policy = self::POLICY_NOINDEX_NOFOLLOW;
			$policyText = 'hasOldidParam';
		} elseif ($this->isNonExistentPage()) {
			$policy = self::POLICY_NOINDEX_NOFOLLOW;
			$policyText = 'isNonExistentPage';
		} else {
			// Note: we do not need to check if $this->title exists after this
			// point. The reason is that isNonExistentPage() has been called.
			list($policy, $policyText) = $this->getRobotPolicyBasedOnTitle();
		}

		return array($policy, $policyText);
	}

	public function getRobotPolicyBasedOnTitle() {
		$cache = wfGetCache(CACHE_MEMSTATIC);
		$cachekey = self::getCacheKey($this->title);
		$res = $cache->get($cachekey);

		if (is_array($res)) {
			$policy = $res['policy'];
			$policyText = $res['text'] . '_cached';
			return array($policy, $policyText);
		}

		if ( $this->title->getArticleID() <= 0 || !$this->title->inNamespace(NS_MAIN) ) {
			//not an article page, so we don't store in the db
			list($policy, $policyText) = $this->generateRobotPolicyBasedOnTitle();
		} else {
			//now check the db
			$dbr = wfGetDB(DB_SLAVE);
			$row = $dbr->selectRow(RobotPolicy::TABLE_NAME, array('ii_policy', 'ii_reason'), array('ii_page' => $this->title->getArticleID()), __METHOD__);

			if ( $row ) {
				$policy = $row->ii_policy;
				$policyText = $row->ii_reason;
			} else {
				//we shouldn't ever need this, but just in case
				list($policy, $policyText) = $this->generateRobotPolicyBasedOnTitle();
				//now let's save it
				self::saveArticlePolicy($this->title, $policy, $policyText);
			}
		}

		$res = array('policy' => $policy, 'text' => $policyText);
		$cache->set($cachekey, $res);

		return array($policy, $policyText);
	}

	public function generateRobotPolicyBasedOnTitle() {
		$policy = -1;
		$policyText = '';
		if ($this->isNonExistentPage()) {
			$policy = self::POLICY_NOINDEX_NOFOLLOW;
			$policyText = 'isNonExistentPage';
		} elseif ($this->inWhitelist()) {
			$policy = self::POLICY_INDEX_FOLLOW;
			$policyText = 'inWhitelist';
		} elseif ($this->hasUserPageRestrictions()) {
			$policy = self::POLICY_NOINDEX_FOLLOW;
			$policyText = 'hasUserPageRestrictions';
		} elseif ($this->hasBadTemplate()) {
			$policy = self::POLICY_NOINDEX_FOLLOW;
			$policyText = 'hasBadTemplate';
		} elseif ($this->isUnNABbedArticle()) {
			$policy = self::POLICY_NOINDEX_FOLLOW;
			$policyText = 'isUnNABbedArticle';
		} elseif ($this->isBlacklistPage()) {
			$policy = self::POLICY_NOINDEX_NOFOLLOW;
			$policyText = 'isBlacklistPage';
		} elseif ($this->isBadCategory()) {
			$policy = self::POLICY_NOINDEX_NOFOLLOW;
			$policyText = 'isBadCategory';
		}

		// Lastly, if indexation status is not already decided, we
		// use the default indexation based on namespace.
		if ($policy < 0) {
			$policy = $this->getDefaultNamespaceIndexation();
			if ($policy == self::POLICY_DONT_CHANGE) {
				$policyText = 'default';
			} else {
				$policyText = 'namespaceDefault';
			}
		}
		return array($policy, $policyText);
	}

	public function genRobotPolicy() {
		list($policy, $policyText) = $this->genRobotPolicyLong();
		return $policy;
	}

	/**
	 * Get (and cache) the database handle.
	 */
	private static function getDB() {
		static $dbr = null;
		if (!$dbr) $dbr = wfGetDB(DB_SLAVE);
		return $dbr;
	}

	/**
	 * Mediawiki has $wgNamespaceRobotPolicies to set indexation based on
	 * namespace, but we've found that we want more finer grained control and
	 * centralized code to reduce bugs.
	 */
	private function getDefaultNamespaceIndexation() {
		global $wgLanguageCode;

		// Make it so that most namespace pages aren't indexed by Google
		$noindexNamespaces = array(
			NS_TALK, NS_USER_TALK, NS_PROJECT, NS_PROJECT_TALK, NS_IMAGE_TALK,
			NS_MEDIAWIKI, NS_MEDIAWIKI_TALK, NS_TEMPLATE, NS_TEMPLATE_TALK,
			NS_CATEGORY_TALK, NS_ARTICLE_REQUEST, NS_ARTICLE_REQUEST_TALK,
			NS_USER_KUDOS, NS_USER_KUDOS_TALK,
			NS_VIDEO, NS_VIDEO_TALK, NS_VIDEO_COMMENTS, NS_VIDEO_COMMENTS_TALK
		);
		if (defined('NS_MODULE')) {
			$noindexNamespaces[] = NS_MODULE;
			$noindexNamespaces[] = NS_MODULE_TALK;
		}
		$inNoindexNamespace = $this->title &&
			$this->title->inNamespaces( $noindexNamespaces );

		$inImageNamespace = $this->title && $this->title->inNamespace(NS_IMAGE);

		if ($inImageNamespace) {
			return self::POLICY_NOINDEX_FOLLOW;
		} elseif ($inNoindexNamespace && $wgLanguageCode == 'en') {
			return self::POLICY_NOINDEX_FOLLOW;
		} elseif ($inNoindexNamespace && $wgLanguageCode != 'en') {
			return self::POLICY_NOINDEX_NOFOLLOW;
		} else {
			return self::POLICY_DONT_CHANGE;
		}
	}

	/**
	 * Test whether page is being displayed in "printable" form
	 */
	private function isPrintable() {
		$isPrintable = $this->request && $this->request->getVal('printable', '') == 'yes';
		return $isPrintable;
	}

	/**
	 * Test whether page is being displayed as a "variant" -- this is particularly
	 * relevant for ZH, but possibly for other languages that have display variant.
	 *
	 * Reuben note: we tried for months to make it so that Google wouldn't treat
	 * these variant pages as separate pages in the index, by setting a proper
	 * "meta canonical" tag in the <head> section to point back to the zh-hans
	 * article, but Google is stubborn, so making these noindex seems like our best
	 * option to get them out of the index. It feels like Google should be picking
	 * the best Chinese variant of the article to show based on what user is viewing
	 * the article, but we haven't seen evidence that this is happening.
	 */
	private function isVariant() {
		$isVariant = $this->request && $this->request->getVal('variant', '');
		return $isVariant;
	}

	/**
	 * Check whether the ?redirect=no url param is present
	 */
	private function isNoRedirect() {
		$isNoRedirect = $this->request && $this->request->getVal('redirect') == 'no';
		return $isNoRedirect;
	}

	/**
	 * Check whether the origin of the request is the CDN
	 */
	private function isOriginCDN() {
		global $wgIsDevServer;
		if ($wgIsDevServer) {
			$isCDNRequest = false;
		} else {
			$isCDNRequest = preg_match('@^https?://pad@', @$_SERVER['HTTP_X_INITIAL_URL']) > 0;
		}
		return $isCDNRequest;
	}

	/**
	 * Check whether the URL has an &oldid=... param
	 */
	private function hasOldidParam() {
		return $this->request && (boolean)$this->request->getVal('oldid');
	}

	/**
	 * Check whether page exists in DB or not
	 */
	private function isNonExistentPage() {
		if (!$this->title ||
			($this->title->getArticleID() == 0
			 && ! $this->title->inNamespace(NS_SPECIAL))
		) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Don't allow indexing of user pages where the contributor has less
	 * than 500 edits.  Also, ignore pages with a '/' in them, such as
	 * User:Reuben/Sandbox.
	 * Also prevent indexing when the user hasn't shown activity for more
	 * than 90 days.
	 */
	private function hasUserPageRestrictions() {
		global $wgLanguageCode;

		if ($this->title->inNamespace(NS_USER)) {
			if (($this->userNumEdits() < 500 && !$this->isGPlusAuthor())
				|| strpos($this->title->getText(), '/') !== false
				|| $wgLanguageCode != 'en'
			) {
				return true;
			}
			$user = User::newFromName($this->title->getDBkey());
			if ($user !== false
				&& $user->getId() != 0
				&& wfTimestamp() - wfTimestamp(TS_UNIX, $user->getTouched()) > 60*60*24*90
			) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Retrieve number of edits by a user
	 */
	private function userNumEdits() {
		$u = explode("/", $this->title->getText());
		return WikihowUser::getAuthorStats($u[0]);
	}

	/**
	 * Check for G+ authorship
	 */
	private function isGPlusAuthor() {
		$u = explode("/", $this->title->getText());
		return WikihowUser::isGPlusAuthor($u[0]);
	}


	/**
	 * Check to see whether certain templates are affixed to the article.
	 */
	private function hasBadTemplate() {
		$result = 0;
		$articleID = $this->title->getArticleID();
		$sql = "SELECT tl_title FROM templatelinks
				WHERE tl_from = '" . $articleID . "' AND
				tl_title IN ('Speedy', 'Stub', 'Copyvio','Copyviobot','Copyedit','Cleanup','Notifiedcopyviobot','CopyvioNotified','Notifiedcopyvio','Format','Nfd','Inuse')";
		$res = self::getDB()->query($sql, __METHOD__);
		$templates = array();
		foreach ($res as $row) {
			$templates[ $row->tl_title ] = true;
		}
		// Checks to see if an article has the nfd template AND has less
		// than 10,000 page views. If so, it is de-indexed.
		if (@$templates['Nfd']) {
			if ($this->wikiPage->getCount() < 10000) return true;
			unset( $templates['Nfd'] );
		}
		// Checks to see if the article is "In use" AND has little or no content.
		// If so, it is de-indexed.
		if (@$templates['Inuse']) {
			if ($this->title->getLength() < 1500) return true;
			unset( $templates['Inuse'] );
		}
		return count($templates) > 0;
	}

	/**
	 * Check whether the article is yet to be nabbed and is short in length.
	 * Use byte size as a proxy for length for better performance.
	 */
	private function isUnNABbedArticle() {
		$ret = false;
		if ($this->wikiPage
			&& $this->title->inNamespace(NS_MAIN)
			&& class_exists('Newarticleboost')
			&& !Newarticleboost::isNABbed( self::getDB(), $this->title->getArticleID() )
		) {
			$ret = true;
		}
		return $ret;
	}

	/**
	 * Use a white list to include results that should be indexed regardless of
	 * their namespace.
	 */
	private function inWhitelist() {
		static $whitelist = null;
		if (!$whitelist) $whitelist = wfMessage('index-whitelist')->text();
		$urls = explode("\n", $whitelist);
		foreach ($urls as $url) {
			$url = trim($url);
			if ($url) {
				$whiteTitle = Title::newFromURL($url);
				if ($whiteTitle && $whiteTitle->getPrefixedURL() == $this->title->getPrefixedURL()) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * We want to noindex,nofollow the Spam-Blacklist.
	 */
	private function isBlacklistPage() {
		$blacklist = array(
			'Sandbox',
			'Spam-Blacklist'
		);
		$titleKey = $this->title->getDBkey();
		$blacklisted = in_array( $titleKey, $blacklist )
			|| strpos($titleKey, 'Sandbox/') === 0;
		return $blacklisted;
	}

	/**
	 * We want to noindex,nofollow "junk" categories outside the main
	 * category tree.
	 */
	private function isBadCategory() {
		$result = false;
		if ($this->title->getNamespace() == NS_CATEGORY) {
			$articleID = $this->title->getArticleID();
			$categoryText = $this->title->getText();
			$catTreeArray = Categoryhelper::getCategoryTreeArray();

			// Fix for strange behaviour on the French site:
			// Top-level categories seem to get formatted with <big> tags (WHY?!?),
			// e.g.:
			// "<big>'''Foo Bar'''</big>"
			// It's unknown why this happens, and it seems inconsistent.
			// If such a case is detected, we replace the key with:
			// "Foo Bar"
			// TODO: for perf reasons, we might want to check language code first
			// if this issue only appears on the French wiki
			$pattern = "@^<big>'''(.*)'''</big>$@";
			$matches = array();
			foreach ($catTreeArray as $key=>$value) {
				if (preg_match($pattern, $key, $matches)) {
					$catTreeArray[$matches[1]] = $value;
					unset($catTreeArray[$key]);
				}
			}

			unset($catTreeArray['WikiHow']);

			$array_key_exists_recursive = function ($needle, $haystack)
										  use (&$array_key_exists_recursive) {
				foreach ($haystack as $key=>$value) {
					$current_key = $key;
					if ($needle === $key || is_array($value) &&
							$array_key_exists_recursive($needle, $value) !== false) {
						return true;
					}
				}
				return false;
			};

			$result = !$array_key_exists_recursive($categoryText, $catTreeArray);

			// Some categories on International appear as empty pages
			// when they are defined in the `page` table but not the
			// `category` table. Deindex those as well.
			// TODO: This might be redundant, as those articles are
			// likely to not be a part of the main category tree.
			// Further testing required to determine this.
			if ($result) {
				$catDBKey = $this->title->getDBKey();

				$dbr = $this->getDB();
				$res = $dbr->select(
					'category',
					array('*'),
					array('cat_title' => $catDBKey),
					__METHOD__
				);

				$result = $res !== false;
			}
		}

		return $result;
	}

	/**
	 * This function clears memcache for the given article each time
	 * the article is saved. Each time a new memc key is created for
	 * a new rule, it will need to be added to this function.
	 *
	 * It is used when a page cache is purged (url with action=purge)
	 * and in NAB. When the indexation status of a page changes
	 * without the page being edited (for example, when you add it to
	 * an indexation whitelist or blacklist), clearing memcache
	 * allows non-main namespace pages to have their indexation
	 * status recalculated.
	 */
	public static function clearArticleMemc(&$article) {
		if ($article) {
			$title = $article->getTitle();
			self::clearArticleMemcByTitle($title);
		}

		return true;
	}

	private static function clearArticleMemcByTitle($title) {
		if ($title) {
			$cache = wfGetCache(CACHE_MEMSTATIC);

			// Clear the relevant key
			$cachekey = self::getCacheKey($title);
			$cache->delete($cachekey);
		}
	}

	public static function recalcArticlePolicyBasedOnId($aid) {
		$title = Title::newFromID($aid);

		self::recalcArticlePolicyBasedOnTitle($title);
	}

	private static function recalcArticlePolicyBasedOnTitle(&$title) {
		$cache = wfGetCache(CACHE_MEMSTATIC);
		if (!$title || !$title->inNamespace(NS_MAIN) || $title->getArticleID() <= 0) {
			//if it's not a main namespace article, we don't store it in the db
			return;
		}

		$cachekey = self::getCacheKey($title);

		$robotPolicy = RobotPolicy::newFromTitle($title);
		if (!$robotPolicy) {
			$cache->delete($cachekey);
		}

		list($policy, $policyText) = $robotPolicy->generateRobotPolicyBasedOnTitle();

		self::saveArticlePolicy($title, $policy, $policyText);

		$res = array('policy' => $policy, 'text' => $policyText);
		$cache->set($cachekey, $res);
	}

	// Used as hook on page save complete
	public static function recalcArticlePolicy(&$article) {
		if ($article) {
			$title = $article->getTitle();

			self::recalcArticlePolicyBasedOnTitle($title);
		}
	}

	private static function saveArticlePolicy($title, $policy, $policyText) {
		$dbw = wfGetDB(DB_MASTER);
		$values = array('ii_page' => $title->getArticleID(), 'ii_policy' => $policy, 'ii_reason' => $policyText, 'ii_timestamp' => wfTimestamp(TS_MW), 'ii_revision' => $title->getLatestRevID());
		$dbw->upsert(RobotPolicy::TABLE_NAME, $values, array('ii_page'), $values, __METHOD__);
	}

	/**
	 * Generate memcache key consistently
	 */
	private static function getCacheKey($title) {
		return wfMemckey('indexstatus', md5($title->getPrefixedDBkey()) );
	}

	public static function onTitleMoveComplete($oldTitle, $newTitle) {
		self::clearArticleMemcByTitle($newTitle);
		self::clearArticleMemcByTitle($oldTitle);
		return true;
	}

	public static function onArticleDelete($wikiPage) {
		if ($wikiPage) {
			$title = $wikiPage->getTitle();
			if ($title) {
				self::clearArticleMemcByTitle($title);
			}
		}
		return true;
	}

}

/******
 *
CREATE TABLE `index_info` (
`ii_page` int(10) unsigned NOT NULL,
`ii_policy` tinyint(3) unsigned NOT NULL default 0,
`ii_reason` varbinary(32) NOT NULL,
`ii_timestamp` varchar(14) NOT NULL DEFAULT '',
`ii_revision` int(10) unsigned NOT NULL default 0,
PRIMARY KEY (`ii_page`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

 ***********/

