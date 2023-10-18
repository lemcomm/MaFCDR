<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Listing;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Settlement;
use Doctrine\ORM\EntityManager;

class PermissionManager {

	protected $em;
	protected $politics;

	private $recursion_limit = 20; // prevent infinite recursion

	public function __construct(EntityManager $em, Politics $politics) {
		$this->em = $em;
		$this->politics = $politics;
	}

	public function checkRealmPermission(Realm $realm, Character $character, $permission, $return_details=false) {
		// check all positions of the character
		foreach ($character->getPositions() as $position) {
			if ($position->getRealm() == $realm) {
				if ($position->getRuler()) {
					// realm rulers always have all permissions without limits
					return array(true, null, 'ruler', null, null);
				}
				foreach ($position->getPermissions() as $perm) {
					if ($perm->getName() == $permission) {
						if ($return_details) {
							return array(true, null, 'position', $perm->getValue(), $perm->getReserve());
						} else {
							return false;
						}
					}
				}
			}
		}

		// not found anywhere, so default: deny
		if ($return_details) {
			return array(false, null, null);
		} else {
			return false;
		}
	}


	public function checkPlacePermission(Place $place, Character $character, $permission, $return_details=false) {
		// settlement owner always has all permissions without limits
		if ($place->getOccupier() || $place->getOccupant()) {
			$occupied = true;
		} else {
			$occupied = false;
		}
		if (($place->isOwner($character) && !$occupied) OR ($occupied && $place->getOccupant() == $character)) {
			if ($return_details) {
				return array(true, null, 'owner', null);
			} else {
				return true;
			}
		}

		// fetch everyone who is granted this permission
		if (!$occupied) {
			$allowed = $place->getPermissions()->filter(
				function($entry) use ($permission) {
					if ($entry->getPermission()->getName() == $permission && $entry->getListing()) {
						return true;
					} else {
						return false;
					}
				}
			);
		} else {
			$allowed = $place->getOccupationPermissions()->filter(
				function($entry) use ($permission) {
					if ($entry->getPermission()->getName() == $permission && $entry->getListing()) {
						return true;
					} else {
						return false;
					}
				}
			);
		}

		// for all of them, now check if our character is in this listing
		foreach ($allowed as $perm) {
			list($check, $list, $level) = $this->checkListing($perm->getListing(), $character);

			if ($check === false || $check === true) {
				// permission denied or granted
				if ($return_details) {
					return array($check, $list, $level, $perm);
				} else {
					return $check;
				}
			}
			// else not found on list, so continue looking
		}

		// not found anywhere, so default: deny
		if ($return_details) {
			return array(false, null, null, null);
		} else {
			return false;
		}
	}

	public function checkSettlementPermission(Settlement $settlement, Character $character, $permission, $return_details=false) {
		// settlement owner always has all permissions without limits
		if ($settlement->getOccupier() || $settlement->getOccupant()) {
			$occupied = true;
		} else {
			$occupied = false;
		}
		if (!$occupied && ($settlement->getOwner() == $character || $settlement->getSteward() == $character)) {
			if ($return_details) {
				return array(true, null, 'owner', null);
			} else {
				return true;
			}
		} elseif ($occupied && $settlement->getOccupant() == $character) {
			if ($return_details) {
				return array(true, null, 'owner', null);
			} else {
				return true;
			}
		}

		if (!$settlement->getOwner()) {
			if ($return_details) {
				return array(true, null, 'unowned', null);
			} else {
				return true;
			}
		} else {
			if (!$settlement->getOwner()->isActive() || $settlement->getOwner()->getUser()->isBanned()) {
				if ($realm = $settlement->getRealm()) {
					if ($law = $realm->findActiveLaw('slumberingAccess')) {
						$value = $law->getValue();
						$members = false;
						if ($value == 'any') {
							return true;
						} elseif ($value == 'direct') {
							$members = $realm->findMembers(false);
						} elseif ($value == 'realm') {
							$members = $realm->findMembers();
						}
						if ($members && $members->contains($character)) {
							return true;
						}
					}
				}
			}
		}

		// fetch everyone who is granted this permission
		if (!$occupied) {
			$allowed = $settlement->getPermissions()->filter(
				function($entry) use ($permission) {
					if ($entry->getPermission()->getName() == $permission && $entry->getListing()) {
						return true;
					} else {
						return false;
					}
				}
			);
		} else {
			$allowed = $settlement->getOccupationPermissions()->filter(
				function($entry) use ($permission) {
					if ($entry->getPermission()->getName() == $permission && $entry->getListing()) {
						return true;
					} else {
						return false;
					}
				}
			);
		}

		// for all of them, now check if our character is in this listing
		foreach ($allowed as $perm) {
			list($check, $list, $level) = $this->checkListing($perm->getListing(), $character);

			if ($check === false || $check === true) {
				// permission denied or granted
				if ($return_details) {
					return array($check, $list, $level, $perm);
				} else {
					return $check;
				}
			}
			// else not found on list, so continue looking
		}

		// not found anywhere, so default: deny
		if ($return_details) {
			return array(false, null, null, null);
		} else {
			return false;
		}
	}

	public function findMySettlementPermissions(Settlement $settlement, Character $me) {
		$permissions = array();

		// TODO: owner ? - he has all permissions, so we need a hardcoded list or what?

		foreach ($settlement->getPermissions() as $perm) {
			list($check, $list, $level) = $this->checkListing($perm->getListing(), $me);

			if ($check === false || $check === true) {
				// TODO: yes, I'm on the list - now what?
			}
		}

		return $permissions;
	}


	public function checkListing(Listing $list, Character $who, $depth=1) {
		foreach ($list->getMembers() as $member) {
			if ($member->getTargetCharacter()) {
				if ($member->getTargetCharacter() == $who) {
					// he's on the list, so return his allowed status
					return array($member->getAllowed(), $list, 'character');
				}
				if ($member->getIncludeSubs() && $this->politics->isSuperior($who, $member->getTargetCharacter())) {
					// he's not on the list he is a vassal of this guy, who is - so, same story
					return array($member->getAllowed(), $list, 'character');
				}
			}
			if ($member->getTargetRealm()) {
				$realms = $who->findRealms();
				foreach ($realms as $realm) {
					if ($realm == $member->getTargetRealm()) {
						return array($member->getAllowed(), $list, 'realm');
					}
				}
			}
		}

		if ($list->getInheritFrom() && $depth < $this->recursion_limit) {
			// we inherit from somewhere, so if he's not on our list, he might be on there
			//	and thanks to recursion that list will also check its parents
			return $this->checkListing($list->getInheritFrom(), $who, $depth+1);
		}

		// didn't find you anywhere, so we have no idea either way
		return array(null, null, null);
	}
}
