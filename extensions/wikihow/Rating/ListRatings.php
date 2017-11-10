<?php

use MethodHelpfulness\ArticleMethod;

/**
 * List the ratings of some set of pages
 */
class ListRatings extends QueryPage {

	public function __construct( $name = 'ListRatings' ) {
		parent::__construct( $name );
		//is this for articles or samples?
		if (strpos(strtolower($_SERVER['REQUEST_URI']),'sample')) {
			$this->forSamples = true;
			$this->tablePrefix = 'rats_';
			$this->tableName = 'ratesample';
		} else {
			$this->forSamples = false;
			$this->tablePrefix = 'rat_';
			$this->tableName = 'rating';
		}
		list( $limit, $offset ) = wfCheckLimits();
		$this->limit = $limit;
		$this->offset = $offset;
	}

	var $targets = array();
	var $tablePrefix = '';

	function getName() {
		return 'ListRatings';
	}

	function isExpensive( ) { return false; }

	function isSyndicated() { return false; }

	function getOrderFields() {
		return array('R');
	}

	function getSQL() {
		return "SELECT {$this->tablePrefix}page, AVG({$this->tablePrefix}rating) as R, count(*) as C FROM {$this->tableName} WHERE {$this->tablePrefix}isDeleted = '0' GROUP BY {$this->tablePrefix}page";
	}

	function formatResult($skin, $result) {
		if ($this->forSamples) {
			$t = Title::newFromText('Sample/'.$result->rats_page);
		} else {
			$t = Title::newFromId($result->rat_page);
		}

		if($t == null)
			return "";

		if($this->forSamples) {
			//need to tell the linker that the title is known otherwise it adds redlink=1 which eventually breaks the link
			return Linker::linkKnown($t, $t->getFullText()) . " ({$result->C} votes, {$result->R} average)";
		} else {
			return Linker::link($t, $t->getFullText()) . " ({$result->C} votes, {$result->R} average)";
		}
	}

	function getPageHeader( ) {
		$out = $this->getOutput();
		if ($this->forSamples) $out->setPageTitle('List Rated Sample Pages');
		return;
	}
}
