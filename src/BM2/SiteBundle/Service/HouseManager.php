<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\House;
use BM2\SiteBundle\Entity\HouseBackground;
use Doctrine\ORM\EntityManager;


class HouseManager {

	protected $em;
	protected $history;

	public function __construct(EntityManager $em, History $history, DescriptionManager $descman) {
		$this->em = $em;
		$this->history = $history;
		$this->descman = $descman;
	}

	public function create($name, $description = null, $private_description = null, $secret_description = null, $superior = null, $crest = null, $settlement=null, Character $founder) {
		$house = $this->_create($name, $description, $private_description, $secret_description, $crest, $location=null, $settlement=null, $founder);

		$this->history->logEvent(
			$house,
			'event.house.founded',
			array('%link-character%'=>$founder->getId()),
			History::ULTRA, true
		);
		$this->history->logEvent(
			$founder,
			'event.character.house.founded',
			array('%link-realm%'=>$house->getId()),
			History::ULTRA, true
		);
		$this->updateHierarchy($founder, $realm);
		return $house;
	}

	public function subcreate(Character $character, $name, $description = null, $location = null, $settlement = null, $founder, House $id) {
		# Cadet houses won't be created with these so we set them to null in order to ensure they exist for passing to _create.
		$private_description = null;
		$secret_description = null;
		$crest = $founder->getCrest();
		
		$house = $this->_create($name, $description, $private_description, $secret_description, $superior, $crest, $settlement, $founder);
		# We wait to set this because it's not until after that function executes that we have an id to set for the superior/cadet relationship.
		$house->setSuperior($id);
		$id->addCadet($house);

		$this->history->logEvent(
			$id,
			'event.house.subfounded',
			array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$founder->getId(), '%link-house%'=>$house->getId()),
			History::ULTRA, true
		);
		$this->history->logEvent(
			$character,
			'event.character.house.subcreated',
			array('%link-house%'=>$house->getId(), '%link-character%'=>$founder->getId()),
			History::HIGH, true
		);
		$this->history->logEvent(
			$founder,
			'event.character.house.subfounded',
			array('%link-house%'=>$id->getId(), '%link-character-1%'=>$character '%link-character-2%'=>$founder->getId()),
			History::HIGH, true
		);
		$this->history->logEvent(
			$house,
			'event.house.subcreated',
			array('%link-house%'=>$house->getId(), '%link-character-1%'=>$character->getId(), '%link-character-2%'=>$founder->getId()),
			History::ULTRA, true
		);
		return $house;
	}

	private function _create($name, $description = null, $private_description = null, $secret_description = null, $superior = null, $settlement = null, $crest = null, Character $founder) {
		$house = new House;
		$house->setName($name);
		$house->setPrivate($private_description);
		$house->setSecret($secret_description);
		$house->setSuperior($superior);
		$house->setInsideSettlement($settlement);
		$house->setCrest($crest);
		$house->setHead($founder);
		$house->setMembers($founder);
		$house->setGold(0);
		$this->em->persist($house);
		$this->em->flush($house);
		if ($description) {
			$this->descman->newDescription($house, $description, $founder, TRUE);
		}

		return $house;
	}
	
}
