<?php

/********
 This script goes back through the english Ad Exclusion
 English Database and checks for all translations and
 ads them to their respective databases. For now, this
 will be run monthly.
********/

require_once('commandLine.inc');

$dbr = wfGetDB(DB_SLAVE);
$dbw = wfGetDB(DB_MASTER);

$res = DatabaseHelper::batchSelect(AdminAdExclusions::EXCLUSION_TABLE, "*", array());

foreach($res as $row) {
	//check for all translations
	AdminAdExclusions::processTranslations($dbw, $row->ae_page);
}