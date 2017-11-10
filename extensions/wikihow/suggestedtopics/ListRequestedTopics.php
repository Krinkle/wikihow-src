<?php

if ( !defined('MEDIAWIKI') ) die();

class ListRequestedTopics extends SpecialPage {
	const CAT_WIDTH = 201;
	const CAT_HEIGHT = 134;

	public function __construct() {
		parent::__construct( 'ListRequestedTopics' );
	}

	public function execute($par) {
		global $wgRequest, $wgOut, $wgHooks, $wgLanguageCode;

		$wgOut->setHTMLTitle('List Requested Topics - wikiHow');
		$wgOut->setRobotPolicy('noindex,nofollow');

		self::setActiveWidget();
		self::setTopAuthorWidget($par);
		self::getNewArticlesWidget();

		list( $limit, $offset ) = wfCheckLimits();
		$dbr = wfGetDB(DB_SLAVE);

		$wgOut->addModules( ['ext.wikihow.SuggestedTopics'] );
		$wgOut->addModules( ['ext.wikihow.leaderboard'] );

		$wgHooks["pageTabs"][] = array("SuggestedTopicsHooks::requestedTopicsTabs");

		$category = $wgRequest->getVal('category');
		$st_search = $wgRequest->getVal('st_search');

		//heading with link
		$request = '<a href="/Special:RequestTopic" class="editsection">'.wfMsg('requesttopic').'</a>';
		$heading = $request.'<h2>';

		//add altoblock only to headers on opening page
		if (!$st_search && !$category){
			$heading .= '<div class="altblock"></div>';
		}

		$heading .= wfMsg('suggested_list_topics_title').'</h2>';
		//add surpise button
		$heading .= "<a href='/Special:RecommendedArticles?surprise=1' class='button buttonright secondary' id='suggested_surprise'>".wfMsg('suggested_list_button_surprise')."</a>";
		
		if (!$st_search && !$category) {
			//add search box
			$heading .= self::getSearchBox();
			$heading .= '</div> <!-- end div for bodycontents-->';
		}

		$wgOut->addHTML($heading);

		if (!$st_search && !$category) {
			//add sticking second heading
			$html .= '<div class="minor_section section steps   sticky ">';
			$html .= '<h2>';
			$html .= '<div class="altblock"></div>';
			$html .= '<span class="mw-headline">'.wfMsg('Pick-category');
			$html .= '</h2>';
			$html .= "<div class='section_text'>";
			$wgOut->addHTML($html);

			$link = '/Special:ListRequestedTopics';

			$catmap = Categoryhelper::getIconMap();
			ksort($catmap);

			//additional cats added to the end of the list
			$catmap[wfMessage("suggested_list_cat_all")->text()] = "Image:Have-Computer-Fun-Step-22.jpg";
			$catmap[wfMessage("suggested_list_cat_other")->text()] = "Image:Make-a-Light-Bulb-Vase-Step-14.jpg";

			foreach ($catmap as $cat => $image) {

				$title = Title::newFromText($image);
				if ($title) {
					$file = wfFindFile($title, false);
					if (!$file) continue;

					$sourceWidth = $file->getWidth();
					$sourceHeight = $file->getHeight();
					$heightPreference = false;
					if (self::CAT_HEIGHT > self::CAT_WIDTH && $sourceWidth > $sourceHeight) {
						//desired image is portrait
						$heightPreference = true;
					}
					$thumb = $file->getThumbnail(self::CAT_WIDTH, self::CAT_HEIGHT, true, true, $heightPreference);

					$category = urldecode(str_replace("-", " ", $cat));

					$catTitle = Title::newFromText("Category:" . $category);
					if ($catTitle) {
						//'all' category has a different URL
						if ($category == wfMessage("suggested_list_cat_all")->text()){
							$wgOut->addHTML("<div class='thumbnail'><a href=". $link . "?st_search=all><img src='". wfGetPad($thumb->getUrl()) . "' /><div class='text'><p><span>{$category}</span></p></div></a></div>");
						} else {
							$wgOut->addHTML("<div class='thumbnail'><a href=". $link . '?category=' . urlencode($category) . "><img src='" . wfGetPad($thumb->getUrl()) . "' /><div class='text'><p><span>{$category}</span></p></div></a></div>");
						}

					}
				}
			}
			
			$wgOut->addHTML("</div><!-- end section steps sticky -->");
			$wgOut->addHTML("<div class='clearall'></div>");
			$wgOut->addHTML("</div><!-- end section_text -->");
			
		} else { //if the user clicks on one of the icons
			if ($st_search && $st_search != "all") {
				$key = TitleSearch::generateSearchKey($st_search);
				$cat_snippet = ($category) ? "AND st_category = " . $dbr->addQuotes($category) . " " : "";
				$sql = "SELECT st_title, st_user_text, st_user FROM suggested_titles WHERE st_used = 0 " .
					$cat_snippet .
					"AND st_key like " . $dbr->addQuotes("%" . str_replace(" ", "%", $key) . "%") . " ".
					"LIMIT $offset, $limit;";
			} else {
				$sql = "SELECT st_title, st_user_text, st_user FROM suggested_titles WHERE st_used= 0"
				. ($category ? " AND st_category = " . $dbr->addQuotes($category) : '')
				. " AND st_patrolled=1 ORDER BY st_suggested DESC LIMIT $offset, $limit";
			}

			$res = $dbr->query($sql, __METHOD__);
			$wgOut->addHTML(self::getSearchBox($key, $category));

			if ($dbr->numRows($res) > 0) {
				if ($key) {
					$col_header = 'Requests for <strong>"' . htmlentities($key) . '"</strong>';
				} elseif ($category) {
					$col_header = str_replace(" and ", " &amp; ", $category);
				} else {
					$col_header = wfMsg('suggested_list_all');
				}
				
				if ($category && $category != 'Other') {
					$cat_class = preg_replace('/&/','and',$category);
					$cat_class = 'cat_' . strtolower(preg_replace('@[^A-Za-z0-9]@', '', $cat_class));
					$cat_icon = '<div class="cat_icon '.$cat_class.'"></div>';
				}

				$wgOut->addHTML("<table class='suggested_titles_list wh_block'>");
				$wgOut->addHTML("<tr class='st_top_row'><th class='st_icon'>{$cat_icon}</th><th class='st_title'>{$col_header}</th><th>Requested By</th></tr>");
				
				$count = 0;
				foreach ($res as $row) {
					$t = Title::newFromDBKey($row->st_title);
					if (!$t) continue;
					$c = "";
					if ($count % 2 == 1) $c = "class='st_on'";
					if ($row->st_user == 0) {
						$wgOut->addHTML("<tr><td class='st_write'><a href='/Special:CreatePage?target={$t->getPartialURL()}'>Write</td><td class='st_title'>{$t->getText()}</td><td class='st_requestor'>Anonymous</td>
							</tr>");
					} else {
						$u = User::newFromName($row->st_user_text);
						$wgOut->addHTML("<tr><td class='st_write'><a href='/Special:CreatePage?target={$t->getPartialURL()}'>Write</td><td class='st_title'>{$t->getText()}</td><td class='st_requestor'><a href='{$u->getUserPage()->getFullURL()}'>{$u->getName()}</a>
							</tr>");
					}
					$count++;
				}
				$wgOut->addHTML("</table>");
				$key = $st_search;
				if ($offset != 0) {
					$url = $_SERVER['SCRIPT_URI'];
					if ($key)
						$url .= "?st_search=" . urlencode($key);
					elseif ($category)
						$url .= "?category=" . urlencode($category);

					$wgOut->addHTML("<a class='pagination' style='float: left;' href='" . $url . "&offset=" . (max($offset - $limit, 0)) . "'>Previous {$limit}</a>");
				}
				if ($count == $limit) {
					$url = $_SERVER['SCRIPT_URI'];
					if ($key)
						$url .= "?st_search=" . urlencode($key);
					elseif ($category)
						$url .= "?category=" . urlencode($category);

					$wgOut->addHTML("<a class='pagination' style='float: right;' href='" . $url . "&offset=" . ($offset + $limit) . "'>Next {$limit}</a>");
				}
				$wgOut->addHTML("<br class='clearall' />");
			} else {
				if ($key) {
					if ($wgLanguageCode == 'en') {
						$create_link = '/Special:ArticleCreator?t='.urlencode($st_search);
					}
					else {
						$create_link = '/index.php?title='.urlencode($st_search).'&action=edit';
					}

					$html_notfound = '<div class="search_noresults">'.
									wfMessage('suggest_noresults', $st_search)->text().'<br />'.
									'<a href="'.$create_link.'" class="button primary create_btn">'.wfMessage('suggest_start_article', $st_search)->text().'</a><br />'.
									'<a href="/Special:ListRequestedTopics">'.wfMessage('suggest_continue_searching')->text().'</a></div>';

					$wgOut->addHTML($html_notfound);
				}
				else {
					$wgOut->addHTML(wfMsg('suggest_noresults', htmlentities($category)));
				}
			}

			$wgOut->addHTML('</div> <!-- end div for bodycontents-->');
		}
	}

	private static function getSearchBox($searchTerm = "", $category = "") {
		if ($category) $width_style = 'style="width: '.(421-(strlen($category)*6)).'px;"';
	
		$search = '
			<form action="/Special:ListRequestedTopics" id="st_search_form">
			<input type="text" value="' . htmlentities($searchTerm) . '" name="st_search" id="st_search" class="search_input" '.$width_style.' />
			<input type="hidden" name="category" value="' . htmlentities($category) . '" />
			<input type="submit" value="Search ' . htmlentities($category) . '" class="button secondary" id="st_search_btn" style="margin-left:10px;" />
			</form>';
		return $search;
	}

	public static function getCategoryImage($category) {
		$parts = explode(' ', $category);
		$firstName = count($parts) ? strtolower($parts[0]) : '';
		$options = array(
			'arts', 'cars', 'computers', 'education', 'family', 'finance', 'food',
			'health', 'hobbies', 'holidays', 'home', 'personal', 'pets', 'philosophy',
			'relationships', 'sports', 'travel', 'wikihow', 'work', 'youth',
		);
		if (in_array($firstName, $options)) {
			$path = wfGetPad($path);
			$path = wfGetPad("/skins/WikiHow/images/category_icon_$firstName.png");
			$image = "<img src='{$path}' alt='{$category}' />";
		} else {
			$path = '';
			$image = '';
		}
		return $image;
	}

	public static function setActiveWidget() {
		global $wgUser;
		$html = "<div id='stactivewidget'>" . self::getActiveWidget() . "</div>";
		$skin = $wgUser->getSkin();
		$skin->addWidget($html);
	}

	public static function setTopAuthorWidget($target) {
		global $wgUser;
		$html = "<div>" . self::getTopAuthorWidget($target) . "</div>";
		$skin = $wgUser->getSkin();
		$skin->addWidget($html);
	}

	private static function getNewArticlesBox() {
		$dbr = wfGetDB(DB_SLAVE);
		$ids = RisingStar::getRisingStarList(5, $dbr);
		$html = "<div id='side_new_articles'><h3>" . wfMessage('newarticles')->text() . "</h3>\n<table>";
		if ($ids) {
			$res = $dbr->select(array('page'),
				array('page_namespace', 'page_title'),
				array('page_id IN (' . implode(",", $ids) . ")"),
				__METHOD__,
				array('ORDER BY' => 'page_id desc', 'LIMIT' => 5));
			foreach ($res as $row) {
				$t = Title::makeTitle(NS_MAIN, $row->page_title);
				if (!$t) continue;
				$html .= FeaturedArticles::featuredArticlesRow($t);
			}
		}
		$html .=  "</table></div>";
		return $html;
	}

	public static function getNewArticlesWidget() {
		global $wgUser;

		$skin = RequestContext::getMain()->getSkin();
		$html = self::getNewArticlesBox();
		$skin->addWidget($html);
	}

	private static function getTopAuthorWidget($target) {
		$startdate = strtotime('7 days ago');
		$starttimestamp = date('Ymd-G',$startdate) . '!' . floor(date('i',$startdate)/10) . '00000';
		$data = LeaderboardStats::getArticlesWritten($starttimestamp);
		arsort($data);
		$html = "<h3>Top Authors - Last 7 Days</h3><table class='stleaders'>";

		$index = 1;

		foreach ($data as $key => $value) {
			$u = new User();
			$value = number_format($value, 0, "", ',');
			$u->setName($key);
			if (($value > 0) && ($key != '')) {
				$class = "";
				if ($index % 2 == 1)
					$class = 'class="odd"';

				$img = Avatar::getPicture($u->getName(), true);
				if ($img == '') {
					$img = Avatar::getDefaultPicture();
				}

				$html .= "<tr $class>
					<td class='leader_image'>" . $img . "</td>
					<td class='leader_user'>" . Linker::link($u->getUserPage(), $u->getName()) . "</td>
					<td class='leader_count'><a href='/Special:Leaderboard/$target?action=articlelist&lb_name=".$u->getName() ."' >$value</a> </td>
				</tr> ";
				$data[$key] = $value * -1;
				$index++;
			}
			if ($index > 6) break;
		}
		$html .= "</table>";

		return $html;
	}

	public static function getActiveWidget() {
		global $wgUser;

		$html = "<h3>" . wfMsg('st_currentstats') . "</h3><table class='st_stats'>";

		$unw = number_format(self::getUnwrittenTopics(), 0, ".", ", ");

		if ($wgUser->getID() != 0) {
			$today = self::getArticlesWritten(false);
			$topicsToday = self::getTopicsSuggested(false);
			$alltime = self::getArticlesWritten(true);
			$topicsAlltime = self::getTopicsSuggested(true);
		} else {
			$today = Linker::link(Title::makeTitle(NS_SPECIAL, "Userlogin"), "Login");
			$topicsToday = "N/A";
			$alltime = "N/A";
			$topicsAlltime = "N/A";
		}


		$html .= "<tr class='dashed'><td>" . wfMsg('st_numunwritten') . "</td><td class='stcount'>{$unw}</tr>";
		$html .= "<tr><td>" . wfMsg('st_articleswrittentoday') . "</td><td class='stcount' id='patrolledcount'>{$today}</td></tr>";
		$html .= "<tr class='dashed'><td>" . wfMsg('st_articlessuggestedtoday') . "</td><td class='stcount' id='quickedits'>{$topicsToday}</td></tr>";
		$html .= "<tr><td>" . wfMsg('st_alltimewritten'). "</td><td class='stcount' id='alltime'>{$alltime}</td></tr>";
		$html .= "<tr class='dashed'><td>" . wfMsg('st_alltimesuggested'). "</td><td class='stcount'>{$topicsAlltime}</td></tr>";
		$html .= "</table><center>" . wfMsg('rcpatrolstats_activeupdate') . "</center>";
		return $html;
	}

	// Used in community dashboard
	public static function getUnwrittenTopics() {
		$dbr = wfGetDB(DB_SLAVE);
		$count = $dbr->selectField('suggested_titles',
			array('count(*)'),
			array('st_used' => 0),
			__METHOD__);
		return $count;
	}

	private static function getArticlesWritten($alltime) {
		global $wgUser;
		$dbr = wfGetDB(DB_SLAVE);
		$conds = array('fe_user' => $wgUser->getID(), 'page_id = fe_page', 'page_namespace=0');
		if (!$alltime) {
			// just today
			$cutoff = wfTimestamp(TS_MW, time() - 24 * 3600);
			$conds[] = "fe_timestamp > '{$cutoff}'";
		}
		$count = $dbr->selectField( array('firstedit', 'page'),
			array('count(*)'),
			$conds,
			__METHOD__);

		return number_format($count, 0, ".", ", ");
	}

	private static function getTopicsSuggested($alltime) {
		global $wgUser;
		$dbr = wfGetDB(DB_SLAVE);
		$conds = array('fe_user' => $wgUser->getID(), 'fe_page=page_id', 'page_title=st_title', 'page_namespace=0');
		if (!$alltime) {
			// just today
			$cutoff = wfTimestamp(TS_MW, time() - 24 * 3600);
			$conds[] = "fe_timestamp > '{$cutoff}'";
		}
		$count = $dbr->selectField(array('firstedit', 'page' ,'suggested_titles'),
			array('count(*)'),
			$conds,
			__METHOD__);

		return number_format($count, 0, ".", ", ");
	}

}


