<?php 

namespace Calitarus\MessagingBundle\Entity;

class Conversation {

	public function findMeta(User $user) {
		return $this->getMetadata()->filter(
			function($entry) use ($user) {
				return ($entry->getUser() == $user);
			}
		)->first();
	}

}
