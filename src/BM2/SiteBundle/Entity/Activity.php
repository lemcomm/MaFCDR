<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Activity
 */
class Activity {

        public function findChallenger() {
                foreach ($this->participants as $p) {
                        if ($p === $this->organizer) {
                                return $p;
                        }
                }
                return false;
        }
}
