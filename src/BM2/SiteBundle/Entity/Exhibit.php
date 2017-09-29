<?php 

namespace BM2\SiteBundle\Entity;

class Exhibit {


	public function startExhibitConstruction($workers) {
		$this->setActive(false);
		$this->setWorkers($workers);
		$this->setCondition(-$this->getType()->getBuildHours()); // negative value - if we reach 0 the construction is complete
		return $this;
	}

	public function isActive() {
		return $this->getActive();
	}
	
	public function abandon($damage = 1) {
		if ($this->isActive()) {
			$this->setActive(false);
			$this->setCondition(-$damage);
		}
		$this->setWorkers(0);
		return $this;
	}
	
	public function getNametrans() {
		return 'Exhibit.'.$this->getName();
	}
	
	public function getWhere() {
		if ($this->getSettlement() != false) {
			return $this->getSettlement()
		} else {
			return 'Exhibit.near.'.$this->getSettlement()
		}
	}
}
