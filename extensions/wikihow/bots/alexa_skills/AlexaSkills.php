<?php

$wgExtensionCredits['MessengerSearchBot'][] = array(
	'name' => 'AlexaSkillReadArticle Page',
	'author' => 'Jordan Small',
	'description' => 'Alexa skill that queries and reads articles',
);

$wgSpecialPages['AlexaSkillReadArticleWebHook'] = 'AlexaSkillReadArticleWebHook';
$wgAutoloadClasses['AlexaSkillReadArticleWebHook'] = __DIR__ . '/AlexaSkillReadArticleWebHook.php';


