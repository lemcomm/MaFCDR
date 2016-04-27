<?php 

namespace BM2\SiteBundle\Entity;

class EventLog {


	public function getType() {
		if ($this->settlement) return 'settlement';
		if ($this->realm) return 'realm';
		if ($this->character) return 'character';
		if ($this->quest) return 'quest';
		if ($this->artifact) return 'artifact';
		return false;
	}

	public function getSubject() {
		if ($this->settlement) return $this->settlement;
		if ($this->realm) return $this->realm;
		if ($this->character) return $this->character;
		if ($this->quest) return $this->quest;
		if ($this->artifact) return $this->artifact;
		return false;		
	}

	public function getName() {
		if ($this->settlement) return $this->settlement->getName();
		if ($this->realm) return $this->realm->getName();
		if ($this->character) return $this->character->getName();
		if ($this->quest) return $this->quest->getSummary();
		if ($this->artifact) return $this->artifact->getName();
		return false;
	}

}
