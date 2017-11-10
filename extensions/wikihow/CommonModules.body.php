<?php

class CommonModules {

	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {

		// Note: WH.timeStart should be calculated as high in the page as possible
		// to get an accurate time when the page started running JS.
$headScript = <<<EOS
window.WH=window.WH||{timeStart:+(new Date()),lang:{}};
if(/.+@.+/.test(window.location.hash)){window.location.hash='';}
EOS;

		$out->addHeadItem('shared_head_scripts',  HTML::inlineScript($headScript));

		$out->addModules( array( 'ext.wikihow.common_top' ) );
		$out->addModules( array( 'ext.wikihow.common_bottom' ) );

		// Adds printable CSS rules for @media "all" when printable=yes URL param
		// is set.
		if ($out->isPrintable()) {
			$out->addModules( array( 'ext.wikihow.printable_all' ) );
		}
	}

}
