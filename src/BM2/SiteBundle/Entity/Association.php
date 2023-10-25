<?php

namespace BM2\SiteBundle\Entity;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Faction;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\ArrayCollection;

class Association extends Faction {

        public function findAllMemberCharacters($include_myself=true) {
                $all_chars = new ArrayCollection;
                $all_infs = $this->findAllInferiors($include_myself);
                foreach ($all_infs as $inf) {
                        foreach ($inf->getMembers() as $infMember) {
                                $all_chars->add($infMember->getCharacter());
                        }
                }
                return $all_chars;
        }

        public function findAllMembers($include_myself=true) {
                $all_members = new ArrayCollection;
                $all_infs = $this->findAllInferiors($include_myself);
                foreach ($all_infs as $inf) {
                        foreach ($inf->getMembers() as $infMember) {
                                $all_members->add($infMember);
                        }
                }
                return $all_members;
        }

	public function findActiveMembers($with_subs = true, $forceupdate = false) {
                $all_members = new ArrayCollection;
                $all_infs = $this->findAllInferiors(true);
                foreach ($all_infs as $inf) {
                        foreach ($inf->getMembers() as $infMember) {
				if ($infMember->isActive()) {
                                	$all_members->add($infMember);
				}
                        }
                }
                return $all_members;
	}

	public function findMember(Character $char, $all = false) {
		if ($all) {
			$all = $this->findAllMembers(true);
		} else {
			$all = $this->getMembers();
		}
		foreach ($all as $mbr) {
			if ($mbr->getCharacter() === $char) {
				return $mbr;
			}
		}
		return false;
	}

	public function isPublic() {
		$law = $this->findActiveLaw('assocVisibility', false);
		if ($law->getValue() === 'yes') {
			return true;
		} else {
			return false;
		}
	}

	public function findPubliclyVisibleRanks() {
		if ($this->isPublic() && $this->findActiveLaw('rankVisibility', false)->getValue() === 'all') {
			$all = $this->ranks;
		} else {
			$all = new ArrayCollection();
		}
		return $all;
	}

        public function findOwners() {
                $all = new ArrayCollection();
                foreach ($this->ranks as $rank) {
                        if ($rank->isOwner()) {
                                foreach ($rank->getMembers() as $mbr) {
                                        $all->add($mbr->getCharacter());
                                }
                        }
                }
                return $all;
        }
	
}
