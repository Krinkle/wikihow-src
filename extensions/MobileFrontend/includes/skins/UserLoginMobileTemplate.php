<?php
/**
 * Provides a custom login form for mobile devices
 */
class UserLoginMobileTemplate extends UserLoginAndCreateTemplate {
	protected $actionMessages = array(
		'watch' => 'mobile-frontend-watchlist-login-action',
		'edit' => 'mobile-frontend-edit-login-action',
		'' => 'mobile-frontend-generic-login-action',
	);
	protected $pageMessages = array(
		'Uploads' => 'mobile-frontend-donate-image-login-action',
		'Watchlist' => 'mobile-frontend-watchlist-login-action',
	);

	/**
	 * @TODO refactor this into parent template
	 */
	public function execute() {
		$action = $this->data['action'];
		$token = $this->data['token'];
		$watchArticle = $this->getArticleTitleToWatch();
		$stickHTTPS = ( $this->doStickHTTPS() ) ? Html::input( 'wpStickHTTPS', 'true', 'hidden' ) : '';
		$username = ( strlen( $this->data['name'] ) ) ? $this->data['name'] : null;

		// @TODO make sure this also includes returnto and returntoquery from the request
		$query = array(
			'type' => 'signup',
		);
		// Security: $action is already filtered by SpecialUserLogin
		$actionQuery = wfCgiToArray( $action );
		if ( isset( $actionQuery['returnto'] ) ) {
			$query['returnto'] = $actionQuery['returnto'];
		}
		if ( isset( $actionQuery['returntoquery'] ) ) {
			$query['returntoquery'] = $actionQuery['returntoquery'];
			// Allow us to distinguish sign ups from the left nav to logins. This allows us to apply story 1402 A/B test
			if ( $query['returntoquery'] === 'welcome=yes' ) {
				$query['returntoquery'] = 'campaign=leftNavSignup';
			}
		}
		// For Extension:Campaigns
		$campaign = $this->getSkin()->getRequest()->getText( 'campaign' );
		if ( $campaign ) {
			$query['campaign'] = $campaign;
		}

		$signupLink = Linker::link( SpecialPage::getTitleFor( 'Userlogin' ),
			wfMessage( 'mobile-frontend-main-menu-account-create' )->text(),
			array( 'class'=> 'mw-mf-create-account' ), $query );

		$login = Html::openElement( 'div', array( 'id' => 'mw-mf-login', 'class' => 'content' ) );

		$form = Html::openElement( 'div', array() ) .
			Html::openElement( 'form',
				array( 'name' => 'userlogin',
					'class' => 'user-login',
					'method' => 'post',
					'action' => $action ) ) .
			Html::openElement( 'div', array(
				'class' => 'inputs-box',
			) ) .
			Html::input( 'wpName', $username, 'text',
				array( 'class' => 'loginText',
					'placeholder' => wfMessage( 'mobile-frontend-username-placeholder' )->text(),
					'id' => 'wpName1',
					'tabindex' => '1',
					'size' => '20',
					'required' ) ) .
			Html::input( 'wpPassword', null, 'password',
				array( 'class' => 'loginPassword',
					'placeholder' => wfMessage( 'mobile-frontend-password-placeholder' )->text(),
					'id' => 'wpPassword1',
					'tabindex' => '2',
					'size' => '20' ) ) .
			Html::closeElement( 'div' ) .
			Html::input( 'wpRemember', '1', 'hidden' ) .
			Html::input( 'wpLoginAttempt', wfMessage( 'mobile-frontend-login' )->text(), 'submit',
				array( 'id' => 'wpLoginAttempt',
					'class' => 'mw-ui-button mw-ui-constructive',
					'tabindex' => '3' ) ) .
			$signupLink .
			Html::input( 'wpLoginToken', $token, 'hidden' ) .
			Html::input( 'watch', $watchArticle, 'hidden' ) .
			$stickHTTPS .
			Html::closeElement( 'form' ) .
			Html::closeElement( 'div' );
		echo $login;
		$this->renderGuiderMessage();
		$this->renderMessageHtml();
		echo $form;
		echo Html::closeElement( 'div' );
	}

}
