<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

class Portal {

        public function getDestinations() {
                $result = new ArrayCollection;
                $result->add($this->source);
                $result->add($this->destination);
                return $result;
        }
	
}
