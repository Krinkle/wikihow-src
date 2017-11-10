<?php

if ( !defined('MEDIAWIKI') ) die();

class ManageSuggestedTopics extends SpecialPage {

	public function __construct() {
		parent::__construct( 'ManageSuggestedTopics' );
	}

	public function execute($par) {
		global $wgRequest, $wgUser, $wgOut;

		if (!in_array( 'sysop', $wgUser->getGroups()) && !in_array( 'newarticlepatrol', $wgUser->getRights() ) ) {
			$wgOut->setArticleRelated( false );
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
        }

		list( $limit, $offset ) = wfCheckLimits();

		$wgOut->setPageTitle('Manage Suggested Topics');
		$wgOut->setHTMLTitle('Manage Suggested Topics - wikiHow');
		//$wgOut->addModules( ['ext.wikihow.winpop'] );
		$wgOut->setRobotPolicy('noindex,nofollow');

		$dbr = wfGetDB(DB_SLAVE);
		$wgOut->addModules( ['ext.wikihow.SuggestedTopics'] );

		if ($wgRequest->wasPosted()) {
			$accept = array();
			$reject = array();
			$updates = array();
			$newnames = array();
			foreach ($wgRequest->getValues() as $key=>$value) {
				$id = str_replace("ar_", "", $key);
				if ($value == 'accept') {
					$accept[] = $id;
				} elseif ($value == 'reject') {
					$reject[] = $id;
				} elseif (strpos($key, 'st_newname_') !== false) {
					$updates[str_replace('st_newname_', '', $key)] = $value;
					$newnames[str_replace('st_newname_', '', $key)] = $value;
				}
			}
			
			//log all this stuff
			self::logManageSuggestions($accept, $reject, $newnames);
			
			$dbw = wfGetDB(DB_MASTER);
			if (count($accept) > 0) {
				$dbw->update('suggested_titles', array('st_patrolled' => 1), array('st_id' => $accept), __METHOD__);
			}
			if (count($reject) > 0) {
				$dbw->delete('suggested_titles', array('st_id' => $reject), __METHOD__);
			}

			foreach ($updates as $u=>$v) {
				$t = Title::newFromText($v);
				if (!$t) continue;

				// renames occassionally cause conflicts with existing requests, that's a bummer
				if (isset($newnames[$u])) {
					$page = $dbr->selectField('page', array('page_id'), array('page_title' => $t->getDBKey()), __METHOD__);
					if ($page) {
						// wait, this article is already written, doh
						$notify = $dbr->selectField('suggested_titles', array('st_notify'), array('st_id' => $u), __METHOD__);
						if ($notify) {
                			$dbw->insert('suggested_notify', array('sn_page' => $page, 'sn_notify' => $notify, 'sn_timestamp' => wfTimestampNow(TS_MW)), __METHOD__);
						}
						$dbw->delete('suggested_titles', array('st_id' => $u), __METHOD__);
					}
					$id = $dbr->selectField('suggested_titles', array('st_id'), array('st_title' => $t->getDBKey()), __METHOD__);
					if ($id) {
						// well, it already exists... like the Highlander, there can be only one
						$notify = $dbr->selectField('suggested_titles', array('st_notify'), array('st_id' => $u), __METHOD__);
						if ($notify) {
							// append the notify to the existing
							$dbw->update('suggested_titles', array('st_notify = concat(st_notify, ' . $dbr->addQuotes("\n" . $notify) . ")"), array('st_id' => $id), __METHOD__);
						}
						// delete the old one
						$dbw->delete('suggested_titles', array('st_id' => $u), __METHOD__);
					}
				}
				$dbw->update('suggested_titles',
					array('st_title' => $t->getDBKey()),
					array('st_id' => $u),
					__METHOD__);
			}
			
			$wgOut->addHTML(count($accept) . " suggestions accepted, " . count($reject) . " suggestions rejected.");
		}
		$sql = "SELECT st_title, st_user_text, st_category, st_id
				FROM suggested_titles WHERE st_used=0
				AND st_patrolled=0 ORDER BY st_suggested DESC LIMIT $offset, $limit";
		$res = $dbr->query($sql, __METHOD__);
		$wgOut->addHTML("
				<form action='/Special:ManageSuggestedTopics' method='POST' name='suggested_topics_manage'>
				<table class='suggested_titles_list wh_block'>
				<tr class='st_top_row'>
				<td class='st_title'>Article request</td>
				<td>Category</td>
				<td>Edit Title</td>
				<td>Requestor</td>
				<td>Accept</td>
				<td>Reject</td>
			</tr>
			");
		$count = 0;
		foreach ($res as $row) {
			$t = Title::newFromDBKey($row->st_title);
			if (!$t) continue;
			$c = "";
			if ($count % 2 == 1) $c = "class='st_on'";
			$u = User::newFromName($row->st_user_text);

			$wgOut->addHTML("<tr $c>
					<input type='hidden' name='st_newname_{$row->st_id}' value=''/>
					<td class='st_title_m' id='st_display_id_{$row->st_id}'>{$t->getText()}</td>
					<td>{$row->st_category}</td>
					<td><a href='' onclick='WH.SuggestedTopics.editSuggestion({$row->st_id}); return false;'>Edit</a></td>
					" .  ($u ? "<td><a href='{$u->getUserPage()->getFullURL()}' target='new'>{$u->getName()}</a></td>"
							: "<td>{$row->st_user_text}</td>" ) .
					"<td class='st_radio'><input type='radio' name='ar_{$row->st_id}' value='accept'></td>
					<td class='st_radio'><input type='radio' name='ar_{$row->st_id}' value='reject'></td>
				</tr>");
			$count++;
		}
		$wgOut->addHTML("</table>
			<br/><br/>
			<table width='100%'><tr><td style='text-align:right;'><input type='submit' value='Submit' class='button secondary' /></td></tr></table>
			</form>
			");
	}
	
	private static function logManageSuggestions($accept, $reject, $newnames) {
		$title_mst = Title::makeTitle(NS_SPECIAL, "ManageSuggestedTopics");
		
		//accepted
		foreach ($accept as $id) {
			self::logManageSuggestion('added',$title_mst,$newnames[$id],$id);
		}
		
		//rejected
		foreach ($reject as $id) {
			self::logManageSuggestion('removed',$title_mst,$newnames[$id],$id);
		}
	}
	
	//write a log message for the action just taken
	private static function logManageSuggestion($name, $title_mst, $suggestion, $suggest_id) {
		global $wgUser;
		
		if (!$suggestion) {
			//not new, let's dive for it
			$dbr = wfGetDB(DB_SLAVE);
			$suggestion = $dbr->selectField('suggested_titles', 'st_title', array('st_id' => $suggest_id), __METHOD__);
		}
		$page_title = Title::newFromText($suggestion);
		
		if ($page_title) {
			//then log that sucker
			$log = new LogPage( 'suggestion', true );
			$mw_msg = ($name == 'added') ? 'managesuggestions_log_add' : 'managesuggestions_log_remove';
			$msg = wfMsg($mw_msg, $wgUser->getName(), $page_title);
			$log->addEntry($name, $title_mst, $msg);
		}
	}
}

