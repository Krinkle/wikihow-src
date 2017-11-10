<?php

if ( !defined('MEDIAWIKI') ) die();

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'QA Patrol',
    'author' => 'Scott Cushman',
    'description' => 'Tool to patrol recently-submitted questions and answers for article Q&As',
);

$wgSpecialPages['QAPatrol'] = 'QAPatrol';
$wgAutoloadClasses['QAPatrol'] = dirname(__FILE__) . '/QAPatrol.body.php';
$wgAutoloadClasses['QAPatrolStats'] = dirname(__FILE__) . '/QAPatrolStats.class.php';
$wgAutoloadClasses['QAPatrolItem'] = dirname(__FILE__) . '/model/QAPatrolItem.php';
$wgExtensionMessagesFiles['QAPatrol'] = dirname(__FILE__) . '/QAPatrol.i18n.php';

$wgLogTypes[] = 'qa_patrol';
$wgLogNames['qa_patrol'] = 'qa_patrol';
$wgLogHeaders['qa_patrol'] = 'qa_patrol';

$wgResourceModules['ext.wikihow.qa_patrol'] = array(
	'scripts' => 'qa_patrol.js',
	'styles' => 'qa_patrol.css',
	'messages' => array(
		'qap_txt',
		'qap_txt_edit',
		'qap_qid',
		'qap_answer_lf_err',
		'qap_flag_great',
		'qap_flag_thanks'
	),
	'localBasePath' => dirname(__FILE__) . '/',
	'remoteExtPath' => 'wikihow/QAPatrol',
	'position' => 'top',
	'targets' => array( 'desktop', 'mobile' )
);
