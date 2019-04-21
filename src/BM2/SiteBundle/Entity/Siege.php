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
		$this->getDoctrine()->getManager()->flush();
	}

	public function getAttackers() {
		foreach ($this->groups as $group) {
			if ($group->isAttacker()) return $group;
		}
		return null;
	}

	public function getDefenders() {
		foreach ($this->groups as $group) {
			if ($group->isDefender()) return $group;
		}
		return null;
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
	
}
