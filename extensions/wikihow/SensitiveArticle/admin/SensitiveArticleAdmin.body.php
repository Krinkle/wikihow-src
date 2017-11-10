<?php

namespace SensitiveArticle;

/**
 * /Special:SensitiveArticleAdmin, used to edit the Sensitive Article reasons.
 */
class SensitiveArticleAdmin extends \UnlistedSpecialPage
{
	public function __construct()
	{
		parent::__construct('SensitiveArticleAdmin');
	}

	public function execute($par)
	{
		$req = $this->getRequest();
		$out = $this->getOutput();
		$user = $this->getUser();
		$groups = $user->getGroups();

		if ($user->isBlocked() || !in_array('staff', $groups)) {
			$out->setRobotpolicy('noindex,nofollow');
			$out->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		if ($req->wasPosted()) {
			$errors = $this->processAction($req);
			$out->disable();
			echo $errors ?: $this->getHTML($out);
		} else {
			$out->setPageTitle('Sensitive Article Admin');
			$out->addModules('ext.wikihow.SensitiveArticle.admin');
			$html = $this->getHTML($out);
			$out->addHTML("<div id='sensitive_article_admin'>$html</div>");
		}
	}

	/**
	 * Process an AJAX request triggered from the UI
	 */
	private function processAction(\WebRequest $req)
	{
		$action = $req->getText('action');

		if ($action != 'upsert') {
			return "Action not recognized: '$action'";
		}

		$id = $req->getInt('id');
		$name = $req->getText('name');
		$enabled = $req->getText('enabled') === 'true';

		if (!SensitiveReason::newFromValues($id, $name, $enabled)->save()) {
			$values = var_export($req->getValues(), true);
			return "<b>Error</b>. Unable to process request:<br><br><pre>$values</pre>";
		}
	}

	private function getHTML(\OutputPage $out): string
	{
		$mustacheEngine = new \Mustache_Engine([
			'loader' => new \Mustache_Loader_FilesystemLoader(__DIR__ . '/resources' )
		]);
		$vars = [ 'reasons' => SensitiveReason::getAll() ];
		return $mustacheEngine->render('sensitive_article_admin.mustache', $vars);
	}

}

