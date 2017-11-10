// Load GPT asynchronously
function setDFPTargeting(data) {
    for (var key in data) {
        googletag.pubads().setTargeting(key, data[key]);
    }
}
var googletag = googletag || {};
googletag.cmd = googletag.cmd || [];
(function() {
	var gads = document.createElement('script');
	gads.async = true;
	gads.type = 'text/javascript';
	var useSSL = 'https:' == document.location.protocol;
	gads.src = (useSSL ? 'https:' : 'http:') +
	'//www.googletagservices.com/tag/js/gpt.js';
var node = document.getElementsByTagName('script')[0];
node.parentNode.insertBefore(gads, node);
})();
gptRequested = true;
googletag.cmd.push(function() {
	defineGPTSlots();
	googletag.pubads().addEventListener('slotRenderEnded', function(event) {
		if (WH.desktopAds) {
			WH.desktopAds.slotRendered(event.slot, event.size);
		}
	});
	googletag.pubads().addEventListener('impressionViewable', function(event) {
		if (WH.desktopAds) {
			WH.desktopAds.impressionViewable(event.slot);
		}

	});
    setDFPTargeting(dfpKeyVals);
});
