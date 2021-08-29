<?php 

namespace BM2\SiteBundle\Entity;

class Building {

	private $defMin = 0.30;

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

	public function getDefenseScore() {
		if ($this->getType()->getDefenses() <= 0) {
			return 0;
		} else  {
			$worth = $this->getType()->getBuildHours();
			if ($this->getActive()) {
				$completed = 1;
			} else {
				$completed = abs($this->getCondition() / $worth);
			}
			return $this->getType()->getDefenses()*$completed;
		}
	}

}
