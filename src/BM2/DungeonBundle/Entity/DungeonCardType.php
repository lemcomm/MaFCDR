<?php 

namespace BM2\DungeonBundle\Entity;

class DungeonCardType {

	public function getRareText() {
		if ($this->rarity == 0) return 'common'; // exception for leave, etc. cards you can't draw randomly
		if ($this->rarity <= 20) return 'legendary';
		if ($this->rarity <= 100) return 'rare';
		if ($this->rarity <= 400) return 'uncommon';
		return 'common';
	}


}
