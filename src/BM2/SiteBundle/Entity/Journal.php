<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

class Journal {

        public function isPrivate() {
                if (!$this->public || $this->GM_private) {
                        return true;
                }
                return false;
        }

        public function length() {
                return strlen($this->entry);
        }

}
