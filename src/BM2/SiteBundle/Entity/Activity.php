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
                        if ($p->getOrganizer()) {
                                return $p;
                        }
                }
                return false;
        }

        public function findChallenged() {
                foreach ($this->participants as $p) {
                        if (!$p->getOrganizer()) {
                                return $p;
                        }
                }
                return false;
        }

        public function findOrganizer() {
                foreach ($this->participants as $p) {
                        if ($p->getOrganizer()) {
                                return $p;
                        }
                }
                return false;
        }

        public function isAnswerable(Character $char) {
                foreach ($this->participants as $p) {
                        if ($p->getCharacter() !== $char) {
                                # Not this character. Ignore.
                                continue;
                        }
                        if ($p->getAccepted()) {
                                # This character has already answered. End.
                                break;
                        }
                        if ($p->isChallenged()) {
                                return true;
                        }
                        if ($p->isChallenger() && $p->getActivity()->findChallenged() && $p->getActivity()->findChallenged()->getAccepted()) {
                                # We shouldn't *need* the middle check but just in case.
                                # We are the challenger, the challenged has accepted. Now we can accept thier weapon choice.
                                return true;
                        }
                }
                return false;
        }
        
}
