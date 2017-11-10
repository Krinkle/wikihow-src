<?php


use Alexa\Response\Response;
use Doctrine\Common\Annotations\AnnotationRegistry;

class AlexaSkillReadArticleWebHook extends UnlistedSpecialPage {
	const LOG_GROUP = 'AlexaSkillReadArticleWebHook';
	const USAGE_LOGS_EVENT_TYPE = 'alexa_skill_read_article';

	const ATTR_ARTICLE = 'article_data';

	/**
	 * @var ReadArticleBot
	 */
	var $bot = null;

	/**
	 * @var Response
	 */
	var $alexaRequest = null;

	const INTENT_FALLBACK = 'FallbackIntent';
	const INTENT_START = 'wh_start';
	const INTENT_END = 'wh_end';
	const INTENT_HOWTO = 'QueryIntent';
	const INTENT_GOTO_STEP = 'GoToStepIntent';
	const INTENT_START_OVER = 'AMAZON.StartOverIntent';
	const INTENT_FIRST_STEP = 'FirstStep';
	const INTENT_LAST_STEP = 'LastStep';
	const INTENT_STOP = 'AMAZON.StopIntent';
	const INTENT_PAUSE = 'AMAZON.PauseIntent';
	const INTENT_REPEAT = 'AMAZON.RepeatIntent';
	const INTENT_PREVIOUS = 'AMAZON.PreviousIntent';
	const INTENT_RESUME = 'AMAZON.ResumeIntent';
	const INTENT_NEXT = 'AMAZON.NextIntent';
	const INTENT_NO = 'AMAZON.NoIntent';
	const INTENT_YES = 'AMAZON.YesIntent';
	const INTENT_NEXT_STEP = 'NextStep';
	const INTENT_STEP_DETAILS = 'StepDetails';
	const INTENT_CANCEL = 'AMAZON.CancelIntent';
	const INTENT_HELP = 'AMAZON.HelpIntent';

	function __construct() {
		parent::__construct('AlexaSkillReadArticleWebHook');
	}

	public static function fatalHandler() {
		wfDebugLog(self::LOG_GROUP, var_export('Last error on line following', true), true);
		$error = error_get_last();
		if( $error !== NULL) {
			$errno   = $error["type"];
			$errfile = $error["file"];
			$errline = $error["line"];
			$errstr  = $error["message"];

			self::errorHandler($errno, $errstr, $errfile, $errline);
		}
	}

	public static function errorHandler($errno, $errstr, $errfile, $errline) {
		/* Don't execute PHP internal error handler */
		$str = "PHP Error #$errno: '$errstr' in file $errfile on line $errline";
		wfDebugLog(self::LOG_GROUP, var_export($str, true), true);

		return true;
	}

	function execute($par) {
		//Define an error handler if you need to debug errors
		//error_reporting(E_CORE_ERROR|E_COMPILE_ERROR);
		//register_shutdown_function("AlexaSkillReadArticleWebHook::fatalHandler");
		$old_error_handler = set_error_handler("AlexaSkillReadArticleWebHook::errorHandler");


		$this->getOutput()->setRobotPolicy('noindex,nofollow');
		$this->getOutput()->setArticleBodyOnly(true);

		// Needed for search to work properly
		$_SERVER['HTTP_USER_AGENT'] = "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36";

		try {
			$applicationId = WH_ALEXA_SKILL_READ_ARTICLE_APP_ID; // See developer.amazon.com and your Application. Will start with "amzn1.echo-sdk-ams.app."
			$rawRequest = file_get_contents("php://input"); // This is how you would retrieve this with Laravel or Symfony 2.
			$alexaRequestFactory = new \Alexa\Request\RequestFactory();
			$this->initAnnotationsLoader();
			$this->alexaRequest = $alexaRequestFactory->fromRawData($rawRequest, [$applicationId]);

			$this->processRequest();
		}
		catch(Error $e) {
			wfDebugLog(self::LOG_GROUP, var_export(MWExceptionHandler::getLogMessage(new MWException($e)), true), true);
			exit(1);
		}
		catch(Exception $e) {
			wfDebugLog(self::LOG_GROUP, var_export(MWExceptionHandler::getLogMessage(new MWException($e)), true), true);

			if ($e instanceof InvalidArgumentException) {
				http_response_code(400);
			}
			exit(1);
		}
	}

	/**
	 * @param $data
	 */
	public function processRequest() {
		wfDebugLog(self::LOG_GROUP, var_export("Incoming request", true), true);
		wfDebugLog(self::LOG_GROUP, var_export($this->alexaRequest->getData(), true), true);

		$this->initBot();
		$this->processCommand($this->getIntent());
	}

	protected function getIntent() {
		$request = $this->alexaRequest;
		$intent = self::INTENT_FALLBACK;

		if ($request instanceof \Alexa\Request\LaunchRequest) {
			$intent = self::INTENT_START;
		}

		if ($request instanceof \Alexa\Request\IntentRequest) {
			$intent = $request->getIntentName();
		}

		if ($request instanceof \Alexa\Request\SessionEndedRequest) {
			$intent = self::INTENT_END;
		}

		return $intent;
	}


	public function processCommand($intentName) {
		wfDebugLog(self::LOG_GROUP, var_export(__METHOD__ . " - intent name: " .
			$intentName, true), true);
		$bot = $this->getBot();
		switch($intentName) {
			case self::INTENT_NEXT_STEP:
			case self::INTENT_NEXT:
				$responseText = $bot->onIntentNext($bot);
				$response = $this->getTextResponseWithSession($responseText);
				break;
			case self::INTENT_LAST_STEP:
			case self::INTENT_PREVIOUS:
				$responseText = $bot->onIntentPrevious();
				$response = $this->getTextResponseWithSession($responseText);
				break;
			case self::INTENT_PAUSE:
				$responseText = $bot->onIntentPause();
				$response = $this->getTextResponseWithSession($responseText);
				break;
			case self::INTENT_REPEAT:
			case self::INTENT_RESUME:
				$responseText = $bot->onIntentRepeat();
				$response = $this->getTextResponseWithSession($responseText);
				break;
			case self::INTENT_FIRST_STEP:
			case self::INTENT_START_OVER:
				$responseText = $bot->onIntentStartOver();
				$response = $this->getTextResponseWithSession($responseText);
				break;
			case self::INTENT_HOWTO:
				$query = strtolower($this->alexaRequest->getSlot('query'));
				wfDebugLog(self::LOG_GROUP, var_export("User query: $query", true), true);
				$responseText = $bot->onIntentHowTo($query);
				$response = $this->getTextResponseWithSession($responseText);
				$response = $this->addCardToResponse($response);
				break;
			case self::INTENT_GOTO_STEP:
				$stepNum = intVal($this->alexaRequest->getSlot('step_num'));
				wfDebugLog(self::LOG_GROUP, var_export("step number: " . $stepNum, true), true);
				$responseText = $bot->onIntentGoToStep($stepNum);
				$response = $this->getTextResponseWithSession($responseText);
				break;
			case self::INTENT_NO:
				$responseText = $bot->onIntentNo();
				$response = $this->getTextResponseWithSession($responseText);
				if ($bot->getArticleData() && $bot->getArticleData()->isLastStepInMethod()) {
					$response->endSession();
				}
				break;
			case self::INTENT_YES:
				$responseText = $bot->onIntentNo();
				$response = $this->getTextResponseWithSession($responseText);
				break;
			case self::INTENT_STOP:
			case self::INTENT_CANCEL:
				$responseText = $bot->onIntentEnd();
				$response = $this->getTextResponseWithSession($responseText);
				$response->endSession();
				break;
			case self::INTENT_HELP:
				$responseText = $bot->onIntentHelp();
				$response = $this->getTextResponseWithSession($responseText);
				break;
			case self::INTENT_START:
				$responseText = $bot->onIntentStart();
				$response = $this->getTextResponseWithSession($responseText);
				break;
			case self::INTENT_END:
				$this->onSessionEndedRequest();
			case self::INTENT_FALLBACK:
			default:
				$responseText = $bot->onIntentFallback();
				$response = $this->getTextResponseWithSession($responseText);
		}

		$response = $this->setReprompt($response);
		$this->sendResponse($response);
	}

	protected function sendResponse($response) {
		echo json_encode($response->render());
	}

	protected function getTextResponseNoSession($text) {
		$response = new \Alexa\Response\Response;
		$response->respond($text);
		return $response;
	}

	/**
	 * @param Response $response
	 * @return mixed
	 */
	protected function setSessionAttributes($response) {
		foreach ($this->alexaRequest->getSession()->getAttributes() as $key => $val) {
			$response->addSessionAttribute($key, $val);
		}

		$bot = $this->getBot();
		if (!is_null($bot)) {
			$response->addSessionAttribute(self::ATTR_ARTICLE, $bot->getState());
		}

		return $response;
	}

	/**
	 * @param $responseText
	 * @return Response|mixed
	 */
	protected function getTextResponseWithSession($responseText) {
		$response = $this->getTextResponseNoSession($responseText);
		$response = $this->setSessionAttributes($response);
		return $response;
	}


	protected function onSessionEndedRequest() {
		$response = $this->getTextResponseNoSession("");
		$response->endSession();
		$this->sendResponse($response);
	}

	protected function initBot() {
		$articleData = $this->alexaRequest->getSession()->getAttributes()[self::ATTR_ARTICLE];
		wfDebugLog(self::LOG_GROUP, var_export("Article data: ", true), true);
		wfDebugLog(self::LOG_GROUP, var_export($articleData, true), true);

		$this->bot = ReadArticleBot::newFromArticleState($articleData, self::USAGE_LOGS_EVENT_TYPE);
	}

	/**
	 * @return ReadArticleBot
	 */
	protected function getBot() {
		return $this->bot;
	}

	protected function addCardToResponse($response) {
		$bot = $this->bot;
		$a = $bot->getArticleData();
		if ($a && $a->isFirstStepInMethod()) {
			$response->withCard($a->getArticleTitle(), $a->getArticleUrl());
		}

		return $response;
	}

	/**
	 * Certain intents relating to article navigation should have a custom reprompt
	 * @param $response
	 * @return mixed
	 */
	protected function setReprompt($response) {
		// Custom reprompt only when reading an article
		if ($this->getBot()->getArticleData() === NULL) return $response;

		$intentName = $this->getIntent();
		switch ($intentName) {
			case self::INTENT_NEXT:
			case self::INTENT_PREVIOUS:
			case self::INTENT_REPEAT:
			case self::INTENT_RESUME:
			case self::INTENT_FIRST_STEP:
			case self::INTENT_HOWTO:
			case self::INTENT_GOTO_STEP:
				wfDebugLog(self::LOG_GROUP, var_export(__METHOD__ . ": setting custom reprompt", true), true);
				$isLastStep = $this->getBot()->getArticleData()->isLastStepInMethod();
				if ($isLastStep) {
					$prompt = wfMessage('reading_article_no_response_prompt')->text();
				} else {
					$prompt =  wfMessage('reading_article_instructions')->text();
				}
				$response->reprompt($prompt);
				break;
		}
		return $response;
	}

	/**
	 * This loads annotations needed for validating the request in the RequestFactory.  Tried using the
	 * AnnotationRegistry::registerAutoloadNamespace with no luck so creating our own annotation loader
	 */
	protected function initAnnotationsLoader() {
		AnnotationRegistry::registerLoader(function ($class) {
			$namespace = "Symfony\Component\Validator\Constraints";
			if (strpos($class, $namespace) === 0) {
				$file = str_replace("\\", DIRECTORY_SEPARATOR, $class);
				$file = explode(DIRECTORY_SEPARATOR, $file);
				$file = array_pop($file) . ".php";
				wfDebugLog("AlexaSkillReadArticleWebHook", var_export("loader file:", true), true);

				global $IP;
				$basePath = "$IP/extensions/wikihow/common/composer/vendor/symfony/validator/Constraints";
				$filePath = $basePath . DIRECTORY_SEPARATOR . $file;
				wfDebugLog("AlexaSkillReadArticleWebHook", var_export("autoloader require:", true), true);

				wfDebugLog("AlexaSkillReadArticleWebHook", var_export($filePath, true), true);
				if (file_exists($filePath)) {
					wfDebugLog("AlexaSkillReadArticleWebHook", var_export("requiring: $filePath", true), true);
					// file exists makes sure that the loader fails silently
					require_once $filePath;
					return true;
				}
			}
		});
	}
}