<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\House;
use Doctrine\ORM\EntityManager;


class AssociationManager {

	protected $em;
	protected $history;

	public function __construct(EntityManager $em, History $history, DescriptionManager $descman) {
		$this->em = $em;
		$this->history = $history;
		$this->descman = $descman;
	}

	public function create($data, $place=null, $settlement=null, Character $founder) {
		# TODO: Unpack $data from the form.
		# _create(name, description, private description, secret description, superior house, place, settlement, crest, and founder)
		$assoc = $this->_create($name, $motto, $description, $private_description, $secret_description, null, $place, $settlement, $founder);

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

	public function subcreate(Character $character, $name, $motto = null, $description = null, $place = null, $settlement = null, $founder, House $id) {
		# Cadet houses won't be created with these so we set them to null in order to ensure they exist for passing to _create.
		$private_description = null;
		$secret_description = null;

		# _create(name, description, private description, secret description, superior house, settlement, crest, and founder)
		$assoc = $this->_create($name, $motto, $description, $private_description, $secret_description, $id, $settlement, $founder);

		$this->history->openLog($house, $founder);
		$this->history->logEvent(
			$id,
			'event.assoc.subfounded',
			array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$founder->getId(), '%link-assoc%'=>$assoc->getId()),
			History::ULTRA, true
		);
		$this->history->logEvent(
			$character,
			'event.character.assoc.subcreated',
			array('%link-house%'=>$house->getId(), '%link-character%'=>$founder->getId()),
			History::HIGH, true
		);
		$this->history->logEvent(
			$founder,
			'event.character.assoc.subfounded',
			array('%link-character%'=>$founder->getId(), '%link-assoc-1%'=>$id->getId(), '%link-assoc-2%'=>$assoc->getId()),
			History::HIGH, true
		);
		$this->history->logEvent(
			$assoc,
			'event.house.subcreated',
			array('%link-house-1%'=>$id->getId(), '%link-house-2%'=>$id->getId(), '%link-character-1%'=>$character->getId(), '%link-character-2%'=>$founder->getId()),
			History::ULTRA, true
		);
		return $assoc;
	}

	private function _create($name, $motto, $description = null, $private_description = null, $secret_description = null, $superior = null, $place = null, $settlement = null, Character $founder) {
		$assoc = new Association;
		$this->em->persist($assoc);
		$assoc->setName($name);
		$assoc->setMotto($motto);
		$assoc->setPrivate($private_description);
		$assoc->setSecret($secret_description);
		if ($superior) {
			$assoc->setSuperior($superior);
			$superior->addCadet($assoc);
		}
		if ($place) {
			$assoc->setHome($place);
		}
		if ($settlement && !$place) {
			$assoc->setInsideSettlement($settlement);
		}
		# TODO: Founder rank code!
		$assoc->setFounder($founder);
		$assoc->setHead($founder);
		$assoc->setGold(0);
		$assoc->setActive(true);
		$this->em->flush();
		if ($description) {
			$this->descman->newDescription($assoc, $description, $founder, TRUE);
		}

		return $house;
	}

}
