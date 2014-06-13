<?php

class RisingStar {

	function getRS() {
		global $wgMemc;

		$key_rs = wfMemcKey( 'risingstar-feed3', date('YmdG'), number_format( date('i') / 10, 0, '', '' ) );

		$rsOut = $wgMemc->get($key_rs);
		if (!empty($rsOut)) {
			return $rsOut;
		}

		$t = Title::newFromText('wikiHow:Rising-star-feed');
		if ($t->getArticleId() > 0) {
			$r = Revision::newFromTitle($t);
			$text = $r->getText();
		} else {
			return false;
		}	

		// NOTE: temporary patch to handle archives. The authoritative source for RS needs to be 
		// moved to the DB versus the feed article. add archive to array.
		$archives = array('wikiHow:Rising-star-feed/archive1');
		foreach ($archives as $archive) {
			$tarch = Title::newFromText($archive);
			if ($tarch->getArticleId() > 0) {
				$r = Revision::newFromTitle($tarch);
				$text = $r->getText() ."\n". $text;
			}
		}

		$rsOut = array();
		$rs = $text;
		$rs = preg_replace("/==\n/", ',', $rs);
		$rs = preg_replace('/^==/', '', $rs);
		$lines = preg_split("/\r|\n/", $rs, null, PREG_SPLIT_NO_EMPTY);
		$count = 0;
		foreach ($lines as $line) {
			if (preg_match('/^==(.*?),(.*?)$/', $line, $matches)) {

				$dt = $matches[1];
				$title = preg_replace('@http://www\.wikihow\.com/@', '', $matches[2]);
				$title = preg_replace('@http://[^/]*\.com/@', '', $matches[2]);

				$t = Title::newFromText($title);
				if (!isset($t)) {continue;}

				if ($t->isRedirect()) {
					$a = new Article($t);
					$t = Title::newFromRedirect( $a->fetchContent() );
				}

				$rsOut[$t->getPartialURL()] = $dt;
			}
		}
		// sort by most recent first
		$rsOut = array_reverse($rsOut);

		$wgMemc->set($key_rs, $rsOut);
		return $rsOut;
	}

}

