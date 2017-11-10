<?php

class StaffReviewed {

	const STAFF_REVIEWERS_TAG = 'staff_reviewers';
	const STAFF_REVIEWED_ARTICLES_TAG = 'staff_reviewed_articles';

	const STAFF_HELPFUL_THRESHOLD = 80;
	const STAFF_HELPFUL_TOTAL_THRESHOLD = 10;
	const STAFF_HELPFUL_TOTAL_WITH_DELETED_THRESHOLD = 10;

	const STAFF_REVIEWED_SOURCE = 'staff';

	/**
	 * @return array (of names)
	 */
	public static function staffReviewers() {
		$bucket = ConfigStorage::dbGetConfig(self::STAFF_REVIEWERS_TAG, true);
		return explode("\n", $bucket);
	}

	public static function updateArticlesAdminTag(array $article_ids) {
		$err = '';
		$ids = implode("\n",$article_ids);
		ConfigStorage::dbStoreConfig(self::STAFF_REVIEWED_ARTICLES_TAG, $ids, true, $err);
	}

	/**
	 * used nightly in Titus.class.php
	 *
	 * @return array
	 */
	public static function dataForTitus($dbr, $page_id) {
		if (!ArticleTagList::hasTag(self::STAFF_REVIEWED_ARTICLES_TAG, $page_id)) return false;

		$dbr = wfGetDB(DB_SLAVE);
		$lastFellowEdit = $dbr->selectField(
			'titus_copy',
			'ti_last_fellow_edit',
			[
				'ti_language_code' => 'en',
				'ti_page_id' => $page_id
			],
			__METHOD__
		);

		$rev_data = self::revisionData($dbr, $page_id);

		$unixTS = wfTimestamp(TS_UNIX, $rev_data->rev_timestamp);
		$dateStr = DateTime::createFromFormat('U', $unixTS)->format('n/j/y');

		return [
			'name' => $lastFellowEdit,
			'source' => self::STAFF_REVIEWED_SOURCE,
			'date' => $dateStr,
			'revision' => $rev_data->rev_id
		];
	}

	private static function revisionData($dbr, $page_id) {
		$user_ids = self::revisionStaffUsers();

		$res = $dbr->select(
			'revision',
			[
				'rev_timestamp',
				'rev_id'
			],
			[
				'rev_page' => $page_id,
				"rev_user IN ('".implode("','", $user_ids)."')"
			],
			__METHOD__,
			[
				'ORDER BY' => 'rev_timestamp DESC',
				'LIMIT' => 1
			]
		);

		return $res->fetchObject();
	}

	private static function revisionStaffUsers() {
		$staff_users = [
			'Seymour Edits', //main umbrella user
			'Jean17',
			'HisGirlFriday'
		];

		$user_ids = [];
		foreach ($staff_users as $username) {
			$user = User::newFromName($username);
			$id = $user ? $user->getId() : 0;
			if ($id) $user_ids[] = $id;
		}

		return $user_ids;
	}

	/**
	 * @return boolean
	 */
	public static function titusHelpfulnessCheck($titus_row) {
		$helpfulness = 0;

		if ($titus_row->ti_helpful_total >= self::STAFF_HELPFUL_TOTAL_THRESHOLD) {
			$helpfulness = $titus_row->ti_helpful_percentage;
		}
		else {
			if ($titus_row->ti_helpful_total_including_deleted >= self::STAFF_HELPFUL_TOTAL_WITH_DELETED_THRESHOLD) {
				$helpfulness = $titus_row->ti_helpful_percentage_including_deleted;
			}
		}

		return $helpfulness >= self::STAFF_HELPFUL_THRESHOLD;
	}
}