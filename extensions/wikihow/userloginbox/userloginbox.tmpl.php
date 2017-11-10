<div name="userlogin" class="userlogin">

	<h3><?= $hdr_txt ?></h3>
	<div id='social-login-navbar' data-return-to='<?= htmlspecialchars($return_to) ?>'>
		<div id="fb_connect<?=$suffix?>"><a id="fb_login<?=$suffix?>" href="#" role="button" class="ulb_button" aria-label="<?=wfMessage('aria_facebook_login')->showIfExists()?>"><span></span>Facebook</a></div>
		<div id="gplus_connect<?=$suffix?>"><a id="gplus_login<?=$suffix?>" href="#" role="button" class="ulb_button"  aria-label="<?=wfMessage('aria_google_login')->showIfExists()?>"><span></span>Google</a></div>
		<?php if (CivicLogin::isEnabled()): ?>
			<div id="civic_connect<?=$suffix?>"><a id="civic_login<?=$suffix?>" href="#" role="button" class="ulb_button"  aria-label="<?=wfMessage('aria_civic_login')->showIfExists()?>"><span></span>Civic</a></div>
		<?php endif ?>
	</div>

	<div>
		<a href="/Special:UserLogin?type=<?=$btn_link_type?>&returnto=<?=$return_to?>" role="button" id="wh_login<?=$suffix?>" class="ulb_button <?=$is_login?>" aria-label="<?= $hdr_txt ?>"><span></span><?=$wh_txt?></a>
	</div>

	<div class="userlogin_links">
		<?= $bottom_txt_1 ?>
		<a href="/Special:UserLogin?type=<?=$bottom_link_type . $from_http ?>"><?= $bottom_txt_2 ?></a>
	</div>
</div>
