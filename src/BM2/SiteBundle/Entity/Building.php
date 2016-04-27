<?php 

namespace BM2\SiteBundle\Entity;

class Building {


	public function startConstruction($workers) {
		$this->setActive(false);
		$this->setWorkers($workers);
		$this->setCondition(-$this->getType()->getBuildHours()); // negative value - if we reach 0 the construction is complete
		return $this;
	}

	public function getEmployees() {
		// only active buildings use employees
		if ($this->isActive()) {
			$employees = 
				$this->getSettlement()->getFullPopulation() / $this->getType()->getPerPeople()
				+
				pow($this->getSettlement()->getFullPopulation() * 500 / $this->getType()->getPerPeople(), 0.25);

			// as long as we have less than four times the min pop amount, increase the ratio (up to 200%)
			if ($this->getType()->getMinPopulation() > 0 && $this->getSettlement()->getFullPopulation() < $this->getType()->getMinPopulation() * 4) {
				$mod = 2.0 - ($this->getSettlement()->getFullPopulation() / ($this->getType()->getMinPopulation() * 4));
				$employees *= $mod;
			}
			return ceil($employees * pow(2, $this->focus));
		} else {
			return 0;
		}
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

}
