<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\RealmPosition;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\SkillType;

class Character {

	protected $ultimate=false;
	protected $my_realms=null;
	protected $my_houses=null;
	protected $my_assocs=null;
	protected $my_rulerships=false;
	public $full_health = 100;

	public function __toString() {
		return "{$this->id} ({$this->name})";
	}

	public function getPureName() {
		return $this->name;
	}

	public function getName() {
		// override to incorporate the known-as part
		if ($this->getKnownAs()==null) {
			return $this->name;
		} else {
			return '<i>'.$this->known_as.'</i>';
		}
	}

	public function getListName() {
		return $this->getName().' (ID: '.$this->id.')';
	}

	public function DaysInGame() {
		return $this->created->diff(new \DateTime("now"), true)->days;
	}

	public function isRuler() {
		return !$this->findRulerships()->isEmpty();
	}

	public function isNPC() {
		return $this->npc;
	}

	public function isTrial() {
		if ($this->user) return $this->user->isTrial();
		return false;
	}

	public function isDoingAction($action) {
		if ($this->getActions()->exists(
			function($key, $element) use ($action) { return $element->getType() == $action; }
		)) {
			return true;
		} else {
			return false;
		}
	}

	public function findRulerships() {
		if (!$this->my_rulerships) {
			$this->my_rulerships = new ArrayCollection;
			foreach ($this->positions as $position) {
				if ($position->getRuler()) {
					$this->my_rulerships->add($position->getRealm());
				}
			}
		}
		return $this->my_rulerships;
	}

	public function findHighestRulership() {
		$highest = null;
		if ($this->findRulerships()) {
			foreach ($this->findRulerships() as $rulership) {
				if ($highest == NULL) {
					$highest = $rulership;
				}
				if ($rulership->getType() > $highest->getType()) {
					$highest = $rulership;
				}
			}
		}
		return $highest;
	}

	public function isPrisoner() {
		if ($this->getPrisonerOf()) return true; else return false;
	}

	public function hasVisiblePartners() {
		foreach ($this->getPartnerships() as $ps) {
			if ($ps->getActive() && $ps->getPublic()) {
				return true;
			}
		}
		return false;
	}

	public function getFather() {
		return $this->getFatherOrMother(true);
	}
	public function getMother() {
		return $this->getFatherOrMother(false);
	}
	private function getFatherOrMother($male) {
		foreach ($this->getParents() as $parent) {
			if ($parent->getMale() == $male) return $parent;
		}
		return null;
	}

	public function findImmediateRelatives() {
		$relatives = new ArrayCollection;
		if ($this->getParents()) {
			foreach ($this->getParents() as $parent) {
				$relatives[] = $parent;
				foreach ($parent->getChildren() as $child) {
					if ($this != $child) {
						$relatives[] = $child;
					}
				}
			}
		}
		if ($this->getChildren()) {
			foreach ($this->getChildren() as $child) {
				$relatives[] = $child;
			}
		}
		return $relatives;
	}

	public function healthValue() {
		return max(0.0, ($this->full_health - $this->getWounded())) / $this->full_health;
	}

	public function healthStatus() {
		$h = $this->healthValue();
		if ($h > 0.9) return 'perfect';
		if ($h > 0.75) return 'lightly';
		if ($h > 0.5) return 'moderately';
		if ($h > 0.25) return 'seriously';
		return 'mortally';
	}

	public function isActive($include_wounded=false, $include_slumbering=false) {
		if (!$this->location) return false;
		if (!$this->alive) return false;
		if ($this->retired) return false;
		if ($this->slumbering && !$include_slumbering) return false;
		// we can take a few wounds before we go inactive
		if ($this->healthValue() < 0.9 && !$include_wounded) return false;
		if ($this->isPrisoner()) return false;
		return true;
	}

	public function isInBattle() {
		// FIXME: in dispatcher, we simply check if we're in a battlegroup...
		if ($this->hasAction('military.battle')) return true;
		if ($this->hasAction('settlement.attack')) return true;
		return false;
	}

	public function isLooting() {
		if ($this->hasAction('settlement.loot')) return true;
		return false;
	}

	public function findForcedBattles() {
		$engagements = new ArrayCollection;
		foreach ($this->findActions(array('military.battle', 'settlement.attack')) as $act) {
			if ($act->getStringValue('forced')) {
				$engagements->add($act);
			}
		}
		return $engagements;
	}

	public function getVisualSize() {
		$size = 5; // the default visual size for nobles, we're not added as a pseudo-soldier like we are in battle groups
		foreach ($this->units as $unit) {
			$size += $unit->getVisualSize();
		}
		return $size;
	}

	public function getEntourageOfType($type, $only_available=false) {
		if (is_object($type)) {
			return $this->entourage->filter(
				function($entry) use ($type, $only_available) {
					if ($only_available) {
						return ($entry->getType()==$type && $entry->isAlive() && !$entry->getAction());
					} else {
						return ($entry->getType()==$type);
					}
				}
			);
		} else {
			$type = strtolower($type);
			return $this->entourage->filter(
				function($entry) use ($type, $only_available) {
					if ($only_available) {
						return ($entry->getType()->getName()==$type && $entry->isAlive() && !$entry->getAction());
					} else {
						return ($entry->getType()->getName()==$type);
					}
				}
			);
		}
	}

	public function getAvailableEntourageOfType($type) {
		return $this->getEntourageOfType($type, true);
	}

	public function getLivingEntourage() {
		return $this->getEntourage()->filter(
			function($entry) {
				return ($entry->isAlive());
			}
		);
	}

	public function getDeadEntourage() {
		return $this->getEntourage()->filter(
			function($entry) {
				return (!$entry->isAlive());
			}
		);
	}

	public function getActiveEntourageByType() {
		return $this->getEntourageByType(true);
	}

	public function getEntourageByType($active_only=false) {
		$data = array();
		if ($active_only) {
			$npcs = $this->getLivingEntourage();
		} else {
			$npcs = $this->getEntourage();
		}
		foreach ($npcs as $npc) {
			$type = $npc->getType()->getName();
			if (isset($data[$type])) {
				$data[$type]++;
			} else {
				$data[$type] = 1;
			}
		}
		return $data;
	}

	public function getGender() {
		if ($this->male) return "male"; else return "female";
	}
	public function gender($string) {
		if ($this->male) return "gender.".$string;
		switch ($string) {
			case 'he':		return 'gender.she';
			case 'his':		return 'gender.her';
			case 'son':		return 'gender.daughter';
		}
		return "gender.".$string;
	}

	public function isAlive() {
		return $this->getAlive();
	}

	public function findUltimate() {
		if ($this->ultimate!==false) return $this->ultimate;
		if (!$liege=$this->getLiege()) {
			$this->ultimate=$this;
		} else {
			while ($liege->getLiege()) {
				$liege=$liege->getLiege();
			}
			$this->ultimate=$liege;
		}
		return $this->ultimate;
	}

	public function isUltimate() {
		if ($this->findUltimate() == $this) return true;
		return false;
	}

	public function findRealms($check_lord=true) {
		if ($this->my_realms!=null) return $this->my_realms;

		$realms = new ArrayCollection;
		foreach ($this->getPositions() as $position) {
			if (!$realms->contains($position->getRealm())) {
				$realms->add($position->getRealm());
			}
		}
		foreach ($this->getOwnedSettlements() as $estate) {
			if ($realm = $estate->getRealm()) {
				if (!$realms->contains($realm)) {
					$realms->add($realm);
				}
			}
		}
		foreach ($this->getOwnedPlaces() as $place) {
			if ($realm = $place->getRealm()) {
				if (!$realms->contains($realm)) {
					$realms->add($realm);
				}
			}
		}

		if ($check_lord && $this->findAllegiance()) {
			$alg = $this->findAllegiance();
			if (!($alg instanceof Realm)) {
				if ($alg->getRealm() != NULL) {
					if (!$realms->contains($alg->getRealm())) {
						$realms->add($alg->getRealm());
					}
				} elseif ($alg instanceof Character) {
					foreach ($alg->findRealms() as $realm) {
						# Backwards compatibility junk. Remove this when we remvoe $this->liege.
						if (!$realms->contains($realm)) {
							$realms->add($realm);
						}
					}
				}
			} else {
				if ($alg != NULL) {
					if (!$realms->contains($alg)) {
						$realms->add($alg);
					}
				}
			}
		} elseif ($check_lord && $this->getLiege()) {
			foreach ($this->getLiege()->findRealms(false) as $lordrealm) {
				if (!$realms->contains($lordrealm)) {
					$realms->add($lordrealm);
				}
			}
		}

		foreach ($realms as $realm) {
			foreach ($realm->findAllSuperiors() as $suprealm) {
				if (!$realms->contains($suprealm)) {
					$realms->add($suprealm);
				}
			}
		}
		$this->my_realms = $realms;

		return $realms;
	}

	public function findHouses() {
		if ($this->my_houses!=null) return $this->my_houses;
		$houses = new ArrayCollection;
		if ($this->getHouse()) {
			$houses[] = $this->getHouse();
		}
		foreach ($houses as $house) {
			foreach ($house->findAllSuperiors() as $suphouse) {
				if (!$houses->contains($suphouse)) {
					$houses->add($suphouse);
				}
			}
		}
		$this->my_houses = $houses;
		return $houses;
	}

	public function findAssociations() {
		if ($this->my_assocs!=null) return $this->my_assocs;
		$assocs = new ArrayCollection;
		foreach ($this->getAssociationMemberships() as $mbr) {
			$assocs->add($mbr->getAssociation());
		}
		$this->my_assocs = $assocs;
		return $assocs;
	}
	
	public function findSubcreateableAssociations($except = null) {
		$avoid = new ArrayCollection;
		if ($except) {
			$avoid->add($except);
			foreach ($except->findAllInferiors(false) as $minor) {
				$avoid->add($minor);
			}
		}
		$assocs = new ArrayCollection;
		foreach ($this->getAssociationMemberships() as $mbr) {
			if ($rank = $mbr->getRank()) {
				$possible = $mbr->getAssociation();
				if (($rank->getOwner() || $rank->getCreateAssocs()) && !$avoid->contains($possible)) {
					$assocs->add($possible);
				}
			}
		}
		return $assocs;
	}

	public function hasNewEvents() {
		foreach ($this->getReadableLogs() as $log) {
			if ($log->hasNewEvents()) {
				return true;
			}
		}
		return false;
	}

	public function countNewEvents() {
		$count=0;
		foreach ($this->getReadableLogs() as $log) {
			$count += $log->countNewEvents();
		}
		return $count;
	}

	public function hasNewMessages() {
		$permissions = $this->getConvPermissions()->filter(function($entry) {return $entry->getUnread() > 0;});
		if ($permissions->count() > 0) {
			return true;
		}
		return false;
	}

	public function countNewMessages() {
		$permissions = $this->getConvPermissions()->filter(function($entry) {return $entry->getUnread() > 0;});
		$total = 0;
		if ($permissions->count() > 0) {
			foreach ($permissions as $perm) {
				$total += $perm->getUnread();
			}
			return $total;
		}
		return $total;
	}

	public function findActions($key) {
		return $this->actions->filter(
			function($entry) use ($key) {
				if (is_array($key)) {
					return in_array($entry->getType(), $key);
				} else {
					return ($entry->getType()==$key);
				}
			}
		);
	}

	public function hasAction($key) {
		return ($this->findActions($key)->count()>0);
	}

	public function findForeignAffairsRealms() {
		$realms = new ArrayCollection();
		foreach ($this->getPositions() as $pos) {
			if ($pos->getRuler()) {
				$realms->add($pos->getRealm()->getId());
			}
			if ($pos->getType() && $pos->getType()->getName() == 'foreign affairs') {
				$realms->add($pos->getRealm()->getId());
			}
		}
		if ($realms->isEmpty()) {
			return null;
		} else {
			return $realms;
		}
	}

	public function countSoldiers() {
		$count = 0;
		if (!$this->getUnits()->isEmpty()) {
			foreach ($this->getUnits() as $unit) {
				$count += $unit->getActiveSoldiers()->count();
			}
		}
		return $count;
	}

	public function hasNoSoldiers() {
		if ($this->countSoldiers() == 0) {
			return true;
		}
		return false;
	}

	public function findAllegiance() {
		if ($this->realm) {
			return $this->getRealm();
		}
		if ($this->liege_land) {
			return $this->getLiegeLand();
		}
		if ($this->liege_place) {
			return $this->getLiegePlace();
		}
		if ($this->liege_position) {
			return $this->getLiegePosition();
		}
		if ($this->liege) {
			return $this->getLiege();
		}
		return null;
	}

	public function findVassals() {
		$vassals = new ArrayCollection();
		foreach ($this->getPositions() as $key) {
			if ($key->getRuler()) {
				foreach ($key->getRealm()->getVassals() as $val) {
					$vassals->add($val);
				}
			}
			foreach ($key->getVassals() as $val) {
				$vassals->add($val);
			}
		}
		foreach ($this->getOwnedPlaces() as $key) {
			if ($key->getType()->getName() != 'embassy') {
				foreach ($key->getVassals() as $val) {
					$vassals->add($val);
				}
			}
		}
		foreach ($this->getOwnedSettlements() as $key) {
			foreach ($key->getVassals() as $val) {
				$vassals->add($val);
			}
		}
		foreach ($this->getAmbassadorships() as $key) {
			foreach ($key->getVassals() as $val) {
				$vassals->add($val);
			}
		}
		return $vassals;
	}

	public function findPrimaryRealm() {
		if ($this->realm) {
			return $this->getRealm();
		}
		if ($this->liege_land) {
			return $this->getLiegeLand()->getRealm();
		}
		if ($this->liege_place) {
			return $this->getLiegePlace()->getRealm();
		}
		if ($this->liege_position) {
			return $this->getLiegePosition()->getRealm();
		}
		return null;
	}

	public function findLiege() {
		$alleg = $this->findAllegiance();
		if ($alleg instanceof Character) {
			return $alleg;
		}
		if ($alleg instanceof Realm) {
			return $alleg->findRulers();
		}
		if ($alleg instanceof Settlement) {
			return $alleg->getOwner();
		}
		if ($alleg instanceof Place) {
			if ($alleg->getType()->getName() != 'embassy') {
				return $alleg->getOwner();
			} else {
				return $alleg->getAmbassador();
			}
		}
		if ($alleg instanceof RealmPosition) {
			return $alleg->getHolders();
		}
		return null;
	}

	public function findControlledSettlements() {
		$all = new ArrayCollection;
		foreach ($this->getOwnedSettlements() as $each) {
			if (!$each->getOccupant() && !$each->getOccupier()) {
				$all->add($each);
			}
		}
		foreach ($this->getOccupiedSettlements() as $each) {
			$all->add($each);
		}
		foreach ($this->getStewardingSettlements() as $each) {
			if (!$each->getOccupant() && !$each->getOccupier()) {
				$all->add($each);
			}
		}
		return $all;
	}

	public function findAnswerableDuels() {
		$all = new ArrayCollection;
		foreach ($this->getActivityParticipation() as $each) {
			$act = $each->getActivity();
			if ($act->isAnswerable($this)) {
				$all->add($act);
			}
		}
		return $all;
	}

	public function getType() {
		return 'first one';
	}

	public function findSkill(SkillType $skill) {
		foreach ($this->skills as $each) {
			if ($each->getType() === $skill) {
				return $each;
			}
		}
		return false;
	}
	
}
