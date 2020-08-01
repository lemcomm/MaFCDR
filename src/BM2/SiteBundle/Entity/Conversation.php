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

        public function findCharPermissions($char) {
                $criteria = Criteria::create()->where(Criteria::expr()->eq("character", $char));
                return $this->getPermissions()->matching($criteria);
        }

        public function findActiveCharPermission($char) {
                $criteria = Criteria::create()->where(Criteria::expr()->eq("character", $char))->andWhere(Criteria::expr()->eq("active", true));
                return $this->getPermissions()->matching($criteria)->first();
        }

        public function findRelevantPermissions(Character $char, $admin=false) {
                $all = $this->getPermissions();
                if ($admin) {
                        # Admin debug override. Admin view also displays start/end times for permissions.
                        return $all;
                }
                $allmine = $this->findCharPermissions($char);
                $return = new ArrayCollection();
                foreach ($all as $perm) {
                        foreach ($allmine as $mine) {
                                if ($perm == $mine) {
                                        $return->add($perm); #We can always see our own.
                                        break;
                                }
                                #Crosscheck permissions. If no if statement resolves true, we can't see it.
                                if($perm->getActive()) {
                                        # If we're both active, I can see it.
                                        if ($mine->getActive()) {
                                                $return->add($perm);
                                                break;
                                        }
                                        # Check if theirs started while mine was active.
                                        if ($mine->getStartTime() < $perm->getStartTime() && $perm->getStartTime() < $mine->getEndTime()) {
                                                $return->add($perm);
                                                break;
                                        }
                                } else {
                                        # If mine is active, and started before theirs ended, I can see it.
                                        if ($mine->getActive() && $mine->getStartTime() < $perm->getEndTime()) {
                                                $return->add($perm);
                                                break;
                                        }
                                        # Check if their's ended while mine was active.
                                        if ($mine->getStartTime() < $perm->getEndTime() && $perm->getEndTime() < $mine->getEndTime()) {
                                                $return->add($perm);
                                                break;
                                        }
                                        # Check if their's started while mine was active.
                                        if ($mine->getStartTime() < $perm->getStartTime() && $perm->getStartTime() < $mine->getEndTime()) {
                                                $return->add($perm);
                                                break;
                                        }
                                }
                        }
                }
                return $return;
        }

        public function findMessages(Character $char) {
                $perms = $this->findCharPermissions($char);
                $all = new ArrayCollection();
                foreach ($this->getMessages() as $msg) {
                        foreach ($perms as $perm) {
                                if ($perm->getStartTime() <= $msg->getSent() AND ($msg->getSent() <= $perm->getEndTime() OR $perm->getActive())) {
                                        $all->add($msg);
                                        break;
                                }
                        }
                }
                return $all;
        }

        public function findMessagesInWindow(Character $char, $window) {
                $perms = $this->findCharPermissions($char);
                $all = new ArrayCollection();
                foreach ($this->getMessages() as $msg) {
                        foreach ($perms as $perm) {
                                if (($perm->getStartTime() <= $msg->getSent() AND ($msg->getSent() <= $perm->getEndTime() OR $perm->getActive())) AND $msg->getSent() > $window) {
                                        $all->add($msg);
                                        break;
                                }
                        }
                }
                return $all;
        }
        
}
