<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Ad Exclusions Tool',
	'author' => 'Bebeth Steudel',
	'description' => '',
);

$wgSpecialPages['AdminAdExclusions'] = 'AdminAdExclusions';
$wgAutoloadClasses['AdminAdExclusions'] = __DIR__ . '/AdminAdExclusions.body.php';

$wgResourceModules['ext.wikihow.ad_exclusions'] = [
	'targets' => [ 'desktop' ],
	'position' => 'top',
	'remoteExtPath' => 'wikihow/wikihowAds',
	'localBasePath' => dirname(__FILE__),
	'scripts' => [ 'adminadexclusions.js' ],
];


/****
CREATE TABLE IF NOT EXISTS `adexclusions` (
`ae_page` int(10) unsigned NOT NULL,
UNIQUE KEY  (`ae_page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
****/
