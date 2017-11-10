<?php

if ( !defined('MEDIAWIKI') ) die();

class RecommendedArticles extends SpecialPage {

	public function __construct() {
        parent::__construct( 'RecommendedArticles' );
    }

	// for the two little boxes at the top.
	private static function getTopLevelSuggestions($map, $cats) {
		$dbr = wfGetDB(DB_SLAVE);
		$cat1 = $cats[0];
		$cat2 = sizeof($cats) > 1 ? $cats[1] : $cats[0];
		$top = array($cat1, $cat2);
		$suggests = array();
		$users = array();
		$catresults = array();

		$catarray = "(";
		for ($i = 0; $i < count($cats); $i++) {
			if ($i > 0) $catarray .= ",";
			$catarray .= "'{$map[$cats[$i]]}'";
		}
		$catarray .= ")";

		$randstr = wfRandom();
		$conds = array('st_used' => 0, 'st_traffic_volume' => 2, "st_random >= $randstr");

		if (count($cats) > 0)
			$conds[] = "st_category IN $catarray";
		$rows = $dbr->select('suggested_titles', 
			array('st_title', 'st_user', 'st_user_text', 'st_category'),
			$conds,
			__METHOD__,
			array('ORDER BY'=>'st_random', 'GROUP BY' => 'st_category'));

		if ($dbr->numRows($rows) == 0) {
			$conds = array('st_used=0', 'st_traffic_volume'=>2, "st_random >= $randstr");
			$rows = $dbr->select('suggested_titles', 
				array('st_title', 'st_user', 'st_user_text', 'st_category'),
				$conds,
				__METHOD__, 
				array('ORDER BY' => 'st_random', 'GROUP BY' => 'st_category'));
			for ($i = 0; $i < 2; $i++) {
				$row = $dbr->fetchRow($rows);
				$t = Title::makeTitle(NS_MAIN, $row['st_title']);
				$suggests[] = $t;
				$users[] = $row['st_user_text'];
				$userids[] = $row['st_user'];
				$catresults[] = $row['st_category'];
			}
		} elseif ($dbr->numRows($rows) == 1) {
			$row = $dbr->fetchRow($rows);
			$t = Title::makeTitle(NS_MAIN, $row['st_title']);
			$suggests[] = $t;
			$users[] = $row['st_user_text'];
			$userids[] = $row['st_user'];
			$catresults[] = $row['st_category'];

			$randstr = wfRandom();
			$conds = array('st_used=0', 'st_traffic_volume'=>2, "st_random >= $randstr", "st_category IN $catarray", "st_title != '" . $row['st_title'] . "'");
			$rows2 = $dbr->select('suggested_titles',
				array('st_title', 'st_user', 'st_user_text', 'st_category'),
				$conds,
				__METHOD__,
				array('ORDER BY'=>'st_random', 'GROUP BY' => 'st_category'));
			if ($dbr->numRows($rows2) >= 1) {
				$row = $dbr->fetchRow($rows2);
				$t = Title::makeTitle(NS_MAIN, $row['st_title']);
				$suggests[] = $t;
				$users[] = $row['st_user_text'];
				$userids[] = $row['st_user'];
				$catresults[] = $row['st_category'];
			} else {
				$conds = array('st_used=0', 'st_traffic_volume'=>2, "st_random >= $randstr");
				$rows = $dbr->select('suggested_titles',
					array('st_title', 'st_user', 'st_user_text', 'st_category'),
					$conds,
					__METHOD__,
					array('ORDER BY'=>'st_random', 'GROUP BY' => 'st_category'));
				$row = $dbr->fetchRow($rows);
				$t = Title::makeTitle(NS_MAIN, $row['st_title']);
				$suggests[] = $t;
				$users[] = $row['st_user_text'];
				$userids[] = $row['st_user'];
				$catresults[] = $row['st_category'];
			}

		} else {
			for ($i = 0; $i < 2; $i++) {
				$row = $dbr->fetchRow($rows);
				$t = Title::makeTitle(NS_MAIN, $row['st_title']);
				$suggests[] = $t;
				$users[] = $row['st_user_text'];
				$userids[] = $row['st_user'];
				$catresults[] = $row['st_category'];
			}
		}
	
		$s = '';
		for ($i = 0; $i < 2; $i++) {
			if ($i == 1) {
				//add 'or'
				$s .= '<div class="top_suggestion_or">OR</div>';
			}
			
			if ($userids[$i] > 0) {
				$u = User::newFromName($users[$i]);
				$user_line = "<a href='{$u->getUserPage()->getFullURL()}'>{$u->getName()}</a>";
			} else {
				$user_line = wfMsg('anonymous');
			}
			
			$s .= 	'<div class="top_suggestion_box">' .
					'<div class="category">'.$catresults[$i].'</div>' .
					'<div class="title">'.$suggests[$i]->getText().'</div>' .
					'<div class="requestor"><img src="' . Avatar::getAvatarURL($users[$i]) . '"/>' .
					'<a href="/Special:CreatePage?target='.$suggests[$i]->getPartialURL().'" class="button secondary">Write</a>' .
					'Requested By<br />'.$user_line.'</div>' .
					'</div>'; 
		}
		$s .= '<br class="clearall" />';
		
		return $s;
	}

    public function execute($par) {
		global $wgOut, $wgRequest, $wgUser, $wgTitle, $wgLanguageCode, $wgHooks;

		if ($wgLanguageCode != 'en') {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		$map = SuggestCategories::getCatMap(true);
		$cats = SuggestCategories::getSubscribedCats();
		$dbr = wfGetDB(DB_SLAVE);
		$wgOut->setRobotPolicy('noindex,nofollow');
		$wgOut->setHTMLTitle('Manage Suggested Topics - wikiHow');

		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );

		if ($target == 'TopRow') {
			$wgOut->setArticleBodyOnly(true);
			$wgOut->addHTML(self::getTopLevelSuggestions($map, $cats));
			return;
		}
		$wgOut->addModules( ['ext.wikihow.SuggestedTopics'] );

		ListRequestedTopics::setActiveWidget();
		ListRequestedTopics::setTopAuthorWidget($target);
		ListRequestedTopics::getNewArticlesWidget();

		$wgHooks["pageTabs"][] = array("SuggestedTopicsHooks::requestedTopicsTabs");

		//heading with link
		$request = '<a href="/Special:RequestTopic" class="editsection">'.wfMsg('requesttopic').'</a>';
		$heading = $request.'<h2>'.wfMsg('suggestedarticles_header').'</h2>';
						
		$wgOut->addHTML($heading);

		$suggestions = "";

		if (count($cats) > 0) {
			foreach ($cats as $key) {
				$cat = $map[$key];
				$suggestionsArray = array();

				// grab some suggestions
				$randstr = wfRandom();
				$headerDone = false;
				$suggCount = 0;
				// grab 2 suggested articles that are NOT by ANON
				$resUser = $dbr->select('suggested_titles', array('st_title', 'st_user', 'st_user_text'),
					array('st_category' => $cat, 'st_used=0', "st_user > 0"),
					__METHOD__,
					array("ORDER BY" => "st_random", "LIMIT"=>2)
				);
				foreach ($resUser as $userRow) {
					$randSpot = mt_rand(0, 4);
					while (!empty($suggestionsArray[$randSpot]))
						$randSpot = mt_rand(0, 4);
					$suggestionsArray[$randSpot] = new stdClass;
					$suggestionsArray[$randSpot]->title = $userRow->st_title;
					$suggestionsArray[$randSpot]->user = $userRow->st_user;
					$suggCount++;
				}

				$res = $dbr->select('suggested_titles', array('st_title', 'st_user', 'st_user_text'),
					array('st_category' => $cat, 'st_used' => 0, 'st_traffic_volume' => 2, "st_random >= $randstr"),
					__METHOD__,
					array("ORDER BY" => "st_random", "LIMIT"=>5)
				);
				if ($dbr->numRows($res) > 0) {
					foreach ($res as $row) {
						if ($suggCount >= 5)
							break;
						$randSpot = mt_rand(0, 4);
						while (!empty($suggestionsArray[$randSpot]))
							$randSpot = mt_rand(0, 4);
						$suggestionsArray[$randSpot] = new stdClass;
						$suggestionsArray[$randSpot]->title = $row->st_title;
						$suggestionsArray[$randSpot]->user = $row->st_user;
						$suggCount++;
					}
				}
				
				if ($cat != 'Other') {
					$cat_class = 'cat_'.strtolower(str_replace(' ','',$cat));
					$cat_class = preg_replace('/&/','and',$cat_class);
					$cat_icon = '<div class="cat_icon '.$cat_class.'"></div>';
				}
				else {
					$cat_icon = '';
				}

				if ($suggCount > 0) {
					$suggestions .= "<table class='suggested_titles_list wh_block'>";
					$suggestions .= "<tr class='st_top_row'><th class='st_icon'>{$cat_icon}</th><th class='st_title'><strong>{$cat}</strong></th><th>Requested By</th></tr>";
					
					foreach ($suggestionsArray as $suggestion) {
						if (!empty($suggestionsArray)) {
							$t = Title::newFromText(GuidedEditorHelper::formatTitle($suggestion->title));
							if ($suggestion->user > 0) {
								$u = User::newFromId($suggestion->user);
								$u = "<a href='{$u->getUserPage()->getFullURL()}'>{$u->getName()}</a>";
							}
							else
								$u = "Anonymous";
							$suggestions .= "<tr><td class='st_write'><a href='/Special:CreatePage?target={$t->getPartialURL()}'>Write</td><td class='st_title'>{$t->getText()}</td><td class='st_requestor'>{$u}</td></tr>";

						}
					}

					$suggestions .= "</table>";
				}
			}
		}

		if ($wgRequest->getInt('surprise') == 1 || $suggestions == "")
			$wgOut->addHTML("<div id='top_suggestions'>" . self::getTopLevelSuggestions($map, $cats) . "</div>");

		$wgOut->addHTML("<br class='clearall' /><div id='suggested_surprise_big'><a href='/Special:RecommendedArticles?surprise=1' class='button secondary'>".wfMsg('suggested_list_button_surprise')."</a></div><br class='clearall' />");
			
		if (sizeof($cats) == 0) {
			$wgOut->addHTML(wfMsg('suggested_nocats'));
			$wgOut->addHTML("<a href='#' id='choose_cats'>Choose which categories to display</a>");
			return;
		}

		if ($wgUser->getID() > 0) {
			$wgOut->addHTML($suggestions);
			$wgOut->addHTML("<a href='#' id='choose_cats'>Choose which categories to display</a>");
		} else {
			$rt = $wgTitle->getPrefixedURL();
			$q = "returnto={$rt}";
			$wgOut->addHTML(wfMsg('recommend_anon', $q));
		}
    }

}

