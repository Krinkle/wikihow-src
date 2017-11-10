<?php

namespace EditMapper;

use User;

/**
 * Map article edits made by Content Portal editors to 'WRM', 'Seymour Edits', etc
 */
class PortalEditMapper extends EditMapper {

	/**
	 * True if the user has the "Editor" role in Content Portal
	 */
	public function shouldMapEdit(bool $isNewArticle, User $user): bool {
		return $user->hasGroup('editor_team') && !$user->hasGroup('staff');
	}

	public function getDestUser(bool $isNewArticle) {
		$destUsername = $isNewArticle ? 'WRM' : 'Seymour Edits';
		return User::newFromName($destUsername);
	}

}
