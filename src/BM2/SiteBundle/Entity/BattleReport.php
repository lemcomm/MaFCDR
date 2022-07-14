<?php 

namespace BM2\SiteBundle\Entity;

class BattleReport {

	public function getName() {
		return "battle"; // TODO: something better? this is used for links
	}

	public function checkForObserver(Character $char) {
		foreach ($this->observers as $each) {
			if ($each->getCharacter() === $char) {
				return true;
			}
		}
		return false;
	}

	public function countPublicJournals() {
		$i = 0;
		foreach ($this->journals as $each) {
			if ($each->getPublic()) {
				$i++;
			}
		}
		return $i;
	}

}
