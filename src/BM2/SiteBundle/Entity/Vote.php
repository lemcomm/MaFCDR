<?php 

namespace BM2\SiteBundle\Entity;

class Vote {

	public function getWeight() {
		switch ($this->election->getMethod()) {
			case 'spears':
				return $this->character->getActiveSoldiers()->count();
			case 'swords':
				return $this->character->getVisualSize();
			case 'land':
				return $this->character->getEstates()->count();
			case 'heads':
				$pop = 0;
				foreach ($this->character->getEstates() as $e) {
					$pop += $e->getPopulation();
				}
				return $pop;
			case 'banner':
			default:
				return 1;
		}
	}

}
