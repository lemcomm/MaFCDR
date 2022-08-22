<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ActivityParticipant
 */
class ActivityParticipant {

        public function isChallenger() {
                if ($this === $this->getActivity()->getOrganizer()) {
                        return true;
                }
                return false;
        }

        public function isChallenged() {
                if ($this !== $this->getActivity()->getOrganizer()) {
                        return true;
                }
                return false;
        }
  
}
