<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Law
 */
class Law {

        public function getOrg() {
                if ($this->realm) {
                        return $this->realm;
                } else {
                        return $this->association;
                }
        }

        public function isActive() {
                if (!$this->invalidated_on && !$this->repealed_on) {
                        return true;
                }
                return false;
        }
        
}

