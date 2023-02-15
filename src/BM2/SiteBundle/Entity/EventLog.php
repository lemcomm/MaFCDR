<?php

namespace BM2\SiteBundle\Entity;

class EventLog {


	public function getType() {
		if ($this->settlement) return 'settlement';
		if ($this->realm) return 'realm';
		if ($this->character) return 'character';
		if ($this->unit) return 'unit';
		if ($this->place) return 'place';
		if ($this->house) return 'house';
		if ($this->quest) return 'quest';
		if ($this->artifact) return 'artifact';
		if ($this->association) return 'association';
		return false;
	}

	public function getSubject() {
		if ($this->settlement) return $this->settlement;
		if ($this->realm) return $this->realm;
		if ($this->character) return $this->character;
		if ($this->unit) return $this->unit;
		if ($this->place) return $this->place;
		if ($this->house) return $this->house;
		if ($this->quest) return $this->quest;
		if ($this->artifact) return $this->artifact;
		if ($this->association) return $this->association;
		return false;
	}

	public function getName() {
		if ($this->settlement) return $this->settlement->getName();
		if ($this->realm) return $this->realm->getName();
		if ($this->character) return $this->character->getName();
		if ($this->unit) return $this->unit->getSettings()->getName();
		if ($this->place) return $this->place->getName();
		if ($this->house) return $this->house->getName();
		if ($this->quest) return $this->quest->getSummary();
		if ($this->artifact) return $this->artifact->getName();
		if ($this->association) return $this->association->getName();
		return false;
	}

}
