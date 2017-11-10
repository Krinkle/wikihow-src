<?php

class Articlestats extends SpecialPage {

	function __construct() {
		parent::__construct( 'Articlestats' );
	}

	function execute($par) {
		global $wgRequest, $wgOut;

		$this->setHeaders();

		$target = $par != '' ? $par : $wgRequest->getVal('target');

		if (!$target) {
			$wgOut->addHTML(wfMsg('articlestats_notitle'));
			return;
		}

		$t = Title::newFromText($target);
		$id = $t->getArticleID();
		if ($id == 0) {
			$wgOut->addHTML(wfMsg("checkquality_titlenonexistant"));
			return;
		}

		$dbr = wfGetDB(DB_SLAVE);

		$related = $dbr->selectField(
					"pagelinks",
					"count(*)",
					array ('pl_from' => $id),
					__METHOD__);
		$inbound = $dbr->selectField(
					array("pagelinks", "page"),
					"count(*)",
					array( 'pl_namespace' => $t->getNamespace(),
						   'pl_title' => $t->getDBKey(),
						   'page_id=pl_from',
						   'page_namespace=0' ),
					__METHOD__);

		$sources = $dbr->selectField(
						"externallinks",
						"count(*)",
						array( 'el_from' => $t->getArticleID() ),
						__METHOD__);

		$langlinks = $dbr->selectField(
						"langlinks",
						"count(*)",
						array( 'll_from' => $t->getArticleID() ),
						__METHOD__);

		// talk page
		$f = Title::newFromText("Featured", NS_TEMPLATE);

		$tp = $t->getTalkPage();
		$featured = $dbr->selectField(
						"templatelinks",
						"count(*)",
						array( 'tl_from' => $tp->getArticleID(),
							   'tl_namespace' => 10,
							   'tl_title' => 'Featured' ),
						__METHOD__);

		$fadate = "";
		if ($featured > 0) {
			$rev = Revision::newFromTitle($tp );
			$text = $rev->getText();
			$matches = array();
			preg_match('/{{(Featured|fa)[^a-z}]*}}/i', $text, $matches);
			$fadate = $matches[0];
			$fadate = preg_replace("@{{(Featured|Fa)\|@i", "", $fadate);
			$fadate = str_replace("}}", "", $fadate);
			if ($fadate) $fadate = "($fadate)";
			$featured = wfMessage('articlestats_yes');
		} else {
			$featured = wfMessage('articlestats_no');
		}

		$rev = Revision::newFromTitle($t );
		$section = Article::getSection($rev->getText(), 0);
		$intro_photo = preg_match('/\[\[Image:/', $section) == 1 ? wfMsg('articlestats_yes') : wfMsg('articlestats_no');

		$section = Article::getSection($rev->getText(), 1);
		preg_match("/==[ ]*" . wfMsg('steps') . "/", $section, $matches, PREG_OFFSET_CAPTURE);
		if (sizeof($matches) == 0 || $matches[0][1] != 0) {
			$section = Article::getSection($rev->getText(), 2);
		}

		$num_steps = preg_match_all('/^#/im', $section, $matches);
		$num_step_photos = preg_match_all('/\[\[Image:/', $section, $matches);
		$has_stepbystep_photos = wfMsg('articlestats_no');
		if ($num_steps > 0) {
			$has_stepbystep_photos = ($num_step_photos / $num_steps) > 0.5 ? wfMsg('articlestats_yes') : wfMsg('articlestats_no');
		}


		$linkshere = Title::newFromText("Whatlinkshere", NS_SPECIAL);
		$linksherelink = Linker::link( $linkshere, $inbound, array(), array('target' => $t->getPrefixedURL() ) );
		$articlelink = Linker::link( $t, wfMessage('howto', $t->getFullText())->text() );

		$numvotes = $dbr->selectField("rating",
						"count(*)",
						array('rat_page' => $t->getArticleID(), "rat_isdeleted=0"),
						__METHOD__);
		$rating = $dbr->selectField("rating",
						"avg(rat_rating)",
						array('rat_page' => $t->getArticleID(), 'rat_isdeleted' => 0),
						__METHOD__);
		$unique = $dbr->selectField("rating",
						"count(distinct(rat_user_text))",
						array('rat_page' => $t->getArticleID(), "rat_isdeleted=0"),
						__METHOD__);
		$rating = number_format($rating * 100, 0, "", "");


		$a = new Article($t);
		$count = $a->getCount();
		$pageviews = number_format($count, 0, "", ",");


		$accuracy = '<img src="/skins/WikiHow/images/grey_ball.png">&nbsp; &nbsp;' . wfMsg('articlestats_notenoughvotes');
		if ($numvotes >= 5) {
			if ($rating > 70) {
				$ball_color = 'green';
			} elseif ($rating > 40) {
				$ball_color = 'yellow';
			} else {
				$ball_color = 'red';
			}
			$accuracy = '<img src="/skins/WikiHow/images/' . $ball_color . '_ball.png">';
			$accuracy .= "&nbsp; &nbsp;" . wfMsg('articlestats_rating', $rating, $numvotes, $unique);
		}
		if ($index > 10 || $index == 0) {
			$index = wfMsg('articlestats_notintopten', wfMsg('howto', urlencode($t->getText())));
			$index .= "<br/>" . wfMsg('articlestats_lastchecked', substr($max, 0, 10) );
		} elseif ($index < 0) {
			$index = wfMsg('articlestats_notcheckedyet', wfMsg('howto', urlencode($t->getText())));
		} else {
			$index = wfMsg('articlestats_indexrank', wfMsg('howto', urlencode($t->getText())), $index);
			$index .= wfMsg('articlestats_lastchecked', substr($max, 0, 10));
		}

		$cl = SpecialPage::getTitleFor( 'ClearRatings', $t->getText() );

		$wgOut->addHTML("

		<p> $articlelink<br/>
		<table border=0 cellpadding=5>
				<tr><td width='350px;' valign='middle' >
						" . wfMsgExt('articlestats_accuracy', 'parseinline', $cl->getFullText()) . " </td><td valign='middle'> $accuracy<br/> </td></tr>
				<tr><td>" . wfMsgExt('articlestats_hasphotoinintro', 'parseinline') . "</td><td>$intro_photo </td></tr>
				<tr><td>" . wfMsgExt('articlestats_stepbystepphotos', 'parseinline') ."</td><td> $has_stepbystep_photos </td></tr>
				<tr><td>" . wfMsgExt('articlestats_isfeatured', 'parseinline') . "</td><td> $featured $fadate </td></tr>
				<tr><td>" . wfMsgExt('articlestats_numinboundlinks', 'parseinline') . "</td><td>  $linksherelink</td></tr>
				<tr><td>" . wfMsgExt('articlestats_outboundlinks', 'parseinline') . "</td><td> $related </td></tr>
				<tr><td>" . wfMsgExt('articlestats_sources', 'parseinline') . "</td><td> $sources</td></tr>
				<tr><td>" . wfMsgExt('articlestats_langlinks', 'parseinline') . "</td><td> $langlinks</td></tr>
		</table>
		</p> " . wfMsgExt('articlestats_footer', 'parseinline') . "
				");

	}

}

