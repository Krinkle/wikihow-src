<?php

if (!defined('MEDIAWIKI')) die();

class ABTesting {
	public static function addVarnishHeaders() {
		global $wgRequest, $wgTitle;

		if ($wgRequest && $wgTitle) {
            $forceVariant = $wgRequest->getHeader('X-T-Force');
            $variant = $forceVariant ? $wgRequest->getHeader('X-T-Variant') : '';
            $wgRequest->response()->header('Vary: X-T-Variant');
            //$wgRequest->response()->header('Cache-Control: s-maxage=100, must-revalidate, max-age=0');
            $rand = mt_rand(1, 100);
            if ($header == 'A' || (!$header && $rand <= 60)) {
                $wgRequest->response()->header("X-T-Variant: A");
                $wgRequest->response()->header("X-T-Prob: 60");
                print "Variant A (header recvd: $header, rand: $rand)\n";
            } else {
                $wgRequest->response()->header("X-T-Variant: B");
                $wgRequest->response()->header("X-T-Prob: 40");
                print "Variant B (header recvd: $header, rand: $rand)\n";
            }
		}

		return true;
	}
}
