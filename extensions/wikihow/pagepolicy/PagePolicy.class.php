<?php

if (!defined('MEDIAWIKI')) die();

class PagePolicy {

	const VALIDATION_TOKEN_NAME = 'validate';
	const EXCEPTIONS = ['Sandbox'];

	public static function showCurrentTitle($context) {
		static $showCurrentTitle = -1; // compute this lazily, only once
		if ($showCurrentTitle === -1) {
			$title = $context->getTitle();
			$user = $context->getUser();
			if ( $user->isAnon()
				&& $title->exists()
				&& $title->inNamespace(NS_MAIN)
				&& !in_array($title->getDBkey(), static::EXCEPTIONS)
			) {
				$req = $context->getRequest();
				$isNew = $req->getVal('new');
				$token = $req->getVal( self::VALIDATION_TOKEN_NAME );
				if ( $isNew && wfHasCurrentArticleCreationCookie() ) {
					$showCurrentTitle = true;
				} elseif ($token) {
					$pageid = $title->getArticleId();
					$showCurrentTitle = self::validateToken($token, $pageid);
				} else {
					$showCurrentTitle = RobotPolicy::isTitleIndexable($title, $context);
				}
			} else {
				$showCurrentTitle = true;
			}
		}
		return $showCurrentTitle;
	}

	// We want to 404 deindexed pages for anon users and make them log in to see it.
	// This hook runs within Article::view after the article object is fetch but
	// the usual 404 page is displayed.
	public static function onArticleViewHeader(&$article, &$outputDone, &$useParserCache) {
		$ctx = $article->getContext();
		if ( !self::showCurrentTitle($ctx) ) {
			$outputDone = true;
			$useParserCache = false;
			self::displayArticleAsMissing($article, $ctx);
		}

		return true;
	}

	public static function displayArticleAsMissing($article, $ctx) {
		$ctx->getRequest()->response()->header( 'HTTP/1.1 404 Not Found' );
		$ctx->getOutput()->addModules('ext.wikihow.login_popin');

		$vars = [
			'encoded_title' => $ctx->getTitle()->getPartialURL(),
			'view_message' => wfMessage('pplp_login_cta')
		];
		$loader = new Mustache_Loader_CascadingLoader([
			new Mustache_Loader_FilesystemLoader(__DIR__),
		]);
		$options = array('loader' => $loader);
		$m = new Mustache_Engine($options);

		if ( Misc::isMobileMode() ) {
			$html = $m->render('login_mobile.mustache', $vars);
		} else {
			$html = $m->render('login_desktop.mustache', $vars);
		}
		$ctx->getOutput()->addHTML($html);

		$article->mOldId = 0; // necessary because GoodRevision sometimes sets an oldid for anons
	}

	public static function getLoginModal($returnto) {
		$vars = [
			'login_chunk' => UserLoginBox::getLogin(false, true, $returnto),
			'header' => wfMessage('pplp_header')->text()
		];

		$loader = new Mustache_Loader_CascadingLoader([
			new Mustache_Loader_FilesystemLoader(dirname(__FILE__)),
		]);
		$options = array('loader' => $loader);
		$m = new Mustache_Engine($options);
		$html = $m->render('login_popin', $vars);

		return $html;
	}

	private static function validateToken($token, $pageid) {
		list($time, $digest_in) = explode('-', $token);
		$time = (int)$time;
		if (!$digest_in) return false; // check input
		if ($time < time()) return false; // token has expired
		$digest_gen = self::generateToken($time, $pageid);
		return $digest_gen === $digest_in;
	}

	private static function generateToken($time, $pageid) {
		$time = (int)$time;
		$pageid = (int)$pageid;
		if (!$time || !$pageid) return '';
		$data = "$time,$pageid";
		$digest = hash_hmac("sha256", $data, WH_VALIDATRON_HMAC_KEY);
		// keep only first 16 characters, because we don't need THAT much security :^)
		return substr($digest, 0, 16);
	}

	/**
	 * Pass in a URL to append validation tokens to.
	 * @param string $url URL to which to append
	 * @param int $pageid page ID of the page to validate. This must stay constant for token to validate.
	 * @param int $duration how long the validation token should last, in seconds. default is 1 week.
	 */
	public static function generateTokenURL($url, $pageid, $duration = 7 * 24 * 60 * 60) {
		$time = time() + $duration;
		$token = self::generateToken($time, $pageid);
		if ( strpos($url, '?') !== false && !preg_match('@[?&]$@', $url) ) {
			$url .= '&';
		} else {
			$url .= '?';
		}
		$url .= self::VALIDATION_TOKEN_NAME . "=$time-$token";
		return $url;
	}
}
