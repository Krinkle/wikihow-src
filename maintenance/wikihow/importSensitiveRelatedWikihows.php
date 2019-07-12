<?php

require_once __DIR__ . '/../Maintenance.php';


// import the sensitive related wikihows
class importSensitiveRelatedWikihows extends Maintenance {

    public function __construct() {
        parent::__construct();
        $this->mDescription = "import sensitive related wikihows";
    }

	private function emailResults( $message ) {
		global $wgIsDevServer;

		$to = new MailAddress( 'sensitiverelateds@wikihow.com,aaron@wikihow.com,chris@wikihow.com,elizabeth@wikihow.com' );
		if ( $wgIsDevServer ) {
			$to = new MailAddress("aaron@wikihow.com");
		}

		$from = new MailAddress( "alerts@tools1.wikihow.com" );
		$subject = "Import Sensitive Related Wikihows Script";
		$endPart = "\n\nGenerated by " . __FILE__ . " on " . gethostname() . "\n";
		UserMailer::send( $to, $from, $subject, $message . $endPart, null, "text/plain; charset=UTF-8" );
	}

	public function execute() {
		$message = '';
		try {
			$result = SensitiveRelatedWikihows::saveSensitiveRelatedArticles();
			if ( !$result ) {
				$message = "script ran successfully.\nno new pages were imported or deleted";
			} else {
				$message = "script ran successfully.\nresults:\n" . $result;
			}
		} catch ( Exception $e ) {
			$message = "an error occured in this script:\n";
			$message .= $e->getMessage();
		}

		$this->emailResults( $message );
	}
}


$maintClass = "importSensitiveRelatedWikihows";
require_once RUN_MAINTENANCE_IF_MAIN;
