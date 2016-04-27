<?php 

namespace BM2\DungeonBundle\Entity;

class Dungeon {

	public function getCurrentLevel() {
		if (!$this->getParty()) return null;
		return $this->getParty()->getCurrentLevel();
	}

}
