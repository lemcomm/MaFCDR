<?php 

namespace Calitarus\MessagingBundle\Entity;

class ConversationMetadata {

	public function hasRight(Right $right) {
		if ($this->rights->contains($right)) return true;

		return $this->hasRightByName('owner');
	}

	public function hasRightByName($name) {
		return $this->rights->exists(function($key, $element) use ($name) { return $element->getName() == $name || $element->getName() == 'owner'; } );
	}

}
