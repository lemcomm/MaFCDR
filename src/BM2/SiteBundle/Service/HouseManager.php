<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\House;
use BM2\SiteBundle\Entity\HouseBackground;
use Doctrine\ORM\EntityManager;


class HouseManager {

	protected $em;
	protected $history;

	public function __construct(EntityManager $em, History $history) {
		$this->em = $em;
		$this->history = $history;
	}

	public function create($name, $description = null, $private_description = null, $secret_description = null, $superior = null, $crest = null, $location=null, $settlement=null, Character $founder) {
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
		
		$house = $this->_create($name, $description, $private_description, $secret_description, $superior, $crest, $location, $settlement, $founder);
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

	private function _create($name, $formalname, $type, $ruler) {
		$house = new Realm;
		$house->setName($name);
		$house->setDescription($description);
		$house->setPrivateDescription($private_description);
		$house->setSecretDescription($secret_description);
		$house->setSuperior($superior);
		$house->setGeoData($location);
		$house->setInsideSettlement($settlement);
		$house->setCrest($crest);
		$house->setHead($founder);
		$house->setMembers($founder);
		$this->em->persist($house);
		$this->em->flush($house);

		return $realm;
	}
	
}
