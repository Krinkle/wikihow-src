<?php

class Ad {
	var $mHtml;
	var $mType;
	var $mBodyAd;
	var $mLabel;

	public function __construct( $type, $showRightRailLabel = false, $labelExtra = '' ) {
		$this->mType = $type;

		if ( strstr( $this->mType, "rightrail" ) ) {
			$this->mBodyAd = false;
		} else {
			$this->mBodyAd = true;
		}

		$this->mLabel = "";
		if ( $this->mBodyAd || $showRightRailLabel ) {
			$this->mLabel = "ad_label";
			if ( $labelExtra ) {
				$this->mLabel .= " ".$labelExtra;
			}
		}

	}

	public function getLabel() {
		return $this->mLabel;
	}
}

/*
 * default setup for the ad creators
 */
abstract class DesktopAdCreator {
	var $mAds = array();
	var $mShowRightRailLabel = false;
	var $mAdLabelVersion = 1;
	var $mRightRailAdLabelVersion = 1;
	var $mStickyIntro = false;
	var $mDFPKeyVals = array();
	var $mRefreshableRightRail = false;

	protected function getNewAd( $type ) {
		$labelExtra = "";
		$showRRLabel = $this->mShowRightRailLabel;

		if ( strstr( $type, "rightrail" ) ) {
			if ( $this->mRightRailAdLabelVersion > 1 ) {
				$labelExtra = "ad_label_dollar";
			}
		} else {
			if ( $this->mAdLabelVersion > 1 ) {
				$labelExtra = "ad_label_dollar";
			}
		}
		$ad = new Ad( $type, $showRRLabel, $labelExtra );

		return $ad;
	}

	/*
	 * does the right rail have a label
	 * $param boolean
	 */
	public function setShowRightRailLabel( $val ) {
		$this->mShowRightRailLabel = $val;
	}

	/*
	 * intro sticky data attr (to be used client side)
	 * $param boolean
	 */
	public function setStickyIntro( $val ) {
		$this->mStickyIntro = $val;
	}

    /*
     * extra key value to send in dfp
     */
    public function setDFPKeyValue( $key, $val ) {
        $this->mDFPKeyVals[$key] = $val;
    }

    /*
     * get json string of the dfp key vals for use in js
     */
    public function getDFPKeyValsJSON() {
        $dfpKeyVals = $this->mDFPKeyVals;

        // the default value of this always present key val pair
        $dfpKeyVals['refreshing'] = '1';

        $dfpKeyVals = json_encode( $dfpKeyVals );
        return $dfpKeyVals;
    }

	public function getSticky( $ad ) {
		if ( $ad->mType == 'intro' && $this->mStickyIntro == true ) {
			return true;
		}

		return false;
	}

	public function setRefreshableRightRail( $val ) {
		$this->mRefreshableRightRail = $val;
	}

	public function getRefreshable( $ad ) {
		if ( $ad->service == 'dfp' && strstr( $ad->mType, "rightrail2" ) && $this->mRefreshableRightRail ) {
			return true;
		}
		return false;
	}

	public function getRenderRefresh( $ad ) {
		if ( $ad->service == 'dfp' && strstr( $ad->mType, "rightrail2" ) && $this->mRefreshableRightRail ) {
			return true;
		}
		return false;
	}

	public function getViewableRefresh( $ad ) {
		return false;
	}

	public function getIsLastAd( $ad ) {
		if ( $ad->mType == "rightrail2" ) {
			return true;
		}
		return false;
	}

	/*
	 * what type of ad label to appear above ads
	 * $param integer version of ad label
	 */
	public function setAdLabelVersion( $type ) {
		$this->mAdLabelVersion = $type;
	}

	/*
	 * what type of ad label to appear above right rail ads
	 * $param integer version of ad label
	 */
	public function setRightRailAdLabelVersion( $type ) {
		$this->mRightRailAdLabelVersion = $type;
	}

	public function setTopCategory( $topCategory ) {

	}

	/*
	 * gets the ad data for all ads on the page
	 * also requires php query to be initalized for the body of the page
	 */
	abstract public function setupAdHtml();

	/*
	 * uses php query to put the ad html into the body of the page
	 */
	abstract public function insertAdsInBody();

	/*
	 *	any html or js that goes in the head of our page to support this ad setup
	 */
	abstract public function getHeadHtml();
}


/*
 * default desktop ad creator extends from a base class which is used also by older code
 * or else this would be the true base class and when we remove that code this will be
 */
abstract class DefaultDesktopAdCreator extends DesktopAdCreator {
	public function __construct() {
	}


	/*
	 * gets the ad data for all ads on the page
	 * also requires php query to be initalized for the body of the page
	 * or else it will not do anything
	 * we only get the intro and right rail units
	 */
	public function setupAdHtml() {
		if ( !phpQuery::$defaultDocumentID )  {
			return;
		}
		$this->mAds['intro'] = $this->getIntroAd();
		for ( $i = 0; $i < 3; $i++ ) {
			$this->mAds['rightrail'.$i] = $this->getRightRailAd( $i );
		}
		$this->mAds['step'] = $this->getStepAd();
		$this->mAds['method'] = $this->getMethodAd();
		for ( $i = 0; $i < pq('.qz_container')->length; $i++ ) {
			$this->mAds['quiz'.$i] = $this->getQuizAd( $i );
		}
	}

	/*
	 * creates the quiz Ads
	 */
	abstract public function getQuizAd( $num );

	/*
	 * creates the intro Ad
	 */
	abstract public function getIntroAd();

	/*
	 * creates the step Ad
	 */
	abstract public function getStepAd();

	/*
	 * creates a right rail ad based on the right rail position for this ad implementation
	 * @param Integer the right rail number or position on the page usually 0 1 or 2
	 * @return Ad an ad for the right rail
	 */
	abstract public function getRightRailAd( $num );

	/*
	 * uses php query to put the ad html into the body of the page
	 * this only inserts into the intro but for a bigger example look at DeprecatedDFPAdCreator
	 */
	public function insertAdsInBody() {
		// make sure we have php query object
		if ( !phpQuery::$defaultDocumentID )  {
			return;
		}

		$stepAd = $this->mAds['step']->mHtml;
		if ( $stepAd && pq( ".steps_list_2 > li:eq(0)" )->length() ) {
			pq( ".steps_list_2 > li:eq(0)" )->append( $stepAd );
		}

		$methodAd = $this->mAds['method']->mHtml;
		if ( $methodAd && pq( ".steps_list_2:first > li" )->length > 1 && pq( ".steps_list_2:first > li:last-child)" )->length() ) {
			pq( ".steps_list_2:first > li:last-child" )->append( $methodAd );
		}

		$introHtml = $this->mAds['intro']->mHtml;
		if ( $introHtml ) {
			pq( "#intro" )->append( $introHtml )->addClass( "hasad" );
		}

		for ( $i = 0; $i < pq( '.qz_container' )->length; $i++ ) {
			$quizHtml = $this->mAds['quiz'.$i]->mHtml;
			if ( $quizHtml ) {
				pq( '.qz_container' )->eq($i)->append( $quizHtml );
			}
		}
	}
}

class MixedAdCreator extends DefaultDesktopAdCreator {
	var $mAdsenseSlots = array();
	var $mDFPData = array();
	var $mRightRailLabel = false;
	var $mLateLoadDFP = false;
	var $mAdsenseChannels = array();

	public function __construct() {
		$this->mAdsenseSlots = array(
			'intro' => 7862589374,
			'rightrail0' => 4769522171,
		);
		$this->mAdServices = array(
			'intro' => 'adsense',
			'rightrail0' => 'adsense',
			'rightrail1' => 'dfp',
			'rightrail2' => 'dfp'
		);
	}

	/*
	 * required by any dfp classes to set the ad unit paths
	 */
	protected function setDFPAdUnitPaths() {
		$this->mDFPData = array(
			'rightrail1' => array(
				'adUnitPath' => '/10095428/RR2_Test_32',
				'size' => '[300, 600]'
			),
			'rightrail2' => array(
				'adUnitPath' => '/10095428/RR3_Test_32',
				'size' => '[300, 600]'
			),
			'quiz' => array(
				'adUnitPath' => '/10095428/AllPages_Quiz_English_Desktop',
				'size' => '[728, 90]'
			),
		);
	}

	public function enableLateLoadDFP() {
		$this->mLateLoadDFP = true;
	}

	/*
	 * @param Ad
	 * @return int the adsense slot for this ad
	 */
	protected function getAdsenseSlot( $ad ) {
		return $this->mAdsenseSlots[$ad->mType];
	}

	/*
	 * @param Ad
	 * @return string or int channels to be used when creating adsense ad
	 */
	protected function getAdsenseChannels( $ad ) {
		return implode( ',', $this->mAdsenseChannels );
	}

	public function addAdsenseChannel( $channel ) {
		$this->mAdsenseChannels[] = $channel;
	}

	/*
	 * @param Ad
	 * @return string or int channels to be used when creating adsense ad
	 */
	protected function getAdClient( $ad ) {
		return 'ca-pub-9543332082073187';
	}

	/*
	 * return the abg snippet, not wrapped in a script tag
	 * @return string a snippet of javasript used to insert an adsense ad on an <ins> element
	 */
	protected function getAdsByGoogleJS( $ad ) {
		$channels = $this->getAdsenseChannels( $ad );
		$script = "(adsbygoogle = window.adsbygoogle || []).push({";
		if ( $channels ) {
			$script .= "params: {google_ad_channel: '$channels'}";
		}
		$script .= "});";
		return $script;
	}

	protected function getIntroAdDFP() {
		$ad = $this->getNewAd( 'intro' );
		$ad->targetId = 'introad';
		$ad->outerId = 'introad-outer';
		$ad->service = "dfp";
		$ad->width = 728;
		$ad->height = 90;
		$ad->initialLoad = true;
		$ad->lateLoad = false;
		$ad->mHtml = $this->getBodyAdHtml( $ad );
		return $ad;
	}

	protected function getIntroAdAdsense() {
		$ad = $this->getNewAd( 'intro' );
		$ad->targetId = 'introad';
		$ad->outerId = 'introad-outer';
		$ad->service = "adsense";
		$ad->width = 728;
		$ad->height = 120;
		$ad->initialLoad = true;
		$ad->lateLoad = false;
		$ad->mHtml = $this->getBodyAdHtml( $ad );
		return $ad;
	}
	/*
	 * creates the intro Ad
	 */
	public function getIntroAd() {
		$ad = $this->getNewAd( 'intro' );
		// for now only adsense supported for intro
		if ( $this->mAdServices['intro'] == "adsense" ) {
			$ad = $this->getIntroAdAdsense();
		} else if ( $this->mAdServices['intro'] == "dfp" ) {
			$ad = $this->getIntroAdDFP();
		} else {
			return $ad;
		}
		$ad->mHtml .= Html::inlineScript( "WH.desktopAds.addIntroAd('{$ad->outerId}')" );
		return $ad;
	}

	protected function getStepAdAdsense() {
		$ad = $this->getNewAd( 'step' );
		$ad->service = "adsense";
		$ad->adClass = "step_ad";
		$ad->width = 728;
		$ad->height = 90;
		$ad->initialLoad = true;
		$ad->lateLoad = false;
		$ad->mHtml = $this->getBodyAdHtml( $ad );
		return $ad;
	}

	protected function getMethodAdAdsense() {
		$ad = $this->getNewAd( 'method' );
		$ad->service = "adsense";
		$ad->adClass = "step_ad";
		$ad->width = 728;
		$ad->height = 90;
		$ad->initialLoad = true;
		$ad->lateLoad = false;
		$ad->mHtml = $this->getBodyAdHtml( $ad );
		return $ad;
	}

	/*
	 * creates the step Ad
	 */
	public function getStepAd() {
		$ad = $this->getNewAd( 'step' );
		// for now only adsense supported for intro
		if ( $this->mAdServices['step'] == "adsense" ) {
			$ad = $this->getStepAdAdsense();
		}
		return $ad;
	}

	/*
	 * creates the method Ad
	 */
	public function getMethodAd() {
		$ad = $this->getNewAd( 'method' );
		// for now only adsense supported for intro
		if ( $this->mAdServices['method'] == "adsense" ) {
			$ad = $this->getMethodAdAdsense();
		}
		return $ad;
	}

	/*
	 * @return Ad an ad for the first right rail
	 */
	protected function getRightRailFirstAdsense() {
		$ad = $this->getNewAd( 'rightrail0' );
		$ad->service = "adsense";
		$ad->targetId = $ad->mType;
		$ad->containerHeight = 2000;
		$ad->initialLoad = true;
		$ad->lateLoad = false;
		$ad->width = 300;
		$ad->height = 600;
		$ad->mHtml = $this->getRightRailAdHtml( $ad );
		return $ad;
	}

	/*
	 * @return Ad an ad for the second right rail
	 */
	protected function getRightRailSecondAdsense() {
		$ad = $this->getNewAd( 'rightrail1' );
		$ad->service = "adsense";
		$ad->targetId = $ad->mType;
		$ad->containerHeight = 3300;
		$ad->initialLoad = false;
		$ad->lateLoad = false;
		$ad->width = 300;
		$ad->height = 600;
		$ad->mHtml = $this->getRightRailAdHtml( $ad );
		return $ad;
	}

	/*
	 * @return Ad an ad for the third right rail
	 */
	protected function getRightRailThirdAdsense() {
		$ad = $this->getNewAd( 'rightrail2' );
		$ad->service = "adsense";
		$ad->targetId = $ad->mType;
		$ad->containerHeight = 2000;
		$ad->initialLoad = false;
		$ad->lateLoad = false;
		$ad->width = 300;
		$ad->height = 600;
		$ad->mHtml = $this->getRightRailAdHtml( $ad );
		return $ad;
	}

	/*
	 * @return Ad a dfp ad for the first right rail
	 */
	protected function getRightRailFirstDFP() {
		$ad = $this->getNewAd( 'rightrail0' );
		$ad->service = "dfp";
		$ad->targetId = 'div-gpt-ad-1492454101439-0';
		$ad->containerHeight = 2000;
		$ad->initialLoad = true;
		$ad->lateLoad = false;
		$ad->width = 300;
		$ad->height = 600;
		$ad->mHtml = $this->getRightRailAdHtml( $ad );
		return $ad;
	}

	/*
	 * gets the html fo the second right rail ad using dfp
	 * @return Ad an ad for the second right rail
	 */
	protected function getRightRailSecondDFP() {
		$ad = $this->getNewAd( 'rightrail1' );
		$ad->service = "dfp";
		$ad->targetId = 'div-gpt-ad-1492454171520-0';
		$ad->containerHeight = 3300;
		$ad->initialLoad = false;
		$ad->lateLoad = false;
		$ad->width = 300;
		$ad->height = 600;
		$ad->mHtml = $this->getRightRailAdHtml( $ad );
		return $ad;
	}

	/*
	 * @return Ad an ad for the third right rail
	 */
	protected function getRightRailThirdDFP() {
		$ad = $this->getNewAd( 'rightrail2' );
		$ad->service = "dfp";
		$ad->targetId = 'div-gpt-ad-1492454222875-0';
		$ad->containerHeight = 2000;
		$ad->initialLoad = false;
		$ad->lateLoad = false;
		$ad->width = 300;
		$ad->height = 600;
		$ad->mHtml = $this->getRightRailAdHtml( $ad );
		return $ad;
	}

	protected function getDFPInnerHtml( $ad ) {
		$script = "";
		if ( $ad->lateLoad == false ) {
			$script = "googletag.cmd.push(function() { googletag.display('$ad->targetId'); });";
			$script = Html::inlineScript( $script );
		}
		$class = array();
		if ( $ad->getLabel() ) {
			$class[] = $ad->getLabel();
		}
		$attributes = array(
			'id' => $ad->targetId,
			'class' => $class
		);
		$html = Html::element( 'div', $attributes );
		return $html . $script;
	}

	protected function getAdsenseInnerHtml( $ad ) {
		// only get the inner html if initial load is true
		if ( $ad->initialLoad == false ) {
			return "";
		}
		$class = array( 'adsbygoogle' );
		if ( $ad->getLabel() ) {
			$class[] = $ad->getLabel();
		}
		$attributes = array(
			'class' => $class,
			'style' => "display:inline-block;width:".$ad->width."px;height:".$ad->height."px;",
			'data-ad-client' => $this->getAdClient( $ad ),
			'data-ad-slot' => $this->getAdsenseSlot( $ad )
		);
		$ins = Html::element( "ins", $attributes );
		$script = $this->getAdsByGoogleJS( $ad );
		$script = Html::inlineScript( $script );
		return $ins . $script;
	}

	protected function getAdInnerHtml( $ad ) {
		if ( $ad->service == "dfp" ) {
			return $this->getDFPInnerHtml( $ad );
		} else if ( $ad->service == "adsense" ) {
			return $this->getAdsenseInnerHtml( $ad );
		} else {
			return "";
		}
	}

	/*
	 * get the html of a body  ad. works for both dfp and adsense
	 * 
	 * @param Ad
	 * @return string html of the body ad
	 */
	protected function getBodyAdHtml( $ad ) {
		// we may have inner ad html depending on the ad loading setting
		$innerAdHtml = $this->getAdInnerHtml( $ad );

		$attributes = array(
			'class' => array( 'wh_ad_inner' ),
			'data-service' => $ad->service,
			'data-adtargetid' => $ad->targetId,
			'data-loaded' => $ad->initialLoad ? 1 : 0,
			'data-lateload' => $ad->lateLoad ? 1 : 0,
			'data-adsensewidth' => $ad->width,
			'data-adsenseheight' => $ad->height,
			'data-slot' => $this->getAdsenseSlot( $ad ),
			'data-channels' => $this->getAdsenseChannels( $ad ),
			'data-sticky' => $this->getSticky( $ad ),
			'data-refreshable' => $this->getRefreshable( $ad ),
			'data-renderrefresh' => $this->getRenderRefresh( $ad ),
			'data-viewablerefresh' => $this->getViewableRefresh( $ad ),
            'id' => $ad->outerId,
		);
		if ( $ad->adClass ) {
			$attributes['class'][] = $ad->adClass;
		}
		$html = Html::rawElement( 'div', $attributes, $innerAdHtml );
		$html .= Html::element( 'div', ['class' => 'clearall adclear'] );
		return $html;
	}

	/*
	 * get the html of the right rail ad. works for both dfp and adsense
	 * 
	 * @param Ad an ad with service target id and initial load etc defined
	 * @return string html of the  right rail ad
	 */
	protected function getRightRailAdHtml( $ad ) {
		// we may have inner ad html depending on the ad loading setting
		$innerAdHtml = $this->getAdInnerHtml( $ad );

		$attributes = array(
			'class' => 'whad',
			'data-service' => $ad->service,
			'data-adtargetid' => $ad->targetId,
			'data-loaded' => $ad->initialLoad ? 1 : 0,
			'data-lateload' => $ad->lateLoad ? 1 : 0,
			'data-adsensewidth' => $ad->width,
			'data-adsenseheight' => $ad->height,
			'data-slot' => $this->getAdsenseSlot( $ad ),
			'data-channels' => $this->getAdsenseChannels( $ad ),
			'data-refreshable' => $this->getRefreshable( $ad ),
			'data-renderrefresh' => $this->getRenderRefresh( $ad ),
			'data-viewablerefresh' => $this->getViewableRefresh( $ad ),
			'data-lastad' => $this->getIsLastAd( $ad ),
		);
		$html = Html::rawElement( 'div', $attributes, $innerAdHtml );

		$containerAttributes = array(
			'id' => $ad->mType,
			'style' => "height:{$ad->containerHeight}px",
			'class' => 'rr_container',
			'data-position' => 'aftercontent'
		);
		return Html::rawElement( 'div', $containerAttributes, $html );
	}

	/*
	 * @return Ad an ad for the first right rail
	 */
	public function getRightRailFirst() {
		$ad = $this->getNewAd( 'rightrail0' );
		// for now only adsense supported for intro
		if ( $this->mAdServices['rightrail0'] == "adsense" ) {
			$ad = $this->getRightRailFirstAdsense();
		} else if ( $this->mAdServices['rightrail0'] == "dfp" ) {
			$ad = $this->getRightRailFirstDFP();
		}
		return $ad;
	}

	/*
	 * @return Ad an ad for the first right rail
	 */
	public function getRightRailSecond() {
		$ad = $this->getNewAd( 'rightrail1' );
		if ( $this->mAdServices['rightrail1'] == "adsense" ) {
			$ad = $this->getRightRailSecondAdsense();
		} else if ( $this->mAdServices['rightrail1'] == "dfp" ) {
			$ad = $this->getRightRailSecondDFP();
		}
		return $ad;
	}

	/*
	 * @return Ad an ad for the third right rail
	 */
	public function getRightRailThird() {
		$ad = $this->getNewAd( 'rightrail2' );
		if ( $this->mAdServices['rightrail2'] == "adsense" ) {
			$ad = $this->getRightRailThirdAdsense();
		} else if ( $this->mAdServices['rightrail2'] == "dfp" ) {
			$ad = $this->getRightRailThirdDFP();
		}
		return $ad;
	}

	/*
	 * creates a right rail ad based on the right rail position for this ad implementation
	 * @param Integer the right rail number or position on the page usually 0 1 or 2
	 * @return Ad an ad for the right rail but no html
	 */
	public function getRightRailAd( $num ) {
		$type = "rightrail".$num;
		$ad = $this->getNewAd( $type );
		if ( $num == 0 ) {
			$ad = $this->getRightRailFirst();
		} else if ( $num == 1 ) {
			$ad = $this->getRightRailSecond();
		} else if ( $num == 2 ) {
			$ad = $this->getRightRailThird();
		}
		// now ad the js snippet to add the ad to the js sroll handler
		$ad->mHtml .= Html::inlineScript( "WH.desktopAds.addRightRailAd('{$ad->mType}')" );

		return $ad;
	}

	/*
	 * creates the quiz Ad
	 */
	public function getQuizAd( $num ) {
		$ad = $this->getNewAd( 'quiz' );
		$ad->service = "dfp";
		$ad->targetId = 'quizad'.$num;
		$ad->initialLoad = false;
		$ad->lateLoad = false;
		$ad->adClass = "hidden";
		$ad->mHtml = $this->getBodyAdHtml( $ad );
		$ad->mHtml .= Html::inlineScript( "WH.desktopAds.addQuizAd('{$ad->targetId}')" );
		return $ad;
	}

	/*
	 * get js snippet to refresh the first set of ads in a single call
	 */
	protected function getInitialRefreshSnippet() {
		//get initial ad refresh slots snippet to request them both in one call
		$refreshSlots = array();
		foreach ( $this->mAds as $type => $ad ) {
			if ( $ad->service == 'dfp' && $ad->initialLoad ) {
				$id = $ad->targetId;
				$refreshSlots[] = "gptAdSlots['$id']";
			}
		}
		if ( !count( $refreshSlots ) ) {
			return "";
		}
		$refreshParam = implode( ",", $refreshSlots );
		return Html::inlineScript("googletag.cmd.push(function() {googletag.pubads().refresh([$refreshParam]);});");
	}

	/*
	 * set up the ads html and data
	 */
	public function setupAdHtml() {
		$this->setDFPAdUnitPaths();

		parent::setupAdHtml();

		if ( $this->mAds ) {
			// after the first right rail ad, we append the initial refresh all to DFP ads
			$this->mAds['rightrail0']->mHtml .= $this->getInitialRefreshSnippet();
		}
	}

	/*
	 * gets script for adsense and dfp
	 * @return string html for head
	 */
	public function getHeadHtml() {
		$addAdsense = false;
		$addDFP = false;
		foreach ( $this->mAds as $ad ) {
			if ( $ad->service == "adsense" ) {
				$addAdsense = true;
			}

			if ( $ad->service == "dfp" ) {
				$addDFP = true;
			}
		}

		$adsenseScript = "";
		if ( $addAdsense ) {
			$adsenseScript = file_get_contents( dirname( __FILE__ )."/desktopAdsense.js" );
			$adsenseScript = Html::inlineScript( $adsenseScript );
		}

		$dfpScript = "";
		if ( $addDFP ) {
			$dfpScript = $this->getGPTDefine();
			if ( $this->mLateLoadDFP == false ) {
				$dfpInit = file_get_contents( dirname( __FILE__ )."/desktopDFP.js" );
				$dfpScript .= Html::inlineScript( $dfpInit );
			}
		}

		$adLabelStyle = $this->getAdLabelStyle();

		return $adsenseScript . $dfpScript . $adLabelStyle;
	}

	protected function getAdLabelStyle() {
		// get any extra css for ad labels.. the text or the font size etc
		$labelClassName = ".ad_label";
		$labelText = $this->getAdLabelText();
		$css = "{$labelClassName}:before{content:'$labelText';}";
		$css = Html::inlineStyle( $css );
		return $css;
	}

	protected function getAdLabelText() {
		$labelText = wfMessage( 'ad_label' );
		return $labelText;
	}

	protected function getGPTDefine() {
        $dfpKeyVals = $this->getDFPKeyValsJSON();
		$gpt = "var gptAdSlots = [];\n";
		$gpt .= "var dfpKeyVals = $dfpKeyVals;\n";
		$gpt .= "var googletag = googletag || {};\n";
		$gpt .= "googletag.cmd = googletag.cmd || [];\n";
		$gpt .= "var gptRequested = false;\n";
		$gpt .= "function defineGPTSlots() {\n";

		// define all the slots up front
		foreach ( $this->mAds as $type => $ad ) {
			if ( $ad->service != 'dfp' ) {
				continue;
			}
			$adUnitPath = $this->mDFPData[$ad->mType]['adUnitPath'];
			$adSize = $this->mDFPData[$ad->mType]['size'];
			$adId = $ad->targetId;
			$gpt .= "gptAdSlots['$adId'] = googletag.defineSlot('$adUnitPath', $adSize, '$adId').addService(googletag.pubads());\n";
		}

		$gpt .= "googletag.pubads().enableSingleRequest();\n";
		$gpt .= "googletag.pubads().disableInitialLoad();\n";
		$gpt .= "googletag.pubads().collapseEmptyDivs();\n";
		$gpt .= "googletag.enableServices();\n";
		$gpt .= "}\n";
		$result = Html::inlineScript( $gpt );
		return $result;
	}
}

class MainPageAdCreator extends MixedAdCreator {
	public function __construct() {
		$this->mAdsenseSlots = array(
			'rightrail0' => 6166713376,
		);
		$this->mAdServices = array(
			'rightrail0' => 'adsense',
		);
	}

	public function setupAdHtml() {
		// this ad setup only has a single right rail ad
		$this->mAds['rightrail0'] = $this->getRightRailAd( 0 );
	}

	/*
	 * @return Ad an ad for the first right rail
	 */
	protected function getRightRailFirstAdsense() {
		$ad = $this->getNewAd( 'rightrail0' );
		$ad->service = "adsense";
		$ad->targetId = $ad->mType;
		$ad->containerHeight = 600;
		$ad->initialLoad = true;
		$ad->lateLoad = false;
		$ad->width = 300;
		$ad->height = 600;
		$ad->mHtml = $this->getRightRailAdHtml( $ad );
		return $ad;
	}
}

class CategoryPageAdCreator extends MixedAdCreator {
	public function __construct() {
		$this->mAdsenseSlots = array(
			'rightrail0' => 7643446578,
		);
		$this->mAdServices = array(
			'rightrail0' => 'adsense',
		);
	}

	public function setupAdHtml() {
		// this ad setup only has a single right rail ad
		$this->mAds['rightrail0'] = $this->getRightRailAd( 0 );
	}

	/*
	 * @return Ad an ad for the first right rail
	 */
	protected function getRightRailFirstAdsense() {
		$ad = $this->getNewAd( 'rightrail0' );
		$ad->service = "adsense";
		$ad->targetId = $ad->mType;
		$ad->containerHeight = 600;
		$ad->initialLoad = true;
		$ad->lateLoad = false;
		$ad->width = 300;
		$ad->height = 600;
		$ad->mHtml = $this->getRightRailAdHtml( $ad );
		return $ad;
	}
}

class MixedAdCreatorVersion1 extends MixedAdCreator {
	public function __construct() {
		$this->mAdsenseSlots = array(
			'intro' => 7862589374,
			'rightrail0' => 9646625139,
		);
		$this->mAdServices = array(
			'intro' => 'adsense',
			'rightrail0' => 'adsense',
			'rightrail1' => 'dfp',
			'rightrail2' => 'dfp'
		);
	}

	/*
	 * required by any dfp classes to set the ad unit paths
	 */
	protected function setDFPAdUnitPaths() {
		$this->mDFPData = array(
			'rightrail1' => array(
				'adUnitPath' => '/10095428/RR2_AdX',
				'size' => '[300, 600]'
			),
			'rightrail2' => array(
				'adUnitPath' => '/10095428/RR3_AdX',
				'size' => '[300, 600]'
			),
			'quiz' => array(
				'adUnitPath' => '/10095428/AllPages_Quiz_English_Desktop',
				'size' => '[728, 90]'
			),
		);
	}

	/*
	 * @param Ad
	 * @return string or int channels to be used when creating adsense ad
	 */
	protected function getAdClient( $ad ) {
		if ($ad->mType == 'intro' ) {
			return 'ca-pub-9543332082073187';
		}
		return 'ca-pub-5462137703643346';
	}
}
class MixedAdCreatorVersion2 extends MixedAdCreator {
	public function __construct() {
		$this->mAdsenseSlots = array(
			'intro' => 7862589374,
			'step' => 1652132604,
			'method' => 6521315906,
		);
		$this->mAdServices = array(
			'intro' => 'adsense',
			'step' => 'adsense',
			'method' => 'adsense',
			'rightrail0' => 'dfp',
		);
	}
	/*
	 * required by any dfp classes to set the ad unit paths
	 */
	protected function setDFPAdUnitPaths() {
		$this->mDFPData = array(
			'rightrail0' => array(
				'adUnitPath' => '/10095428/Multi_Sized_RR_Unit',
				'size' => '[[300, 250],[300, 600]]'
			),
			'quiz' => array(
				'adUnitPath' => '/10095428/AllPages_Quiz_English_Desktop',
				'size' => '[728, 90]'
			),
		);
	}

	/*
	 * creates a right rail ad based on the right rail position for this ad implementation
	 * @param Integer the right rail number or position on the page usually 0 1 or 2
	 * @return Ad an ad for the right rail but no html
	 */
	public function getRightRailAd( $num ) {
		$type = "rightrail".$num;
		$ad = $this->getNewAd( $type );
		if ( $num == 0 ) {
			$ad = $this->getRightRailFirst();
			// now ad the js snippet to add the ad to the js sroll handler
			$ad->mHtml .= Html::inlineScript( "WH.desktopAds.addRightRailAd('{$ad->mType}')" );
		}

		return $ad;
	}

	public function getIsLastAd( $ad ) {
		if ( $ad->mType == "rightrail0" ) {
			return true;
		}
		return false;
	}

	public function getRefreshable( $ad ) {
		if ( $ad->service == 'dfp' && strstr( $ad->mType, "rightrail0") && $this->mRefreshableRightRail ) {
			return true;
		}
		return false;
	}
	public function getRenderRefresh( $ad ) {
		return false;
	}

	public function getViewableRefresh( $ad ) {
		return true;
	}
}

class AlternateDomainAdCreator extends MixedAdCreatorVersion2 {
	public function __construct() {
		global $domainName;
		if ( strstr( $domainName, "howyougetfit.com" ) ) {
			$this->mAdsenseSlots = array(
				'intro' => 2258884570,
			);
		} else if ( strstr( $domainName, "wikihow.tech" ) ) {
			$this->mAdsenseSlots = array(
				'intro' => 8305418177,
			);
		} else if ( strstr( $domainName, "wikihow.pet" ) ) {
			$this->mAdsenseSlots = array(
				'intro' => 3009706573,
			);
		} else if ( strstr( $domainName, "howyoulivelife.com" ) ) {
			$this->mAdsenseSlots = array(
				'intro' => 4845456904,
			);
		} else if ( strstr( $domainName, "wikihow.life" ) ) {
			$this->mAdsenseSlots = array(
				'intro' => 3917364520,
			);
		} else if ( strstr( $domainName, "wikihow.fitness" ) ) {
			$this->mAdsenseSlots = array(
				'intro' => 1291201186,
			);
		} else if ( strstr( $domainName, "wikihow.mom" ) ) {
			$this->mAdsenseSlots = array(
				'intro' => 1099629495,
			);
		}
		$this->mAdServices = array(
			'intro' => 'adsense',
			'rightrail0' => 'dfp',
		);
	}

	protected function setDFPAdUnitPaths() {
		global $domainName;
		if ( strstr( $domainName, "howyougetfit.com" ) ) {
            $adUnitPath = 'AllPages_RR_1_HowYouGetFit_Desktop_All';
		} else if ( strstr( $domainName, "wikihow.tech" ) ) {
            $adUnitPath = 'AllPages_RR_1_WikiHowTech_Desktop_All';
		} else if ( strstr( $domainName, "wikihow.pet" ) ) {
            $adUnitPath = 'AllPages_RR_1_WikiHowPet_Desktop_All';
		} else if ( strstr( $domainName, "howyoulivelife.com" ) ) {
			$adUnitPath = 'AllPages_RR_1_HowYouLifeLife_Desktop_All';
		} else if ( strstr( $domainName, "wikihow.life" ) ) {
			$adUnitPath = 'AllPages_RR_1_wikiHowLife_Desktop_All';
		} else if ( strstr( $domainName, "wikihow.fitness" ) ) {
			$adUnitPath = 'AllPages_RR_1_wikiHowFit_Desktop_All';
		} else if ( strstr( $domainName, "wikihow.mom" ) ) {
			$adUnitPath = 'AllPages_RR_1_wikiHowMom_Desktop_All';
		}
		$this->mDFPData = array(
			'rightrail0' => array(
				'adUnitPath' => '/10095428/' . $adUnitPath,
				'size' => '[[300, 250], [300, 600]]'
			),
		);
	}
	public function getQuizAd( $num ) {
		return "";
	}
}

class DocViewerAdCreator extends MixedAdCreator {

	public function __construct() {
		$this->mAdsenseSlots = array(
			'docviewer1' => 4591712179,
		);
	}


	/*
	 * @param Ad
	 * @return string or int channels to be used when creating adsense ad
	 */
	protected function getAdClient( $ad ) {
		return 'ca-pub-9543332082073187';
	}

	protected function setDFPAdUnitPaths() {
		$this->mDFPData = array(
			'docviewer0' => array(
				'adUnitPath' => '/10095428/Image_Ad_Sample_Page',
				'size' => '[[300, 250], [300, 600]]'
			)
		);
	}

	protected function getAdsenseChannels( $ad ) {
		return "";
	}

	/*
	 * gets the ad data for all ads on the page
	 */
	public function setupAdHtml() {
		$this->setDFPAdUnitPaths();
		// after the first right rail ad, we append the initial refresh all to DFP ads
		for ( $i = 0; $i < 2; $i++ ) {
			$this->mAds['docviewer'.$i] = $this->getDocViewerAd( $i );
		}
		$this->mAds['docviewer0']->mHtml .= $this->getInitialRefreshSnippet();
	}

	public function getDocViewerAd( $num ) {
		$type = "docviewer".$num;
		$ad = $this->getNewAd( $type );
		if ( $num == 0 ) {
			$ad->service = "dfp";
			$ad->targetId = 'div-gpt-ad-1354818302611-0';
			$ad->containerHeight = 600;
			$ad->initialLoad = true;
			if ( $this->mShowRightRailLabel == false ) {
				$ad->mLabel = "";
			}
			$ad->mHtml = $this->getRightRailAdHtml( $ad );
            $ad->mHtml .= Html::inlineScript( "WH.desktopAds.addRightRailAd('{$ad->mType}')" );
		} else if ( $num == 1 ) {
			$ad->service = "adsense";
			$ad->targetId = $ad->mType;
			$ad->initialLoad = true;
			$ad->adClass = "docviewad";
			$ad->width = 728;
			$ad->height = 90;
			$ad->mHtml = $this->getBodyAdHtml( $ad );
		}
		return $ad;
	}

	public function getRefreshable( $ad ) {
		if ( $ad->service == 'dfp' && strstr( $ad->mType, "docviewer0" ) && $this->mRefreshableRightRail ) {
			return true;
		}
		return false;
	}
	public function getRenderRefresh( $ad ) {
		return false;
	}

	public function getViewableRefresh( $ad ) {
		return true;
	}
}
class InternationalAdCreator extends MixedAdCreatorVersion2 {
	public function __construct() {
		$this->mAdsenseSlots = array(
			'intro' => 2583804979,
		);
		$this->mAdServices = array(
			'intro' => 'adsense',
			'rightrail0' => 'dfp',
		);
	}

	protected function getAdsenseChannels( $ad ) {
		return "";
	}
	protected function setDFPAdUnitPaths() {
		$this->mDFPData = array(
			'rightrail0' => array(
				'adUnitPath' => '/10095428/AllPages_RR_1_Intl_Desktop_All_Refresh',
				'size' => '[[300, 250], [300, 600]]'
			),
		);
	}

	public function getQuizAd( $num ) {
		return $this->getNewAd( "quiz".$num );
	}

}
