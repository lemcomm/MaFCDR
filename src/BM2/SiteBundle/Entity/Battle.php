<?php 

namespace BM2\SiteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;


class Battle {

	private $nobles = null;
	private $soldiers = null;
	private $attackers = null;
	private $defenders = null;
	private $defense_bonus = -1;

	public function getName() {
		$name = '';
		foreach ($this->getGroups() as $group) {
			if ($name!='') {
				$name.=' vs. '; // FIXME: how to translate this?
			}
			switch (count($group->getCharacters())) {
				case 0: // no characters, so it's an attack on a settlement, right?
					if ($this->getSettlement()) {
						$name.=$this->getSettlement()->getName();
					} else {
						// this should never happen
					}
					break;
				case 1:
				case 2:
					$names = array();
					foreach ($group->getCharacters() as $c) {
						$names[] = $c->getName();
					}
					$name.=implode(', ', $names);
					break;
				default:
					// FIXME: improve this, e.g. check realms shared and use that
					$name.='various';
			}
			if ($group->getAttacker()==false && $this->getSettlement() && count($group->getCharacters()) > 0) {
				$name.=', '.$this->getSettlement()->getName();
			}
		}
		return $name;
	}

	public function getAttacker() {
		foreach ($this->groups as $group) {
			if ($group->isAttacker()) return $group;
		}
		return null;
	}

	public function getActiveAttackersCount() {
		if (null === $this->attackers) {
			$this->attackers = 0;
			foreach ($this->groups as $group) {
				if ($group->isAttacker()) {
					$this->attackers += $group->getActiveSoldiers()->count();
				}
			}
		}
		return $this->attackers;
	}

	public function getDefender() {
		foreach ($this->groups as $group) {
			if ($group->isDefender()) return $group;
		}
		return null;
	}

	public function getDefenseBonus() {
		if ($this->defense_bonus == -1) {
			$this->defense_bonus = 0;
			foreach ($this->getDefenseBuildings() as $building) {		
				$this->defense_bonus += $building->getDefenses();
			}
		}

		return $this->defense_bonus;
	}

	public function getDefenseBuildings() {
		$def = new ArrayCollection();
		if ($this->getSettlement()) {
			foreach ($this->getSettlement()->getActiveBuildings() as $building) {
				if ($building->getType()->getDefenses() != 0) {
					$def->add($building->getType());
				}
			}
		}
		return $def;
	}

	public function getActiveDefendersCount() {
		if (null === $this->defenders) {
			$this->defenders = 0;
			foreach ($this->groups as $group) {
				if ($group->isDefender()) {
					$this->defenders += $group->getActiveSoldiers()->count();
				}
			}
			if ($this->getSettlement()) {
				$this->defenders += $this->getSettlement()->getActiveMilitia()->count();
			}
		}
		return $this->defenders;
	}


	public function getNoblesCount() {
		if (null === $this->nobles) {
			$this->nobles = 0;
			foreach ($this->groups as $group) {
				$this->nobles += $group->getCharacters()->count();
			}
		}
		return $this->nobles;
	}

	public function getSoldiersCount() {
		if (null === $this->soldiers) {
			$this->soldiers = 0;
			foreach ($this->groups as $group) {
				$this->soldiers += $group->getSoldiers()->count();
			}
		}
		return $this->soldiers;
	}

}
