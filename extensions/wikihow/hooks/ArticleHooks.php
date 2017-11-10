<?php

if (!defined('MEDIAWIKI')) die();

class ArticleHooks {

	public static function onArticleSaveUndoEditMarkPatrolled($article, $user, $p2, $p3, $p5, $p6, $p7) {
		global $wgMemc, $wgRequest;

		$oldid = $wgRequest->getInt('wpUndoEdit');
		if ($oldid) {
			// using db master to avoid db replication lag
			$dbr = wfGetDB(DB_MASTER);
			$rcid = $dbr->selectField('recentchanges', 'rc_id', array('rc_this_oldid' => $oldid), __METHOD__);
			RecentChange::markPatrolled($rcid);
			PatrolLog::record($rcid, false);
		}

		// In WikiHowSkin.php we cache the info for the author line. we want to
		// remove this if that article was edited so that old info isn't cached.
		if ($article && class_exists('SkinWikihowskin')) {
			$cachekey = ArticleAuthors::getLoadAuthorsCachekey($article->getID());
			$wgMemc->delete($cachekey);
		}

		return true;
	}

	public static function updatePageFeaturedFurtherEditing($article, $user, $text, $summary, $flags) {
		if ($article) {
			$t = $article->getTitle();
			if (!$t || !$t->inNamespace(NS_MAIN)) {
				return true;
			}
		}

		$templates = explode("\n", wfMessage('templates_further_editing')->inContentLanguage()->text());
		$regexps = array();
		foreach ($templates as $template) {
			$template = trim($template);
			if ($template == "") continue;
			$regexps[] ='\{\{' . $template;
		}
		$re = "@" . implode("|", $regexps) . "@i";

		$updates = array();
		if (preg_match_all($re, $text, $matches)) {
			$updates['page_further_editing'] = 1;
		}
		else{
			$updates['page_further_editing'] = 0; //added this to remove the further_editing tag if its no longer needed
		}
		if (preg_match("@\{\{fa\}\}@i", $text)) {
			$updates['page_is_featured'] = 1;
		}
		if (sizeof($updates) > 0) {
			$dbw = wfGetDB(DB_MASTER);
			$dbw->update('page', $updates, array('page_id'=>$t->getArticleID()), __METHOD__);
		}
		return true;
	}

	public static function editPageBeforeEditToolbar(&$toolbar) {
		global $wgStylePath, $wgOut, $wgLanguageCode;

		$params = array(
			$image = $wgStylePath . '/owl/images/1x1_transparent.gif',
			// Note that we use the tip both for the ALT tag and the TITLE tag of the image.
			// Older browsers show a "speedtip" type message only for ALT.
			// Ideally these should be different, realistically they
			// probably don't need to be.
			$tip = 'Weave links',
			$open = '',
			$close = '',
			$sample = '',
			$cssId = 'weave_button',
		);
		$script = Xml::encodeJsCall( 'mw.toolbar.addButton', $params );
		$wgOut->addScript( Html::inlineScript( ResourceLoader::makeLoaderConditionalScript($script) ) );

		$params = array(
			$image = $wgStylePath . '/owl/images/1x1_transparent.gif',
			// Note that we use the tip both for the ALT tag and the TITLE tag of the image.
			// Older browsers show a "speedtip" type message only for ALT.
			// Ideally these should be different, realistically they
			// probably don't need to be.
			$tip = 'Add Image',
			$open = '',
			$close = '',
			$sample = '',
			$cssId = 'imageupload_button',
		);
		$script = Xml::encodeJsCall( 'mw.toolbar.addButton', $params );
		$wgOut->addScript( Html::inlineScript( ResourceLoader::makeLoaderConditionalScript($script) ) );

		// TODO, from Reuben: this RL module and JS/CSS/HTML should really be attached inside the
		//   EditPage::showEditForm:initial hook, which happens just before the edit form. Doing
		//   this hook work inside the edit form creates some pretty arbitrary restrictions (like
		//   the form-within-a-form problem).
		$wgOut->addModules('ext.wikihow.popbox');
		$popbox = PopBox::getPopBoxJSAdvanced();
		$popbox_div = PopBox::getPopBoxDiv();
		$wgOut->addHTML($popbox_div . $popbox);

		return true;
	}

	function onDoEditSectionLink($skin, $nt, $section, $tooltip, &$result, $lang) {
		$query = array();
		$query['action'] = "edit";
		$query['section'] = $section;

		//INTL: Edit section buttons need to be bigger for intl sites
		$editSectionButtonClass = "editsection";
		$customAttribs = array(
			'class' => $editSectionButtonClass,
			'onclick' => "gatTrack(gatUser,\'Edit\',\'Edit_section\');",
			'tabindex' => '-1',
			'title' => wfMessage('editsectionhint')->rawParams( htmlspecialchars($tooltip) )->escaped(),
			'aria-label' => wfMessage('aria_edit_section')->rawParams( htmlspecialchars($tooltip) )->showIfExists(),
		);

		$result = Linker::link( $nt, wfMessage('editsection')->text(), $customAttribs, $query, "known");

		return true;
	}

	/**
	 * Add global variables
	 */
	public static function addGlobalVariables(&$vars, $outputPage) {
		global $wgFBAppId, $wgGoogleAppId;
		$vars['wgWikihowSiteRev'] = WH_SITEREV;
		$vars['wgFBAppId'] = $wgFBAppId;
		$vars['wgGoogleAppId'] = $wgGoogleAppId;
		$vars['wgCivicAppId'] = WH_CIVIC_APP_ID;

		return true;
	}

	// Add to the list of available JS vars on every page
	public static function addJSglobals(&$vars) {
		$vars['wgCDNbase'] = wfGetPad('');
        $tree = Categoryhelper::getCurrentParentCategoryTree();
        $cats = Categoryhelper::cleanCurrentParentCategoryTree( $tree );
		$vars['wgCategories'] = $cats;
		return true;
	}

	public static function onDeferHeadScripts($outputPage, &$defer) {
		$ctx = $outputPage->getContext();
		if ($ctx->getTitle()->inNamespace(NS_MAIN)
			&& $ctx->getRequest()->getVal('action', 'view') == 'view'
			&& ! $ctx->getTitle()->isMainPage()
		) {
			$isMobileMode = Misc::isMobileMode();
			$defer = $isMobileMode;
		}
		return true;
	}

	public static function onArticleShowPatrolFooter() {
		return false;
	}

	public static function turnOffAutoTOC(&$parser) {
		$parser->mShowToc = false;

		return true;
	}

	public static function runAtAGlanceTest( $title ) {
		if ( class_exists( 'AtAGlance' ) ) {
			AtAGlance::runArticleHookTest( $title );
		}
		return true;
	}

	public static function firstEditPopCheck($page, $user) {
		global $wgLanguageCode;

		if ($wgLanguageCode != 'en') return true;

		$ctx = RequestContext::getMain();
		$title = $ctx->getTitle();
		if (!$title || !$title->inNamespace(NS_MAIN)) return true;

		$t = $page->getTitle();
		if (!$t || !$t->exists() || !$t->inNamespace(NS_MAIN)) return true;

		$first_edit = $user->isAnon() ? $_COOKIE['num_edits'] == 1 : $user->getEditCount() == 0;
		if (!$first_edit) return true;

		// it must have at least two revisions to show popup
		$dbr = wfGetDB(DB_SLAVE);
		$rev_count = $dbr->selectField('revision', 'count(*)', array('rev_page' => $page->getID()), __METHOD__);
		if ($rev_count < 2) return true;

		// set the trigger cookie
		$ctx->getRequest()->response()->setcookie('firstEditPop1', 1, time()+3600, array('secure') );

		return true;
	}

	public static function firstEditPopIt() {
		$ctx = RequestContext::getMain();
		$title = $ctx->getTitle();

		if ( $title && $title->inNamespace(NS_MAIN) && $ctx->getRequest()->getCookie( 'firstEditPop1' )  == 1 ) {
			$out = $ctx->getOutput();
			$out->addModules('ext.wikihow.first_edit_modal');
			//remove the cookie
			$ctx->getRequest()->response()->setcookie('firstEditPop1', 0, time()-3600, array('secure') );
		}
		return true;
	}

	// Run on PageContentSaveComplete. It adds a tag to the first main namespace
	// edit done by a user with 0 contributions. Note that this is tag is not set
	// for anon users because they don't have a running contrib count.
	public static function onPageContentSaveCompleteAddFirstEditTag(
		$article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId
	) {
		global $wgIgnoreNamespacesForEditCount;

		if ($user && $revision && $article
			&& !$user->isAnon()
			&& !in_array( $article->getTitle()->getNamespace(), $wgIgnoreNamespacesForEditCount )
			&& $user->getEditCount() == 0
		) {
			ChangeTags::addTags("First Contribution from User", null, $revision->getId());
		}
		return true;
	}

	// hook run when the good revision for an article has been updated
	public static function updateExpertVerifiedRevision( $pageId, $revisionId ) {
		if ( class_exists( 'ArticleVerifyReview' ) && class_exists( 'VerifyData' ) ) {
			if ( VerifyData::inVerifyList( $pageId ) ) {
				ArticleVerifyReview::addItem( $pageId, $revisionId );
			}
		}
		return true;
	}

}
