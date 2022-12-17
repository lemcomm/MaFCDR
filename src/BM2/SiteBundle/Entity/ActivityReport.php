<?php

namespace BM2\SiteBundle\Entity;

use BM2\SiteBundle\Entity\Character;
use Doctrine\ORM\Mapping as ORM;

/**
 * ActivityReport
 */
class ActivityReport {

        public function checkForObserver(Character $char) {
                foreach ($this->observers as $each) {
                        if ($each->getCharacter() === $char) {
                                return true;
                        }
                }
                return false;
        }

	public function countPublicJournals() {
		$i = 0;
		foreach ($this->journals as $each) {
			if ($each->getPublic()) {
				$i++;
			}
		}
		return $i;
	}
}
