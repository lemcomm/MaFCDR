<?php 

namespace BM2\DungeonBundle\Entity;

class Dungeoneer {

	public function getCurrentDungeon() {
		if (!$this->getParty()) return null;
		return $this->getParty()->getDungeon();
	}

	public function isInDungeon() {
		if ($this->getInDungeon() && $this->getParty() && $this->getParty()->getDungeon()) {
			return true;
		}
		return false;
	}

	public function getPower() {
		// apply modifier, but it can never fall below 20%
		$power = $this->power + $this->mod_power;
		return (max($power, round($this->power/20)));
	}

	public function getDefense() {
		// apply modifier, but it can never fall below 20%
		$defense = $this->defense + $this->mod_defense;
		return (max($defense, round($this->defense/20)));
	}

}
