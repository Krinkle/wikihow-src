<?php
if ( ! defined( 'MEDIAWIKI' ) )
die();

$wgExtensionCredits['specialpage'][] = array(
'name' => 'Article Reviewers',
'author' => 'Bebeth Steudel',
);

$wgSpecialPages['ArticleReviewers'] = 'ArticleReviewers';
$wgAutoloadClasses['ArticleReviewers'] = dirname(__FILE__) . '/ArticleReviewers.body.php';
$wgExtensionMessagesFiles['ArticleReviewers'] = __DIR__ . '/ArticleReviewers.i18n.php';

$wgSpecialPages['AdminArticleReviewers'] = 'AdminArticleReviewers';
$wgAutoloadClasses['AdminArticleReviewers'] = dirname(__FILE__) . '/AdminArticleReviewers.body.php';

$wgResourceModules['ext.wikihow.articlereviewers'] = array(
	'styles' => array('articlereviewers.css'),
	'localBasePath' => dirname(__FILE__) . '/',
	'remoteExtPath' => 'wikihow/ArticleReviewers',
	'position' => 'top',
	'targets' => array( 'desktop', 'mobile' ),
);

$wgResourceModules['ext.wikihow.articlereviewers_script'] = array(
	'scripts' => array('articlereviewers.js'),
	'localBasePath' => dirname(__FILE__) . '/',
	'remoteExtPath' => 'wikihow/ArticleReviewers',
	'position' => 'bottom',
	'targets' => array( 'desktop' ),
);

$wgResourceModules['ext.wikihow.mobilearticlereviewers'] = array(
	'styles' => array('mobilearticlereviewers.css'),
	'localBasePath' => dirname(__FILE__) . '/',
	'remoteExtPath' => 'wikihow/ArticleReviewers',
	'position' => 'top',
	'targets' => array( 'desktop', 'mobile' ),
);

$wgResourceModules['ext.wikihow.adminarticlereviewers'] = array(
	'styles' => array('../../uploadify/uploadify.css'),
	'localBasePath' => dirname(__FILE__) . '/',
	'remoteExtPath' => 'wikihow/ArticleReviewers',
	'position' => 'top',
	'targets' => array( 'desktop', 'mobile' ),
	'scripts' => array('adminarticlereviewers.js'),
);

/*********

CREATE TABLE `verifier_info` (
`vi_name` varchar(10) NOT NULL DEFAULT '',
`vi_info` blob NOT NULL,
PRIMARY KEY (`vi_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1

******/
