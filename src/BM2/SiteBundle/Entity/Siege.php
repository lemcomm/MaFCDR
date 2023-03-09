<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\Common\Collections\ArrayCollection;

class Siege {

	public function getLeader($side) {
		$leader = null;
		foreach ($this->groups as $group) {
			if ($side == 'attacker' && $group->isAttacker()) {
				$leader = $group->getLeader();
			} else if ($side == 'defender' && $group->isDefender()) {
				$leader = $group->getLeader();
			}
		}
		return $leader;
	}
	
	public function setLeader($side, $character) {
		foreach ($this->groups as $group) {
			if ($side == 'attackers' && $group->isAttacker()) {
				$group->setLeader($character);
			} else if ($side == 'defenders' && $group->isDefender()) {
				$group->setLeader($character);
			}
		}
	}

	public function getDefender() {
		foreach ($this->groups as $group) {
			if ($this->attacker != $group) {
				return $group;
			}
		}
	}

	public function getCharacters() {
		$allsiegers = new ArrayCollection;
		foreach ($this->groups as $group) {
			foreach ($group->getCharacters() as $character) {
				$allsiegers->add($character);
			}
		}

		return $allsiegers;
	}

	public function updateEncirclement() {
		$chars = $this->attacker->getCharacters();
		$count = 0;
		foreach ($chars as $char) {
			foreach ($char->getUnits() as $unit) {
				$count += $unit->getActiveSoldiers()->count();
			}
		}
		if ($count >= $this->encirclement) {
			$this->setEncircled(true);
			return $this;
		} else {
			$this->setEncircled(false);
			return $this;
		}
	}
	
}
