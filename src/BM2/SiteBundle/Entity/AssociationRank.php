<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;

/**
 * AssociationRank
 */
class AssociationRank {

        public function canSubcreate() {
                if ($this->owner || $this->subcreate) {
                        return true;
                }
                return false;
        }

        public function findAllKnownSubordinates() {
                if ($this->owner || $this->viewAll) {
                        return $this->findAllSubordinates();
                }
                if ($this->viewDown > 0) {
                        return $this->findKnownSubordinates(1, $this->viewDown);
                }
                return new ArrayCollection();
        }

        public function findAllSubordinates() {
                $subs = new ArrayCollection();
                foreach ($this->getSubordinates() as $sub) {
                        $subs->add($sub);
                        $suball = $sub->findAllSubordinates();
                        foreach ($suball as $subsub) {
                                if (!$subs->contains($subsub)) {
                                        $subs->add($subsub);
                                }
                        }
                }
                return $subs;
        }

        public function findKnownSubordinates($depth, $max) {
                $subs = new ArrayCollection();
                foreach ($this->getSubordinates() as $sub) {
                        $subs->add($sub);
                        if ($depth < $max) {
                                $suball = $sub->findKnownSubordinates($depth+1, $max);
                                foreach ($suball as $subsub) {
                                        if (!$subs->contains($subsub)) {
                                                $subs->add($subsub);
                                        }
                                }
                        }
                }
                return $subs;
        }

        public function findManageableSubordinates() {
                if ($this->owner || ($this->manage && $this->viewAll)) {
                        return $this->findAllSubordinates();
                } elseif ($this->manage) {
                        return $this->findAllKnownSubordinates();
                } else {
                        return new ArrayCollection;
                }
        }

        public function findAllKnownSuperiors() {
                if ($this->viewAll) {
                        return $this->findAllSuperiors();
                }
                if ($this->viewUp > 0) {
                        return $this->findKnownSuperiors(1, $this->viewUp);
                }
                return new ArrayCollection();
        }

        public function findAllSuperiors() {
                $sups = new ArrayCollection();
                if ($mySup = $this->superior) {
                        $sups->add($this->getSuperior());
                        $supall = $mySup->findAllSuperiors();
                        foreach ($supall as $sup) {
                                if (!$sups->contains($sup)) {
                                        $sups->add($sup);
                                }
                        }

                }
                return $sups;
        }

        public function findKnownSuperiors($depth, $max) {
                $sups = new ArrayCollection();
                if ($mySup = $this->superior) {
                        $sups->add($this->getSuperior());
                        if ($depth > $max) {
                                $supall = $mySup->findAllSuperiors();
                                foreach ($supall as $sup) {
                                        if (!$sups->contains($sup)) {
                                                $sups->add($sup);
                                        }
                                }
                        }

                }
                return $sups;
        }

        public function findRankDifference($rank) {
                $diff = 0;
                $assoc = $this->getAssocaition();
                if ($rank->getAssociation() === $assoc) {
                        if ($rank === $this) {
                                return 0;
                        }
                        $visLaw = $assoc->findLaw('rankVisibility');
                        if ($visLaw == 'direct') {
                                # This takes advantage of the fact that superiors are returned in order. The first result of findAll is the immediate, the next is the one after, etc.
                                foreach ($rank->findAllSuperiors() as $sup) {
                                        $diff++;
                                        if ($sup === $rank) {
                                                return $diff;
                                        }
                                }
                                foreach ($rank->findAllSubordinates() as $sub) {
                                        $diff--;
                                        if ($sub === $rank) {
                                                return $diff;
                                        }
                                }
                        } elseif ($visLaw == 'crossCompare') {
                                return $this->getLevel() - $rank->getLevel();
                        }
                }
                return 'Outside Range'; #This should only happen if you compare between associations or chains of hierarchy.
        }
	
	
}
