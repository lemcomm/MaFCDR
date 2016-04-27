<?php 

namespace BM2\SiteBundle\Entity;


class Mercenaries {


	public function getTotalPrice() {
		if ($this->getHiredBy()) {
			return ceil($this->countLiving() * $this->getPrice());
		}
	}

	public function countLiving() {
		$living = $this->getSoldiers()->filter(
			function($entry) {
				return ($entry->isAlive());
			}
		);
		return $living->count();
	}


}
