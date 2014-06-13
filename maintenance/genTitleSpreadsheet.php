<?
//
// Generate a list of (With Video, With Pictures) type extra info that
// you find for titles.  This is for Chris.
//
// Copied and changed from GenTitleExtraInfo.body.php by Reuben.
//

require_once("commandLine.inc");

global $IP;
require_once("$IP/skins/WikiHowSkin.php");

print "querying database...\n";
$dbr = wfGetDB(DB_SLAVE);
$titles = array();
$sql = 'SELECT page_title FROM page WHERE page_namespace=' . NS_MAIN . ' AND page_is_redirect=0';
$res = $dbr->query($sql, __FILE__);
foreach ($res as $obj) {
	$titles[] = Title::newFromDBkey($obj->page_title);
}
print "found " . count($titles) . " articles.\n";

$file = isset($argv[0]) ? $argv[0] : 'out.csv';
print "writing output to $file...\n";
$fp = fopen($file, 'w');
if (!$fp) die("error: could not write to file $file\n");
fputs($fp, "id,full-title,url\n");

global $wgLanguageCode;
foreach ($titles as $title) {
	$tt = TitleTests::newFromTitle($title);
	if (!$tt) continue;
	$id = $title->getArticleId();
	if(!$argv[1] || $argv[1] != "1") {
		$htmlTitle = $tt->getTitle();
	}
	else {
		$howto = wfMessage('howto', $title)->text();
		$htmlTitle = wfMessage('pagetitle', $howto)->text();
	}
	$url = Misc::getLangBaseURL($wgLanguageCode) . '/' . $title->getPartialURL();

	$out = array($id, $htmlTitle, $url);
	fputcsv($fp, $out);
}

fclose($fp);

print "done.\n";

