<?php 

namespace BM2\SiteBundle\Entity;


class NPC {

	public function isSoldier() {
		return false;
	}

	public function isEntourage() {
		return false;
	}

	public function isActive($include_routed=false) {
		if (!$this->isAlive()) return false;
		if ($this->isWounded()) return false;
		if (!$include_routed && $this->isRouted()) return false;
		return true;
	}

	public function isWounded() {
		return ($this->wounded > 0);
	}
	public function wound($value=1) {
		$this->wounded+=$value;
		return $this;
	}
	public function heal($value=1) {
		$this->wounded = max(0, $this->wounded - $value);
		return $this;
	}
	public function HealOrDie() {
		if (rand(0,100)<$this->wounded) {
			$this->kill();
			return false;
		} else {
			$this->heal(rand(1,10));
			return true;
		}
	}
	
	public function hungerMod() {
		$lvl = $this->hungry;
		if ($lvl == 0) {
			return 1;
		} elseif ($lvl > 140) {
			return 0;
		} else {
			return 1-($lvl/140);
		}
	}


	public function isHungry() {
		return ($this->hungry > 0);
	}
	public function makeHungry($value=1) {
		if ($value > 0) {
			$this->hungry+=$value;
		} else {
			$this->feed();
		}
		return $this;
	}
	public function feed() {
		if ($this->hungry>0) {
			$this->hungry-=5; // drops fairly rapidly
		}
		if ($this->hungry<0) {
			$this->hungry = 0;
		}
		return $this;
	}

	public function isAlive() {
		return $this->getAlive();
	}
	public function kill() {
		$this->setAlive(false);
		$this->hungry = 0; // we abuse this counter for rot count now
		$this->cleanOffers();
		if ($this->getHome()) {
			$this->getHome()->setWarFatigue($this->getHome()->getWarFatigue() + $this->getDistanceHome());
		}
	}

	public function isLocked() {
		return $this->getLocked();
	}

	public function gainExperience($amount=1) {
		$this->experience += intval(ceil($amount));
	}


	// compatability methods - override these if the child entity implements the related functionality
	public function cleanOffers() { }

}
