<?php

require_once __DIR__ . "/sso/jsconnect.php";

class Vanilla extends UnlistedSpecialPage {

    function __construct() {
        parent::__construct( 'Vanilla' );
    }

    static function sync($user) {
    	global $wgVanillaDB;
    	 $user->load();
    	 $db = DatabaseBase::factory('mysql');
    	 $db->open($wgVanillaDB['host'], $wgVanillaDB['user'], $wgVanillaDB['password'], $wgVanillaDB['dbname']);

    	 $vanillaID = $db->selectField('GDN_UserAuthentication', array('UserID'), array('ForeignUserKey'=> $user->getID()));

    	 if (!$vanillaID) return false;

    	 $updates = array( 'Banned' => (int)$user->isBlocked(), 'Verified' => (int)$user->isEmailConfirmed() );
    	 $opts = array( 'UserID' => $vanillaID );
    	 $db->update( 'GDN_User', $updates, $opts, __METHOD__ );
    }

    function getRole() {
    	$groups = $this->getUser()->getGroups();

    	// Always go in order from most permissions to least permissions

    	if ( in_array( 'staff', $groups ) || $this->getUser()->getName() == 'Lojjik' ) {
    		return 'administrator';
    	}

    	if ( in_array( 'sysop', $groups ) ) {
    		return 'moderator';
    	}

    	return 'member';

    }

	function execute($par) {
		if ( $this->getRequest()->getVal('action') === 'sso' ) {
			self::sync($this->getUser());
			$this->getOutput()->disable();
			header('Content-Type: application/javascript');

			$user = array();

			if ( !$this->getUser()->isAnon() && !$this->getUser()->isBlocked() ) {

				$user['uniqueid'] = $this->getUser()->getId();
				$user['name'] = $this->getUser()->getName();

				$email = $this->getUser()->getEmail();

				if ( !$email || !$this->getUser()->isEmailConfirmed() ) {
					$email = $this->getUser()->getId() . '@forums.wikihow.com';
				}

				$user['email'] = $email;
				// We link to the full static url here because wfGetPad() can return relative urls
				// in certain contexts
				$avatarUrl = Avatar::getAvatarURL( $this->getUser()->getName() );
				if (!preg_match( '@^https?:@', $avatarUrl) ) {
				  $user['photourl'] = 'http://pad1.whstatic.com' . $avatarUrl;
				} else {
				  $user['photourl'] = $avatarUrl;
				}

				$user['roles'] = $this->getRole();
			}

			$secure = true;

			echo WriteJsConnect($user, $this->getRequest()->getValues(), WH_VANILLA_CLIENT_ID, WH_VANILLA_SECRET, $secure);
		}
	}
}

