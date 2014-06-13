<?php                                                                           
if ( ! defined( 'MEDIAWIKI' ) )
  die();

$wgAutoloadClasses['WHLogFactory'] = dirname(__FILE__) . '/WikihowLog.body.php';
