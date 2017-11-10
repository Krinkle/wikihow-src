<?php

if ( !defined( 'MEDIAWIKI' ) ) die();

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Suggested Topics',
    'author' => 'Bebeth',
    'description' => 'Suggested topics: help authors find topics to write about on wikiHow',
);

$wgSpecialPages['RequestTopic'] = 'RequestTopic';
$wgSpecialPages['ListRequestedTopics'] = 'ListRequestedTopics';
$wgSpecialPages['ManageSuggestedTopics'] = 'ManageSuggestedTopics';
$wgSpecialPages['RenameSuggestion'] = 'RenameSuggestion';
$wgSpecialPages['YourArticles'] = 'YourArticles';
$wgSpecialPages['RecommendedArticles'] = 'RecommendedArticles';
$wgSpecialPages['SuggestCategories'] = 'SuggestCategories';


# Internationalisation file
$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['RequestTopic'] = $dir . 'SuggestedTopics.i18n.php';
$wgExtensionMessagesFiles['RequestTopicAlias'] = $dir . 'RequestTopic.alias.php';
$wgExtensionMessagesFiles['ListRequestedTopics'] = $dir . 'SuggestedTopics.i18n.php';
$wgExtensionMessagesFiles['ListRequestedTopicsAlias'] = $dir . 'ListRequestedTopics.alias.php';
$wgExtensionMessagesFiles['ManageSuggestedTopics'] = $dir . 'SuggestedTopics.i18n.php';
$wgExtensionMessagesFiles['RecommendedArticles'] = $dir . 'SuggestedTopics.i18n.php';
$wgExtensionMessagesFiles['YourArticles'] = $dir . 'SuggestedTopics.i18n.php';

$wgAutoloadClasses['RequestTopic']              = $dir . 'RequestTopic.php';
$wgAutoloadClasses['ManageSuggestedTopics']     = $dir . 'ManageSuggestedTopics.php';
$wgAutoloadClasses['ListRequestedTopics']       = $dir . 'ListRequestedTopics.php';
$wgAutoloadClasses['RenameSuggestion']          = $dir . 'RenameSuggestion.php';
$wgAutoloadClasses['YourArticles']              = $dir . 'YourArticles.php';
$wgAutoloadClasses['RecommendedArticles']       = $dir . 'RecommendedArticles.php';
$wgAutoloadClasses['SuggestCategories']         = $dir . 'SuggestCategories.php';
$wgAutoloadClasses['SuggestedTopicsHooks']      = $dir . 'SuggestedTopicsHooks.php';

$wgHooks['NABArticleFinished'][] = array("SuggestedTopicsHooks::notifyRequesterOnNab");

$wgResourceModules['ext.wikihow.SuggestedTopics'] = [
	'localBasePath' => __DIR__,
	'targets' => [ 'desktop' ],
	'styles' => [ 'suggestedtopics.css' ],
	'scripts' => [ 'suggestedtopics.js' ],
	'dependencies' => [ 'ext.wikihow.common_top', 'jquery.ui.dialog' ],
	'remoteExtPath' => 'wikihow',
	'position' => 'top' ];
