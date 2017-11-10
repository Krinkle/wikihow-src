<?php

if (!defined('MEDIAWIKI')) die();

$wgExtensionCredits['specialpage'][] = array (
    'name' => 'Article Rater',
    'author' => 'RJS Bhatia',
    'description'=> 'Script for rating articles using classifier',
    );
    
$wgSpecialPages['Aqrater']='Aqrater';
$wgAutoloadClasses['Aqrater']=dirname(__FILE__).'/Aqrater.body.php';