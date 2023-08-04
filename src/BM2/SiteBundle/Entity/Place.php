<?php 

namespace BM2\SiteBundle\Entity;

use BM2\SiteBundle\Entity\Association;
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

        public function containsAssociation(Association $assoc) {
                foreach ($this->getAssociations() as $ap) {
                        # Cycle through AssociationPlace intermediary objects.
                        if ($ap->getAssociation() === $assoc) {
                                return true;
                        }
                }
                return false;
        }

        public function isOwner(Character $char) {
                $type = $this->getType()->getName();
                if ($type == 'capital') {
                        if (
        			(!$this->getRealm() && $this->getOwner() === $char) ||
        			($this->getRealm() && $this->getRealm()->findRulers()->contains($char))
        		) {
                                return true;
                        }
                } elseif ($type == 'embassy') {
                        if (
                                $this->getAmbassador() === $char ||
        			(!$this->getAmbassador() && $this->getOwningRealm() && $this->getOwningRealm()->findRulers()->contains($char)) ||
        			(!$this->getAmbassador() && !$this->getOwningRealm() && $this->getHostingRealm() && $this->getHostingRealm()->findRulers()->contains($char)) ||
        			(!$this->getAmbassador() && !$this->getOwningRealm() && !$this->getHostingRealm() && $this->getOwner() == $char)
                        ) {
                                return true;
                        }
                } elseif ($this->getOwner() === $char) {
                        return true;
                } elseif (!$this->getOwner() && ($this->getGeoData()->getSettlement()->getOwner() === $char || $this->getGeoData()->getSettlement()->getSteward() === $char))
                return false;
        }

}
