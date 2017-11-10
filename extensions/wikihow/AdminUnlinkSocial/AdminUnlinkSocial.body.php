<?php

use SocialAuth\FacebookSocialUser;
use SocialAuth\GoogleSocialUser;
use SocialAuth\SocialUser;

class AdminUnlinkSocial extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'AdminUnlinkSocial' );
	}

	function execute($par) {
		global $wgOut, $wgUser, $wgRequest;

		// Restrict page access to staff members
		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		// Set the page title and load the JS and CSS files
		$wgOut->setPageTitle(wfMessage('unlinksocial-page-title')->text());
		$wgOut->addModules('ext.wikihow.adminunlinksocial.scripts');
		$wgOut->addModuleStyles('ext.wikihow.adminunlinksocial.styles');

		// Map the request to the appropriate action
		if (!$wgRequest->wasPosted()) {
			$this->renderPage();
		} elseif ($wgRequest->getText('action') == 'getUser') {
			$this->apiGetUser();
		} elseif ($wgRequest->getText('action') == 'unlinkGoogle') {
			$this->apiUnlink('Google');
		} elseif ($wgRequest->getText('action') == 'unlinkFacebook') {
			$this->apiUnlink('Facebook');
		} else {
			$this->apiError('Action not supported');
		}
	}

	private function renderPage() {
		global $wgOut;
		$tmpl = new EasyTemplate(dirname(__FILE__));
		$wgOut->addHTML($tmpl->execute('AdminUnlinkSocial.tmpl.php'));
	}

	private function apiGetUser() {
		$wikiHowName = $this->getRequest()->getText('username');
		if (empty($wikiHowName)) {
			$this->apiError("Missing 'username' parameter");
			return;
		}
		$wikiHowId = User::newFromName($wikiHowName)->getId();
		if (empty($wikiHowId)) {
			$googleId = $facebookId = false;
		} else {
			$su = GoogleSocialUser::newFromWhId($wikiHowId);
			$googleId = $su ? $su->getExternalId() : false;

			$su = FacebookSocialUser::newFromWhId($wikiHowId);
			$facebookId = $su ? $su->getExternalId() : false;
		}

		Misc::jsonResponse(compact('wikiHowName', 'wikiHowId', 'googleId', 'facebookId'));
	}

	private function apiUnlink($type) {
		$wikiHowId = $this->getRequest()->getText('wikiHowId');
		if (empty($wikiHowId)) {
			$this->apiError("Missing 'wikiHowId' parameter");
			return;
		}

		$su = SocialUser::newFactory($type)::newFromWhId($wikiHowId);

		if (!$su) {
			$this->apiError("No $type account linked to WikiHow ID $wikiHowId");
			return;
		}

		if ($su->unlink()) {
			Misc::jsonResponse(['success' => "$type has been unlinked from the account."]);
		} else {
			$this->apiError("Unable to unlink account (WikiHow ID = $wikiHowId, $type ID = {$su->getExternalId()})");
		}
	}

	private function apiError($msg = 'The API call resulted in an error.') {
		Misc::jsonResponse(['error' => $msg], 400);
	}

}
