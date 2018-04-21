<?php 

namespace Calitarus\MessagingBundle\Entity;


class User {

	public function getName() {
		return $this->app_user->getName();
	}


	public function countNewMessages() {
		$new = 0;
		foreach ($this->getConversationsMetadata() as $meta) {
			$new += $meta->getUnread();
		}
		return $new;
	}


	public function hasNewMessages() {
		foreach ($this->getConversationsMetadata() as $meta) {
			if ($meta->getUnread() > 0 ) {
				return true;
			}
		}
		return false;
	}

}
