<?php

/**
 * nightly script to add users to the TopAnswerers tables
 **/

require_once __DIR__ . '/../Maintenance.php';

class UpdateTopAnswerers extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Nightly script to add users to the TopAnswerers tables.";
	}

	public function execute() {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			[
				TopAnswerers::TABLE_ANSWERER_STATS,
				TopAnswerers::TABLE_TOP_ANSWERERS
			],
			'qas_user_id',
			[
				'qas_answers_count >= '. TopAnswerers::THRESHOLD_ANSWER_COUNT,
				'qas_avg_app_rating > '. TopAnswerers::THRESHOLD_APPROVAL_RATING,
				'qas_avg_sim_score > '. TopAnswerers::THRESHOLD_SIMILARITY_SCORE,
				'ta_user_id IS NULL'
			],
			__METHOD__,
			[],
			[
				TopAnswerers::TABLE_TOP_ANSWERERS => ['LEFT JOIN', 'ta_user_id = qas_user_id']
			]
		);

		foreach ($res as $row) {
			self::addUser($row->qas_user_id);
		}
	}

	private static function addUser($user_id) {
		$ta = new TopAnswerers();

		//only for new ones
		if ($ta->loadByUserId($user_id)) return false;

		$ta->userId = $user_id;
		$ta->source = TopAnswerers::SOURCE_AUTO;
		$ta->save();
	}
}

$maintClass = 'UpdateTopAnswerers';
require_once RUN_MAINTENANCE_IF_MAIN;
