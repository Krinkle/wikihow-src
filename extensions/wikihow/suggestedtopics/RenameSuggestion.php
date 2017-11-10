<?php

if ( !defined('MEDIAWIKI') ) die();

class RenameSuggestion extends UnlistedSpecialPage {

    public function __construct() {
        parent::__construct( 'RenameSuggestion' );
    }

    public function execute($par) {
		global $wgOut, $wgRequest;
		$name = $wgRequest->getVal( 'name' );
		$id = $wgRequest->getVal( 'id' );
		$wgOut->setArticleBodyOnly(true);
		$wgOut->addHTML(wfMsg('suggested_edit_title',$name,$id));
    }

}

