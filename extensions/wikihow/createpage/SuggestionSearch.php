<?php

class SuggestionSearch extends UnlistedSpecialPage {

	function __construct() {
	   parent::__construct( 'SuggestionSearch' );
	}

	function matchKeyTitles($text, $limit = 10) {
		global $wgMemc;

		$gotit = array();
		$text = trim($text);
		$limit = intval($limit);

		$cacheKey = wfMemcKey('matchsuggtitles', $limit, $text);
		$result = $wgMemc->get($cacheKey);
		if (is_array($result)) {
			return $result;
		}

		$key = TitleSearch::generateSearchKey($text);

		$db = wfGetDB( DB_MASTER );

		$base = "SELECT suggested_titles.st_title, st_id FROM suggested_titles WHERE ";
		$sql = $base . " convert(st_title using latin1) like " . $db->addQuotes($text . "%"). " and st_used = 0 ";
		$sql .= " LIMIT $limit;";
		$result = array();
		$res = $db->query( $sql, 'WH SuggestionSearch::matchKeyTitles1' );
		while ( $row = $db->fetchObject($res) ) {
			$con = array();
			$con[0] = $row->st_title;
			$con[1] = $row->st_id;
			$result[] = $con;
			$gotit[$row->st_title] = 1;
		}

		if (count($result) >= $limit) {
			$wgMemc->set($cacheKey, $result, 3600);
			return $result;
		}

		// TODO: we need to use $db->addQuotes() in this query to avoid
		// SQL injections
		$base = "SELECT suggested_titles.st_title, suggested_titles.st_id FROM suggested_titles WHERE ";
		$sql = $base . " st_key LIKE '%" . str_replace(" ", "%", $key) . "%' AND st_used = 0 ";
		$sql .= " LIMIT $limit;";
		$res = $db->query( $sql, 'WH SuggestionSearch::matchKeyTitles2' );
		while ( count($result) < $limit && $row = $db->fetchObject($res) ) {
			if (!isset($gotit[$row->st_title])) {
				$con = array();
				$con[0] = $row->st_title;
				$con[1] = $row->st_id;
				$result[] = $con;
				$gotit[$row->st_title] = 1;
			}
		}

		if (count($result) >= $limit) {
			$wgMemc->set($cacheKey, $result, 3600);
			return $result;
		}

		$ksplit = explode(" ", $key);
		if (count($ksplit) > 1) {
			$sql = $base . " ( ";
			foreach ($ksplit as $i=>$k) {
				$sql .= ($i > 0 ? " OR" : "") . " st_key LIKE '%$k%'"  ;
			}
			$sql .= " ) AND st_used = 0 ";
			$sql .= " LIMIT $limit;";
			$res = $db->query( $sql, 'WH SuggestionSearch::matchKeyTitles3' );
			while ( count($result) < $limit && $row = $db->fetchObject( $res ) ) {
				if (!isset($gotit[$row->st_title]))  {
					$con = array();
					$con[0] = $row->st_title;
					$con[1] = $row->st_id;
					$result[] = $con;
				}
			}
		}

		$wgMemc->set($cacheKey, $result, 3600);
	    return $result;
	}

	function execute($par) {
		global $wgRequest, $wgOut;

		$t1 = time();
		$search = $wgRequest->getVal("qu");

		if ($search == "") exit;

		$search = strtolower($search);
		$howto = strtolower(wfMsg('howto', ''));
		if (strpos($search, $howto) === 0) {
			$search = substr($search, 6);
			$search = trim($search);
		}
		$t = Title::newFromText($search, 0);
		$dbkey = $t->getDBKey();

		$array = "";
		$titles = $this->matchKeyTitles($search);
		foreach ($titles as $con) {
			$t = Title::newFromDBkey($con[0]);
			$title = $t ? $t->getFullText() : '';
			$array .= '"' . str_replace("\"", "\\\"", $title) . '", ' ;
		}
		if (strlen($array) > 2) $array = substr($array, 0, strlen($array) - 2); // trim the last comma
		$array1 = $array;

		$array = "";
		foreach ($titles as $con) {
			$array .=  "\" \", ";
		}
		if (strlen($array) > 2) $array = substr($array, 0, strlen($array) - 2); // trim the last comma
		$array2 = $array;

		print 'WH.AC.sendRPCDone(frameElement, "' . $search . '", new Array(' . $array1 . '), new Array(' . $array2 . '), new Array(""));';

		$wgOut->disable();
	}

}
