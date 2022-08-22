<?php

namespace BM2\SiteBundle\Entity;

use BM2\SiteBundle\Entity\Character;
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

        public function findChallenged() {
                foreach ($this->participants as $p) {
                        if ($p !== $this->organizer) {
                                return $p;
                        }
                }
                return false;
        }

        public function isAnswerable(Character $char) {
                foreach ($this->participants as $p) {
                        if ($p->getCharacter() === $char && $p->isChallenged() && !$p->getAccepted()) {
                                return true;
                        }
                        if ($p->getCharacter() === $char && $p->isChallenger() && $p->getActivity()->findChallenged()->getAccepted() && !$p->getAccepted()) {
                                return true;
                        }
                }
                return false;
        }
}
