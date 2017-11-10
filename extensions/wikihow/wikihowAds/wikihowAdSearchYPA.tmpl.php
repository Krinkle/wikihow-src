<script type='text/javascript'>
	var initAds = $.getScript('https://s.yimg.com/uv/dm/scripts/syndication.js', function() {
		var adOptions = {
			DeskTop: {
				SiteLink: false,
				EnhancedSiteLink: false
			},
			Mobile: {
				SiteLink: false,
				EnhancedSiteLink: false
			}
		};
		var templateOptions = {
			DeskTop : {
				AdUnit : {
					borderColor : "#d2d8cd",
					lineSpacing : 20, //﴾valid values 8-25﴾adSpacing : 15,
					adSpacing: 5, // valid values 5-15
					font : "Helvetica,arial,sans-serif",
					urlAboveDescription: true,
					color: "#545"
				},
				Title : {
					fontsize : 16, //﴾ valid value 8-18 ﴾, color : "#ABABAB",
					underline : false,
					bold: true,
					color: '#363',
					onHover : {
						underline : true
					}
				},
				Description : {
					color: "#545"
				},
				URL : {
					color: '#363',
					onHover : {
						underline : true
					}
				},
				LocalAds: {
					color: "#363",
					onHover : {
						underline : true
					}
				},
				SmartAnnotations : {
					color: "#545454",
				},
				MerchantRating: {
					color: "#363",
					onHover : {
						underline : true
					}
				}
			},
			Mobile : {
				AdUnit : {
					borderColor : "#d2d8cd",
					lineSpacing : 20, //﴾valid values 8-25﴾adSpacing : 15,
					adSpacing: 5, // valid values 5-15
					font : "Helvetica,arial,sans-serif",
					urlAboveDescription: true,
					color: "#545"
				},
				Title : {
					fontsize : 16, //﴾ valid value 8-18 ﴾, color : "#ABABAB",
					underline : false,
					bold: true,
					color: '#363',
					onHover : {
						underline : true
					}
				},
				Description : {
					color: "#545"
				},
				URL : {
					color: '#363',
					onHover : {
						underline : true
					}
				},
				LocalAds: {
					color: "#363",
					onHover : {
						underline : true
					}
				},
				SmartAnnotations : {
					color: "#545454",
				},
				MerchantRating: {
					color: "#363",
					onHover : {
						underline : true
					}
				}
			}
		};

		var adOptions2 = $.extend(true, {}, adOptions);
		var templateOptions2 = $.extend(true, {}, templateOptions);

		var slotIdPrefix = '<?=$slotIdPrefix?>';
		var adConfig = '<?=$adConfig;?>';
		var adTagType =  '<?=$adTypeTag;?>';

		window.ypaAds.insertMultiAd({
			ypaAdConfig   : adConfig,
			ypaAdTypeTag  : adTagType,
			ypaPubParams : {
				query: mw.util.getParamValue('search'),
			},
			ypaAdSlotInfo : [
				{
					EnhancedSiteLink: false,
					SiteLink: false,
					ypaAdSlotId : slotIdPrefix + 'WH_Top_Center',
					ypaAdDivId  : 'search_adcontainer1',
					ypaAdWidth  : '722',
					ypaAdHeight : '312',
					ypaSlotOptions : {
						AdOptions: adOptions,
						TemplateOptions : templateOptions
					},
				},
				{
					EnhancedSiteLink: false,
					SiteLink: false,
					ypaAdSlotId : slotIdPrefix + 'WH_Mid_Center',
					ypaAdDivId  : 'search_adcontainer3',
					ypaAdWidth  : '722',
					ypaAdHeight : '312',
					ypaSlotOptions : {
						AdOptions: adOptions2,
						TemplateOptions : templateOptions2
					}
				}
			]
		});
	});
</script>