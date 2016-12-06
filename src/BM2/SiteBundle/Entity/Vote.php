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
			case 'castles':
				$castles = 0;
				foreach ($this->character->getEstates()->getBuildings() as $buildings) {
					foreach ($buildings as $b) {
						if ($b->getType() == 'Palisade') {
							$castles++;
						}
						if ($b->getType() == 'Wood Castle') {
							$castles += 2;
						}
						if ($b->getType() == 'Stone Castle') {
							$castles += 4;
						}
						if ($b->getType() == 'Fortress') {
							$castles += 8;
						}
						if ($b->getType() == 'Citadel') {
							$castles += 16;
						}
					}
				}
				return $castles;
			case 'banner':
			default:
				return 1;
		}
	}

}
