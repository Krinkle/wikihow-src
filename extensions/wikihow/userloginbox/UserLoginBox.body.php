<?php

class UserLoginBox extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('UserLoginBox');
	}

	/**
	 * getLogin()
	 * -----------------
	 * returns html of the login box used for things like desktop nav pulldown & desktop homepage
	 *
	 * - $isHead (t/f) = is the desktop nav
	 * - $isLogin (t/f)
	 *			true = assumes user is logging in w/ their account (alt link for sign up)
	 *			false = assumes user needs to create an account (alt link for log in w/ acct)
	 * - $returnto (string) = page to which to return the user after login/signup
	 */
	public function getLogin($isHead = false, $isLogin = true, $returnto = '') {
		global $wgSecureLogin;
		$ctx = RequestContext::getMain();

		$from_http = $wgSecureLogin && $ctx->getRequest()->getProtocol() == 'http' ? '&fromhttp=1' : '';

		$return_link = $returnto ?: $ctx->getTitle()->getPrefixedURL();

		$tmpl = new EasyTemplate( __DIR__ );
		$tmpl->set_vars(array(
			'suffix' => $isHead ? '_head' : '',
			'from_http' => $from_http,
			'return_to' => $return_link,
			'hdr_txt' => $isLogin ? wfMessage('ulb_login')->text() : wfMessage('ulb_joinwh')->text(),
			'btn_link_type' => $isLogin ? 'login' : 'signup',
			'bottom_link_type' => $isLogin ? 'signup' : 'login',
			'bottom_txt_1' => $isLogin ? wfMessage('ulb_nologin')->text() : wfMessage('ulb_haveacct')->text(),
			'bottom_txt_2' => $isLogin ? wfMessage('nologinlink')->text() : wfMessage('ulb_login')->text(),
			'wh_txt' => $isLogin ? wfMessage('ulb_whacct')->text() : wfMessage('ulb_email')->text(),
			'is_login' => $isLogin ? 'ulb_login' : 'ulb_signup'
		));
		$html = $tmpl->execute('userloginbox.tmpl.php');

		$ctx->getOutput()->addModules('ext.wikihow.userloginbox');
		return $html;
	}

	public function execute($par) {
		$this->getOutput()->setArticleBodyOnly(true);
	}
}
