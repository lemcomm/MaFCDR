<?php 

namespace Calitarus\MessagingBundle\Entity;

class MessageMetadata {

	public function hasFlag(Flag $right) {
		return ($this->flags->contains($right));
	}

	public function hasFlagByName($name) {
		return $this->flags->exists(function($key, $element) use ($name) { return $element->getName() == $name; } );
	}

}
