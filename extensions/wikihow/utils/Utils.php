<?php

$wgAutoloadClasses['FileAttachmentResponse'] = dirname( __FILE__ ) . '/FileAttachmentResponse.php';
$wgAutoloadClasses['FileAttachmentMailer'] = dirname( __FILE__ ) . '/FileAttachmentMailer.php';
$wgAutoloadClasses['DataUtil'] = dirname( __FILE__ ) . '/DataUtil.php';
$wgAutoloadClasses['UrlUtil'] = dirname( __FILE__ ) . '/UrlUtil.php';
$wgAutoloadClasses['WilsonConfidenceInterval'] = dirname( __FILE__ ) . '/WilsonConfidenceInterval.php';
$wgAutoloadClasses['BadWordFilter'] = dirname( __FILE__ ) . '/BadWordFilter.php';
$wgAutoloadClasses['FileUtil'] = dirname( __FILE__ ) . '/FileUtil.php';
$wgAutoloadClasses['RandomTitleGenerator'] = dirname( __FILE__ ) . '/RandomTitleGenerator.php';
$wgAutoloadClasses['GooglePageSpeedUtil'] = dirname( __FILE__ ) . '/GooglePageSpeedUtil.php';
$wgAutoloadClasses['DOMUtil'] = dirname( __FILE__ ) . '/DOMUtil.php';


$wgHooks['UnitTestsList'][] = array( 'BadWordFilter::onUnitTestsList');
