<?php

if (!defined('MEDIAWIKI')) {
	die();
}

class TAInsertArticleQuestionJob extends Job {

	public function __construct(Title $title, array $params, $id = 0) {
		parent::__construct('TAInsertArticleQuestionJob', $title, $params, $id);
	}

	/**
	 * update:
	 * - average approval rating
	 * - average similarity score
	 *
	 * @return bool
	 */
	public function run() {
		$aid 		= $this->params['aid'];
		$aqid 	= $this->params['aqid'];

		$qadb = QADB::newInstance();
		$aq = $qadb->getArticleQuestionByArticleQuestionId($aqid);

		if ($aq && $aq->getSubmitterUserId()) {
			$user_id = $aq->getSubmitterUserId();

			$dbw = wfGetDB(DB_MASTER);

			//add category
			self::addCat($dbw, $aid, $user_id);

			//up the answer count
			self::upAnswerCount($dbw, $user_id);

			//touch the last answer date
			$ta = new TopAnswerers();
			$ta->loadByUserId($user_id);
			$ta->save();
		}
	}

	/**
	 * addCat()
	 *
	 * run when a new article question is inserted
	 * adds another category to our qa_answerer_categories table
	 *
	 * @param $dbw 			= db
	 * @param $aid 			= article id
	 * @param $user_id 	= submitter user id
	 */
	private static function addCat($dbw, $aid, $user_id) {
		$cat = TopAnswerers::getCat($aid);

		if ($cat) {
			$res = $dbw->upsert(
				TopAnswerers::TABLE_ANSWERER_CATEGORIES,
				[
					'qac_user_id' => $user_id,
					'qac_category' => $cat,
					'qac_count' => 1
				],
				[
					'qac_user_id',
					'qac_category'
				],
				[
					'qac_user_id = VALUES(qac_user_id)',
					'qac_category = VALUES(qac_category)',
					'qac_count = VALUES(qac_count)+qac_count'
				],
				__METHOD__
			);
		}
	}

	/**
	 * upAnswerCount()
	 *
	 * add 1 to the qa_answerer_stats.qas_answer_count

	 * @param $dbw 			= db
	 * @param $user_id 	= submitter user id
	 */
	private static function upAnswerCount($dbw, $user_id) {
		$res = $dbw->upsert(
			TopAnswerers::TABLE_ANSWERER_STATS,
			[
				'qas_answers_count' => 1,
				'qas_user_id' => $user_id
			],
			['qas_user_id'],
			[
				'qas_answers_count = VALUES(qas_answers_count)+qas_answers_count',
				'qas_user_id = VALUES(qas_user_id)'
			],
			__METHOD__
		);
	}

}
