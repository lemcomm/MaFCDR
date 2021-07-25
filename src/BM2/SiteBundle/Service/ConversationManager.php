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
use BM2\SiteBundle\Entity\House;
use BM2\SiteBundle\Entity\Message;
use BM2\SiteBundle\Entity\MessageRecipient;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\RealmPosition;
use BM2\SiteBundle\Entity\Settlement;

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

        public function getOrgConversations(Character $char) {
                $query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me AND (c.realm IS NOT NULL OR c.house IS NOT NULL) ORDER BY c.realm ASC, c.updated DESC');
                $query->setParameter('me', $char);
                return $query->getResult();
        }

        public function getPrivateConversations(Character $char) {
                $query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me AND c.realm IS NULL AND c.house IS NULL ORDER BY c.updated DESC');
                $query->setParameter('me', $char);
                return $query->getResult();
        }

        public function getConversationsCount(Character $char) {
                $query = $this->em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me');
                $query->setParameter('me', $char);
                return $query->getSingleScalarResult();
        }

        public function getOrgConversationsCount(Character $char) {
                $query = $this->em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me AND (c.realm IS NOT NULL OR c.house IS NOT NULL)');
                $query->setParameter('me', $char);
                return $query->getSingleScalarResult();
        }

        public function getPrivateConversationsCount(Character $char) {
                $query = $this->em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me AND c.realm IS NULL AND c.house IS NULL');
                $query->setParameter('me', $char);
                return $query->getSingleScalarResult();
        }

        public function getActiveConversationsCount(Character $char) {
                $query = $this->em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me AND p.active = true');
                $query->setParameter('me', $char);
                return $query->getSingleScalarResult();
        }

        public function getActiveOrgConversationsCount(Character $char) {
                $query = $this->em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me AND p.active = true AND (c.realm IS NOT NULL OR c.house IS NOT NULL)');
                $query->setParameter('me', $char);
                return $query->getSingleScalarResult();
        }

        public function getActivePrivateConversationsCount(Character $char) {
                $query = $this->em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Conversation c JOIN c.permissions p WHERE p.character = :me AND p.active = true AND c.realm IS NULL AND c.house IS NULL');
                $query->setParameter('me', $char);
                return $query->getSingleScalarResult();
        }

        public function getLegacyContacts(Character $char) {
                $query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Character c JOIN c.conv_permissions p JOIN p.conversation t WHERE t IN (SELECT conv FROM BM2SiteBundle:Conversation conv JOIN conv.permissions perm WHERE perm.character = :me AND perm.active = TRUE)');
                $query->setParameter('me', $char);
                return $query->getResult();
	}

        public function getUnreadConvPermissions(Character $char) {
                $criteria = Criteria::create()->where(Criteria::expr()->gt("unread", 0));
                return $char->getConvPermissions()->matching($criteria);
        }

        public function getActiveConvPermissions(Character $char) {
                $criteria = Criteria::create()->where(Criteria::expr()->eq("active", true));
                return $char->getConvPermissions()->matching($criteria);
        }

        public function getActivePrivatePermissions(Character $char) {
                $query = $this->em->createQuery('SELECT p FROM BM2SiteBundle:ConversationPermission p JOIN p.conversation c WHERE p.active = true and c.realm is null and c.house is null and p.character = :me');
                $query->setParameters(['me'=>$char]);
                return new ArrayCollection($query->getResult());
        }

        public function getAllRecentMessages(Character $char, string $string) {
                # Start simple, get a datetime object of the earliest mesage we want to se, get a datetime object of now.
                $startTime = new \DateTime($string);
                $now = new \DateTime("now");

                # Get all the permissions that end after the earliest date or don't have an end set (are null) that are for our character and where the associated conversation actually has relevant messages.
                $query = $this->em->createQuery('SELECT p.id as perm, c.id as conv, p.start_time as start, p.end_time FROM BM2SiteBundle:ConversationPermission p JOIN p.conversation c WHERE p.character = :me AND (p.end_time > :start_time OR p.end_time IS NULL) AND (c.updated >= :start_time)');
                $query->setParameters(['start_time' => $startTime, 'me' => $char]);
                $perms = $query->getResult();

                # Variable preparation. $megaString is used for our main query.
                $allPerms = [];
                $megaString = 'SELECT m, c, p FROM BM2SiteBundle:Message m JOIN m.sender p JOIN m.conversation c WHERE';
                $first = true;
                $i = 1;
                $sets = [];
                $allConvs = [];
                foreach ($perms as $perm) {
                        # First we need to standardize out un-ended permissions, so we decare end as now if there isn't an end.
                        $start = $perm['start'];
                        if (array_key_exists('end', $perm)) {
                                $end = $perm['end'];
                        } else {
                                $end = $now;
                        }

                        # For every set we need three variables for the query, the conversation id, the start time, and the end time.
                        $sets[$i] = [
                                'conv'=>$perm['conv'],
                                'start'=>$start,
                                'end'=>$end
                        ];

                        # The first needs just a WHERE while the rest need OR WHERE in order for the query to build right.
                        if (!$first) {
                                $megaString .=' OR (m.conversation = :conv'.$i.' AND m.sent >= :startTime AND m.sent >= :start'.$i.' AND m.sent <= :end'.$i.')';
                        } else {
                                $megaString .=' (m.conversation = :conv'.$i.' AND m.sent >= :startTime AND m.sent >= :start'.$i.' AND m.sent <= :end'.$i.')';
                                $first = false;
                        }

                        # Add the permission to the processing stack and increment $i.
                        $allPerms[] = $perm['perm'];
                        if (!in_array($perm['conv'], $allConvs)) {
                                $allConvs[] = $perm['conv'];
                        }
                        $i++;
                }
                $i--; # Set pointer back one after end of foreach looping.

                # Unload our $sets array of arrays into a singular parameter array for passing into the query. Since order doesn't matter, we just decrement $i until it's 0.
                $parameters = [];
                while ($i > 0) {
                        $parameters['conv'.$i] = $sets[$i]['conv'];
                        $parameters['start'.$i] = $sets[$i]['start'];
                        $parameters['end'.$i] = $sets[$i]['end'];
                        $i--;
                }

                # Side load local conversations into the stack, if a local conversation exists for this character.
                if ($local = $char->getLocalConversation()) {
                        if (!$first) {
                                $megaString .= ' OR';
                        }
                        $megaString .= ' (m.conversation = :local AND m.sent >= :startTime)';
                        $parameters['local'] = $local->getId();
                        $parameters['startTime'] = $startTime;
                }

                # Ordering for the query.
                $megaString .= ' ORDER BY m.sent ASC';

                # Load our megaString into our megaQuery.
                $megaQuery = $this->em->createQuery($megaString);

                # Load paramters into query.
                $megaQuery->setParameters($parameters);
                $allMsgs = $megaQuery->getResult();

                # Update permissions for conversations that we've viewed to show that we've viewed them.
                $query = $this->em->createQuery('UPDATE BM2SiteBundle:ConversationPermission p SET p.unread = 0, p.last_access = :date WHERE p in (:perms)');
                $query->setParameters(['date'=>$now, 'perms'=>$allPerms]);
                $query->execute();
                if ($local) {
                        $query = $this->em->createQuery('UPDATE BM2SiteBundle:Message m SET m.read = TRUE WHERE m.conversation = :local AND m.sent >= :startTime');
                        $query->setParameters(['local'=>$local, 'startTime'=>$startTime]);
                        $query->execute();
                }
                return new ArrayCollection($allMsgs);
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
		if ($local = $char->getLocalConversation()) {
			foreach ($local->getMessages() as $msg) {
				if (!$msg->getRead()) {
					$allMsg->add($msg);
					$msg->setRead(true);
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
                        $now = new \DateTime("now");
                        $new = new Message();
                        $this->em->persist($new);
                        $new->setType($type);
                        $new->setCycle($this->appstate->getCycle());
                        $new->setSent($now);
                        $new->setContent($text);
                        if ($type != 'system') {
                                $new->setSender($char);
                        }
                        if ($replyTo) {
                                $target = $this->em->getRepository('BM2SiteBundle:Message')->findOneById($replyTo);
                                if ($target) {
                                        $new->setReplyTo($target);
                                }
                        }
                        $count = $conv->findActivePermissions()->count();
                        $new->setRecipientCount($count);
                        $new->setConversation($conv);
                        $conv->setUpdated($now);
                        $this->em->flush();
                        return $new;
                } else {
                        return 'noActivePerm';
                }
        }

        public function writeLocalMessage(Character $char, $target, $topic, $type, $text, $replyTo = null, $group) {
                #TODO: Finish reworking this.
                if ($target == 'place') {
                        $recipients = $char->getInsidePlace()->getCharactersPresent();
                } elseif ($target == 'settlement') {
                        $recipients = $char->getInsideSettlement()->getCharactersPresent();
                } else {
                        $recipients = $target;
                }
                $count = $recipients->count();
                if (!$recipients->contains($char)) {
                        $recipients->add($char);
                }

                $now = new \DateTime("now");
                $cycle = $this->appstate->getCycle();
                if ($replyTo) {
                        $origTarget = $this->em->getRepository('BM2SiteBundle:Message')->findOneById($replyTo);
                } else {
                        $origTarget = FALSE;
                }

                foreach ($recipients as $rec) {
                        if (!$rec->getLocalConversation()) {
                                $conv = new Conversation();
                                $this->em->persist($conv);
                                $conv->setLocalFor($rec);
                                $conv->setCreated($now);
                                $conv->setActive(true);
                                $conv->setCycle($cycle);
                                $conv->setUpdated($now);
                        } else {
                                $conv = $rec->getLocalConversation();
                                $conv->setUpdated($now);
                        }
                        $msg = new Message();
                        $this->em->persist($msg);
                        $msg->setConversation($conv);
                        $msg->setType($type);
                        $msg->setTopic($topic);
                        $msg->setCycle($cycle);
                        $msg->setSender($char);
                        $msg->setSent($now);
                        $msg->setContent($text);
                        if ($origTarget) {
                                $targetMsg = $this->em->getRepository('BM2SiteBundle:Message')->findOneBy(['sent'=>$origTarget->getSent(), 'sender'=>$origTarget->getSender(), 'content'=>$origTarget->getContent()]);
                                if ($targetMsg) {
                                        $msg->setReplyTo($target);
                                }
                        }
                        $msg->setRecipientCount($count);
                        $msg->setTarget($group);
                        foreach ($recipients as $recip) {
                                $msgRec = new MessageRecipient();
                                $this->em->persist($msgRec);
                                $msgRec->setMessage($msg);
                                $msgRec->setCharacter($recip);
                        }
                        if ($conv->getLocalFor() == $char) {
                                $mine = $msg;
                        }
                }
                $this->em->flush();
                return $mine;
        }

        public function newConversation(Character $char=null, $recipients=null, $topic, $type, $content = null, $org = null, $system = null, $local = false) {
                if ($recipients === null && $org === null && $local === false) {
                        return 'no recipients';
                }
                $realm = null;
                $house = null;
                if ($org) {
                        if ($org instanceof Realm) {
                                $realm = $org;
                        } elseif ($org instanceof House) {
                                $house = $org;
                        }
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
                $added = [];

                if (!$realm && !$house && !$local) {
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
                        $added[] = $char;
                } elseif ($realm) {
                        $conv->setRealm($realm);
                        $recipients = $realm->findMembers();
                } elseif ($house) {
                        $conv->setHouse($house);
                        $recipients = $house->findAllLiving();
                } elseif ($local) {
                        $conv->setLocalFor($character);
                }
                $counter = 0;
                foreach ($recipients as $recipient) {
                        if (!in_array($recipient, $added)) {
                                $counter++;
                                $perm = new ConversationPermission();
                                $this->em->persist($perm);
                                $perm->setStartTime($now);
                                $perm->setCharacter($recipient);
                                $perm->setConversation($conv);
                                $perm->setOwner(false);
                                $perm->setManager(false);
                                $perm->setActive(true);
                                if ($content) {
                                        $perm->setUnread(1);
                                } else {
                                        $perm->setUnread(0);
                                }
                                $added[] = $recipient;
                        } else {
                                #Do nothing, duplicate recipient.
                        }
                }

                # writeMessage(Conversation $conv, $replyTo = null, Character $char = null, $text, $type)
                if ($content) {
                        $msg = $this->writeMessage($conv, null, $char, $content, $type);
                }

                $this->em->flush();
                return $conv;
        }

        public function newSystemMessage(Conversation $conv, $type, ArrayCollection $data=null, Character $originator=null, $flush=true, $extra=null) {
                $now = new \DateTime("now");
                $cycle = $this->appstate->getCycle();
                if ($originator) {
                        $origin = '[c:'.$originator->getId().']';
                } else {
                        $origin = '*The System*';
                }
                if ($type == 'newperms') {
                        $content = $origin.' has added the following people to the conversation: ';
                        $count = $data->count();
                        if ($count == 1) {
                                $content .= $data[0]->getName();
                        } else {
                                $i = 0;
                                foreach ($data as $char) {
                                        $i++;
                                        if ($i == $count) {
                                                $content .= 'and [c:'.$char->getId().']';
                                        } else {
                                                $content .= '[c:'.$char->getId().'], ';
                                        }
                                }
                        }
                } elseif ($type == 'removal') {
                        $content = $origin.' has removed the following people from the conversation: ';
                        $count = $data->count();
                        if ($count == 1) {
                                $content .= '[c:'.$data[0]->getId().'].';
                        } else {
                                $i = 0;
                                foreach ($data as $char) {
                                        $i++;
                                        if ($i == $count) {
                                                $content .= 'and [c:'.$char->getId().'].';
                                        } else {
                                                $content .= '[c:'.$char->getId().'], ';
                                        }
                                }
                        }
                } elseif ($type == 'left') {
                        $content = $origin.' has left the conversation.';
                } elseif ($type == 'realmnew') {
                        $content = 'A new First One by the name of '.$origin.' has appeared in the realm as a knight at [p:'.$extra['where'].'].';
                } elseif ($type == 'realmnew2') {
                        $content = 'A new First One by the name of '.$origin.' has appeared in the subrealm of [r:'.$extra['realm'].'] as a knight at [p:'.$extra['where'].'].';
                } elseif ($type == 'housenew') {
                        $content = 'A new First One by the name of '.$origin.' has sworn allegiance to the house at [p:'.$extra['where'].'].';
                } elseif ($type == 'realmjoinplace') {
                        $content = 'A First One by the name of '.$origin.' at has joined the realm as a knight of [p:'.$extra['where'].'].';
                } elseif ($type == 'realmjoinplace2') {
                        $content = 'A First One by the name of '.$origin.' at has joined the subrealm of [r:'.$extra['realm'].'] as a knight of [p:'.$extra['where'].'].';
                } elseif ($type == 'realmjoinsettlement') {
                        $content = 'A First One by the name of '.$origin.' at has joined the realm as a knight of [e:'.$extra['where'].'].';
                } elseif ($type == 'realmjoinsettlement2') {
                        $content = 'A First One by the name of '.$origin.' at has joined the subrealm of [r:'.$extra['realm'].'] as a knight of [e:'.$extra['where'].'].';
                } elseif ($type == 'realmjoinposition') {
                        $content = 'A First One by the name of '.$origin.' at has joined the realm as a knight of [realmpos:'.$extra['where'].'].';
                } elseif ($type == 'realmjoinposition2') {
                        $content = 'A First One by the name of '.$origin.' at has joined the subrealm of [r:'.$extra['realm'].'] as a knight of [realmpos:'.$extra['where'].'].';
                }

                # writeMessage(Conversation $conv, $replyTo = null, Character $char = null, $text, $type)
                $msg = $this->writeMessage($conv, null, null, $content, 'system');

                foreach ($conv->findActivePermissions() as $perm) {
                        $perm->setUnread($perm->getUnread()+1);
                }

                if ($flush) {
                        $this->em->flush();
                }

                return $msg;
        }

        public function pruneConversation(Conversation $conv) {
                $keep = new ArrayCollection();
                $perms = $conv->getPermissions();
                $all = $conv->getMessages();
                # Grab all conversation messages and go through each of them.
                foreach ($all as $msg) {
                        # Grab all conversation permissions and go through each of them.
                        if ($perms->count() > 0) {
                                $keep = false;
                                foreach ($perms as $perm) {
                                        # If the message exists within the bounds of a permission, add it to $keep.
                                        if ($perm->getStartTime() <= $msg->getSent() AND ($msg->getSent() <= $perm->getEndTime() OR $perm->getActive())) {
                                                $keep = true;
                                                break;
                                        }
                                }
                                if (!$keep) {
                                        $this->em->remove($msg);
                                }
                        } else {
                                $this->em->remove($msg);
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
                        return 'deleted';
                }
                return 'pruned';
        }

        public function leaveAllConversations(Character $char) {
                $change = false;
                $now = new \DateTime("now");
                foreach ($char->getConvPermissions() as $perm) {
                        if ($perm->getActive()) {
                                $perm->setActive(false);
                                $perm->setEndTime($now);
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
                $realm = $conv->getRealm();
                $house = $conv->getHouse();
                $added = new ArrayCollection();
                $removed = new ArrayCollection();
                $now = new \DateTime("now");

                if ($realm) {
                        $entity = $realm;
                        $members = $realm->findMembers();
                } else {
                        $entity = $house;
                        $members = $house->findAllLiving();
                }

                if ($entity) {
                        if ($members && !$members->isEmpty()) {
                                $perms = $conv->findActivePermissions();
                                foreach ($members as $member) {
                                        if (!$conv->findActiveCharPermission($member)) {
                                                // this user is missing from the conversation, but should be there
                                                $this->addParticipant($conv, $member);
                                                $added->add($member);
                                        }
                                }

                                foreach ($conv->findActivePermissions() as $perm) {
                                        if (!$members->contains($perm->getCharacter())) {
                                                # Should no longer have active participation. Inactivate their permissions.
                                                $perm->setActive(FALSE);
                                                $perm->setEndTime($now);
                                                $removed->add($member);
                                        }
                                }
                        }
                }
                if ($added->count() > 0) {
                        $this->newSystemMessage($conv, 'newperms', $added, null, false, null);
                }
                if ($removed->count() > 0) {
                        $this->newSystemMessage($conv, 'removal', $removed, null, false, null);
                }
                return array('added'=>$added, 'removed'=>$removed);
        }

        public function sendNewCharacterMsg(Realm $realm = null, House $house = null, Place $place, Character $char) {
                $em = $this->em;
                $ultimate = null;
                $sameRealm = false;
                if ($realm) {
                        if ($realm->isUltimate()) {
                                $ultimate = $realm;
                                $same = true;
                        } else {
                                $ultimate = $realm->findUltimate();
                        }
                }
                # public function newSystemMessage(Conversation $conv, $type, ArrayCollection $data=null, Character $originator=null, $flush=true, $extra=null)
                $conv = null;
                $supConv = null;
                if ($realm && $same) {
                        $conv = $em->getRepository('BM2SiteBundle:Conversation')->findOneBy(['realm'=>$realm, 'system'=>'announcements']);
                        $this->addParticipant($conv, $char);
                        $em->flush();
                        $this->newSystemMessage($conv, 'realmnew', null, $char, null, ['where'=>$place->getId()]);
                } elseif ($realm && !$same) {
                        $conv = $em->getRepository('BM2SiteBundle:Conversation')->findOneBy(['realm'=>$realm, 'system'=>'announcements']);
                        $this->addParticipant($conv, $char);
                        $em->flush();
                        $this->newSystemMessage($conv, 'realmnew', null, $char, null, ['where'=>$place->getId()]);
                        $supConv = $em->getRepository('BM2SiteBundle:Conversation')->findOneBy(['realm'=>$ultimate, 'system'=>'announcements']);
                        $this->addParticipant($supConv, $char);
                        $em->flush();
                        $this->newSystemMessage($supConv, 'realmnew2', null, $char, null, ['realm'=>$realm->getId(), 'where'=>$place->getId()]);
                } elseif ($house) {
                        $conv = $em->getRepository('BM2SiteBundle:Conversation')->findOneBy(['house'=>$house, 'system'=>'announcements']);
                        $this->addParticipant($conv, $char);
                        $em->flush();
                        $this->newSystemMessage($conv, 'housenew', null, $char, null, ['where'=>$place->getId()]);
                }
                return [$conv, $supConv];
        }

        public function sendExistingCharacterMsg(Realm $realm = null, Settlement $settlement = null, Place $place = null, RealmPosition $pos = null, Character $char, $publicJoin = FALSE) {
                $em = $this->em;
                if ($realm === NULL && $settlement && $settlement->getRealm()) {
                        $realm = $settlement->getRealm();
                } elseif ($realm === NULL && $place && $place->getRealm()) {
                        $realm = $place->getRealm();
                } elseif ($realm === NULL && $pos && $pos->getRealm()) {
                        $realm = $pos->getRealm();
                }

                $ultimate = null;
                $sameRealm = false;
                if ($realm->isUltimate()) {
                        $ultimate = $realm;
                        $same = true;
                } else {
                        $ultimate = $realm->findUltimate();
                }

                # public function newSystemMessage(Conversation $conv, $type, ArrayCollection $data=null, Character $originator=null, $flush=true, $extra=null)
                $conv = null;
                $supConv = null;
                # We only actually need $realm->getId() passed through sometimes, but it's simpler to just always prepare it and only use it sometimes.
                if ($realm && $place) {
                        #Joined through a place.
                        $string = 'realmjoinplace';
                        $extra = ['realm'=>$realm->getId(), 'place'=>$place->getId()];
                } elseif ($realm && $settlement) {
                        #joined through a settlement.
                        $string = 'realmjoinsettlement';
                        $extra = ['realm'=>$realm->getId(), 'settlement'=>$settlement->getId()];
                } elseif ($realm && $pos) {
                        #joined through a position.
                        $string = 'realmjoinposition';
                        $extra = ['realm'=>$realm->getId(), 'pos'=>$pos->getId()];
                } elseif ($realm && $publicJoin) {
                        #joined through some other means.
                        #TODO: Public joins and utilizing the publicJoin var.
                }
                if ($realm && $same) {
                        $conv = $em->getRepository('BM2SiteBundle:Conversation')->findOneBy(['realm'=>$realm, 'system'=>'announcements']);
                        $this->addParticipant($conv, $char);
                        $em->flush();
                        $this->newSystemMessage($conv, $string, null, $char, null, $extra);
                } elseif ($realm && !$same) {
                        $conv = $em->getRepository('BM2SiteBundle:Conversation')->findOneBy(['realm'=>$realm, 'system'=>'announcements']);
                        $this->addParticipant($conv, $char);
                        $em->flush();
                        $this->newSystemMessage($conv, $string, null, $char, null, $extra);
                        $supConv = $em->getRepository('BM2SiteBundle:Conversation')->findOneBy(['realm'=>$ultimate, 'system'=>'announcements']);
                        $this->addParticipant($supConv, $char);
                        $em->flush();
                        $this->newSystemMessage($supConv, $string.'2', null, $char, null, $extra);
                }
                return [$conv, $supConv];
        }

        public function addParticipant(Conversation $conv, Character $char) {
                $perm = $this->em->getRepository('BM2SiteBundle:ConversationPermission')->findOneBy(['conversation'=>$conv, 'character'=>$char,'active'=>true]);
                if (!$perm) {
                        $now = new \DateTime("now");
                        $perm = new ConversationPermission();
                        $this->em->persist($perm);
                        $perm->setConversation($conv);
                        $perm->setCharacter($char);
                        $perm->setStartTime($now);
                        $perm->setActive(true);
                        $perm->setUnread(0);
                        $perm->setManager(false);
                        $perm->setOwner(false);
                }
                return $perm;
        }
}
