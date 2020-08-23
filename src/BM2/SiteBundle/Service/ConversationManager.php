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

        public function getAllRecentMessages(Character $char, string $string) {
                #TODO: This function is possibly one of the slowest in the game. Like the conversation list, it scales with the number of conversations and permissions we have.
                # The main thing slowing it down is that you have to check every message against the character permissions to see if should be rendered or not.
                # If it was just "you're in this conversation or not" we could do this in two queries. Because you can be in the conversation historically but not actively participating though, we have to sort things out.
                $endTime = new \DateTime($string);
                $query = $this->em->createQuery('SELECT p FROM BM2SiteBundle:ConversationPermission p WHERE p.character = :me AND (p.end_time > :end_time OR p.end_time IS NULL)');
                $query->setParameters(['end_time' => $endTime, 'me' => $char]);
                $allConv = [];
                foreach ($query->getResult() as $perm) {
                        if ($perm->getUnread() > 0) {
                                $perm->setUnread(0);
                        }
                        $perm->setLastAccess(new \DateTime("now"));
                        if (!in_array($perm->getConversation(), $allConv)) {
                                $allConv[] = $perm->getConversation();
                        }
                }

                $allMsg = new ArrayCollection();
                foreach ($allConv as $conv) {
                        foreach ($conv->findMessagesInWindow($char, $endTime) as $msg) {
                                $allMsg->add($msg);
                        }
                }
                $iterator = $allMsg->getIterator();
                $iterator->uasort(function($a, $b) {
                        return ($a->getSent() > $b->getSent()) ? 1 : -1 ;
                });
                $this->em->flush();
                return new ArrayCollection(iterator_to_array($iterator));
        }

        public function getAllUnreadMessages(Character $char) {
                $unread = new ArrayCollection();
                foreach ($char->getConvPermissions()->filter(function($entry) {return $entry->getActive() == true;}) as $perm) {
                        if ($perm->getUnread() > 0) {
                                $perm->setUnread(0);
                        }
                        $perm->setLastAccess(new \DateTime("now"));
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
                $this->em->flush();
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

        public function writeMessage(Conversation $conv, $replyTo = null, Character $char = null, $text, $type) {
                if ($type == 'system') {
                        $valid = true;
                } else {
                        $valid = $conv->findActiveCharPermission($char);
                }
                if ($valid) {
                        $new = new Message();
                        $this->em->persist($new);
                        $new->setType($type);
                        $new->setCycle($this->appstate->getCycle());
                        $new->setSent(new \DateTime("now"));
                        $new->setContent($text);
                        if ($type != 'system') {
                                $new->setSender($char);
                        }
                        if ($replyTo !== NULL) {
                                $target = $this->em->getRepository('BM2SiteBundle:Message')->findOneById($replyTo);
                                if ($target) {
                                        $new->setReplyTo($target);
                                }
                        }
                        $count = $conv->findActivePermissions()->count();
                        $new->setRecipients($count);
                        $new->setConversation($conv);
                        $this->em->flush();
                        return $new;
                } else {
                        return 'noActivePerm';
                }
        }

        public function newConversation(Character $char=null, $recipients=null, $topic, $type, $content, Realm $realm = null, $system = null) {
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
                if ($system) {
                        $conv->setSystem($system);
                }

                if (!$realm) {
                        $creator = new ConversationPermission();
                        $this->em->persist($creator);
                        $creator->setOwner(true);
                        $creator->setManager(true);
                        $creator->setStartTime($now);
                        $creator->setActive(true);
                        $creator->setUnread(0);
                        $creator->setConversation($conv);
                        $creator->setCharacter($char);
                        $creator->setLastAccess($now);
                } else {
                        $conv->setRealm($realm);
                        $recipients = $realm->findMembers();
                }
                $counter = 0;
                foreach ($recipients as $recipient) {
                        $counter++;
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

                if ($content) {
                        $msg = new Message();
                        $this->em->persist($msg);
                        $msg->setConversation($conv);
                        $msg->setSender($char);
                        $msg->setSent($now);
                        $msg->setType($type);
                        $msg->setCycle($cycle);
                        $msg->setContent($content);
                        $new->setRecipients($counter);
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

        public function leaveAllConversations(Character $char) {
                $change = false;
                foreach ($char->getConvPermissions() as $perm) {
                        if ($perm->getActive()) {
                                $perm->setActive(false);
                                if (!$change) {
                                        $change = true;
                                }
                        }
                }
                if ($change) {
                        $this->em->flush();
                }
        }

        public function removeAllConversations(Character $char) {
                $change = false;
                $allConvs = new ArrayCollection();
                foreach ($char->getConvPermissions() as $perm) {
                        $allConvs->add($perm->getConversation());
                        $this->em->remove($perm);
                        if (!$change) {
                                $change = true;
                        }
                }
                foreach ($allConvs as $conv) {
                        $this->pruneConversation($conv);
                }
        }

        public function updateMembers(Conversation $conv) {
                #TODO: This function is supposed to update all realm conversations with the correct participants. Legacy code follows.
                /*
                $realm = $conversation->getAppReference();
                $added = 0;
                $removed = 0;

                if ($realm) {
                        $members = $realm->findMembers();

                        if ($members && !$members->isEmpty()) {
                                $query = $this->em->createQuery('SELECT u FROM MsgBundle:User u WHERE u.app_user IN (:members)');
                                $query->setParameter('members', $members->toArray());
                                $users = new ArrayCollection($query->getResult());

                                $query = $this->em->createQuery('SELECT u FROM MsgBundle:User u JOIN u.conversations_metadata m WHERE m.conversation = :conversation');
                                $query->setParameter('conversation', $conversation);
                                $participants = new ArrayCollection($query->getResult());

                                foreach ($users as $user) {
                                        if (!$participants->contains($user)) {
                                                // this user is missing from the conversation, but should be there
                                                $this->addParticipant($conversation, $user);
                                                $participants->add($user); // make sure we don't add anyone twice
                                                $added++;
                                        }
                                }

                                foreach ($participants as $part) {
                                        if (!$users->contains($part)) {
                                                // this user is in the conversation, but shouldn't - remove him
                                                $this->removeParticipant($conversation, $part);
                                                $participants->removeElement($part);
                                                $removed++;
                                        }
                                }
                        }
                }
                return array('added'=>$added, 'removed'=>$removed);
                */
        }
}
