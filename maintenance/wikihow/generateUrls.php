<?php
//
// Generate a list of all URLs for the sitemap generator and for
// scripts that crawl the site (like to generate cache.wikihow.com)
//

require_once __DIR__ . '/../commandLine.inc';

class GenerateURLsMaintenance {

	static function iso8601_date($time) {
		$date = substr($time, 0, 4)  . "-"
			  . substr($time, 4, 2)  . "-"
			  . substr($time, 6, 2)  . "T"
			  . substr($time, 8, 2)  . ":"
			  . substr($time, 10, 2) . ":"
			  . substr($time, 12, 2) . "Z" ;
		return $date;
	}

	static function listArticles($titlesOnly, $touchedSince, $relativeURLs=false, $forSitemap=false) {
		$PAGE_SIZE = 2000;
		$dbr = wfGetDB(DB_SLAVE);

		$domainIds = array();
		// keep track of any pages in test domain
		if ($forSitemap && class_exists('AlternateDomain')) {
			$domainIds = AlternateDomain::getAllPages();
		}

		for ($page = 0; ; $page++) {
			$offset = $PAGE_SIZE * $page;
			if ($touchedSince) {
				$sql = "SELECT page_id, page_title, page_touched FROM page, recentchanges WHERE page_id = rc_cur_id AND page_namespace = 0 AND page_is_redirect = 0 AND rc_timestamp >= '$touchedSince' AND rc_minor = 0 GROUP BY page_id ORDER BY page_touched DESC LIMIT $offset,$PAGE_SIZE";
			} else {
				$sql = "SELECT page_id, page_title, page_touched FROM page WHERE page_namespace = " . NS_MAIN . " AND page_is_redirect = 0 ORDER BY page_touched DESC LIMIT $offset,$PAGE_SIZE";
			}
			$res = $dbr->query($sql, __FILE__);
			if (!$res->numRows()) break;
			foreach ($res as $row) {
				$title = Title::newFromDBKey($row->page_title);
				if (!$title) {
					continue;
				}

				// check if the page is part of the domain test and if so remove it
				if (isset($domainIds[$row->page_id])) {
					continue;
				}

				if (class_exists('RobotPolicy')) {
					$indexed = RobotPolicy::isIndexable($title);
					if (!$indexed) {
						continue;
					}
				}

				if ($titlesOnly) {
					$line = $row->page_id . ' ' . $title->getDBkey();
				} else {
					$line = $relativeURLs ? $title->getLocalURL() : $title->getCanonicalURL();
					$line .= ' lastmod=' . self::iso8601_date($row->page_touched);
				}
				print "$line\n";
			}
		}
	}

	static function categoryTreeToList($node, &$list) {
		foreach ($node as $name => $subNode) {
			$list[] = $name;
			if ($subNode && is_array($subNode)) {
				self::categoryTreeToList($subNode, $list);
			}
		}
	}

	static function listCategories($titlesOnly) {
		$epoch = wfTimestamp( TS_MW, strtotime('January 1, 2010') );

		$ch = new Categoryhelper();
		$tree = $ch->getCategoryTreeArray();
		unset($tree['WikiHow']);
		$list = array();
		self::categoryTreeToList($tree, $list);

		foreach ($list as $cat) {
			$title = Title::makeTitle(NS_CATEGORY, $cat);
			if (!$title || $title->getArticleID() <= 0) continue;
			if ($titlesOnly) {
				$line = $title->getArticleID() . ' ' . $title->getPrefixedDBkey();
			} else {
				$line = $title->getCanonicalURL() . ' lastmod=' .  self::iso8601_date($epoch);
			}
			print "$line\n";
		}
	}

	static function main() {
		$opts = getopt('', array('titles-only', 'categories', 'since:', 'relative', 'forsitemap'));
		$titles_only = isset($opts['titles-only']);
		$categories = isset($opts['categories']);
		$since = isset($opts['since']) ? wfTimestamp(TS_MW, $opts['since']) : '';
		$relative = isset($opts['relative']); // Output relative article URLs. E.g. '/Hug'
		// flag for if we are generating this list for the sitemap
		$forSitemap = isset($opts['forsitemap']);

		if (!$categories) {
			self::listArticles($titles_only, $since, $relative, $forSitemap);
		} else {
			self::listCategories($titles_only);
		}
	}

}

GenerateURLsMaintenance::main();
