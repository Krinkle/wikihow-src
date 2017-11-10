<?php

if ( !defined('MEDIAWIKI') ) die();

class RequestTopic extends SpecialPage {

	public function __construct() {
		parent::__construct( 'RequestTopic' );
	}

	public function execute($par) {
		global $wgRequest, $wgUser, $wgOut;

		$pass_captcha = true;
		if ($wgRequest->wasPosted()) {
			$fc = new FancyCaptcha();
			$pass_captcha   = $fc->passCaptcha();
		}

		$wgOut->setPageTitle(wfMsg('suggest_header'));
		if ($wgRequest->wasPosted() && $pass_captcha) {
			$dbr = wfGetDB(DB_SLAVE);

			$title = GuidedEditorHelper::formatTitle($wgRequest->getVal('suggest_topic'));
			$s = Title::newFromText($title);
			if (!$s) {
				$wgOut->addHTML("There was an error creating this title.");
				return;
			}
			// does the request exist as an article?
			if ($s->getArticleID()) {
				$wgOut->addHTML(wfMsg('suggested_article_exists_title'));
				$wgOut->addHTML(wfMsg('suggested_article_exists_info', $s->getText(), $s->getFullURL()));
				return;
			}
			// does the request exist in the list of suggested titles?
			$email = $wgRequest->getVal('suggest_email');
			if (!$wgRequest->getCheck('suggest_email_me_check'))
				$email = '';

			$count = $dbr->selectField('suggested_titles', array('count(*)'), array('st_title' => $s->getDBKey()), __METHOD__);
			$dbw = wfGetDB(DB_MASTER);
			if ($count == 0) {
			    $dbw->insert('suggested_titles',
					array('st_title'	=> $s->getDBKey(),
						'st_user'		=> $wgUser->getID(),
						'st_user_text'	=> $wgUser->getName(),
						'st_isrequest'	=> 1,
						'st_category'	=> $wgRequest->getVal('suggest_category'),
						'st_suggested'	=> wfTimestampNow(),
						'st_notify'		=> $email,
						'st_source'		=> 'req',
						'st_key'		=> TitleSearch::generateSearchKey($title),
						'st_group'		=> rand(0, 4)
					),
					__METHOD__);
			} elseif ($email) {
				// request exists lets add the user's email to the list of notifications
				$existing = $dbr->selectField('suggested_titles', array('st_notify'), array('st_title' => $s->getDBKey()), __METHOD__);
				if ($existing)
					$email = "$existing, $email";
				$dbw->update('suggested_titles',
					array('st_notify' => $email),
					array('st_title' => $s->getDBKey()));
			}
			$wgOut->addModules( ['ext.wikihow.SuggestedTopics'] );
			$wgOut->addHTML(wfMsg("suggest_confirmation_owl", $s->getFullURL(), $s->getText()));
			return;
		}

		$wgOut->setHTMLTitle('Requested Topics - wikiHow');
		$wgOut->setRobotPolicy('noindex,nofollow');

		$wgOut->addModules( ['ext.wikihow.SuggestedTopics'] );
		$wgOut->addHTML(wfMsg('suggest_sub_header'));

		$wgOut->addHTML("<form action='/Special:RequestTopic' method='POST' onsubmit='return WH.SuggestedTopics.checkSTForm();' name='suggest_topic_form'>");
		$wgOut->addScript("<script type='text/javascript'/>var gSelectCat = '" . wfMsg('suggest_please_select_cat') . "';
		var gEnterTitle = '" . wfMsg('suggest_please_enter_title') . "';
		var gEnterEmail  = '" . wfMsg('suggest_please_enter_email') . "';
		</script>");

		$fc = new FancyCaptcha();
		$cats = self::getCategoryOptions();
		$wgOut->addHTML(wfMsg('suggest_input_form', $cats, $fc->getForm(),  $pass_captcha ? "" : wfMsg('suggest_captcha_failed'), $wgUser->getEmail()));
		$wgOut->addHTML("</form>");
	}

	public static function getCategoryOptions($default = "") {
		// only do this for logged in users
		$t = Title::newFromDBKey("WikiHow:" . wfMsg('requestcategories') );
		$r = Revision::newFromTitle($t);
		if (!$r)
			return '';
		$cat_array = explode("\n", $r->getText());
		$s = "";
		foreach ($cat_array as $line) {
			$line = trim($line);
			if ($line == "" || strpos($line, "[[") === 0) continue;
			$tokens = explode(":", $line);
			$val = "";
			$val = trim($tokens[sizeof($tokens) - 1]);
			$s .= "<OPTION class='input_med' VALUE=\"" . $val . "\">" . $line . "</OPTION>\n";
		}
		$s = str_replace("\"$default\"", "\"$default\" SELECTED", $s);

		return $s;
	}

}
