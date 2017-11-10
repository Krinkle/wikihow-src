<?php

if ( !defined('MEDIAWIKI') ) die();

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Q&A Box',
    'author' => 'Scott Cushman',
    'description' => "It's like Knowledge Box but, you know, for Q&As...",
);

$wgSpecialPages['QABox'] = 'QABox';
$wgAutoloadClasses['QABox'] = dirname(__FILE__) . '/QABox.body.php';
$wgExtensionMessagesFiles['QABox'] = dirname(__FILE__) . '/QABox.i18n.php';

$wgResourceModules['ext.wikihow.qa_box'] = array(
	'scripts' => 'qa_box.js',
	'styles' => 'qa_box.css',
	'messages' => array(
		'qab_submit',
		'qab_email',
		'qab_maxed',
		'qab_min',
		'qab_thanks'
	),
	'localBasePath' => dirname(__FILE__) . '/',
	'remoteExtPath' => 'wikihow/qabox',
	'targets' => array( 'desktop' ),
	'dependencies' => ['wikihow.common.pub_sub']
);


