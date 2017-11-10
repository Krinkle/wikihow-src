<?php

if (!defined('MEDIAWIKI')) {
	die();
}

class TAUpdateStatsJob extends Job {

	public function __construct(Title $title, array $params, $id = 0) {
		parent::__construct('TAUpdateStatsJob', $title, $params, $id);
	}

	/**
	 * update:
	 * - qas_avg_app_rating
	 * - qas_avg_sim_score
	 *
	 * @return bool
	 */
	public function run() {
		$user_id 		= $this->params['user_id'];						//submitter userid
		$sim_score 	= $this->params['sim_score'];					//similarity score (1 if unchanged)
		$approved 	= intval($this->params['approved']);	//boolean

		if (empty($user_id)) return false;

		//get current values
		$dbw = wfGetDB(DB_MASTER);
		$res = $dbw->select(
			TopAnswerers::TABLE_ANSWERER_STATS,
			[
				'qas_avg_app_rating',
				'qas_avg_sim_score'
			],
			['qas_user_id' => $user_id],
			__METHOD__
		);

		$row = $res->fetchObject();
		$old_approval_rating = $row->qas_avg_app_rating == 0.00 ? 0 : $row->qas_avg_app_rating;
		$old_sim_score = $row->qas_avg_sim_score == 0.00 ? 0 : $row->qas_avg_sim_score;

		//get the new average approval rating
		$avg_app_rating = self::calculateAverage($old_approval_rating, $approved);

		//get the new average similarity score (if it was approved)
		if ($approved) {
			$avg_sim_score = self::calculateAverage($old_sim_score, $sim_score);
		}
		else {
			$avg_sim_score = $old_sim_score;
		}

		// set the new averages
		$res = $dbw->upsert(
			TopAnswerers::TABLE_ANSWERER_STATS,
			[
				'qas_avg_app_rating' => $avg_app_rating,
				'qas_avg_sim_score' => $avg_sim_score,
				'qas_user_id' => $user_id
			],
			['qas_user_id' => $user_id],
			[
				'qas_avg_app_rating = VALUES(qas_avg_app_rating)',
				'qas_avg_sim_score = VALUES(qas_avg_sim_score)',
				'qas_user_id = VALUES(qas_user_id)'
			],
			__METHOD__
		);

		return $res;
	}

	private static function calculateAverage($old, $new) {
		$avg = !empty($old) ? ($old + $new) / 2 : $new;
		return $avg;
	}
}
