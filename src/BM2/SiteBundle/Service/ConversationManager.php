<?php

namespace BM2\SiteBundle\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;

use BM2\SiteBundle\Service\AppState;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Conversation;
use BM2\SiteBundle\Entity\Message;
use BM2\SiteBundle\Entity\Realm;

class ConversationManager {

        private $em;
        private $appstate;
        private $logger;

        public function __construct(EntityManager $em, AppState $appstate, Logger $logger) {
                $this->em = $em;
		$this->appstate = $appstate;
		$this->logger = $logger;
        }

        public function getConversations(Character $char) {
                $query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me ORDER BY c.realm ASC, c.updated DESC');
                $query->setParameter('me', $char);
                return $query->getResult();
        }

        public function getConversationsCount(Character $char) {
                $query = $this->em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me');
                $query->setParameter('me', $char);
                return $query->getSingleScalarResult();
        }

        public function getLegacyContacts(Character $char) {
                $query1 = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me');
                $query1->setParameter('me', $char);
                $result1 = $query1->getResult();
                $query2 = $this->em->createQuery('SELECT DISTINCT c FROM BM2SiteBundle:Character c JOIN c.conv_permissions p JOIN p.conversation t WHERE t IN :conv');
                $query2->setParameter('conv', $result1);
                return $query2->getResult();
	}

        public function getUnreadConvPermissions(Character $char) {
                $criteria = Criteria::create()->where(Criteria::expr()->gt("unread", 0));
                return $char->getConvPermissions()->matching($criteria);
        }

        public function getActiveConvPermissions(Character $char) {
                $criteria = Criteria::create()->where(Criteria::expr()->eq("active", true));
                return $char->getConvPermissions()->matching($criteria);
        }

        public function getAllRecentMessages(Character $char, string $freq) {
                switch ($freq) {
                        case '12h':
                                $endTime = new DateTime("-12 hours");
                                break;
                        case '24h':
                                $endTime = new DateTime("-24 hours");
                                break;
                        case '3d':
                                $endTime = new DateTime("-3 days");
                                break;
                        case '7d':
                                $endTime = new DateTime("-7 days");
                                break;
                        case '14d':
                                $endTime = new DateTime("-14 days");
                                break;
                        case '1m':
                                $endTime = new DateTime("-1 month");
                                break;
                }
                $query = $this->em->createQuery('SELECT p FROM BM2SiteBundle:ConversationPermission p WHERE p.character = :me AND (p.end_time > :end_time OR p.end_time IS NULL)');
                $query->setParameters(['end_time' => $endTime, 'me' => $char]);
                $allConv = [];
                foreach ($query->getResult() as $perm) {
                        if (!in_array($perm->getConversation()->getId(), $allConv)) {
                                $allConv[] = $perm->getConversation()->getId();
                        }
                }
                $query = $this->em->createQuery('SELECT m FROM BM2SiteBundle:Message m WHERE m.conversation in :all AND m.sent > :end_time ORDER BY m.sent ASC');
                $query->setParameters(['end_time' => $endTime, 'all' => $allConv]);
                return $query->getResult();
        }

        public function getAllUnreadMessages(Character $char) {
                $unread = new ArrayCollection();
                foreach ($char->getConvPermissions()->filter(function($entry) {return $entry->getActive() == true;}) as $perm) {
                        if ($total = $perm->getUnread() > 0) {
                                $counter = 0;
                                foreach ($perm->getConversation()->getMessages() as $message) {
                                        if ($message->sent() > $perm->getLastAccess()) {
                                                $unread->add($message);
                                        }
                                }
                        }
                }
                # We got the messages, now sort them...
                $iterator = $unread->getIterator();
                $iterator->uasort(function($a, $b) {
                        return ($a->getSent() > $b->getSent()) ? -1 : 1 ;
                });
                return new ArrayCollection(iterator_to_array($iterator));
        }

        public function getConvUnreadMessages(Character $char, Conversation $conv) {
                $unread = new ArrayCollection();
                foreach ($char->getConvPermissions()->filter(function($entry) use ($conv) {return $entry->getConversation() == $conv;}) as $perm) {
                        if ($total = $perm->getUnread() > 0) {
                                $counter = 0;
                                foreach ($perm->getConversation()->getMessages() as $message) {
                                        if ($message->sent() > $perm->getLastAccess()) {
                                                $unread->add($message);
                                        }
                                }
                        }
                }
        }

        public function countFlaggedMessages(Character $char) {

        }

        public function leaveConversation(Character $char, Converastion $conv) {
                $target = $conv->getPermissions()->findOneBy(['active'=>true, 'end'=>null, 'char'=>$char]);
                if ($target) {
                        if ($target->getOwner()) {
                                $this->findNewOwner($conv, $char);
                        }
                        if ($target->getManager()) {
                                $target->setManager(false);
                        }
                        $target->setEnd(new \DateTime('now'));
                        $target->setActive(false);
                }
        }

        public function removePlayerConversation(Character $char, Conversation $conv) {
                $perms = $conv->getPermissions()->findBy(['char' => $char]);
                foreach ($perms as $perm) {
                        $this->em->remove($perm);
                }
        }

        public function removeOrpahnConversations() {
                $all = $this->em->getRepository('BM2SiteBundle:Conversation');
                $count = 0;
                foreach ($all as $conv) {
                        if ($conv->getPermissions()->count() == 0) {
                                $this->em->remove($conv);
                                $count++;
                        }
                }
                return $count;
        }

        public function findNewOwner(Conversation $conv, Character $char) {
                if ($conv->getSystem() !== null) {
                        return true; #System conversations are managed separately.
                }
                if ($conv->getRealm() !== null) {
                        return true; #Realm conversations are managed separately.
                }
                $options = $conv->getPermissions()->findBy(['active'=>true, 'end'=>null, 'manager'=>true]);
                if ($options->count() > 0) {
                        $bestOpt = null;
                        $bestDate = null;
                        foreach ($options as $opt) {
                                if (!$bestDate) {
                                        $bestOpt = $opt;
                                        $bestDate = $opt->getStartTime();
                                }
                                if ($opt->getStartTime() < $bestDate) {
                                        $bestOpt = $opt;
                                        $bestDate = $opt->getStartTime();
                                }
                        }
                        $bestOpt->setOwner(true);
                        $bestOpt->setManager(false);
                } else {
                        $options = $conv->getPermissions()->findBy(['active'=>true, 'end'=>null]);
                        if ($options->count() > 0) {
                                $bestOpt = null;
                                $bestDate = null;
                                foreach ($options as $opt) {
                                        if (!$bestDate) {
                                                $bestOpt = $opt;
                                                $bestDate = $opt->getStartTime();
                                        }
                                        if ($opt->getStartTime() < $bestDate) {
                                                $bestOpt = $opt;
                                                $bestDate = $opt->getStartTime();
                                        }
                                }
                                $bestOpt->setOwner(true);
                        }
                }
        }

        public function writeMessage(Conversation $conv, $msg = null, Character $char, $text, $type) {
                $valid = $conv->findActiveCharPermission($char);
                if ($valid) {
                        $new = new Message();
                        $this->em->persist($new);
                        $new->setType($type);
                        $new->setCycle($this->appstate->getCycle());
                        $new->setSent(new \DateTime("now"));
                        $new->setContent($text);
                        $new->setSender($char);
                        if ($msg !== NULL) {
                                $target = $this->em->getRepository('BM2SiteBundle:Message')->findOneById($msg);
                                if ($target) {
                                        $new->setReplyTo($target);
                                }
                        }
                        $new->setConversation($conv);
                        $this->em->flush();
                        return $new;
                } else {
                        return 'noActivePerm';
                }
        }
}
