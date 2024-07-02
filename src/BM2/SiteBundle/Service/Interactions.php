<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Place;
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
	private $pol;

	public function __construct(EntityManager $em, Geography $geo, History $history, PermissionManager $pm, Politics $pol, Logger $logger) {
		$this->em = $em;
		$this->geo = $geo;
		$this->history = $history;
		$this->pm = $pm;
		$this->logger = $logger;
		$this->pol = $pol;
	}


	public function characterEnterSettlement(Character $character, Settlement $settlement, $force = false) {
		if ($character->getInsideSettlement() == $settlement) {
			return true; // we are already inside
		}

		#TODO: The entire if below this line might be redundant now that dispatcher also has all this logic.
		if (!$force) {
			// check if we are allowed inside - but only for fortified settlements
			if ($settlement->isFortified() && !$this->pm->checkSettlementPermission($settlement, $character, 'visit')) {
				return false;
			}

			// if we are currently engaged in battle, we can't enter
			if ($character->isInBattle()) {
				return false;
			}

			// if there is a siege going on and they've encircled the place, we can't enter
			if ($settlement->getSiege() && $settlement->getSiege()->getEncircled()) {
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
		if ($settlement->getOwner() != $character || $settlement->getSteward() != $character) {
			$this->history->visitLog($settlement, $character);
		}

		// TODO: we could make the counter depend on the "importance" of the person, i.e. if he's a ruler, owns land, etc.
		// TODO: ugly hard-coded limit - do we want to change it, make it flexible, or just leave it?
		if ($character->getUnits()) {
			$count = 0;
			foreach ($character->getUnits() as $unit) {
				$count += $unit->getLivingSoldiers()->count();
			}
			if ($count > 5) {
				$this->history->logEvent(
					$settlement,
					$force?'event.settlement.forceentered2':'event.settlement.entered2',
					array('%link-character%'=>$character->getId(), '%soldiers%'=>$count),
					History::LOW, true, 10
				);
			}
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
			if ($settlement->getOwner() != $prisoner || $settlement->getSteward() != $prisoner) {
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
		$this->em->flush();

		return true;
	}

	public function characterLeaveSettlement(Character $character, $force = false) {
		$settlement = $character->getInsideSettlement();
		if (!$settlement) return false;

		if ($force && (!$settlement->isFortified() || $this->pm->checkSettlementPermission($settlement, $character, 'visit'))) {
			// people with visiting permission cannot be forced out
			return false;
		}

		if ($settlement->getSiege() && $settlement->getSiege()->getEncircled()) {
			// the large army outside the gate (and the gatekeepers protecting you) laugh at your attempts to leave.
			return false;
		}

		// don't ask me why, but the below two lines work in this order and FAIL if reversed. Yeah. Fuck Doctrine.
		// $settlement->removeCharactersPresent($character); => Symfony 2.7 turns this into a DELETE - WTF ???
		$character->setInsideSettlement(null);

		// close history log
		if ($settlement->getOwner() != $character || $settlement->getSteward() != $character) {
			$this->history->closeLog($settlement, $character);
		}

		if ($character->getInsidePlace() && $character->getInsidePlace()->getSettlement()) {
			# If you leave a settlement while in a place in the settlement, you leave the place as well. How logical. :P
			$leftPlace = $this->characterLeavePlace($character);
			if (!$leftPlace) {
				return false; # We appear to be trapped in the Place!
			}
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
			if ($settlement->getOwner() != $prisoner || $settlement->getSteward() != $prisoner) {
				$this->history->closeLog($settlement, $prisoner);
			}
		}
		// if you're a prisoner yourself, your captor can stay, sorry, you don't get to define his location...
		$this->em->flush();

		return true;
	}

	#TODO: Move this getClassName method, and it's siblings in other files, into a single HelperService file.
	private function getClassName($entity) {
		$classname = get_class($entity);
		if ($pos = strrpos($classname, '\\')) return substr($classname, $pos + 1);
		return $pos;
	}

	// no type-hinting because target can be either a settlement, character, or place
	public function characterViewDetails(Character $character=null, $target=null) {
		$details=array('spot'=>false, 'spotmore'=>false, 'merchant'=>false, 'prospector'=>false, 'spy'=>false);
		if (!$character) return $details;

		if (!$character->getLocation()) {
			// still in start mode
			$details['startme'] = true;
			return $details;
		}
		// FIXME: This does NOT take watchtowers into account - I should probably create a checkSpotted() method or something
		switch ($this->getClassName($target)) {
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
				if ($this->pm->checkPlacePermission($target, $character, 'see') OR $target->getVisible()) {
					$distance = $this->geo->calculateDistanceToPlace($character, $target);
					$spot = $this->geo->calculateSpottingDistance($character);
					if ($target->getSettlement()) {
						$action = $this->geo->calculateActionDistance($target->getSettlement());
					} else {
						$action = 0;
					}
					if ($distance < $spot+$action) {
						$details['spot'] = true;
						if ($distance < max($spot, $action)) {
							$details['spotmore'] = true;
						}
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

	public function characterEnterPlace(Character $character, Place $place, $force = false) {
		if ($character->getInsidePlace() == $place) {
			return false; // You are already here...
		} elseif ($character->getInsidePlace()) {
			$leave = $this->characterLeavePlace($character, $force);
			if (!$leave) {
				return false; // Can't leave here...
			}
		}

		if (!$force) {
			// check if we are allowed inside - but only for fortified settlements
			if (!$place->getPublic() && $place->isFortified() && !$this->pm->checkPlacePermission($place, $character, 'visit')) {
				return false;
			}

			// if we are currently engaged in battle, we can't enter
			if ($character->isInBattle()) {
				return false;
			}
		}

		if ($place->getSettlement() != $character->getInsideSettlement()) {
			// Not in the settlement the place is in.
			return false;
		}

		// TODO: check if place in action range
		$character->setInsidePlace($place);
		$place->addCharactersPresent($character);

		// If the Place is in a Settlement, set us in it, otherwise, set us to the place's location.
		if ($settlement = $place->getSettlement()) {
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
		} else {
			$loc = $place->getLocation();
		}
		$character->setLocation($loc);
		$character->setTravel(null)->setProgress(null)->setSpeed(null);

		// open history log
		if ($place->getOwner() != $character) {
			$this->history->visitLog($place, $character);
		}

		// TODO: we could make the counter depend on the "importance" of the person, i.e. if he's a ruler, owns land, etc.
		// TODO: ugly hard-coded limit - do we want to change it, make it flexible, or just leave it?


		if ($character->getUnits()) {
			$count = 0;
			foreach ($character->getUnits() as $unit) {
				$count += $unit->getLivingSoldiers()->count();
			}
			if ($count > 5) {
				$this->history->logEvent(
					$place,
					$force?'event.place.forceentered2':'event.place.entered2',
					array('%link-character%'=>$character->getId(), '%soldiers%'=>$count),
					History::LOW, true, 10
				);
			}
		} else {
			$this->history->logEvent(
				$place,
				$force?'event.place.forceentered':'event.place.entered',
				array('%link-character%'=>$character->getId()),
				History::LOW, true, 10
			);
		}

		// bring your prisoners with you
		foreach ($character->getPrisoners() as $prisoner) {
			$prisoner->setInsidePlace($place);
			$prisoner->setLocation($loc);
			$place->addCharactersPresent($prisoner);
			if ($place->getOwner() != $prisoner) {
				$this->history->visitLog($place, $prisoner);
			}
		}

		// if you're a prisoner yourself, bring your captor with you
		if ($captor = $character->getPrisonerOf()) {
			$captor->setInsidePlace($place);
			$captor->setLocation($loc);
			$place->addCharactersPresent($captor);
			if ($place->getOwner() != $captor) {
				$this->history->visitLog($place, $captor);
			}
		}
		$this->em->flush();

		return true;
	}

	public function characterLeavePlace(Character $character, $force = false) {
		$place = $character->getInsidePlace();
		if (!$place) return false;

		if ($force && (!$place->isFortified() || $this->pm->checkPlacePermission($settlement, $character, 'visit'))) {
			// people with visiting permission cannot be forced out
			return false;
		}

		// don't ask me why, but the below two lines work in this order and FAIL if reversed. Yeah. Fuck Doctrine.
		// $settlement->removeCharactersPresent($character); => Symfony 2.7 turns this into a DELETE - WTF ???
		$character->setInsidePlace(null);

		// close history log
		if ($place->getOwner() != $character) {
			$this->history->closeLog($place, $character);
		}

		$this->history->logEvent(
			$place,
			$force?'event.place.forceleft':'event.place.left',
			array('%link-character%'=>$character->getId()),
			History::LOW, true, 10
		);

		// bring your prisoners with you
		foreach ($character->getPrisoners() as $prisoner) {
			$prisoner->setInsidePlace(null);
			// $settlement->removeCharactersPresent($prisoner); => Symfony 2.7 turns this into a DELETE - WTF ???
			if ($place->getOwner() != $prisoner) {
				$this->history->closeLog($place, $prisoner);
			}
		}
		// if you're a prisoner yourself, your captor can stay, sorry, you don't get to define his location...
		$this->em->flush();

		return true;
	}

	public function abandonSettlement(Character $character, Settlement $settlement, $keepRealm) {
		if ($settlement->getOwner() === $character) {
			if (!$keepRealm) {
				$this->pol->changeSettlementRealm($settlement, $settlement->getOccupier(), 'abandon');
			}
			$this->pol->changeSettlementOwner($settlement, $settlement->getOccupant(), 'abandon');
			$this->em->flush();
			return true;
		} elseif ($settlement->getOccupant() === $character) {
			$this->pol->endOccupation($settlement, 'abandon', false, $character);
			$this->em->flush();
			return true;
		} else {
			return false;
		}
	}
}
