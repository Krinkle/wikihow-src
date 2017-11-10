<?php

class ProfileBadges extends SpecialPage {

	/***************************
	 **
	 **
	 ***************************/
	function __construct() {
		parent::__construct( 'ProfileBadges' );
	}

	function execute($par) {
		global $wgOut;

		$wgOut->addHTML(HtmlSnips::makeUrlTag('/extensions/wikihow/ProfileBadges.css'));

		$wgOut->setPageTitle(wfMsg('ab-title'));

		$wgOut->addHTML("<div class='undoArticleInner'>");
		$wgOut->addHTML(ProfileBadges::getBadge('admin'));
		$wgOut->addHTML(ProfileBadges::getBadge('nab'));
		$wgOut->addHTML(ProfileBadges::getBadge('fa'));
		$wgOut->addHTML(ProfileBadges::getBadge('welcome'));
		$wgOut->addHTML("</div>");
	}

	function getBadge($badgeName) {
		$html = "<div class='ab-box'>";
		$html .= "<div class='ab-badge ab-" . $badgeName . "'></div>";
		$html .= "<h3>" . wfMsg("ab-" . $badgeName . "-title") . "</h3>";
		$html .= wfMsgWikiHtml("ab-" . $badgeName . "-description");
		$html .= "</div>";

		return $html;
	}

}

