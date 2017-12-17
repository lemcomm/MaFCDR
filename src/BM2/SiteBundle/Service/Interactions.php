<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;


class Interactions {

	private $em;
	private $geo;
	private $history;
	private $permission_manager;
	private $logger;


	public function __construct(EntityManager $em, Geography $geo, History $history, PermissionManager $pm, Logger $logger) {
		$this->em = $em;
		$this->geo = $geo;
		$this->history = $history;
		$this->permission_manager = $pm;
		$this->logger = $logger;
	}


	public function characterEnterSettlement(Character $character, Settlement $settlement, $force = false) {
		if ($character->getInsideSettlement() == $settlement) {
			return true; // we are already inside
		}

		if (!$force) {
			// check if we are allowed inside - but only for fortified settlements
			if ($settlement->isFortified() && !$this->permission_manager->checkSettlementPermission($settlement, $character, 'visit')) {
				return false;
			}

			// if we are currently engaged in battle, we can't enter
			if ($character->isInBattle()) {
				return false;
			}
		}

		// TODO: check if settlement in action range
		$character->setInsideSettlement($settlement);
		$settlement->addCharactersPresent($character);

		// set location to within settlement center, defined as half the settlement action distance
		$center_radius = $this->geo->calculateActionDistance($settlement) / 2;
		$passable = false;
		$loc = $character->getLocation();
		while (!$passable) {
			// random polar coordinates within this circle:
			$theta = rand(0,359);
			$r = rand(0,$center_radius);
			// convert to cartesian
			$x = $r * cos($theta);
			$y = $r * sin($theta);

			// place character
			$center = $settlement->getGeoData()->getCenter();
			$loc = new Point($center->getX() + $x, $center->getY() + $y);
			// make sure this doesn't put us into the ocean in coastal settlements!
			$query = $this->em->createQuery('SELECT g.passable FROM BM2SiteBundle:GeoData g WHERE ST_Contains(g.poly, ST_Point(:x,:y))=true')->setParameters(array('x'=>$loc->getX(), 'y'=>$loc->getY()));
			$passable = $query->getSingleScalarResult();
		}
		$character->setLocation($loc);
		$character->setTravel(null)->setProgress(null)->setSpeed(null);

		// open history log
		if ($settlement->getOwner() != $character) {
			$this->history->visitLog($settlement, $character);
		}

		// TODO: we could make the counter depend on the "importance" of the person, i.e. if he's a ruler, owns land, etc.
		// TODO: ugly hard-coded limit - do we want to change it, make it flexible, or just leave it?
		if ($character->getSoldiers() && ($soldiers = $character->getLivingSoldiers()->count()) > 5) {
			$this->history->logEvent(
				$settlement,
				$force?'event.settlement.forceentered2':'event.settlement.entered2',
				array('%link-character%'=>$character->getId(), '%soldiers%'=>$soldiers),
				History::LOW, true, 10
			);
		} else {
			$this->history->logEvent(
				$settlement,
				$force?'event.settlement.forceentered':'event.settlement.entered',
				array('%link-character%'=>$character->getId()),
				History::LOW, true, 10
			);
		}


		// bring your prisoners with you 
		foreach ($character->getPrisoners() as $prisoner) {
			$prisoner->setInsideSettlement($settlement);
			$prisoner->setLocation($loc);
			$settlement->addCharactersPresent($prisoner);
			if ($settlement->getOwner() != $prisoner) {
				$this->history->visitLog($settlement, $prisoner);
			}
		}
		// if you're a prisoner yourself, bring your captor with you
		if ($captor = $character->getPrisonerOf()) {
			$captor->setInsideSettlement($settlement);
			$captor->setLocation($loc);
			$settlement->addCharactersPresent($captor);
			if ($settlement->getOwner() != $captor) {
				$this->history->visitLog($settlement, $captor);
			}
		}

		return true;
	}

	public function characterLeaveSettlement(Character $character, $force = false) {
		$settlement = $character->getInsideSettlement();
		if (!$settlement) return false;

		if ($force && (!$settlement->isFortified() || $this->permission_manager->checkSettlementPermission($settlement, $character, 'visit'))) {
			// people with visiting permission cannot be forced out
			return false;
		}

		// don't ask me why, but the below two lines work in this order and FAIL if reversed. Yeah. Fuck Doctrine.
		// $settlement->removeCharactersPresent($character); => Symfony 2.7 turns this into a DELETE - WTF ???
		$character->setInsideSettlement(null);

		// close history log
		if ($settlement->getOwner() != $character) {
			$this->history->closeLog($settlement, $character);
		}

		$this->history->logEvent(
			$settlement,
			$force?'event.settlement.forceleft':'event.settlement.left',
			array('%link-character%'=>$character->getId()),
			History::LOW, true, 10
		);

		// bring your prisoners with you 
		foreach ($character->getPrisoners() as $prisoner) {
			$prisoner->setInsideSettlement(null);
			// $settlement->removeCharactersPresent($prisoner); => Symfony 2.7 turns this into a DELETE - WTF ???
			if ($settlement->getOwner() != $prisoner) {
				$this->history->closeLog($settlement, $prisoner);
			}
		}
		// if you're a prisoner yourself, your captor can stay, sorry, you don't get to define his location...

		return true;
	}

	#TODO: Move this getClassName method, and it's siblings in other files, into a single HelperService file.
	private function getClassName($entity) {
		$classname = get_class($entity);
		if ($pos = strrpos($classname, '\\')) return substr($classname, $pos + 1);
		return $pos;
	}

	// no type-hinting because target can be either a settlement or a character
	public function characterViewDetails(Character $character=null, $target=null) {
		$details=array('spot'=>false, 'spotmore'=>false, 'merchant'=>false, 'prospector'=>false, 'spy'=>false);
		if (!$character) return $details;

		if (!$character->getLocation()) {
			// still in start mode
			$details['startme'] = true;
			return $details;
		}
		// FIXME: This does NOT take watchtowers into account - I should probably create a checkSpotted() method or something
		switch ($this->getClassName($target))) {
			case 'Settlement':
				$distance = $this->geo->calculateDistanceToSettlement($character, $target);
				$spot = $this->geo->calculateSpottingDistance($character);
				$action = $this->geo->calculateActionDistance($target);
				if ($distance < $spot+$action) {
					$details['spot'] = true;
					if ($distance < max($spot, $action)) {
						$details['spotmore'] = true;
					}
				}
				break;
			case 'Character':
				if ($character == $target) {
					$distance = 0;
				} else {
					$distance = $this->geo->calculateDistanceToCharacter($character, $target);
				}
				$spot = $this->geo->calculateSpottingDistance($character);
				if ($distance < $spot) {
					$details['spot'] = true;
					// FIXME: ugly: hardcoded
					if ($distance < $spot/2) {
						$details['spotmore'] = true;
					}
				}
				break;
			case 'Place':
				$distance = $this->geo->calculateDistanceToPlace($character, $target);
				$spot = $this->geo->calculateSpottingDistance($character);
				$action = $this->geo->calculateActionDistance($target);
				if ($distance < $spot+$action) {
					$details['spot'] = true;
					if ($distance < max($spot, $action)) {
						$details['spotmore'] = true;
					}
				}
				break;
		}
		$number = $character->getAvailableEntourageOfType('Merchant')->count();
		if ($number>0) $details['merchant']=true;

		$number = $character->getAvailableEntourageOfType('Prospector')->count();
		if ($number>0) $details['prospector']=true;

		$number = $character->getAvailableEntourageOfType('Spy')->count();
		if ($number>0) $details['spy']=true;

		return $details;
	}

}
