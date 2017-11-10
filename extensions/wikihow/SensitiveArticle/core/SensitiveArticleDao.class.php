<?php

namespace SensitiveArticle;

/**
 * Data Access Object for the `sensitive_*` tables
 */
class SensitiveArticleDao
{
	public function getSensitiveArticleData(int $pageId): \Iterator
	{
		$fields = ['sa_page_id', 'sa_reason_id', 'sa_rev_id', 'sa_user_id', 'sa_date'];
		$conds = ['sa_page_id' => $pageId];
		$res = wfGetDB(DB_SLAVE)->select('sensitive_article', $fields, $conds);
		return $res ?? new \EmptyIterator();
	}

	public function insertSensitiveArticleData(SensitiveArticle $sa): bool
	{
		$rows = [];
		foreach ($sa->reasonIds as $reasonId) {
			$rows[] = [
				'sa_page_id' => $sa->pageId,
				'sa_reason_id' => $reasonId,
				'sa_rev_id' => $sa->revId,
				'sa_user_id' => $sa->userId,
				'sa_date' => $sa->date,
			];
		}
		return wfGetDB(DB_MASTER)->insert('sensitive_article', $rows);
	}

	/**
	 * @return ResultWrapper|bool
	 */
	public function deleteSensitiveArticleData(int $pageId)
	{
		return wfGetDB(DB_MASTER)->delete('sensitive_article', ['sa_page_id' => $pageId]);
	}

	public function getAllReasons(): \Iterator
	{
		$fields = [ 'sr_id', 'sr_name', 'sr_enabled' ];
		$options = [ 'ORDER BY' => 'sr_name' ];
		$res = wfGetDB(DB_SLAVE)->select('sensitive_reason', $fields, [], __METHOD__, $options);
		return $res ?? new \EmptyIterator();
	}

	public function insertReason(SensitiveReason $sr): bool
	{
		$values = [
			'sr_id' => $sr->id,
			'sr_name' => $sr->name,
			'sr_enabled' => (int) $sr->enabled
		];
		return wfGetDB(DB_MASTER)->insert('sensitive_reason', $values);
	}

	public function updateReason(SensitiveReason $sr): bool
	{
		$values = [ 'sr_name' => $sr->name, 'sr_enabled' => (int) $sr->enabled ];
		$conds = [ 'sr_id' => $sr->id ];
		return wfGetDB(DB_MASTER)->update('sensitive_reason', $values, $conds);
	}

	public function getNewReasonId(): int
	{
		$id = (int) wfGetDB(DB_SLAVE)->selectField('sensitive_reason', 'max(sr_id)');
		return $id + 1;
	}

}
