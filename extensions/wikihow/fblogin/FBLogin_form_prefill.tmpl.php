<div id='fbc'>
	<div id='fbc_header'>
		<img id='fbc_icon' src ='<?= $fbicon ?>' style='width:auto' />
		<div class='fbc_header_text' id='fbc_header_default'><?= $isApiSignup ? wfMsg('sl_header_update_details') : wfMsg('fbc_the_registration_form_has_been_prefilled') ?></div>
	</div>
	<form method='POST' id='fbc_form' action='<?= $formUrl ?>'>
		<input type='hidden' name='action' value='updateDetails'/>
		<input type='hidden' name='token' value='<?= $token ?>'/>
		<input type='hidden' name='returnTo' value='<?= $returnTo ?>'/>
		<input type='hidden' name='isSignup' value='<?= $isSignup ?>'/>
		<input name='proposed_username' class='fbc_readonly fbc_username' type='hidden' value='<?= $username ?>'/>
<? if($isMobile): ?>
		<div id='fbc_error'><?= $error ?></div>

		<label class='fbc_label first'><?= wfMsg('fbc_wikihow_username') ?></label>
		<div id='fbc_faux_username' class='fbc_readonly'>
			<span id='fbc_x'></span>
			<img class='fbc_user_avatar' src='<?= $picture ?>' style='width:auto'>
			<div id='fbc_user_text'>
				<div id='fbc_user_text_username'><?= $username ?></div>
				<div id='fbc_user_text_info'><?= $affiliations ?> <?= $numFriends ?> <?= wfMsg('fbc_friends') ?></div>
			</div>
		</div>
		<input class='input_med fbc_hidden' type='text' name='requested_username' id='fbc_requested_username'/>

		<label class='fbc_label'><?= wfMsg('fbc_email_address') ?></label>
		<input class='input_med fbc_readonly' name='email' type='text' value='<?= $email ?>' readonly='readonly'/>

		<input type='submit' id='fbc_submit' class='button primary' value='<?= $isApiSignup ? wfMsg('sl_submit_save_details') : wfMsg('sl_submit_register') ?>'/>
<? else: ?>
		<table>
			<tr><td colspan='2'><div id='fbc_error'><?= $error ?></div></td></tr>
			<tr>
				<td class='fbc_label'><?= wfMsg('fbc_wikihow_username') ?></td>
				<td>
					<div id='fbc_faux_username' class='fbc_readonly'>
						<span id='fbc_x'></span>
						<img class='fbc_user_avatar' src='<?= $picture ?>' style='width:auto'>
						<div id='fbc_user_text'>
							<div id='fbc_user_text_username'><?= $username ?></div>
							<div id='fbc_user_text_info'><?= $affiliations ?> <?= $numFriends ?> <?= wfMsg('fbc_friends') ?></div>
						</div>
					</div>
					<input class='input_med fbc_hidden' type='text' name='requested_username' id='fbc_requested_username'/>
				</td>
			<tr>
				<td class='fbc_label'><?= wfMsg('fbc_email_address') ?></td>
				<td><input class='input_med fbc_readonly' name='email' type='text' value='<?= $email ?>' readonly='readonly'/></td>
			</tr>
			<tr>
				<td></td>
				<td><input type='submit' id='fbc_submit' class='button primary' value='<?= $isApiSignup ? wfMsg('sl_submit_save_details') : wfMsg('sl_submit_register') ?>'/></td>
			</tr>
		</table>
<? endif; ?>
	</form>
<? if(!$isApiSignup): ?>
	<div id='fbc_footer'><?= wfMsg('fbc_clicking_register_will_give_wikihow_access') ?>
	<a href='http://www.facebook.com/about/login/' class='fbc_link'><?= wfMsg('fbc_learn_more') ?>.</a></div>
<? endif; ?>
</div>
