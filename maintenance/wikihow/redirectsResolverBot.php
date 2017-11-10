<?php
/**
 * redirectsResolverBot.php
 *
 * @author Lojjik Braughler
 * 12/14/2015
 *
 * Updates internal links that point to redirects
 * to the redirect target they should be pointing to.
 */

require_once __DIR__ . '/../Maintenance.php';

class UpdateLinks extends Maintenance {

	private $bot;

	public function __construct() {
		parent::__construct();
		$this->addOption('limit', 'Specify a limit of number of articles to do', false, true);
	}

	public function execute() {
		global $wgUser;
		$this->bot = $wgUser = User::newFromName('RedirectsBot');

		$limit = 0;

		if ($this->hasOption('limit')) {
			$limit = $this->getOption('limit');
		}

		// track number of articles we change so we don't go over the specified limit
		$articlesUpdated = 0;
		$this->output("Fetching articles...\n");
		$articles = $this->fetchArticles();

		foreach ($articles as $article) {
			// Added because of fatal errors
			if (!$article) continue;

			$replacements  = 0;
			$links         = $this->getRedirectLinks($article);
			$text          = $this->getArticleText($article);
			$initialLength = mb_strlen( $text );
			$title         = $article->getFullText();

			foreach ($links as $link) {
				$text = preg_replace("~\[\[" . preg_quote($link->old_link, '~') . "\|([^[]*)\]\]~",
										"[[" . $link->new_link . "|$1]]",
										$text, -1, $count);

				if ($count > 0) $replacements += $count;

			}

			$newLength = mb_strlen( $text );

			if ( abs( $newLength - $initialLength ) > 250 ) {
				$this->output( "*** Warning: Content change in excess of 250 characters: `$title'.\n" );
			}

			// We're only going to try publishing if we changed something
			if ($replacements > 0) {
				$status = $this->publishChanges($article, $text, "Updating link to point directly to article instead of redirect");

				if ( $status->isGood() ) {
					$this->output("> Published changes to $title... " . (($limit > 0)? "(" . ($articlesUpdated + 1) . "/$limit)" : "" ).  "\n");
					$articlesUpdated++;
				} else {
					$this->output("*** Skipped over $title, reason:\n");
					$this->output( $status->getWikiText() . "\n" );
				}

				// If we've exceeded the limit, stop processing any more articles
				if ($limit !== 0 && $articlesUpdated >= $limit)
					break;
			}
		}

	}

	/**
	 * Fetches main namespace articles from the database
	 *
	 * @return array of Title objects
	 */
	private function fetchArticles() {
		$dbr      = wfGetDB(DB_SLAVE);
		$articles = array();
		$results  = $dbr->select(array(
			'page'
		), array(
			'page_title'
		), array(
			'page_namespace' => NS_MAIN,
			'page_is_redirect' => 0
		), __METHOD__);

		foreach ($results as $row) {
			$articles[] = Title::newFromDBkey($row->page_title);
		}

		return $articles;
	}

	/**
	 * Looks up all links on this page that point to redirects.
	 * If they point to a redirect, it looks up its title key
	 * @return array of stdClass objects with properties:
	 *          - old_link - text form of a title that redirects
	 *          - new_link - text form of a title that the redirects points to
	 */
	private function getRedirectLinks($article) {
		$dbr   = wfGetDB(DB_SLAVE);
		$links = array();

		// SELECT  pl_title  FROM `pagelinks`,`page`   WHERE
		// (page_title = pl_title) AND page_namespace = '0' AND pl_from = '6670789'
		// AND pl_namespace = '0' AND pl_from_namespace = '0' AND page_is_redirect = '1'

		$results = $dbr->select(array(
			'pagelinks',
			'page'
		), array(
			'pl_title'
		), array(
			'page_title = pl_title',
			'page_namespace' => NS_MAIN,
			'pl_from' => $article->getArticleID(),
			'pl_namespace' => NS_MAIN,
			'page_is_redirect' => '1'
		), __METHOD__);

		foreach ($results as $result) {
			// Ignore these because they are in templates
			if ($result->pl_title == "Writer's-Guide" || $result->pl_title == "Title-policy") {
				continue;
			}

			// SELECT rd_namespace,rd_title  FROM `page`,`redirect`   WHERE (page_id = rd_from)
			// AND page_title = 'Teach-Your-Dog-to-Come' AND page_namespace = '0'
			// AND page_is_redirect = '1'

			$redirectResults = $dbr->select(array(
				'page',
				'redirect'
			), array(
				'rd_namespace',
				'rd_title'
			), array(
				'page_id = rd_from',
				'page_title' => $result->pl_title,
				'page_namespace' => NS_MAIN, // extra field to assist with quick sorting
				'page_is_redirect' => 1 // extra field to assist with quick sorting
			), __METHOD__);

			$row             = $redirectResults->fetchObject();

			if (!$row) continue;

			// Get the full text of the redirect target so we can use it
			// to replace the old text in the article`
			$title = Title::makeTitle($row->rd_namespace, $row->rd_title)->getText();

			$l           = new stdClass();
			$l->old_link = str_replace('-', ' ', $result->pl_title);
			$l->new_link = $title;
			$links[]     = $l;
		}

		return $links;

	}

	/**
	 * Saves the changes to the article in the database.
	 * @param Title $article - Title object representing the page that has been changed
	 * @param string $text - plain text form of the page's new contents
	 * @param string $summary - edit summary to be used when committing the edit
	 *
	 * @return Status object indicating whether the publish was successful
	 */
	private function publishChanges($article, $text, $summary) {

		$status = Status::newGood();

		if ($text == '') {
			$status->error( 'Text was blank' );
			return $status;
		}

		$revision = Revision::newFromPageId($article->getArticleID());
		$page     = WikiPage::newFromID($article->getArticleID());
		$content  = ContentHandler::makeContent($text, $revision->getTitle());
		$status = $page->doEditContent($content, $summary, EDIT_UPDATE | EDIT_FORCE_BOT | EDIT_MINOR, false, $this->bot);

		return $status;
	}

	/**
	 * @param Title $article - Title object representing page to extract text from
	 * @return string containing the raw text content of the page, or empty if unsuccessful
	 */
	private function getArticleText($article) {
		$revision = Revision::newFromPageId($article->getArticleID());

		if (!$revision || !$revision instanceof Revision) {
			return '';
		}

		$content = $revision->getContent(Revision::RAW);
		return ContentHandler::getContentText($content);
	}

}

$maintClass = 'UpdateLinks';
require_once RUN_MAINTENANCE_IF_MAIN;