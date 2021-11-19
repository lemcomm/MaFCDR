<?php

namespace BM2\SiteBundle\Entity;

use BM2\SiteBundle\Entity\Character;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Association
 */
class Association {

	protected $ultimate=false;

	public function findUltimate() {
		if ($this->ultimate!==false) {
			return $this->ultimate;
		}
		if (!$superior=$this->getSuperior()) {
			$this->ultimate=$this;
		} else {
			while ($superior->getSuperior()) {
				$superior=$superior->getSuperior();
			}
			$this->ultimate=$superior;
		}
		return $this->ultimate;
	}

	public function isUltimate() {
		if ($this->findUltimate() == $this) return true;
		return false;
	}

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

        public function findAllInferiors($include_myself = false) {
                $all_infs = new ArrayCollection;
                if ($include_myself) {
                        $all_infs->add($this);
                }
                foreach ($this->getInferiors() as $inf) {
                        $all_infs->add($inf);
                        $suball = $inf->findAllInferiors();
                        foreach ($suball as $sub) {
                                if (!$all->contains($sub)) {
                                        $all->add($sub);
                                }
                        }
                }
                return $all_infs;
        }

	public function findLaw($search) {
		foreach ($this->getLaws() as $law) {
			if ($law->getType()->getName() == $search) {
				return $law;
			}
		}
		return false;
	}
  
  
}
