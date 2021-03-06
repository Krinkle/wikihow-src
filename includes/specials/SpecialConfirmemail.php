<?php
/**
 * Implements Special:Confirmemail and Special:Invalidateemail
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

/**
 * Special page allows users to request email confirmation message, and handles
 * processing of the confirmation code when the link in the email is followed
 *
 * @ingroup SpecialPage
 * @author Brion Vibber
 * @author Rob Church <robchur@gmail.com>
 */
class EmailConfirmation extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'Confirmemail', 'editmyprivateinfo' );
	}

	/**
	 * Main execution point
	 *
	 * @param null|string $code Confirmation code passed to the page
	 */
	function execute( $code ) {
		$this->setHeaders();

		$this->checkReadOnly();
		$this->checkPermissions();

		// wikiHow changed (12/2018): adding referrer origin meta tag so that we
		// don't send the ConfirmEmail code to external sites by accident in the
		// Referer http header. I don't consider this a real security issue since
		// this URL is effectively burned after it's viewed, but the security
		// researcher might see something I don't. What ya gonna do?
		$out = $this->getOutput();
		$out->addMeta('referrer', 'origin');

		$this->requireLogin( 'confirmemail_needlogin' );

		// This could also let someone check the current email address, so
		// require both permissions.
		if ( !$this->getUser()->isAllowed( 'viewmyprivateinfo' ) ) {
			throw new PermissionsError( 'viewmyprivateinfo' );
		}

		if ( $code === null || $code === '' ) {
			if ( Sanitizer::validateEmail( $this->getUser()->getEmail() ) ) {
				$this->showRequestForm();
			} else {
				$this->getOutput()->addWikiMsg( 'confirmemail_noemail' );
			}
		} else {
			$this->attemptConfirm( $code );
		}
	}

	/**
	 * Show a nice form for the user to request a confirmation mail
	 */
	function showRequestForm() {
		$user = $this->getUser();
		$out = $this->getOutput();

		if ( $this->getRequest()->wasPosted() &&
			$user->matchEditToken( $this->getRequest()->getText( 'token' ) )
		) {
			$status = $user->sendConfirmationMail();
			if ( $status->isGood() ) {
				$out->addWikiMsg( 'confirmemail_sent' );
			} else {
				$out->addWikiText( $status->getWikiText( 'confirmemail_sendfailed' ) );
			}
		} elseif ( $user->isEmailConfirmed() ) {
			// date and time are separate parameters to facilitate localisation.
			// $time is kept for backward compat reasons.
			// 'emailauthenticated' is also used in SpecialPreferences.php
			$lang = $this->getLanguage();
			$emailAuthenticated = $user->getEmailAuthenticationTimestamp();
			$time = $lang->userTimeAndDate( $emailAuthenticated, $user );
			$d = $lang->userDate( $emailAuthenticated, $user );
			$t = $lang->userTime( $emailAuthenticated, $user );
			$out->addWikiMsg( 'emailauthenticated', $time, $d, $t );
		} else {
			if ( $user->isEmailConfirmationPending() ) {
				$out->wrapWikiMsg(
					"<div class=\"error mw-confirmemail-pending\">\n$1\n</div>",
					'confirmemail_pending'
				);
			}

			$out->addWikiMsg( 'confirmemail_text' );
			$form = Html::openElement(
				'form',
				array( 'method' => 'post', 'action' => $this->getPageTitle()->getLocalURL() )
			) . "\n";
			$form .= Html::hidden( 'token', $user->getEditToken() ) . "\n";
			$form .= Xml::submitButton( $this->msg( 'confirmemail_send' )->text() ) . "\n";
			$form .= Html::closeElement( 'form' ) . "\n";
			$out->addHTML( $form );
		}
	}

	/**
	 * Attempt to confirm the user's email address and show success or failure
	 * as needed; if successful, take the user to log in
	 *
	 * @param string $code Confirmation code
	 */
	function attemptConfirm( $code ) {
		$user = User::newFromConfirmationCode( $code );
		if ( !is_object( $user ) ) {
			$this->getOutput()->addWikiMsg( 'confirmemail_invalid' );

			return;
		}

		$user->confirmEmail();
		$user->saveSettings();
		$message = $this->getUser()->isLoggedIn() ? 'confirmemail_loggedin' : 'confirmemail_success';
		$this->getOutput()->addWikiMsg( $message );

		if ( !$this->getUser()->isLoggedIn() ) {
			$title = SpecialPage::getTitleFor( 'Userlogin' );
			$this->getOutput()->returnToMain( true, $title );
		}
	}
}

/**
 * Special page allows users to cancel an email confirmation using the e-mail
 * confirmation code
 *
 * @ingroup SpecialPage
 */
class EmailInvalidation extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'Invalidateemail', 'editmyprivateinfo' );
	}

	function execute( $code ) {
		$this->setHeaders();
		$this->checkReadOnly();
		$this->checkPermissions();
		$this->attemptInvalidate( $code );
	}

	/**
	 * Attempt to invalidate the user's email address and show success or failure
	 * as needed; if successful, link to main page
	 *
	 * @param string $code Confirmation code
	 */
	function attemptInvalidate( $code ) {
		$user = User::newFromConfirmationCode( $code );
		if ( !is_object( $user ) ) {
			$this->getOutput()->addWikiMsg( 'confirmemail_invalid' );

			return;
		}

		$user->invalidateEmail();
		$user->saveSettings();
		$this->getOutput()->addWikiMsg( 'confirmemail_invalidated' );

		if ( !$this->getUser()->isLoggedIn() ) {
			$this->getOutput()->returnToMain();
		}
	}
}
