<?php 

namespace BM2\DungeonBundle\Entity;

class DungeonParty {

	public function countActiveMembers() {
		return $this->getActiveMembers()->count();
	}

	public function getActiveMembers() {
		return $this->getMembers()->filter(
			function($entry) {
				return $entry->isInDungeon();
			}
		);
	}

}
