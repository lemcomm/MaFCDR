<?php 

namespace BM2\SiteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

class Place {

        public function isFortified() {
                if ($this->isDefended()) {
                        return true;
                } else {
                        return false;
                }
        }

	public function isDefended() {
		if ($this->countDefenders()>0) {
                        return true;
                } else {
                        return false;
                }
	}

	public function countDefenders() {
		$defenders = 0;
		foreach ($this->findDefenders() as $char) {
			$defenders += $char->getActiveSoldiers()->count();
		}
                foreach ($this->getUnits() as $unit) {
                        $defenders += $unit->getActiveSoldiers()->count();
                }
		return $defenders;
	}

	public function findDefenders() {
		// anyone with a "defend place" action who is nearby
		$defenders = new ArrayCollection;
		foreach ($this->getRelatedActions() as $act) {
			if ($act->getType()=='place.defend') {
				$defenders->add($act->getCharacter());
			}
		}
		return $defenders;
	}

}
