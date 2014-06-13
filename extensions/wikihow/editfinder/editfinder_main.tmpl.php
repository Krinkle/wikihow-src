<?=$css?>
<?php
	$langKeys = array('stub','copyedit','format','app-name');
	echo Wikihow_i18n::genJSMsgs($langKeys);
?>
<div class="tool">
<div id="editfinder_head" class="tool_header">
	<p id="editfinder_help" class="tool_help"><a href="/<?=$helparticle?>" target="_blank">Learn how</a></p>
	<a href="#" id="edit_keys">Get Shortcuts</a>
	<div id="editfinder_options">
		<a href="#" id="editfinder_skip" class="button secondary"><?=$nope?></a>
		<a href="#" class="button primary" id="editfinder_yes"><?=$yep?></a>
	</div>
	<h1><?=$question?></h1>
	<div id="editfinder_cat_header"><b><?=$uc_categories?>:</b> <span id="user_cats"></span> (<a href="" class="editfinder_choose_cats">change</a>)</div>
</div>
<div id='editfinder_spinner'><img src='/extensions/wikihow/rotate.gif' alt='' /></div>
<div id='editfinder_preview_updated'></div>
<div id='editfinder_preview'></div>
<div id='article_contents'></div>
<div id="editfinder_cat_footer">
	Not finding an article you like?  <a href="" class="editfinder_choose_cats">Choose <?=$lc_categories?></a>
</div>
</div>
<div id="edit_info" style="display:none;">
	<?= wfMessage('editfinder_keys')->text(); ?>
</div>
