<?php 

namespace BM2\DungeonBundle\Entity;

class DungeonMonsterType {

	public function getPoints() {
		return ($this->power + $this->defense) * $this->wounds * $this->attacks;
	}


	public function getDanger() {
		return round( ( ($this->power*$this->attacks)  + $this->wounds*10)/10 );
	}

	public function getResilience() {
		return round( ($this->defense * $this->wounds)/10 );
	}


}
