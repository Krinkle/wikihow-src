<?php

class DOMUtil
{
	/**
	 * Hide links to de-indexed articles so web crawlers don't see them, which can hurt SEO.
	 */
	public static function hideLinksFromAnons(bool $isMobile)
	{
		global $wgTitle, $wgUser;

		if (// Replace links only for anons...
			$wgUser->isLoggedIn() ||
			// in indexable pages...
			!RobotPolicy::isIndexable($wgTitle) ||
			// that haven't been whitelisted
			ArticleTagList::hasTag('deindexed_link_removal_whitelist', $wgTitle->getArticleID())
		) {
			return;
		}

		list($hrefs, $links) = DOMHelper::findLinks($isMobile);
		if (!$hrefs)
			return;

		// Find out which pages are indexed articles

		$res = wfGetDB(DB_SLAVE)->select(
			['page', 'index_info'],
			['page_title'],
			[
				'page_namespace' => 0,
				'page_title' => $hrefs,
				'page_id = ii_page',
				'ii_policy IN (1, 4)',
			]
		);

		$isIndexed = [];
		foreach ($res as $row) {
			$isIndexed[$row->page_title] = true;
		}

		// Replace all other links with their anchor text

		$idx = 0;
		foreach ($hrefs as $href) {
			if (!isset($isIndexed[$href])) {
				DOMHelper::hideLink($links[$idx]);
			}
			$idx++;
		}
	}
}

class DOMHelper
{
	/**
	 * Find all links to other pages
	 */
	public static function findLinks(bool $isMobile): array
	{
		$hrefs = []; // [string]      Relative URLs without the leading '/'
		$links = []; // [DOMElement]  <a> elements
		$query = $isMobile ? 'a' : '#bodycontents a';

		foreach(pq($query) as $a) {
			$hasImageInLink = pq('img', $a)->length;
			if ($hasImageInLink) {
				continue;
			}

			$title = $a->getAttribute('title');
			$href = $a->getAttribute('href');
			if ($title && $href && strpos($href, '/') === 0 && strpos($href, '.php') === false) {
				$hrefs[] = urldecode(substr($href, 1));
				$links[] = $a;
			}
		}
		return [$hrefs, $links];
	}

	/**
	 * Replace an HTML link with its anchor text
	 */
	public static function hideLink(DOMElement $link)
	{
		$pqObject = pq($link);
		$pqObject->replaceWith($pqObject->text());
	}
}
