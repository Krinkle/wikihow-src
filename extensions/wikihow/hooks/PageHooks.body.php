<?php

class PageHooks {

	// Allow varnish to purge un-urlencoded version of urls so that articles such
	// as Solve-a-Rubik's-Cube-(Easy-Move-Notation) can be requested without
	// passing through the varnish caches. We had a site stability issue for
	// logged in users on 5/19/2014 because Google featured the Rubik's cube on
	// their home page and a lot of people suddenly searched for it. All requests
	// were passed to our backend, which caused stability issues.
	public static function onTitleSquidURLsDecode($title, &$urls) {
		$reverse = array_flip($urls);
		foreach (array_keys($reverse) as $url) {
			$decoded = urldecode($url);
			if ( !isset($reverse[$decoded]) ) {
				$reverse[$decoded] = true;
				$urls[] = $decoded;
			}
		}

		return true;
	}

	public static function onTitleSquidURLsPurgeVariants($title, &$urls) {
		global $wgContLang, $wgLanguageCode;

		// Do we really need to purge the history of a video page? probably not
		// anons don't care about video histories
		if (!$title->inNamespace(NS_VIDEO)) {
			$historyUrl = $title->getInternalURL( 'action=history' );
			if ($wgLanguageCode == 'en') {
				$partialUrl = preg_replace("@^https?://[^/]+/@", "/", $historyUrl);
				$historyUrl = 'https://www.wikihow.com' . $partialUrl;
			}
			$urls[] = $historyUrl;

			// purge variant urls as well
			if ($wgContLang->hasVariants()) {
				$variants = $wgContLang->getVariants();
				foreach ($variants as $vCode) {
					if ($vCode == $wgContLang->getCode()) continue; // we don't want default variant
					$urls[] = $title->getInternalURL('', $vCode);
				}
			}
		}

		// Purge both http and https urls for now. Oct 2017.
		$newUrls = [];
		foreach ($urls as &$url) {
			if (preg_match('@^http://@', $url)) {
				$url = preg_replace('@^http:@', 'https:', $url);
			}
			if (preg_match('@^https://@', $url)) {
				$newUrl = preg_replace('@^https:@', 'http:', $url);
				$newUrls[] = $newUrl;
			}
		}
		foreach ($newUrls as $newUrl) {
			$urls[] = $newUrl;
		}

		return true;
	}

	// We simulate the useformat=mobile to display the mobile layout
	// when coming in on a mobile m. domain. We hook in as early as
	// possible because if MobileContext::shouldDisplayMobileView() is
	// called before this hook, its value is computed then cached
	// incorrectly.
	public static function onSetupAfterCacheSetMobile() {
		global $wgRequest, $wgNoMobileRedirectTest;

		// Uses raw headers rather than trying to instantiate a mobile
		// context object, which might not be possible
		if ( Misc::isMobileModeLite() ) {
			$wgRequest->setVal('useformat', 'mobile');
		}
		return true;
	}

	public static function onApiBeforeMain(&$main) {
		global $wgRequest;

		// Uses raw headers rather than trying to instantiate a mobile
		// context object, which might not be possible
		if ( Misc::isMobileModeLite() ) {
			$wgRequest->setVal('useformat', 'mobile');
		}
		return true;
	}

	// We hook into the UnknownAction callback so that we set the
	// resulting page as 404 when the action is not found.
	// WARNING: If we ever start supporting other actions by using
	// this hook, we should just test that this hook does not set
	// the page as 404 for those new actions.
	public static function onUnknownAction($action, $page) {
		$page->getContext()->getOutput()->setStatusCode('404');
		return true;
	}

	/**
	 * A callback check if the request is behind fastly, and if so, look for
	 * the XFF header.
	 */
	public static function checkFastlyProxy($ip, &$trusted) {
		if (!$trusted) {
			$value = isset($_SERVER[WH_FASTLY_HEADER_NAME]) ? $_SERVER[WH_FASTLY_HEADER_NAME] : '';
			$trusted = $value == WH_FASTLY_HEADER_VALUE;
		}
		return true;
	}

	/**
	 * Mediawiki 1.21 seems to redirect pages differently from 1.12, so we recreate
	 * the 1.12 functionality from "redirect" articles that are present in the DB.
	 *    - Reuben, 12/23/2013
	 */
	public static function onInitializeArticleMaybeRedirect($title, $request, $ignoreRedirect, &$target, $article) {
		if ( !$ignoreRedirect && !$target && $article->isRedirect() ) {
			$target = $article->followRedirect();
			if ($target instanceof Title) {
				if ( Misc::isMobileMode() && GoogleAmp::hasAmpParam( $request ) ) {
					$target = GoogleAmp::getAmpUrl( $target);
				} else {
					$target = $target->getFullURL();
				}
			}
		}
		return true;
	}

	public static function addVarnishHeaders($out, $skin) {
		$req = $out->getRequest();
		$title = $out->getTitle();
		if ($req && $title) {
			$layoutStr = Misc::isMobileMode() ? 'mb' : 'dt';
			$req->response()->header("X-Layout: $layoutStr");
			$out->addVaryHeader('X-Layout');

			$id = $title->getArticleID();
			$rollingResetStr = ($id > 0 ? ' roll' . ($id % 10) : '');
			$idResetStr = ($id > 0 ? ' id' . $id : '');
			$req->response()->header("Surrogate-Key: $layoutStr$rollingResetStr$idResetStr");
		}
		return true;
	}

	/**
	 * Runs on BeforePageDisplay hook, making creating a new internet.org
	 * cache vertical in front end caches (varnish).
	 *
	 * 1) Add the Vary header X-InternetOrg to every mobile domain response.
	 * 2) We add the X-InternetOrg: 1 HTTP response header to all
	 *    internet.org requests as well.
	 */
	public static function addInternetOrgVaryHeader($out, $skin) {
        if ( $out && Misc::isMobileMode() ) {
			$req = $out->getRequest();
			$out->addVaryHeader('X-InternetOrg');
			if ($req && WikihowMobileTools::isInternetOrgRequest() ) {
				$req->response()->header('X-InternetOrg: 1');
			}
		}
		return true;
	}

	// Check country ban
	public static function enforceCountryPageViewBan(&$outputPage, &$text) {
		$countryBanHeader = $outputPage->getRequest()->getHeader('X-Country-Ban');
		if ($countryBanHeader == 'YES'
			//|| $outputPage->getRequest()->getVal('test') == 'ban'
		) {
			$text = '
				<p><br/>
				Article cannot be viewed<br/>
				Please visit <a href="/Main-Page">wikiHow Main Page</a> instead.</p>';
		}
		return true;
	}

	/* data schema
	 *
	 CREATE TABLE redirect_page (
		rp_page_id int(8) unsigned NOT NULL,
		rp_folded varchar(255) NOT NULL,
		rp_redir varchar(255) NOT NULL,
		PRIMARY KEY(rp_page_id),
		INDEX(rp_folded)
	 );
	 */


	/**
	 * Callback to check for a case-folded redirect
	 */
	public static function check404Redirect($title) {
		$redirectTitle = Misc::getCaseRedirect( $title );

		if ( $redirectTitle ) {
			return $redirectTitle->getPartialURL();
		}
	}

	/**
	 * Callback to create, modify or delete a case-folded redirect
	 */
	private static function modify404Redirect($pageid, $newTitle) {
		static $dbw = null;
		if (!$dbw) $dbw = wfGetDB(DB_MASTER);
		$pageid = intval($pageid);

		if ($pageid <= 0) {
			return;
		} elseif (!$newTitle
				|| !$newTitle->exists()
				|| $newTitle->getNamespace() != NS_MAIN)
		{
			$dbw->delete('redirect_page', array('rp_page_id' => $pageid), __METHOD__);
		} else {
			// debug:
			//$field = $dbw->selectField('redirect_page', 'count(*)', array('rp_page_id'=>$pageid));
			//if ($field > 0) { print "$pageid $newTitle\n"; }
			$newrow = array(
				'rp_page_id' => intval($pageid),
				'rp_folded' => Misc::redirectGetFolded( $newTitle->getText() ),
				'rp_redir' => substr( $newTitle->getText(), 0, 255 ),
			);
			$dbw->replace('redirect_page', 'rp_page_id', $newrow, __METHOD__);
		}
	}

	public static function setPage404IfNotExists() {
		global $wgTitle, $wgOut, $wgLanguageCode;

		// Note: if namespace < 0, it's a virtual namespace like NS_SPECIAL
		// Check if image exists for foreign language images, because Title may not exist since image may only be on English
		if ($wgTitle
			&& $wgTitle->getNamespace() >= 0
			&& !$wgTitle->exists()
			&& ($wgLanguageCode == 'en'
				|| !$wgTitle->inNamespace(NS_IMAGE)
				|| !wfFindFile($wgTitle))
		) {
			$redirect = self::check404Redirect($wgTitle);
			if (!$redirect) {
				$wgOut->setStatusCode(404);
			} else {
				$wgOut->redirect('/' . $redirect, 301);
			}
		}
		return true;
	}

	//
	// Hooks for managing 404 redirect system
	//
	public static function fix404AfterMove($oldTitle, $newTitle) {
		if ($oldTitle && $newTitle) {
			self::modify404Redirect($oldTitle->getArticleID(), null);
			self::modify404Redirect($newTitle->getArticleID(), $newTitle);
		}
		return true;
	}

    /**
     * Hook for purging title based watermark thumbnails when a page moves
     */
    public static function onTitleMoveCompletePurgeThumbnails($oldTitle, $newTitle) {
        $newId = $newTitle->getArticleID();
        $dbw = wfGetDB( DB_MASTER );
        $table = "moved_title_images";
        $values = array( "mti_page_id" => $newId, 'mti_processed' => 0);
        $options = array( 'IGNORE' );

        $dbw->insert( $table, $values, __METHOD__, $options );

        return true;
    }

	public static function fix404AfterDelete($wikiPage) {
		if ($wikiPage) {
			$pageid = $wikiPage->getId();
			if ($pageid > 0) {
				self::modify404Redirect($pageid, null);
			}
		}
		return true;
	}

	public static function fix404AfterInsert($article) {
		if ($article) {
			$title = $article->getTitle();
			if ($title) {
				self::modify404Redirect($article->getID(), $title);
			}
		}
		return true;
	}

	public static function fix404AfterUndelete($title) {
		if ($title) {
			$pageid = $title->getArticleID();
			self::modify404Redirect($pageid, $title);
		}
		return true;
	}

	public static function onResourceLoaderStartupModuleQuery(&$query) {
		unset($query['version']);
		return true;
	}

	public static function onConfigStorageDbStoreConfig($key, $val) {
		if (class_exists('UserCompletedImages') && $key == UserCompletedImages::CONFIG_KEY) {
			$oldVal = ConfigStorage::dbGetConfig(UserCompletedImages::CONFIG_KEY);
			$oldVal = $oldVal ? explode("\n", $oldVal) : array();
			$val = $val ? explode("\n", $val) : array();
			UserCompletedImages::addToWhitelist(array_diff($val, $oldVal));
			UserCompletedImages::removeFromWhitelist(array_diff($oldVal, $val));
		}
		return true;
	}

	/**
	 * Decide whether on not to autopatrol an edit
	 */
	public static function onMaybeAutoPatrol($page, $user, &$patrolled) {
		global $wgLanguageCode, $wgRequest;

		// If this edit was already flagged autopatrol, only
		// keep this flag if the user has the autopatrol preference on
		if ( $patrolled && !$user->getOption('autopatrol') ) {
			$patrolled = false;
		}

		$userGroups = $user->getGroups();

		// All edits from users in the bot group are autopatrolled
		$noAutoPatrolBots = array('AnonLogBot');
		if ( in_array('bot', $userGroups)
			&& !in_array($user->getName(), $noAutoPatrolBots) )
		{
			$patrolled = true;
		}

		// Force auto-patrol for translators and international
		if ( $wgLanguageCode != "en" &&
			( in_array('sysop', $userGroups)
				|| in_array('staff', $userGroups)
				|| in_array('translator', $userGroups)
				|| in_array($user->getName(), array('AlfredoBot', 'InterwikiBot', wfMessage('translator_account')->plain()))
			))
		{
			$patrolled = true;
		}

		// All edits to User_kudos and User_kudos_talk namespace are autopatrolled
		if ( $page->mTitle->inNamespaces( NS_USER_KUDOS, NS_USER_KUDOS_TALK ) ) {
			$patrolled = true;
		}

		// All edits to User namespace (if editing their own page page) are autopatrolled
		if ( $page->mTitle->inNamespace(NS_USER) ) {
			$userName = $user->getName();
			$pageUser = $page->mTitle->getBaseText();
			if ($userName == $pageUser) {
				$patrolled = true;
			}
		}

		if ( $page->mTitle->inNamespace(NS_MAIN) ) {
			//all edits to overwritable articles are autopatrolled
			if ( $page->exists() && Newarticleboost::isOverwriteAllowed($page->getTitle()) && $wgRequest->getVal("overwrite") == "yes" ) {
				$patrolled = true;
			}

			//all new articles no longer go into RCP
			$oldestRevision =  $page->getOldestRevision();
			$newestRevision = $page->getRevision();
			if ( $oldestRevision != null && $newestRevision != null && $oldestRevision->getId() == $newestRevision->getId() ) {
				$patrolled = true;
			}
		}

		return true;
	}

	// Temporary, for redirect debugging
//	public static function onBeforePageRedirect($out, $redirect) {
//		global $wgUser, $wgDebugRedirects;
//		if ($wgUser && in_array($wgUser->getName(), array('Reuben', 'Anna'))) {
//			$url = htmlspecialchars( $redirect );
//			print "<html>\n<head>\n<title>Redirect</title>\n</head>\n<body>\n";
//			print "<p>You are Anna or Reuben so you see this on a redirect\n";
//			print "<p>Location: <a href=\"$url\">$url</a></p>\n";
//			print "<pre>current backtrace:\n" . wfBacktrace() . "</pre>\n";
//			print "<pre>redirect point:\n" . $out->mRedirectSource . "</pre>\n";
//			print "</body>\n</html>\n";
//			exit;
//		}
//		return true;
//	}

	public static function addFirebug(OutputPage &$out, Skin &$skin) {
		if (@$_GET['firebug'] == true) {
			$out->addHeadItem('firebug', '<script src="//getfirebug.com/releases/lite/1.2/firebug-lite-compressed.js"></script>');
		}
		return true;
	}

	public static function checkForDiscussionPage(&$out) {
		$title = $out->getTitle();

		//talk pages for anons redir to login
		if ($title && $title->isTalkPage() && $title->getNamespace() != NS_USER_TALK && $out->getUser()->isAnon()) {
			$login = 'index.php?title='.SpecialPage::getTitleFor('Userlogin').'&type=signup&returnto='.urlencode($title->getPrefixedURL());
			$out->redirect($login);
		}
		return true;
	}

	// check for any query parameters that we do not allow in the url like a username or user
	public static function maybeRedirectRemoveInvalidQueryParams(&$title, &$unused, &$output, &$user, $request, $mediaWiki) {
		global $wgLanguageCode, $wgIsSecureSite, $wgIsStageDomain;

		// for now allow any query params in the non main namespace titles
		if ( !$title
			|| !$title->inNamespace(NS_MAIN)
			|| $request->wasPosted()
		) {
			return true;
		}

		$redirect = false;
		$query = $request->getValues();
		$regex = '/.@./';
		$badvalues = [];
		foreach ( $query as $key => $value ) {
			if ( $key == "title" ) {
				continue;
			}
			if ( is_string($value) && preg_match( $regex, $value ) ) {
				$redirect = true;
				array_push($badvalues, $value);
				unset( $query[$key] );
			}
		}

		if ( $redirect == true ) {
			unset( $query['title'] );
			$url = wfAppendQuery( $title->getFullURL(), $query );
			$debugtext = "maybeRedirectRemoveInvalidQueryParams: redirect == true; values = " . join(", ", $badvalues);
			wfDebugLog('redirects', $debugtext);
			$output->redirect( $url, 301 );
		}
		return true;
	}

	// Redirect anons to HTTPS if they come in on HTTP
	public static function maybeRedirectHTTPS(&$title, &$unused, &$output, &$user, $request, $mediaWiki) {
		global $wgIsSecureSite;

		// HTTP -> HTTPS redirect
		// NOTE: never redirect if fromhttp=1 is in URL, since this
		//       param means request original went to HTTP
		// NOTE: don't redirect posted requests
		if ( !$wgIsSecureSite
			&& $user && $user->isAnon()
			&& !$request->getVal('fromhttp', 0)
			&& !$request->wasPosted()
		) {
			$redirUrl = wfExpandUrl( $request->getRequestURL(), PROTO_HTTPS );

			$debugtext = "maybeRedirectHTTPS HTTP -> HTTPS: wgIsSecureSite: $wgIsSecureSite, user.IsAnon: " . var_export($user->isAnon(), true) . " forceHTTPS: " . $request->getCookie('forceHTTPS', '') . " fromhttp: " . $request->getVal('fromhttp', 0) . " wasPosted: " . var_export($request->wasPosted(), true);
			wfDebugLog('redirects', $debugtext);
			$output->redirect( $redirUrl, 301 );
		}

		return true;
	}

	/**
	 * Mediawiki 1.21 doesn't natively redirect immediately if your http Host header
	 * isn't the same as $wgServer. We rely on this functionality so that domain names
	 * like wiki.ehow.com redirect to www.wikihow.com.
	 */
	private static function maybeRedirectToCanonical($output, $request, $httpHost) {
		global $wgServer, $wgIsAppServer, $wgIsDevServer, $wgIsToolsServer, $wgIsTitusServer;

		if (($wgIsAppServer || $wgIsDevServer)
			&& $wgServer != '//' . $httpHost
			&& !preg_match("@[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+@", $httpHost) // check that Host is not an IP
			&& !$wgIsToolsServer
			&& !$wgIsTitusServer
		) {
			$debugtext = "maybeRedirectToCanonical: wgIsAppServer: $wgIsAppServer, wgIsDevServer: $wgIsDevServer, wgServer != // . httpHost: " . var_export(($wgServer != '//' . $httpHost), true). " , hostIsIp " . var_export(preg_match("@[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+@", $httpHost), true) . " , wgIsToolsServer: $wgIsToolsServer, wgIsTitusServer: $wgIsTitusServer";
			wfDebugLog('redirects', $debugtext);
			$output->redirect( $wgServer . $request->getRequestURL(), 301 );
		}

		return true;
	}

	/**
	 * Redirect or 404 domains such as wikihow.es, testers.wikihow.com, ...
	 */
	public static function maybeRedirectProductionDomain(&$title, &$unused, &$output, &$user, $request, $mediaWiki) {
		global $wgServer, $wgCommandLineMode;

		$httpHost = (string)$request->getHeader('HOST');
		if (!$wgCommandLineMode) {
			if (preg_match('@^(apache[0-9]+|html5|pad[0-9]*|testers)\.wikihow\.com$@', $httpHost)) {
				header('HTTP/1.0 404 Not Found');
				die("Domain deactivated");
			}

			$preRedir = $wgServer;

			# We want to redirect the CCTLD domain wikihow.es to es.wikihow.com for
			# all languages where we own the domain. See bug #997 for reference.
			if (preg_match('@(^|\.)wikihow\.(de|fr|nl)$@', $httpHost, $m)) {
				$wgServer = 'https://' . $m[2] . '.wikihow.com';
			} elseif (preg_match('@(^|\.)wikihow\.(es|com\.mx)$@', $httpHost)) {
				$wgServer = 'https://es.wikihow.com';
			} elseif (preg_match('@(^|\.)wikihow\.in$@', $httpHost)) {
				$wgServer = 'https://hi.wikihow.com';
			} elseif (preg_match('@(^|\.)wikihow\.(id|co\.id)$@', $httpHost)) {
				$wgServer = 'https://id.wikihow.com';
			} elseif (preg_match('@ja\.(m\.)?wikihow\.com$@', $httpHost)) {
				// Note: Special case for Japanese, where we're redirecting the other way
				$wgServer = 'https://www.wikihow.jp';
			} elseif (preg_match('@^wikihow\.jp$@', $httpHost)) {
				$wgServer = 'https://www.wikihow.jp';
			} elseif (preg_match('@(^|\.)wikihow\.(pt|com\.br)$@', $httpHost)) {
				$wgServer = 'https://pt.wikihow.com';
			} elseif (preg_match('@(^|\.)wikihow\.in\.th$@', $httpHost)) {
				$wgServer = 'https://th.wikihow.com';
			} elseif (preg_match('@vi\.(m\.)?wikihow\.com$@', $httpHost)) {
				// Note: Special case for Vietnamese
				$wgServer = 'https://www.wikihow.vn';
			} elseif (preg_match('@^wikihow\.vn$@', $httpHost)) {
				$wgServer = 'https://www.wikihow.vn';
			} elseif (preg_match('@it\.(m\.)?wikihow\.com$@', $httpHost)) {
				// Note: Special case for Italian
				$wgServer = 'https://www.wikihow.it';
			} elseif (preg_match('@^wikihow\.it$@', $httpHost)) {
				$wgServer = 'https://www.wikihow.it';
			} elseif (preg_match('@cs\.(m\.)?wikihow\.com$@', $httpHost)) {
				// Note: Special case for Czech
				$wgServer = 'https://www.wikihow.cz';
			} elseif (preg_match('@^wikihow\.cz$@', $httpHost)) {
				$wgServer = 'https://www.wikihow.cz';
			} elseif (preg_match('@(^|\.)wikihow\.(cn|hk|tw)$@', $httpHost)) {
				$wgServer = 'https://zh.wikihow.com';
			}
			if ($preRedir != $wgServer) {
				$debugtext = "maybeRedirectProductionDomain: preRedir: $preRedir, wgServer: $wgServer";
				wfDebugLog('redirects', $debugtext);
				$output->redirect( $wgServer . $request->getRequestURL(), 301 );
			}
		}

		self::maybeRedirectToCanonical($output, $request, $httpHost);

		return true;
	}

	public static function maybeRedirectTitus(&$title, &$unused, &$output, &$user, $request, $mediaWiki) {
		global $wgIsSecureSite, $wgIsTitusServer;

		if ($wgIsTitusServer) {
			if (!$wgIsSecureSite) {
				// redirect to https url
				$redirUrl = wfExpandUrl( $request->getRequestURL(), PROTO_HTTPS );
				$output->redirect( $redirUrl );
			} else {
				$domainName = @$_SERVER['SERVER_NAME'];
				$isTitus = $domainName == 'titus.wikiknowhow.com';
				$isFlavius = $domainName == 'flavius.wikiknowhow.com';
				$isWVL = $domainName == 'wvl.wikiknowhow.com';

				if ($isTitus || $isFlavius || $isWVL) {
					$uri = @$_SERVER['REQUEST_URI'];
					if ($wgCommandLineMode
						|| strpos($uri, 'Special:TitusQuery') !== false
						|| strpos($uri, 'Special:FlaviusQueryTool') !== false
						|| strpos($uri, 'Special:MMKManager') !== false
						|| strpos($uri, 'Special:Domitian') !== false
						|| strpos($uri, 'Special:Turker') !== false
						|| strpos($uri, 'Special:EditTurk') !== false
						|| strpos($uri, 'Special:AqRater') !== false
						|| strpos($uri, 'Special:ACRater') !== false
						|| strpos($uri, 'Special:WikiVisualLibrary') !== false
						|| strpos($uri, 'Special:UserReviewImporter') !== false
						|| strpos($uri, 'Special:HistoricalPV') !== false
						|| strpos($uri, 'Special:Keywordtool') !== false

						|| stripos($uri, 'special:userlog') !== false
						|| stripos($uri, 'Special:GPlusLogin') !== false
						|| strpos($uri, 'Special:Login') !== false
						|| strpos($uri, 'Special:Captcha') !== false
						|| strpos($uri, 'Special:Resetpass') !== false
						|| strpos($uri, 'Special:ChangePassword') !== false
						|| strpos($uri, 'Special:ClassifyTitles') !== false
						|| strpos($uri, '/load.php') !== false
					) {
						# do something here? no.
					} else {
						if ($isTitus && (!$title || $title->inNamespace(NS_MAIN))) {
							$output->redirect( $wgServer . '/Special:TitusQueryTool' );
						} elseif ($isFlavius) {
							$output->redirect( $wgServer . '/Special:FlaviusQueryTool' );
						} elseif ($isWVL) {
							$output->redirect( $wgServer . '/Special:WikiVisualLibrary' );
						}
					}
				}
			}
		}

		return true;
	}

	/**
	 * @param $title
	 * @param $unused
	 * @param $output
	 * @param $user
	 * @param $request
	 * @param $mediaWiki
	 * @return bool
	 *
	 * Redirect to a specific special page for all request to prevent spiders from indexing the dev server
	 */
	public static function redirectIfNotBotRequest(&$title, &$unused, &$output, &$user, $request, $mediaWiki) {
		global $wgIsSecureSite, $isSecureDevServer, $wgCommandLineMode;

		if ($isSecureDevServer) {
			if (!$wgIsSecureSite) {
				// redirect to https url
				$redirUrl = wfExpandUrl( $request->getRequestURL(), PROTO_HTTPS );
				$output->redirect( $redirUrl );
			} else {
				$uri = @$_SERVER['REQUEST_URI'];
				if ($wgCommandLineMode
					|| strpos($uri, 'Special:MessengerSearchBot') !== false
					|| strpos($uri, 'Special:AlexaSkillReadArticleWebHook') !== false
					|| strpos($uri, 'Special:APIAIWikihowAgentWebHook') !== false
				) {
					# do something here? no.
				} else {
					$output->redirect( '/Special:AlexaSkillReadArticleWebHook');
				}
			}
		}

		return true;
	}

	public static function beforeArticlePurge( $wikiPage ) {
		if ( $wikiPage ) {
			RobotPolicy::clearArticleMemc( $wikiPage );
			RelatedWikihows::clearArticleMemc( $wikiPage );
		}
		return true;
	}

	// Turn on HTTPS for all logged in users on wikiHow
	public static function makeHTTPSforAllUsers($user, &$https) {
		global $wgIsDevServer, $wgServer;
		if (!$wgIsDevServer && $user && !$user->isAnon()) {
			// We haven't paid for the Fastly shared cert for domains like
			// es.m.wikihow.com, r.wikidogs.com, etc yet. So we exclude
			// these domains from HTTPS for now so that the user doesn't
			// see a terrible warning message.
			$count = substr_count($wgServer, '.');
			if ($count <= 2) {
				$https = true;
			} else {
				$https = false;
			}
		}
	}

	public static function onOutputPageBodyAttributes($out, $skin, &$bodyAttrs ) {
		if(class_exists("CustomContent")) {
			$customContentClass = CustomContent::getPageClass($out->getTitle());
			if($customContentClass != "") {
				$bodyAttrs['class'] .= ' ' . $customContentClass;
			}
		}

		return true;
	}
}

