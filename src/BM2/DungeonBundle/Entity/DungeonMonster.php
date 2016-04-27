<?php 

namespace BM2\DungeonBundle\Entity;

class DungeonMonster {

	public function getName() {
		return $this->amount."x ".$this->type->getName()." (size ".$this->size.")";
	}

}
