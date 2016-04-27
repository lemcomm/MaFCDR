<?php 

namespace BM2\SiteBundle\Entity;

class Achievement {

	public function __toString() {
		return "achievement {$this->key} ({$this->value})";
	}

	public function getValue() {
		switch ($this->getType()) {
			case 'battlesize':	return floor(sqrt($this->value));
			default:
				return $this->value;
		}
	}
}
