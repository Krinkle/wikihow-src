<div id="spch-container" class="tool">
	<div id="spch-head" class="tool_header">
        <div id="spch-prompt">
		<div id="spch-options">
            <a href="#" class='button secondary' id="spch-no"><?= wfMsg("spch-no"); ?></a>
            <a href="#" class='button secondary' id="spch-skip"><?= wfMsg("spch-skip"); ?></a>
			<a href="#" class="button primary spch-button-yes" id="spch-yes"><?= wfMsg('spch-yes'); ?></a>
			<a href="#" id="spch-qe"><?= wfMsg("spch-qe"); ?></a>
		</div>
		<div id="spch-edit-buttons">
			<a href="#" class='button primary' id="spch-next"><?= wfMsg("spch-next"); ?></a>
			<a href="#" class='button secondary' id="spch-cancel"><?= wfMsg("spch-cancel"); ?></a>
		</div>
		<h1><?= wfMsg('spch-question'); ?></h1>
		</div>
		<div id="spch-snippet" class="clearall">
			<?=wfMessage('spch-loading-next')->text();?>
        </div>
	</div>
	<div id='spch-preview'></div>
	<div id='spch-id'></div>
	<div class='spch-waiting'><img src='<?= wfGetPad('/extensions/wikihow/rotate.gif') ?>' alt='' /></div>
</div>
