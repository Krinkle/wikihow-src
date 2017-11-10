<?php

/**
 * Created by PhpStorm.
 * User: jordan
 * Date: 3/10/17
 * Time: 3:56 PM
 */
class ReverificationExporter {
	const ACTION_EXPORT_RANGE = 'export_new';
	const ACTION_EXPORT_ALL = 'export_range';

	const DELIMETER = "\t";

	const HEADER_ROW = [
		'Article ID',
		'Article Title',
		'Article Url',
		'Date action taken',
		'Verifier Name',
		'Reverified',
		'Date Reverified',
		'Re-verified article version URL',
		'Quick Feedback text',
		'Request Extensive Feedback',
		'Feedback Editor',
		'Flagged for Outside Review',
		'Script Export Timestamp',
		'Reverification ID',
	];

	var $exportType = null;

	function __construct() {}

	function exportData($exportType, $from = null, $to = null) {
		$response = new FileAttachmentResponse($this->getFilename($exportType));
		$response->start();
		$response->outputData($this->getHeaderRow());

		$db = ReverificationDB::getInstance();
		$reverifications = $db->getExported($from, $to);

		$ts = wfTimestampNow();
		$reverificationIds = [];
		foreach ($reverifications as $rever) {
			$response->outputData($this->getReverificationOutputRow($rever, $ts));
			$reverificationIds []= $rever->getId();
		}

		if (empty($reverifications)) {
			$response->outputData(wfMessage('rva_not_found')->text());
		}

		if ($exportType == self::ACTION_EXPORT_RANGE) {
			$db->updateExportTimestamp($reverificationIds, $ts);
		}

		$response->end();
	}

	protected function getFilename($exportType) {
		return 'reverifications_' . $exportType . '_' . wfTimestampNow() . ".xls";
	}

	protected function getHeaderRow() {
		return implode(self::DELIMETER, self::HEADER_ROW) . "\n";
	}

	protected function getEmptyRow() {
		return array_fill_keys(self::HEADER_ROW, '');
	}

	protected function getReverificationOutputRow(ReverificationData $rever, $ts) {
		$row = $this->getEmptyRow();
		$t = Title::newFromId($rever->getAid());

		$row['Article ID'] = $rever->getAid();
		if ($t && $t->exists()) {
			$row['Article Title'] = $t->getText();
			$row['Article Url'] = Misc::getLangBaseURL('en') . $t->getLocalURL();
			$row['Date action taken'] = $rever->getNewDate(ReverificationData::FORMAT_SPREADSHEET);
			$row['Verifier Name'] = $rever->getVerifierName();
			$row['Reverified'] = $rever->getReverified() ? 'Y' : 'N';


			// Export the following rows if the article is reverified
			if ($rever->getReverified()) {
				$row['Re-verified article version URL'] = Misc::getLangBaseURL('en') . $t->getLocalURL("oldid=" . $rever->getNewRevId());
			}

			$row['Date Reverified'] = $rever->getReverified() ?
				$rever->getNewDate(ReverificationData::FORMAT_SPREADSHEET) : '';
			$row['Quick Feedback text'] = $rever->getFeedback();
			$row['Request Extensive Feedback'] = $rever->getExtensiveFeedback() ? 'Y' : 'N';
			$row['Feedback Editor'] = $rever->getFeedbackEditor() ?: '';
			$row['Flagged for Outside Review'] = $rever->getFlag() ? 'Y' : 'N';
			$row['Script Export Timestamp'] = $rever->getScriptExportTimestamp();
			$row['Reverification ID'] = $rever->getId();
		} else {
			$row['Article Title'] = 'Error: Article not found for given Article ID';
		}

		return implode(self::DELIMETER, array_values($row)) . "\n";
	}
}