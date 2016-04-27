<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Message;
use BM2\SiteBundle\Entity\MessageLink;
use BM2\SiteBundle\Entity\MessageGroup;
use BM2\SiteBundle\Entity\MessageTowerLink;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\GeoFeature;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Battle;
use Doctrine\ORM\EntityManager;

use Doctrine\Common\Collections\ArrayCollection;


/*
	TODO: also add conversations to battles
*/

class Communication {

	const SEND_RANGE =		 10000;
	const RECEIVE_RANGE =	 20000;
	const BROADCAST_RANGE =  25000;
	const LINK_RANGE =		400000;
	const MAX_LINKS =				  5;

	protected $em;
	protected $appstate;
	protected $tower_building=null;

	public function __construct(EntityManager $em, AppState $appstate) {
		$this->em = $em;
		$this->appstate = $appstate;
	}

	public function TowerBuilding() {
		if (!$this->tower_building) {
			// FIXME: set this right, and also change in Dispatcher.php
			$this->tower_building = $this->em->getRepository('BM2SiteBundle:BuildingType')->findOneByName('Inn');
		}
		return $this->tower_building;
	}

	public function NewMessage(Character $sender=null, $text, $tags, Character $sealed_character=null, MessageGroup $sealed_group=null, Realm $sealed_realm=null, $lifetime=60) {
		$msg = new Message;
		$msg->setSender($sender);
		$msg->setContent($text);
		$msg->setTags($tags);
		$msg->setTs(new \DateTime("now"));
		$msg->setCycle($this->appstate->getCycle());
		$msg->setLifetime($lifetime);

		$msg->setSealedCharacter($sealed_character);
		$msg->setSealedGroup($sealed_group);
		$msg->setSealedRealm($sealed_realm);
		$this->em->persist($msg);

		// FIXME: for some reason, this doesn't seem to work. - or maybe it works, but if it's sealed for someone except me not?
		if ($sender) {
			// add message link for sender, so we have a record of sent messages
			$this->addLink($sender, $msg, true);
		}

		return $msg;
	}

	// TODO: personal communications (interaction / spotting range?)

	public function reachableTowers(Character $character) {
		$nearby = $this->nearbyTowers($character);
		$linked = $this->linkedTowers($character);

		$my_realms = $character->findRealms();

		$realms = array();
		$settlements = array();
		foreach (array_merge($nearby, $linked) as $row) {
			if (isset($row['link'])) {
				$link = $row['link'];
				$settlement = $row['link']->getSettlement();
			} else {
				$link = false;
				$settlement = $row['settlement'];
			}

			$data = array('settlement'=>$settlement, 'distance'=>$row['distance'], 'send'=>false, 'receive'=>$settlement->hasBuilding($this->TowerBuilding()), 'link'=>$link);
			if ($row['distance'] < Communication::SEND_RANGE || ($link && $row['distance'] < Communication::LINK_RANGE) )  {
				$data['send'] = $data['receive'];
			}
			$settlements[] = $data;

			if ($settlement->getRealm()) {
				foreach ($settlement->getRealm()->findAllSuperiors(true) as $realm) {
					if (isset($realms[$realm->getId()])) {
						if ($data['send']) {
							$realms[$realm->getId()]['send'] = $my_realms->contains($realm);
						}
						if ($data['receive']) {
							$realms[$realm->getId()]['receive'] = true;
						}
					} else {
						$realms[$realm->getId()] = array('realm'=>$realm, 'send'=>$my_realms->contains($realm)?$data['send']:false, 'receive'=>$data['receive']);
					}
				}
			}
		}
		return array('settlements'=>$settlements, 'realms'=>$realms);
	}

	public function linkedTowers(Character $character) {
		$query = $this->em->createQuery('SELECT s as settlement, ST_DISTANCE(c.location, g.center) as distance, l as link FROM BM2SiteBundle:MessageTowerLink l JOIN l.settlement s JOIN s.buildings b JOIN s.geo_data g JOIN l.character c WHERE c = :me ORDER BY s.name');
		$query->setParameters(array('me'=>$character));
		return $query->getResult();
	}

	public function nearbyTowers(Character $character) {
		$query = $this->em->createQuery('SELECT s as settlement, ST_DISTANCE(c.location, g.center) as distance FROM BM2SiteBundle:Settlement s JOIN s.buildings b JOIN s.geo_data g, BM2SiteBundle:Character c WHERE c = :me AND b.type = :tower AND b.active = true AND ST_DISTANCE(c.location, g.center) < :range ORDER BY s.name');
		$query->setParameters(array('me'=>$character, 'tower'=>$this->TowerBuilding(), 'range'=>max(Communication::SEND_RANGE, Communication::RECEIVE_RANGE)));
		return $query->getResult();
	}

	public function LocalMessage(Message $msg, Settlement $here) {
		$recipients = $this->broadcast_recipients(new ArrayCollection(array($here)));
		$this->sendMessage($msg, $recipients);
	}

	public function BroadcastMessage(Message $msg, Settlement $start, Realm $realm) {
		$all = $this->gather_broadcast_settlements($start, $realm);
		$recipients = $this->broadcast_recipients($all);
		$this->sendMessage($msg, $recipients);
		return count($all);
	}

	public function gather_broadcast_settlements(Settlement $start, Realm $realm) {
		$seen = new ArrayCollection;
		$all_realms = $realm->findAllInferiors(true);
		$realms = $all_realms->map(function($entry){ return $entry->getId(); })->toArray();

		return $this->broadcast_settlements($start, $realms, $seen);
	}

	private function broadcast_settlements(Settlement $settlement, $realms, ArrayCollection $seen) {
		$seen->add($settlement);
		$query = $this->em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s JOIN s.geo_data g JOIN s.buildings b, BM2SiteBundle:GeoData x WHERE x = :me AND b.type = :tower AND b.active = true AND ST_DISTANCE(g.center, x.center) < :range AND s.realm IN (:realms)');
		$query->setParameters(array('me'=>$settlement->getGeoData(), 'tower'=>$this->TowerBuilding(), 'realms'=>$realms, 'range'=>Communication::BROADCAST_RANGE));
		foreach ($query->getResult() as $target) {
			if (!$seen->contains($target)) {
				$seen = $this->broadcast_settlements($target, $realms, $seen);
			}
		}
		return $seen;
	}

	private function broadcast_recipients(ArrayCollection $settlements) {
		$query = $this->em->createQuery('SELECT DISTINCT c.id FROM BM2SiteBundle:Character c, BM2SiteBundle:Settlement s JOIN s.geo_data g, BM2SiteBundle:MessageTowerLink l WHERE s IN (:settlements) AND c.active = true AND (ST_DISTANCE(c.location, g.center) < :range OR (l.settlement = s AND l.character = c AND l.active = true))');
		$query->setParameters(array('range'=>Communication::RECEIVE_RANGE, 'settlements'=>$settlements->toArray()));
		return $query->getResult();
	}

	public function addLink(Character $recipient, Message $msg, $read=false) {
		if ($recipient != $msg->getSender()) { // because he gets it added seperately (to ensure he has a copy even if he is not in range)
			$link = new MessageLink;
			$link->setRecipient($recipient);
			$link->setMessage($msg);
			$link->setRead($read);
			$this->em->persist($link);
		}
	}

	private function sendMessage(Message $msg, $recipients) {
		foreach ($recipients as $recipient) {
			$recipient_reference = $this->em->getReference('BM2SiteBundle:Character', $recipient['id']);
			$this->addLink($recipient_reference, $msg);
		}
	}

	public function CreateMessageGroup($name, $open, Character $character, Settlement $settlement=null, Battle $battle=null) {
		$group = new MessageGroup;
		$group->setName($name)->setOpen($open);
		if ($character) {
			$group->addOwner($character);
			$group->addMember($character);
		}
		if ($settlement) {
			$group->addTower($settlement);
		}
		$group->setBattle($battle);
		$this->em->persist($group);
		return $group;
	}

	// FIXME: TODO: battle-linked message groups should have a timer or something (or by distance?)
	//						and what happens when the battle is over? (battles get removed from the DB, only battlereports stay)

	public function joinGroup(Character $character, MessageGroup $group, $notification=true) {
		$group->addMember($character);

		if ($notification) {
			// FIXME: this should be a translatable message
			$msg = $this->NewMessage(null, $character->getName().' has joined the group.', array('tower'), null, $group);
			foreach ($group->getTowers() as $tower) {
				$this->LocalMessage($msg, $tower);
			}
		}
	}

	public function leaveGroup(Character $character, MessageGroup $group, $notification=true) {
		$group->removeMember($character);

		if ($notification) {
			// FIXME: this should be a translatable message
			$this->NewMessage(null, $character->getName().' has left the group.', array('tower'), null, $group);
			foreach ($group->getTowers() as $tower) {
				$this->LocalMessage($msg, $tower);
			}
		}
	}

	public function createTowerLink(Character $character, Settlement $tower) {
		// FIXME: prevent duplicates !
		$link = new MessageTowerLink;
		$link->setCharacter($character);
		$link->setSettlement($tower);
		$link->setCycle($this->appstate->getCycle());
		$link->setActive(true);
		$link->setStatus('');
		$this->em->persist($link);
		return $link;
	}

	public function removeTowerLink(Character $character, Settlement $tower) {
		$link = $this->em->getRepository('BM2SiteBundle:MessageTowerLink')->findOneBy(array('character'=>$character, 'settlement'=>$tower));
		if ($link) {
			$this->em->remove($link);
		}
	}

}
