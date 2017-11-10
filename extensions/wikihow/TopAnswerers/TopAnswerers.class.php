<?php
/*
CREATE TABLE `top_answerers` (
	`ta_id` int(10) PRIMARY KEY AUTO_INCREMENT,
	`ta_user_id` int(10) NOT NULL DEFAULT 0,
	`ta_source` varbinary(14) NOT NULL DEFAULT '',
	`ta_is_blocked` tinyint(4) NOT NULL DEFAULT 0,
	`ta_created` varbinary(14) NOT NULL DEFAULT '',
	`ta_updated` varbinary(14) NOT NULL DEFAULT '',
	UNIQUE KEY (`ta_user_id`)
);

CREATE TABLE `qa_answerer_categories` (
	`qac_id` int(10) PRIMARY KEY AUTO_INCREMENT,
	`qac_user_id` int(10) NOT NULL DEFAULT 0,
	`qac_category` varbinary(255) NOT NULL DEFAULT '',
	`qac_count` int(4) NOT NULL DEFAULT 0,
	UNIQUE KEY `qac_index` (`qac_user_id`,`qac_category`),
	KEY (`qac_count`)
);

CREATE TABLE `qa_answerer_stats` (
	`qas_id` int(10) PRIMARY KEY AUTO_INCREMENT,
	`qas_user_id` int(10) NOT NULL DEFAULT 0,
	`qas_answers_count` int(4) NOT NULL DEFAULT 0,
	`qas_avg_app_rating` DECIMAL(3,2) UNSIGNED NOT NULL DEFAULT 0,
	`qas_avg_sim_score` DECIMAL(3,2) UNSIGNED NOT NULL DEFAULT 0,
	UNIQUE KEY (`qas_user_id`)
);
*/
class TopAnswerers {
	var $id, $userId, $source, $isBlocked, $createDate, $updateDate;
	var $avgAppRating, $avgSimScore, $answersCount;
	var $userName, $userRealName, $userLink, $userImage, $topCats;

	const TABLE_TOP_ANSWERERS 				= 'top_answerers';
	const TABLE_ANSWERER_CATEGORIES 	= 'qa_answerer_categories';
	const TABLE_ANSWERER_STATS 				= 'qa_answerer_stats';
	const SOURCE_ADMIN 								= 'admin';
	const SOURCE_AUTO 								= 'auto';
	const THRESHOLD_ANSWER_COUNT			= 50;
	const THRESHOLD_APPROVAL_RATING 	= .75;
	const THRESHOLD_SIMILARITY_SCORE 	= .6;
	const MEMCACHE_TOPUSERS						= 'top_answerers_user_ids';

	var $cat_limit 		= 3; 	//set to grab a different # of categories
	var $current_cat 	= 0;	//set to ignore the current category (for listing on an article)

	public function __construct() {
		$this->userId 			= 0;
		$this->source 			= '';
		$this->isBlocked		= 0;
		$this->createDate 	= wfTimeStampNow();
		$this->updateDate 	= wfTimeStampNow();
		$this->avgAppRating = 0;
		$this->avgSimScore 	= 0;
		$this->answersCount = 0;
		$this->userName 		= '';
		$this->userRealName = '';
		$this->userLink 		= '';
		$this->userImage		= '';
		$this->topCats 			= '';
	}

	public function loadFromDbRow($row) {
		$this->id 					= $row->ta_id;
		$this->userId 			= $row->ta_user_id;
		$this->source 			= $row->ta_source;
		$this->isBlocked		= $row->ta_is_blocked;
		$this->createDate 	= $row->ta_created;
		$this->updateDate 	= $row->ta_updated;
		$this->avgAppRating = $row->qas_avg_app_rating;
		$this->avgSimScore 	= $row->qas_avg_sim_score;
		$this->answersCount = $row->qas_answers_count;

		//get user data
		$user = User::newFromId($this->userId);
		if ($user) {
			$this->userName 		= $user->getName();
			$this->userRealName = $user->getRealName();
			$this->userLink 		= $user->getUserPage()->getLocalURL();
			$this->userImage		= Avatar::getAvatarURL($user->getName());
		}

		//category info
		$this->topCats = $this->getTopCatsData();
	}

	/**
	 * loadById()
	 *
	 * populate TopAnswerers object from an id
	 *
	 * @param $id = top_answerers.ta_id
	 * @return boolean
	 */
	public function loadById($id) {
		if (!is_int($id)) return false;

		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			[
				self::TABLE_TOP_ANSWERERS,
				self::TABLE_ANSWERER_STATS
			],
			'*',
			['ta_id' => $id],
			__METHOD__,
			[],
			[
				self::TABLE_ANSWERER_STATS => ['LEFT JOIN', 'ta_user_id = qas_user_id']
			]
		);

		$row = $res->fetchObject();

		if ($row) {
			$this->loadFromDbRow($row);
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * loadByUserId()
	 *
	 * populate TopAnswerers object from a userid
	 *
	 * @param $user_id = top_answerers.ta_user_id
	 * @return boolean
	 */
	public function loadByUserId($user_id) {
		if (empty($user_id)) return false;

		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			[
				self::TABLE_TOP_ANSWERERS,
				self::TABLE_ANSWERER_STATS
			],
			'*',
			['ta_user_id' => $user_id],
			__METHOD__,
			[],
			[
				self::TABLE_ANSWERER_STATS => ['LEFT JOIN', 'ta_user_id = qas_user_id']
			]
		);

		$row = $res->fetchObject();

		if ($row) {
			$this->loadFromDbRow($row);
			return true;
		}

		return false;
	}

	/**
	 * save()
	 *
	 * save a new/existing TopAnswerers object to the database
	 * @return boolean
	 */
	public function save() {
		if (empty($this->userId)) return false;

		$dbw = wfGetDB(DB_MASTER);

		$res = $dbw->upsert(
			self::TABLE_TOP_ANSWERERS,
			[
				'ta_user_id' 				=> $this->userId,
				'ta_source' 				=> $this->source,
				'ta_is_blocked' 		=> $this->isBlocked,
				'ta_created'				=> $this->createDate,
				'ta_updated'				=> wfTimeStampNow(),
			],
			['ta_user_id'],
			[
				'ta_user_id = VALUES(ta_user_id)',
				'ta_source = VALUES(ta_source)',
				'ta_is_blocked = VALUES(ta_is_blocked)',
				'ta_updated = VALUES(ta_updated)'
			],
			__METHOD__
		);

		//NEW!
		if (empty($this->id)) {
			//let's load up all the stuff
			$this->loadByUserId($this->userId);
			//let's also send a congratulatory email
			$this->sendGratsEmail();
			//oh! and clear the ta cache
			$this->clearTAcache();
		}

		return $res;
	}

	public function toJSON() {
		return [
			'ta_id' 						=> $this->id,
			'ta_user_id' 				=> $this->userId,
			'ta_source' 				=> $this->source,
			'ta_is_blocked' 		=> $this->isBlocked,
			'ta_created'				=> $this->createDate,
			'ta_updated'				=> $this->updateDate,
			'ta_user_name'			=> $this->userName,
			'ta_user_real_name'	=> $this->userRealName,
			'ta_user_link'			=> $this->userLink,
			'ta_user_image'			=> $this->userImage,
			'ta_avg_app_rating'	=> $this->avgAppRating,
			'ta_avg_sim_score' 	=> $this->avgSimScore,
			'ta_answers_count' 	=> $this->answersCount,
			'ta_top_cats'				=> $this->topCats
		];
	}

	/**
	 * setCurrentCat()
	 *
	 * sets $this->current_cat so we can ignore it when we grab the top cats
	 *
	 * @param $article_id
	 */
	public function setCurrentCat($article_id) {
		$this->current_cat = self::getCat($article_id);
	}

	/**
	 * getTopCatsData()
	 *
	 * grab all the top cats from qa_answerer_categories
	 *
	 * @param $this->cat_limit		= max number of categories to return
	 * @param $this->current_cat	= id of category in which the current article is
	 *
	 * @return array of category links, category names, # of answers in that cat
	 */
	public function getTopCatsData() {
		$dbr = wfGetDB(DB_SLAVE);
		$cats = [];

		$where = [
			'qac_user_id' => $this->userId
		];
		if (!empty($this->current_cat)) $where[] = 'qac_category <> '. $dbr->addQuotes($this->current_cat);

		$options = [
			'ORDER BY' => 'qac_count DESC'
		];
		if (!empty($this->cat_limit)) $options['LIMIT'] = $this->cat_limit;

		$res = $dbr->select(
			self::TABLE_ANSWERER_CATEGORIES,
			[
				'qac_category',
				'qac_count'
			],
			$where,
			__METHOD__,
			$options
		);

		$templates = $this->badTemplates(true);

		foreach ($res as $row) {
			if (in_array($row->qac_category,$templates)) continue;

			$cats[] = [
				'cat_link' => '/Category:'.str_replace(' ','-',$row->qac_category),
				'cat' => $row->qac_category,
				'cat_count' => $row->qac_count
			];
		}

		return $cats;
	}


	/**
	 * getTACount()
	 *
	 * @return integer of the number of top answerers
	 */
	public static function getTACount() {
		$blocked = 0;
		$res = self::getCount($blocked);
		return $res;
	}

	/**
	 * getTABlockedCount()
	 *
	 * @return integer of the number of top answerers who have been removed
	 */
	public static function getTABlockedCount() {
		$blocked = 1;
		$res = self::getCount($blocked);
		return $res;
	}

	/**
	 * getCount()
	 *
	 * @param $blocked = 1 or 0 whether we want blocked or not
	 * @return integer of the number of top answerers who have been removed
	 */
	private static function getCount($blocked) {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->selectField(
			self::TABLE_TOP_ANSWERERS,
			'count(*)',
			['ta_is_blocked' => $blocked],
			__METHOD__
		);
		return $res;
	}

	/**
	 * getTAAnswerCount()
	 *
	 * @return integer of the number of total answers top answerers have answered
	 */
	public static function getTAAnswerCount() {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->selectField(
			[
				self::TABLE_TOP_ANSWERERS,
				self::TABLE_ANSWERER_STATS
			],
			'SUM(qas_answers_count)',
			[
				'ta_is_blocked' => 0,
				'ta_user_id = qas_user_id'
			],
			__METHOD__
		);
		return $res;
	}

	/**
	 * getTAs()
	 *
	 * @return array of Top Answerers objects
	 */
	public static function getTAs($order_by = '') {
		$ta_results = [];

		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			[
				self::TABLE_TOP_ANSWERERS,
				self::TABLE_ANSWERER_STATS,
				'wiki_shared.user'
			],
			'*',
			['ta_is_blocked' => 0],
			__METHOD__,
			[
				'ORDER BY' => $order_by ?: 'user_name'
			],
			[
				self::TABLE_ANSWERER_STATS => ['LEFT JOIN', 'ta_user_id = qas_user_id'],
				'wiki_shared.user' => ['LEFT JOIN', 'ta_user_id = user_id']
			]
		);

		foreach ($res as $row) {
			$ta = new TopAnswerers();
			$ta->loadFromDbRow($row);
			$ta_results[] = $ta;
		}

		return $ta_results;
	}

	/**
	 * getBlockedUsers()
	 *
	 * @return array of results (id, ta_user_id)
	 */
	public static function getBlockedUsers() {
		$ta_results = [];

		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			[
				self::TABLE_TOP_ANSWERERS,
				'wiki_shared.user'
			],
			'*',
			['ta_is_blocked' => 1],
			__METHOD__,
			[],
			[
				'wiki_shared.user' => ['LEFT JOIN', 'ta_user_id = user_id']
			]
		);

		foreach ($res as $row) {
			$ta = new TopAnswerers();
			$ta->loadFromDbRow($row);
			$ta_results[] = $ta;
		}

		return $ta_results;
	}

	/**
	 * getCat()
	 *
	 * get the deepest sub-category of an article
	 * @param $article_id
	 * @return string
	 */
	public static function getCat($article_id) {
		if (empty($article_id)) return '';

		$t = Title::newFromId($article_id);
		if (empty($t)) return '';

		$cats = Categoryhelper::getCurrentParentCategories($t);
		if (empty($cats) || !is_array($cats) || sizeof($cats) == 0) return '';

		$keys = array_keys($cats);
		$keys = str_replace('Category:', '', $keys);

		$templates = self::badTemplates();

		//get the top subcat we're not ignoring
		$actual_cats = array_diff($keys, $templates);

		$cat = array_values($actual_cats);
		if (empty($cat) || sizeof($cat) == 0) return '';

		//remove that pesky hyphen
		$cat = str_replace('-',' ',$cat[0]);

		return $cat;
	}

	/**
	 * getAllTaUserIds()
	 *
	 * @return array()
	 */
	public static function getAllTaUserIds() {
		global $wgMemc;

		$key = wfMemcKey(self::MEMCACHE_TOPUSERS);
		$tas = $wgMemc->get($key);

		if (empty($tas)) {
			$dbr = wfGetDB(DB_SLAVE);
			$res = $dbr->select(
				self::TABLE_TOP_ANSWERERS,
				'ta_user_id',
				[
					'ta_is_blocked' => 0
				],
				__METHOD__
			);

			if ($res) {
				foreach ($res as $row) {
					$tas[] = $row->ta_user_id;
				}
				$wgMemc->set($key, $tas);
			}
		}

		return $tas;
	}

	/**
	 * clearTAcache()
	 * - clears the cache we use for storing all the TA user ids (used by Q&A Patrol)
	 */
	private function clearTAcache() {
		global $wgMemc;
		$wgMemc->delete( wfMemcKey(self::MEMCACHE_TOPUSERS) );
	}

	/**
	 * sendGratsEmail()
	 *
	 * sends a congratulatory email when a new user becomes a Top Answerer
	 */
	private function sendGratsEmail() {
		$user = User::newFromId($this->userId);
		if (!$user || $user->getOption('disableqaemail') == '1') return;

		$email 	= $user->getEmail();
		$name 	= $user->getName();

		if ($email) {
			$to 					= new MailAddress($email);
			$from 				= new MailAddress('wikiHow Team <support@wikihow.com>');
			$subject 			= wfMessage('ta_grats_subject')->text();
			$content_type = "text/html; charset=UTF-8";
			$answer_link 	= wfExpandUrl('/Special:AnswerQuestions');

			$link = UnsubscribeLink::newFromId($user->getId());
			$body = wfMessage('ta_grats_body', $name, $answer_link, $link->getLink())->text();

			UserMailer::send($to, $from, $subject, $body, null, $content_type);

			//send an email to alissa too!
			$to = new MailAddress('alissa@wikihow.com');
			$subject = '[Top Answerer email] '.$name;
			UserMailer::send($to, $from, $subject, $body, null, $content_type);
		}
	}

	public static function badTemplates($spaces = false) {
		$templates = wfMessage('categories_to_ignore')->inContentLanguage()->text();
		$templates = explode("\n", $templates);
		$templates = str_replace('http://www.wikihow.com/Category:', '', $templates);

		if ($spaces) $templates = str_replace('-', ' ', $templates);

		return $templates;
	}


	/************ HOOKS **************/
	public static function onInsertArticleQuestion($aid, $aqid, $isNew) {
		if ($isNew) {
			$jobTitle = Title::newFromId($aid);
			$jobParams = [
				'aid' 	=> $aid,
				'aqid' 	=> $aqid
			];
			$job = Job::factory('TAInsertArticleQuestionJob', $jobTitle, $jobParams);
			JobQueueGroup::singleton()->push($job);
		}
		return true;
	}

	public static function onUpdateAnswererStats($aid, $user_id, $sim_score, $approved) {
		$jobTitle = Title::newFromId($aid);
		$jobParams = [
			'user_id' 	=> $user_id,
			'sim_score'	=> $sim_score,
			'approved' 	=> intval($approved)
		];
		$job = Job::factory('TAUpdateStatsJob', $jobTitle, $jobParams);
		JobQueueGroup::singleton()->push($job);
		return true;
	}
}
