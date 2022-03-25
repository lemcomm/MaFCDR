<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\House;
use Doctrine\ORM\EntityManager;


class HouseManager {

	protected $em;
	protected $history;

	public function __construct(EntityManager $em, History $history, DescriptionManager $descman) {
		$this->em = $em;
		$this->history = $history;
		$this->descman = $descman;
	}

	public function create($name, $motto = null, $description = null, $private_description = null, $secret_description = null, $place=null, $settlement=null, $crest = null, Character $founder) {
		# _create(name, description, private description, secret description, superior house, place, settlement, crest, and founder)
		$house = $this->_create($name, $motto, $description, $private_description, $secret_description, null, $place, $settlement, $crest, $founder);

		$this->history->openLog($house, $founder);
		$this->history->logEvent(
			$house,
			'event.house.founded',
			array('%link-character%'=>$founder->getId()),
			History::ULTRA, true
		);
		$this->history->logEvent(
			$founder,
			'event.character.house.founded',
			array('%link-house%'=>$house->getId()),
			History::ULTRA, true
		);
		$this->em->flush();
		return $house;
	}

	public function subcreate($name, $motto = null, $description = null, $private_description = null, $secret_description = null, $place = null, $settlement = null, $crest = null, $founder, House $id) {
		# _create(name, description, private description, secret description, superior house, settlement, crest, and founder)
		$house = $this->_create($name, $motto, $description, $private_description, $secret_description, $id, $place, $settlement, $crest, $founder);

		$this->history->openLog($house, $founder);
		$this->history->logEvent(
			$id,
			'event.house.subfounded',
			array('%link-character%'=>$founder->getId(), '%link-house%'=>$house->getId()),
			History::ULTRA, true
		);
		$this->history->logEvent(
			$founder,
			'event.character.house.subfounded',
			array('%link-house-1%'=>$id->getId(), '%link-house-2%'=>$house->getId()),
			History::HIGH, true
		);
		$this->history->logEvent(
			$house,
			'event.house.subcreated',
			array('%link-house%'=>$id->getId(), '%link-character%'=>$founder->getId()),
			History::ULTRA, true
		);
		foreach ($founder->getRequests() as $req) {
			if ($req->getType() == 'house.subcreate') {
				$this->em->remove($req);
			}
		}
		$this->em->flush();
		return $house;
	}

	private function _create($name, $motto, $description = null, $private_description = null, $secret_description = null, $superior = null, $place = null, $settlement = null, $crest = null, Character $founder) {
		$house = new House;
		$this->em->persist($house);
		$house->setName($name);
		$house->setMotto($motto);
		$house->setPrivate($private_description);
		$house->setSecret($secret_description);
		if ($superior) {
			$house->setSuperior($superior);
			$superior->addCadet($house);
		}
		if ($place) {
			$house->setHome($place);
		}
		if ($settlement) {
			$house->setInsideSettlement($settlement);
		}
		$house->setCrest($crest);
		$house->setFounder($founder);
		$house->setHead($founder);
		$house->setGold(0);
		$house->setActive(true);
		$founder->setHouse($house);
		$this->em->flush();
		if ($description) {
			$this->descman->newDescription($house, $description, $founder, TRUE);
		}

		return $house;
	}

}
