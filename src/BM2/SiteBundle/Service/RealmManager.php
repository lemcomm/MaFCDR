<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Election;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\RealmLaw;
use BM2\SiteBundle\Entity\RealmPosition;
use Calitarus\MessagingBundle\Service\MessageManager;
use Doctrine\ORM\EntityManager;


class RealmManager {

	protected $em;
	protected $history;
	protected $politics;
	protected $messagemanager;

	public $available_ruler_election = array('banner', 'spears', 'swords', 'land', 'heads');


	public function __construct(EntityManager $em, History $history, Politics $politics, MessageManager $messagemanager) {
		$this->em = $em;
		$this->history = $history;
		$this->politics = $politics;
		$this->messagemanager = $messagemanager;
	}

	public function create($name, $formalname, $type, Character $founder) {
		$realm = $this->_create($name, $formalname, $type, $founder);

		$this->history->logEvent(
			$realm,
			'event.realm.founded',
			array('%link-character%'=>$founder->getId()),
			History::ULTRA, true
		);
		$this->history->logEvent(
			$founder,
			'event.character.realmfounded',
			array('%link-realm%'=>$realm->getId()),
			History::HIGH, true
		);
		$this->updateHierarchy($founder, $realm);
		return $realm;
	}

	public function subcreate($name, $formalname, $type, Character $ruler, Character $founder, Realm $parentrealm) {
		$realm = $this->_create($name, $formalname, $type, $ruler);
		$realm->setSuperior($parentrealm);
		$parentrealm->addInferior($realm);

		$this->history->logEvent(
			$realm,
			'event.subrealm.founded',
			array('%link-character-1%'=>$founder->getId(), '%link-character-2%'=>$ruler->getId(), '%link-realm%'=>$parentrealm->getId()),
			History::ULTRA, true
		);
		$this->history->logEvent(
			$founder,
			'event.character.realmfounded',
			array('%link-realm%'=>$realm->getId()),
			History::HIGH, true
		);
		$this->history->logEvent(
			$ruler,
			'event.character.realmgranted',
			array('%link-realm%'=>$realm->getId(), '%link-character%'=>$founder->getId()),
			History::HIGH, true
		);
		$this->updateHierarchy($ruler, $realm, false);
		return $realm;
	}

	private function _create($name, $formalname, $type, $ruler) {
		$realm = new Realm;
		$realm->setName($name)->setFormalName($formalname);
		$realm->setActive(true);
		$realm->setType($type);
		$realm->setColourHex('#cccccc');
		$realm->setColourRgb('204,204,204');
		$this->em->persist($realm);
		$this->em->flush($realm); // or we don't have a realm ID that we need below

		// create ruler position
		$position = new RealmPosition;
		$position->setRealm($realm);
		$position->setRuler(true);
		$position->setName('ruler');
		$position->setDescription('This is the rulership position for the realm.');
		$position->setElected(true);
		$position->setInherit(true);
		$position->setTerm(0);
		$this->em->persist($position);
		$realm->addPosition($position);

		$this->makeRuler($realm, $ruler);

		// default laws
		$elect = new RealmLaw;
		$elect->setRealm($realm);
		$elect->setName('estates')->setDescription("");
		$elect->setMandatory(true);
		$elect->setValueString("inherit");
		$this->em->persist($elect);
		$realm->addLaw($elect);

		return $realm;
	}

	public function abandon(Realm $realm) {
		$realm->setActive(false);
		$this->history->logEvent(
			$realm,
			'event.realm.deserted',
			array(),
			History::ULTRA, true
		);
		foreach ($realm->getEstates() as $e) {
			if ($realm->getSuperior() && $realm->getSuperior()->getActive()) {
				$this->politics->changeSettlementRealm($e, $realm->getSuperior(), 'fail');
			} else {
				$this->politics->changeSettlementRealm($e, null, 'fail');
			}
		}
	}

	private function updateHierarchy(Character $char, Realm $realm, $setrealm=true) {
		// update the downwards hierarchy on a new realm creation
		// everyone on here gets unlimited access, because the realm just got founded
		$this->history->openLog($realm, $char);

		$query = $this->em->createQuery('SELECT c FROM MsgBundle:Conversation c WHERE c.app_reference = :realm');
		$query->setParameter('realm', $realm);
		foreach ($query->getResult() as $conversation) {
			$this->messagemanager->updateMembers($conversation);
		}

		if ($setrealm) {
			foreach ($char->getEstates() as $estate) {
				if (!$estate->getRealm()) {
					$this->politics->changeSettlementRealm($estate, $realm, 'update');
				}
			}
		}

		foreach ($char->getVassals() as $vassal) {
			$this->updateHierarchy($vassal, $realm, $setrealm);
		}
	}


	public function abdicate(Realm $realm, Character $oldruler, Character $successor=null) {
		// ruler abdication and announcement of successor (or not)

		foreach ($realm->getPositions() as $pos) {
			if ($pos->getRuler()) {
				$pos->removeHolder($oldruler);
				$oldruler->removePosition($pos);
			}
		}

		if ($successor) {
			$this->history->logEvent(
				$realm,
				'event.realm.abdicated',
				array('%link-character-1%'=>$oldruler->getId(), '%link-character-2%'=>$successor->getId()),
				History::HIGH, true
			);
			$this->history->logEvent(
				$oldruler,
				'event.character.abdicated',
				array('%link-realm%'=>$realm->getId(), '%link-character%'=>$successor->getId()),
				History::HIGH, true
			);
			$this->history->logEvent(
				$successor,
				'event.character.succeeds',
				array('%link-realm%'=>$realm->getId(), '%link-character%'=>$oldruler->getId()),
				History::HIGH, true
			);
			$this->makeRuler($realm, $successor);
		} else {
			$this->history->logEvent(
				$realm,
				'event.realm.abdicated2',
				array('%link-character%'=>$oldruler->getId()),
				History::HIGH, true
			);
			$this->history->logEvent(
				$oldruler,
				'event.character.abdicated2',
				array('%link-realm%'=>$realm->getId()),
				History::HIGH, true
			);
		}
	}


	public function makeRuler(Realm $realm, Character $newruler, $ignore_position=false) {
		// find rulership position - we assume here that there's only one
		if (!$ignore_position) foreach ($realm->getPositions() as $position) {
			if ($position->getRuler() && !$position->getHolders()->contains($newruler)) {
				$position->addHolder($newruler);
				$newruler->addPosition($position);
			}
		}

		$this->removeRulerLiege($realm, $newruler);
	}
	
/*	public function restoreSubRealm(Realm $realm, Realm $deadrealm, Character $newruler) {
		//this will allow superior realms to restore dead sub-realms --Andrew
		if ($deadrealm->getActive() == false && $deadrealm->getSuperior()==$realm) {
			$this->makeRuler($deadrealm, $newruler);
			$deadrealm->setActive(true);
			$this->history->logEvent(
				$deadrealm,
				'event.realm.restored',
				array('%link-realm%'=>$realm->getID(), '%link-character%'=>$newruler->getId()),
				History::ULTRA, true
			);
		}
	}
*/

	public function removeRulerLiege(Realm $realm, Character $newruler) {
		if ($liege = $newruler->getLiege()) {
			$this->history->logEvent(
				$liege,
				'politics.oath.nowruler',
				array('%link-realm%'=>$realm->getId(), '%link-character%'=>$newruler->getId()),
				History::MEDIUM, true
			);
			$liege->removeVassal($newruler);
			$newruler->setLiege(null);
		}		
	}


	public function countElection(Election $election) {
		$election->setClosed(true);

		$candidates = array();
		foreach ($election->getVotes() as $vote) {
			$c = $vote->getTargetCharacter()->getId();
			if (!isset($candidates[$c])) {
				$candidates[$c] = array('char'=>$vote->getTargetCharacter(), 'votes'=>0, 'weight'=>0);
			}
			$candidates[$c]['votes'] += $vote->getVote();
			$candidates[$c]['weight'] += $vote->getVote() * $vote->getWeight();
		}

		$winner = null;
		$max = 0;

		foreach ($candidates as $c) {
			if ($c['weight'] > $max) {
				$winner = $c['char'];
				$max = $c['weight'];
			}
		}

		if ($winner) {
			$election->setWinner($winner);
			if ($election->getPosition()) {
				$election->getPosition()->addHolder($winner);
				$winner->addPosition($election->getPosition());
				$this->history->logEvent(
					$winner,
					'event.character.position.elected',
					array('%link-realm%'=>$election->getRealm()->getId(), '%link-realmposition%'=>$election->getPosition()->getId()),
					History::MEDIUM, true
				);
				$this->history->logEvent(
					$election->getRealm(),
					'event.realm.elected',
					array('%link-character%'=>$winner->getId()),
					History::MEDIUM, true
				);

				if ($election->getPosition()->getRuler()) {
					$this->removeRulerLiege($election->getRealm(), $winner);
				}
			}
		}
	}
}
