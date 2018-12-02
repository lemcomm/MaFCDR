<?php 

namespace BM2\SiteBundle\Entity;

class UnitSettings {

	public function getForType() {
		# Check whether this is for a character or for a unit (because characters are quasi units)
		if ($this->character) {
			return 'character';
		} else {
			return 'unit';
		}
	}

	public function getSoldiers() {
		# Since we'll probably do this a lot, lets just save ourselves some code and declare this logic here.
		# Return soldiers of the character, if this is for a character, or for the unit, if for the unit.
		if ($this->character) {
			return $this->character->getSoldiers();
		} else {
			return null;
			#TODO: return $this->unit->getSoldiers();
		}
	}

}
