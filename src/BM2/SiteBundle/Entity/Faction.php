<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

class Faction {

	protected $ultimate=false;

	public function findUltimate() {
		if ($this->ultimate!==false) {
			return $this->ultimate;
		}
		$superior = $this->getSuperior();
		if (!$superior || $this === $superior) {
			$this->ultimate=$this;
		} else {
			while ($superior->getSuperior()) {
				if ($superior->getSuperior() !== $superior) {
					$superior=$superior->getSuperior();
				}
			}
			$this->ultimate=$superior;
		}
		return $this->ultimate;
	}

	public function isUltimate() {
		if ($this->findUltimate() === $this) return true;
		return false;
	}

	public function findHierarchy($include_myself = false) {
		$all = new ArrayCollection;
		if ($include_myself) {
			$all->add($this);
		}
		foreach ($this->findAllSuperiors() as $sup) {
			$all->add($sup);
		}
		foreach ($this->findAllInferiors() as $sub) {
			$all->add($sub);
		}
		return $all;
	}

        public function findAllInferiors($include_myself = false) {
                $all = new ArrayCollection;
                if ($include_myself) {
                        $all->add($this);
                }
                foreach ($this->getInferiors() as $inf) {
			if ($inf !== $this) {
	                        $all->add($inf);
	                        $suball = $inf->findAllInferiors();
	                        foreach ($suball as $sub) {
	                                if (!$all->contains($sub)) {
	                                        $all->add($sub);
	                                }
	                        }
			}
                }
                return $all;
        }

	public function findDeadInferiors() {
		$all = new ArrayCollection;
		foreach ($this->getInferiors() as $sub) {
			if (!$sub->getActive() && $sub !== $this) {
				$all->add($sub);
			}
		}

		return $all;
	}

	public function findAllSuperiors($include_myself = false) {
		$all = new ArrayCollection;
		if ($include_myself) {
			$all->add($this);
		}
		if ($superior = $this->getSuperior()) {
			if ($superior !== $this) {
				$all->add($superior);
				$supall = $superior->findAllSuperiors();
				foreach ($supall as $sup) {
					if (!$all->contains($sup)) {
						$all->add($sup);
					}
				}
			}
		}
		return $all;
	}

	public function findLaw($search) {
		foreach ($this->getLaws() as $law) {
			if ($law->getType()->getName() == $search) {
				return $law;
			}
		}
		return false;
	}

	public function findMultipleLaws($haystack, $active = true) {
		$needles = [];
		foreach ($this->getLaws() as $law) {
			if (in_array($law->getType()->getName(), $haystack) && $law->isActive() === $active) {
				$needles[] = $law;
			}
		}
		return $needles;
	}

	public function findActiveLaws() {
		$all = new ArrayCollection();
		foreach ($this->findAllSuperiors(true) as $faction) {
			foreach ($faction->getLaws() as $law) {
				if ($law->isActive()) {
					$all->add($law);
				}
			}
		}
		return $all;
	}

	public function findInactiveLaws() {
		$all = new ArrayCollection();
		foreach ($this->findAllSuperiors(true) as $faction) {
			foreach ($faction->getLaws() as $law) {
				if (!$law->isActive()) {
					$all->add($law);
				}
			}
		}
		return $all;
	}

	public function findActivePlayers() {
		$users = new ArrayCollection();
		foreach ($this->findActiveMembers() as $each) {
			if (!$users->contains($each->getUser())) {
				$users->add($each->getUser());
			}
		}
		return $users;
	}
}
