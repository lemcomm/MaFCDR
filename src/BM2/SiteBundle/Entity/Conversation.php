<?php

namespace BM2\SiteBundle\Entity;

use BM2SiteBundle\Entity\Character;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

/**
 * Conversation
 */
class Conversation {

        public function findUnread ($char) {
                $criteria = Criteria::create()->where(Criteria::expr()->eq("character", $char))->orderBy(["id" => Criteria::DESC])->setMaxResults(1);
                return $this->getPermissions()->matching($criteria)->first()->getUnread();
        }

        public function findActivePermissions() {
                $criteria = Criteria::create()->where(Criteria::expr()->eq("active", true));
                return $this->getPermissions()->matching($criteria);
        }
        
}
