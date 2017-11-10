<?
if ($q == null):
	return;
endif;
?>
<script type="text/javascript">
	$(window).load(function() {
		if ($('.search').is(':visible') && !$('#search_oversearch').is(':visible')) {
			$('.search').click();
		}
	});
</script>
<? if (count($results) == 0): ?>
	<h2 class='sr_title'><?= wfMsgForContent('lsearch_no_results_for', $enc_q) ?></h2>
<? endif; ?>
<? if ($suggestionLink): ?>
	<div class="sr_suggest"><?= wfMsg('lsearch_suggestion', $suggestionLink) ?></div>
<? endif; ?>
<? if (count($results) == 0): ?>
	<div class="sr_noresults"><?= wfMsg('lsearch_desktop_noresults', $enc_q) ?></div>
	<div id='searchresults_footer'><br /></div>
	<? return; ?>
<? endif; ?>

<div id='searchresults_list' class='wh_block'>
	<div id="search_adcontainer1"></div>
	<?
	$noImgCount = 0;
	foreach($results as $i => $result):

		if (empty($result['img_thumb_100'])) {
			$result['img_thumb_100'] = $noImgCount ++ % 2 == 0 ?
				$no_img_green : $no_img_blue;
		}
		if (!(class_exists('AndroidHelper') && AndroidHelper::isAndroidRequest() && $result['is_category'])):
	?>
		<? $url = $result['url']; ?>
		<?
			if (!preg_match('@^http:@', $url)) {
				$url = $BASE_URL . '/' . $url;
			}
		?>
		<a href=<?= $url ?> >
			<div class="result">
				<? if (!$result['is_category']): ?>
					<div class='result_thumb'>
					<? if (!empty($result['img_thumb_100'])): ?>
						<img src="<?= $result['img_thumb_100'] ?>" />
					<? endif; ?>
					</div>
				<? else: ?>
					<div class='result_thumb cat_thumb'><img src="<?= $result['img_thumb_100'] ? $result['img_thumb_100'] : $noImg ?>" /></div>
				<? endif; ?>

				<div class="result_data">
				<? if ($result['has_supplement']): ?>
					<? if (!$result['is_category']): ?>
						<div class="result_link"><?= $result['title_match'] ?></div>
					<? else: ?>
						<div class="result_link"><?= wfMsg('lsearch_article_category', $result['title_match']) ?></div>
					<? endif; ?>
					<div class="result_data_divider"></div>
					<ul class="search_results_stats">
						<li class="sr_view"><span class="sp_circle sp_views_icon"></span>
							<?=wfMessage('lsearch_views', number_format($result['popularity']))->text();?>
						</li>
						<li class="sr_updated"><span class="sp_circle sp_updated_icon"></span>
							<?=wfTimeAgo(wfTimestamp(TS_UNIX, $result['timestamp']), true);?>
						</li>
						<? if ($result['verified']): ?>
							<li class="sp_verif">
								<span class="sp_circle sp_verif_icon"></span>
								<span class="sp_search_verified"><?= SocialProofStats::getIntroMessage($result['verified']) ?></span>
							</li>
						<? endif ?>
					</ul>
				<? else: ?>
					<p class="result_link"><?= $result['title_match'] ?></p>
				<? endif; // has_supplement ?>
				<? // Sherlock-form ?>
				<?= EasyTemplate::html('sherlock-form', array("index" => $i + $first, "result" => $result)); ?>
				</div>
			</div>
		</a>
	<?
		endif;
	endforeach;
	?>
	<div id="search_adcontainer3"></div>
	<?=$ads;?>
</div>

<?
if (($total > $start + $max_results
		&& $last == $start + $max_results)
	|| $start >= $max_results):
		$resultsMsg = class_exists('AndroidHelper') && AndroidHelper::isAndroidRequest() ? 'lsearch_results_range_android' : 'lsearch_results_range';
?>

	<div id='searchresults_footer'>
		<?=$next_button.$prev_button?>
		<div class="sr_foot_results"><?= wfMsg($resultsMsg, $first, $last, number_format($total)) ?></div>
		<div class="sr_text"><?= wfMsg('lsearch_mediawiki', $specialPageURL . "?search=" . urlencode($q)) ?></div>
	</div>

<? endif; ?>