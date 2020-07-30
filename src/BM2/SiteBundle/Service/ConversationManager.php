<?php

namespace BM2\SiteBundle\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;

use BM2\SiteBundle\Service\AppState;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Conversation;
use BM2\SiteBundle\Entity\ConversationPermission;
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
                # Fetch all conversations in which I have an active permission...
                $query1 = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me AND p.active = TRUE');
                $query1->setParameter('me', $char);
                $result1 = $query1->getResult();
                # Fetch all distinct characters who have permissions in a conversation I'm part of.
                $query2 = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Character c JOIN c.conv_permissions p JOIN p.conversation t WHERE t IN (:conv)');
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

        public function findNewOwner(Conversation $conv, Character $char, $flush=true) {
                if ($conv->getSystem() !== null) {
                        return true; #System conversations are managed separately.
                }
                if ($conv->getRealm() !== null) {
                        return true; #Realm conversations are managed separately.
                }

                $query = $this->em->createQuery('SELECT count(p.id) FROM BM2SiteBundle:ConversationPermission p WHERE p.character != :me AND p.conversation = :conv AND p.owner = true AND p.active = true');
                $query->setParameters(['me'=>$char, 'conv'=>$conv]);
                if ($query->getSingleScalarResult() > 0) {
                        return true; # We already have another owner.
                }

                $query = $this->em->createQuery('SELECT p FROM BM2SiteBundle:ConversationPermission p WHERE p.character != :me AND p.conversation = :conv AND p.manager = true AND p.active = true ORDER BY p.start_time ASC');
                $query->setParameters(['me'=>$char, 'conv'=>$conv]);
                $options = $query->getResult();
                if (count($options) > 0) {
                        $options[0]->setOwner(true);
                } else {
                        $query = $this->em->createQuery('SELECT p FROM BM2SiteBundle:ConversationPermission p WHERE p.character != :me AND p.conversation = :conv AND p.active = true ORDER BY p.start_time ASC');
                        $query->setParameters(['me'=>$char, 'conv'=>$conv]);
                        $options = $query->getResult();
                        if (count($options) > 0) {
                                $options[0]->setOwner(true);
                        }
                }
                if($flush) {
                        $this->em->flush();
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

        public function newConversation(Character $char, $recipients=null, $topic, $type, $content, Realm $realm = null) {
                if ($recipients === NULL && $realm === NULL) {
                        return 'no recipients';
                }
                $now = new \DateTime("now");
                $cycle = $this->appstate->getCycle();

                $conv = new Conversation();
                $this->em->persist($conv);
                $conv->setTopic($topic);
                $conv->setCreated($now);
                $conv->setActive(true);
                $conv->setCycle($cycle);
                $conv->setUpdated($now);

                $msg = new Message();
                $this->em->persist($msg);
                $msg->setConversation($conv);
                $msg->setSender($char);
                $msg->setSent($now);
                $msg->setType($type);
                $msg->setCycle($cycle);
                $msg->setContent($content);

                $creator = new ConversationPermission();
                $this->em->persist($creator);
                if (!$realm) {
                        $creator->setOwner(true);
                        $creator->setManager(true);
                } else {
                        $conv->setRealm($realm);
                        $recipients = $realm->findMembers();
                }
                $creator->setStartTime($now);
                $creator->setActive(true);
                $creator->setUnread(0);
                $creator->setConversation($conv);
                $creator->setCharacter($char);
                $creator->setLastAccess($now);
                foreach ($recipients as $recipient) {
                        $perm = new ConversationPermission();
                        $this->em->persist($perm);
                        $perm->setStartTime($now);
                        $perm->setCharacter($recipient);
                        $perm->setConversation($conv);
                        $perm->setOwner(false);
                        $perm->setManager(false);
                        $perm->setActive(true);
                        $perm->setUnread(1);
                }

                $this->em->flush();
                return $conv;
        }

        public function newSystemMessage(Conversation $conv, $type, ArrayCollection $data=null, Character $originator=null, $flush=true) {
                $now = new \DateTime("now");
                $cycle = $this->appstate->getCycle();
                if ($type == 'newperms') {
                        $content = $originator->getName().' has added the following people to the conversation: ';
                        $count = $data->count();
                        if ($count == 1) {
                                $content .= $data[0]->getName();
                        } else {
                                $i = 0;
                                foreach ($data as $char) {
                                        $i++;
                                        if ($i == $count) {
                                                $content .= 'and '.$char->getName().'.';
                                        } else {
                                                $content .= $char->getName().', ';
                                        }
                                }
                        }
                } elseif ($type == 'removal') {
                        $content = $originator->getName().' has removed the following people from the conversation: ';
                        $count = $data->count();
                        if ($count == 1) {
                                $content .= $data[0]->getName().'.';
                        } else {
                                $i = 0;
                                foreach ($data as $char) {
                                        $i++;
                                        if ($i == $count) {
                                                $content .= 'and '.$char->getName().'.';
                                        } else {
                                                $content .= $char->getName().', ';
                                        }
                                }
                        }
                } elseif ($type == 'left') {
                        $content = $originator->getName().' has left the conversation.';
                }

                $msg = new Message();
                $this->em->persist($msg);
                $msg->setConversation($conv);
                $msg->setSent($now);
                $msg->setType('system');
                $msg->setCycle($cycle);
                $msg->setContent($content);
                if (!$conv->getRealm()) {
                        foreach ($conv->findActivePermissions() as $perm) {
                                $perm->setUnread($perm->getUnread()+1);
                        }
                }

                if ($flush) {
                        $this->em->flush();
                }

                return $msg;
        }

        public function pruneConversation(Conversation $conv) {
                $keep = new ArrayCollection();
                $all = $conv->getMessages();
                # Grab all conversation messages and go through each of them.
                foreach ($all as $msg) {
                        # Grab all conversation permissions and go through each of them.
                        $perms = $conv->getPermissions();
                        if ($perms->count() > 0) {
                                foreach ($perms as $perm) {
                                        # If the message exists within the bounds of a permission, add it to $keep.
                                        if ($perm->getStartTime() <= $msg->getSent() AND ($msg->getSent() <= $perm->getEndTime() OR $perm->getActive())) {
                                                $keep->add($msg);
                                                break;
                                        }
                                }
                                # Go through all messages. If they don't exist in the Keep array, remove it.
                                foreach ($all as $msg) {
                                        if (!$keep->contains($msg)) {
                                                $this->em->remove($msg);
                                        }
                                }
                        } else {
                                foreach ($all as $msg) {
                                        $this->em->remove($msg);
                                }
                        }
                }
                $this->em->flush();
                if ($conv->findActivePermissions()->count() == 0 && $conv->getMessages()->count() == 0) {
                        foreach ($conv->getpermissions() as $perm) {
                                $this->em->remove($perm);
                                $this->em->flush();
                        }
                        $this->em->remove($conv);
                        $this->em->flush();
                }
                return true;
        }
}
