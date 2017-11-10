<?php

if ( !defined('MEDIAWIKI') ) die();

class YourArticles extends SpecialPage {

    public function __construct() {
        parent::__construct( 'YourArticles' );
    }

	private static function getAuthors($t) {
		$dbr = wfGetDB(DB_SLAVE);
		$authors = array();
        $res = $dbr->select('revision',
            array('rev_user', 'rev_user_text'),
            array('rev_page'=> $t->getArticleID()),
            __METHOD__,
            array('ORDER BY' => 'rev_timestamp')
        );
		foreach ($res as $row) {
            if ($row->rev_user == 0) {
               $authors['anonymous'] = 1;
            } elseif (!isset($authors[$row->rev_user_text])) {
               $authors[$row->rev_user_text] = 1;
            }
        }
		return array_reverse($authors);
	}

    public function execute($par) {
		global $wgOut, $wgUser, $wgTitle, $wgLanguageCode, $wgHooks;

		if ($wgLanguageCode != 'en') {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			$wgOut->setRobotPolicy('noindex,nofollow');
			return;
		}

		$wgOut->addModules( ['ext.wikihow.SuggestedTopics'] );

		ListRequestedTopics::setActiveWidget();
		ListRequestedTopics::setTopAuthorWidget($par);
		ListRequestedTopics::getNewArticlesWidget();

		$wgHooks["pageTabs"][] = array("SuggestedTopicsHooks::requestedTopicsTabs");

		$wgOut->setHTMLTitle('Articles Started By You - wikiHow');
		$wgOut->setRobotPolicy('noindex,nofollow');

		//heading with link
		$request = '<a href="/Special:RequestTopic" class="editsection">'.wfMsg('requesttopic').'</a>';
		$heading = $request.'<h2>'.wfMsg('your_articles_header').'</h2>';
						
		//add surpise button
		$heading .= "<a href='/Special:RecommendedArticles?surprise=1' class='button buttonright secondary' id='suggested_surprise'>".wfMsg('suggested_list_button_surprise')."</a><br /><br /><br />";
		$wgOut->addHTML($heading);

		if ($wgUser->getID() > 0) {

			$dbr = wfGetDB(DB_SLAVE);
			$res = $dbr->query("select * from firstedit left join page on fe_page=page_id
					left join suggested_titles on page_title=st_title and page_namespace= 0 where fe_user={$wgUser->getID()} and page_id is not NULL order by st_category",
				__METHOD__);

			if ($dbr->numRows($res) == 0) {
				$wgOut->addHTML(wfMsg("yourarticles_none"));
				return;
			}

			$last_cat = "-";

			// group it by categories
			// sometimes st_category is not set, so we have to grab the top category
			// from the title object of the target article
			$articles = array();
			foreach ($res as $row) {
				$t = Title::makeTitle(NS_MAIN, $row->page_title);
				$cat = $row->st_category;
				if ($cat == '') {
					$str = Categoryhelper::getTopCategory($t);
					if ($str != '')  {
						$title = Title::makeTitle(NS_CATEGORY, $str);
						$cat = $title->getText();
					} else {
						$cat = "Other";
					}
				}
				if (!isset($articles[$cat]))
					$articles[$cat] = array();
				$articles[$cat][] = $row;
			}
			
			foreach ($articles as $cat=>$article_array) {
				$image = ListRequestedTopics::getCategoryImage($cat);
				$style = "";
				if ($image == "") {
					$style = "style='padding-left:67px;'";
				}
				 
				$wgOut->addHTML('<h2>'.$cat.'</h2><div class="wh_block"><table class="suggested_titles_list">');
				
				foreach ($article_array as $row) {
					$t = Title::makeTitle(NS_MAIN, $row->page_title);
					$ago = wfTimeAgo($row->page_touched);
					$authors = array_keys(self::getAuthors($t));
					$a_out = array();
					for ($i = 0; $i < 2 && sizeof($authors) > 0; $i++) {
						$a = array_shift($authors);
						if ($a == 'anonymous')  {
							$a_out[] = "Anonymous"; // duh
						} else {
							$u = User::newFromName($a);
							if (!$u) {
								echo "{$a} broke";
								exit;
							}
							$a_out[] = "<a href='{$u->getUserPage()->getFullURL()}'>{$u->getName()}</a>";
						}
					}
					$img = ImageHelper::getGalleryImage($t, 46, 35);
					$wgOut->addHTML("<tr><td class='article_image'><img src='{$img}' alt='' width='46' height='35' /></td>"
						 . "<td><h3><a href='{$t->getFullURL()}' class='title'>" . wfMsg('howto', $t->getFullText()) . "</a></h3>"
						. "<p class='meta_info'>Authored by: <a href='{$wgUser->getUserPage()->getFullURL()}'>You</a></p>"
						. "<p class='meta_info'>Edits by: " . implode(", ", $a_out) . " (<a href='{$t->getFullURL()}?action=credits'>see all</a>)</p>"
						. "<p class='meta_info'>Last updated {$ago}</p>"
						. "</td>"
						. "<td class='view_count'>" . number_format($row->page_counter, 0, "", ",") . "</td></tr>"
					);
				}
				$wgOut->addHTML('</table></div>');
			}
		} else {
			$rt = $wgTitle->getPrefixedURL();
			$q = "returnto={$rt}";
			$wgOut->addHTML( wfMsg('yourarticles_anon', $q) );
		}
    }

}

