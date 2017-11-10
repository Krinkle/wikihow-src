<?php

if ( !defined('MEDIAWIKI') ) die();

$wgAutoloadClasses['ConfigStorage'] = __DIR__ . '/ConfigStorage.php';
$wgAutoloadClasses['AdminTags'] = __DIR__ . '/SpecialAdminTags.php';
$wgAutoloadClasses['ArticleTag'] = __DIR__ . '/ArticleTag.php';
$wgAutoloadClasses['ArticleTagList'] = __DIR__ . '/ArticleTagList.php';
//$wgAutoloadClasses['ABTesting'] = __DIR__ . '/ABTesting.php';
$wgExtensionMessagesFiles['ArticleTagAlias'] = __DIR__ . '/ArticleTags.alias.php';

$wgHooks['ConfigStorageStoreConfig'] = ['ArticleTag::onConfigStorageStoreConfig'];
//$wgHooks['AddVarnishHeaders'] = ['ABTesting::onAddVarnishHeaders'];
$wgSpecialPages['AdminTags'] = 'AdminTags';
$wgSpecialPages['AdminConfigEditor'] = 'AdminTags'; // alias from old special page name
