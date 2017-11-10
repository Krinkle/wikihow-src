<?php

if (!defined('MEDIAWIKI')) {
	die();
}

$wgExtensionCredits['other'][] = array(
	'name' => 'SensitiveArticle',
	'author' => 'Alberto Burgos',
	'description' => 'Sensitive Article Tagging'
);

// Classes shared across the project

$wgAutoloadClasses['SensitiveArticle\SensitiveArticleDao'] = dirname( __FILE__ ) . '/core/SensitiveArticleDao.class.php';
$wgAutoloadClasses['SensitiveArticle\SensitiveArticle'] = dirname( __FILE__ ) . '/core/SensitiveArticle.class.php';

$wgAutoloadClasses['SensitiveArticle\SensitiveReasonDao'] = dirname( __FILE__ ) . '/core/SensitiveReasonDao.class.php';
$wgAutoloadClasses['SensitiveArticle\SensitiveReason'] = dirname( __FILE__ ) . '/core/SensitiveReason.class.php';

// The Sensitive Article Tagging widget on the staff-only section

$wgSpecialPages['SensitiveArticleWidgetApi'] = 'SensitiveArticle\SensitiveArticleWidgetApi';

$wgAutoloadClasses['SensitiveArticle\SensitiveArticleWidget'] = dirname( __FILE__ ) . '/widget/SensitiveArticleWidget.class.php';
$wgAutoloadClasses['SensitiveArticle\SensitiveArticleWidgetApi'] = dirname( __FILE__ ) . '/widget/SensitiveArticleWidgetApi.body.php';

// Special:SensitiveArticleAdmin

$wgSpecialPages['SensitiveArticleAdmin'] = 'SensitiveArticle\SensitiveArticleAdmin';

$wgAutoloadClasses['SensitiveArticle\SensitiveArticleAdmin'] = dirname( __FILE__ ) . '/admin/SensitiveArticleAdmin.body.php';

$wgResourceModules['ext.wikihow.SensitiveArticle.admin'] = [
	'targets' => ['desktop', 'mobile'],
	'position' => 'top',
	'remoteExtPath' => 'wikihow/SensitiveArticle/admin/resources',
	'localBasePath' => dirname(__FILE__) . '/admin/resources',
	'styles' => ['sensitive_article_admin.less'],
	'scripts' => ['sensitive_article_admin.js'],
];
