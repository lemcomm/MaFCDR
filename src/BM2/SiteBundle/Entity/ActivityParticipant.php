<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ActivityParticipant
 */
class ActivityParticipant {

        public function isChallenger() {
                if ($this->getOrganizer()) {
                        return true;
                }
                return false;
        }

        public function isChallenged() {
                if (!$this->getOrganizer()) {
                        return true;
                }
                return false;
        }
  
}
