<?php

if ( !defined('MEDIAWIKI') ) die();

$wgAutoloadClasses['MobileTabs'] = __DIR__ . '/MobileTabs.class.php';
$wgExtensionMessagesFiles['MobileTabs'] = __DIR__ .'/mobiletabs.i18n.php';

$wgHooks['BeforePageDisplay'][] = 'MobileTabs::onBeforePageDisplay';

$wgResourceModules['ext.wikihow.mobiletabs'] = array(
	'scripts' => array('mobiletabs.js'),
	'localBasePath' => dirname(__FILE__) . '/',
	'remoteExtPath' => 'wikihow/mobiletabs',
	'position' => 'top',
	'targets' => array( 'mobile' ),
);