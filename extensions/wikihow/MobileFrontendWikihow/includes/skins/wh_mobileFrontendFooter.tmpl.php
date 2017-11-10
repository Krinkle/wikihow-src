<? if ($showOptimizely): ?>
	<?php print OptimizelyPageSelector::getOptimizelyTag() ?>
<? endif; ?>
<? if ($showInternetOrgAnalytics): ?>
	<?= WikihowMobileTools::getInternetOrgAnalytics() ?>
<? endif; ?>
