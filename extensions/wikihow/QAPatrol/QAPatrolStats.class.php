<?php

class QAPatrolStats {

	const STAT_LIMIT = 1000;
	static $expertView = false;
	static $topAnswererView = false;

	public static function getStats($expertView, $topAnswererView) {
		$username = RequestContext::getMain()->getRequest()->getVal('user');
		$user = $username ? User::newFromName($username) : null;

		self::$expertView = $expertView;
		self::$topAnswererView = $topAnswererView;

		$html = self::getStatsUploads($user);
		$html .= self::getRecentQAs($user);
		return $html;
	}

	private static function getStatsUploads($user) {
		$html = '';

		$user_id = $user ? $user->getId() : '';
		if ($user_id) {
			$html = '<h2>User: '.$user->getName().'</h2>';
		}

		$dbr = wfGetDB(DB_SLAVE);
		$uploads = array();

		$day = wfTimestamp(TS_MW, time() - 1 * 24 * 3600);
		$count = self::runStatQuery($dbr, $user_id, $day);
		$uploads["last 24 hours"] = $count;

		$week = wfTimestamp(TS_MW, time() - 7 * 24 * 3600);
		$count = self::runStatQuery($dbr, $user_id, $week);
		$uploads["last 7 days"] = $count;

		$month = wfTimestamp(TS_MW, time() - 30 * 24 * 3600);
		$count = self::runStatQuery($dbr, $user_id, $month);
		$uploads["last 30 days"] = $count;

		$count = self::runStatQuery($dbr, $user_id, '');
		$uploads["allTime"] = $count;

		$exp = self::$expertView ? 'Expert ' : '';

		$html .=  $exp."Q&As approved for articles:<br />";
		foreach($uploads as $key => $val) {
			$html .= $key.", $val<br />";
		}
		return $html;
	}

	private static function getRecentQAs($user) {
		$dbr = wfGetDB(DB_SLAVE);

		$where = [
			'qap_aqid = qa_id',
			'qap_page_id = page_id'
		];

		$user_id = $user ? $user->getId(): '';
		if ($user_id) $where['qap_user_id'] = $user_id;

		if (self::$expertView) $where[] = 'qap_verifier_id > 0';

		$res = $dbr->select(
			array('qa_patrol', 'qa_articles_questions', 'page'),
			array('page_title','qap_question','qap_answer','qap_user_id','qap_submitter_user_id','qap_verifier_id'),
			$where,
			__METHOD__,
			array('ORDER BY' => 'qa_updated_timestamp DESC', 'LIMIT' => self::STAT_LIMIT)
		);

		$exp = self::$expertView ? ' expert' : '';
		$html = '<br /><br />Last '. self::STAT_LIMIT . $exp .' Q&A additions:<br /><br />';
		foreach ($res as $row) {
			$user = $row->qap_user_id ? User::newFromId($row->qap_user_id)->getName() : 'Anonymous';

			if (self::$expertView) {
				$answerer = $row->qap_verifier_id ? VerifyData::getVerifierInfoById($row->qap_verifier_id)->name : '';
			}
			else {
				$answerer = $row->qap_submitter_user_id ? User::newFromId($row->qap_submitter_user_id)->getName() : 'Anonymous';
			}

			$html .= '<div><a href="'.$row->page_title.'" target="_blank">'.str_replace('-',' ',$row->page_title).'</a><br />
							<b>Q:</b> '.$row->qap_question.'<br />
							<b>A:</b> '.$row->qap_answer.'<br />
							<b>Patroller:</b> '.$user.'<br />
							<b>Answerer:</b> '.$answerer.'</div><br />';
		}

		return $html;
	}

	private static function runStatQuery($dbr, $user_id, $timespan) {
		$where = array('qap_aqid = qa_id');
		if ($user_id) $where['qap_user_id'] = $user_id;
		if ($timespan) $where[] = "qa_updated_timestamp > $timespan";
		if (self::$expertView) $where[] = 'qap_verifier_id > 0';

		$count = $dbr->selectField(array('qa_articles_questions','qa_patrol'), 'count(*)', $where, __METHOD__);

		return $count;
	}
}
