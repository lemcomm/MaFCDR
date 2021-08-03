<?php

namespace BM2\SiteBundle\Entity;


class Soldier extends NPC {

	protected $morale=0;
	protected $is_fortified=false;
	protected $ranged=-1, $melee=-1, $defense=-1, $charge=-1;
	protected $isNoble = false;
	protected $isFighting = false;
	protected $attacks = 0;
	protected $casualties = 0;
	protected $xp_gained = 0;


	public function __toString() {
		$base = $this->getBase()?$this->getBase()->getId():"%";
		$char = $this->getCharacter()?$this->getCharacter()->getId():"%";
		return "soldier #{$this->id} ({$this->getName()}, {$this->getType()}, base $base, char $char)";
	}

	public function isActive($include_routed=false) {
		if (!$this->isAlive() || $this->getTrainingRequired() > 0 || $this->getTravelDays() > 0) return false;
		if ($this->getType()=='noble') {
			// nobles have their own active check
			return $this->getCharacter()->isActive($include_routed, true);
		}
		// we can take a few wounds before we go inactive
		$can_take = 1;
		if ($this->getExperience() > 10) $can_take++;
		if ($this->getExperience() > 30) $can_take++;
		if ($this->getExperience() > 100) $can_take++;
		if (parent::getWounded() > $can_take) return false;
		if (!$include_routed && $this->isRouted()) return false;
		return true;
	}

	public function wound($value=1) {
		parent::wound($value);
		if ($this->getType()=='noble') {
			$this->getCharacter()->setWounded($this->getCharacter()->getWounded()+$value);
		}
		return $this;
	}

	public function getWounded($character_real=false) {
		if (!$character_real || $this->getType()!='noble') return parent::getWounded();
		return $this->getCharacter()->getWounded();
	}

	public function setFighting($value) {
		$this->isFighting = $value;
		return $this;
	}
	public function isFighting() {
		return $this->isFighting;
	}

	public function getAttacks() {
		return $this->attacks;
	}
	public function addAttack($value=1) {
		$this->attacks += $value;
	}
	public function resetAttacks() {
		$this->attacks = 0;
	}

	public function addXP($xp) {
		$this->xp_gained += $xp;
	}

	public function addCasualty() {
		$this->casualties++;
	}
	public function getCasualties() {
		return $this->casualties;
	}
	public function resetCasualties() {
		$this->casualties = 0;
	}

	public function getMorale() { return $this->morale; }
	public function setMorale($value) { $this->morale=$value; return $this; }
	public function reduceMorale($value=1) { $this->morale-=$value; return $this; }
	public function gainMorale($value=1) { $this->morale+=$value; return $this; }

	public function getAllInUnit() {
		if ($this->isNoble) {
			return $this;
		}
		return $this->getUnit()->getSoldiers();
	}

	public function getType() {
		if ($this->isNoble) return 'noble';
		if (!$this->weapon && !$this->armour && !$this->equipment) return 'rabble';

		$def = 0;
		if ($this->armour) { $def += $this->armour->getDefense(); }
		if ($this->equipment) { $def += $this->equipment->getDefense(); }

		if ($this->mount) {
			if ($this->weapon && $this->weapon->getRanged() > 0) {
				return 'mounted archer';
			} else {
				if ($def >= 80) {
					return 'heavy cavalry';
				} else {
					return 'light cavalry';
				}
			}
		}
		if ($this->weapon && $this->weapon->getRanged() > 0) {
			if ($def >= 50) {
				return 'armoured archer';
			} else {
				return 'archer';
			}
		}
		if ($this->armour && $this->armour->getDefense() >= 60) {
			return 'heavy infantry';
		}

		if ($def >= 40) {
			return 'medium infantry';
		}
		return 'light infantry';
	}

	public function getVisualSize() {
		switch ($this->getType()) {
			case 'noble':					return 5;
			case 'cavalry':
			case 'light cavalry':
			case 'heavy cavalry':		return 4;
			case 'mounted archer':		return 3;
			case 'armoured archer':     return 3;
			case 'archer':				return 2;
			case 'heavy infantry':		return 3;
			case 'medium infantry':		return 2;
			case 'light infantry':
			default:							return 1;
		}
	}

	public function isFortified() {
		return $this->is_fortified;
	}
	public function setFortified($state=true) {
		$this->is_fortified = $state;
		return $this;
	}

	public function getWeapon() {
		if ($this->has_weapon) return $this->weapon;
		return null;
	}
	public function getArmour() {
		if ($this->has_armour) return $this->armour;
		return null;
	}
	public function getEquipment() {
		if ($this->has_equipment) return $this->equipment;
		return null;
	}
	public function getMount() {
		if ($this->has_mount) return $this->mount;
		return null;
	}
	public function getTrainedWeapon() {
		return $this->weapon;
	}
	public function getTrainedArmour() {
		return $this->armour;
	}
	public function getTrainedEquipment() {
		return $this->equipment;
	}
	public function getTrainedMount() {
		return $this->mount;
	}
	public function setWeapon(EquipmentType $item=null) {
		$this->weapon = $item;
		$this->has_weapon = true;
		return $this;
	}
	public function setArmour(EquipmentType $item=null) {
		$this->armour = $item;
		$this->has_armour = true;
		return $this;
	}
	public function setEquipment(EquipmentType $item=null) {
		$this->equipment = $item;
		$this->has_equipment = true;
		return $this;
	}
	public function setMount(EquipmentType $item=null) {
		$this->mount = $item;
		$this->has_mount = true;
		return $this;
	}
	public function dropWeapon() {
		$this->has_weapon = false;
		return $this;
	}
	public function dropArmour() {
		$this->has_armour = false;
		return $this;
	}
	public function dropEquipment() {
		$this->has_equipment = false;
		return $this;
	}
	public function dropMount() {
		$this->has_mount = false;
		return $this;
	}


	public function setNoble($is=true) {
		$this->isNoble = $is;
	}
	public function isNoble() {
		return $this->isNoble;
	}

	public function isRouted() {
		return $this->getRouted();
	}


	public function isMilitia() {
		return ($this->getTrainingRequired()<=0);
	}
	public function isRecruit() {
		return ($this->getTrainingRequired()>0);
	}

	public function isRanged() {
		if ($this->getWeapon() && $this->getWeapon()->getRanged() > $this->getWeapon()->getMelee()) {
			return true;
		} else {
			return false;
		}
	}

	public function isLancer() {
		if ($this->getMount() && $this->getEquipment() && $this->getEquipment()->getName() == 'Lance') {
			return true;
		} else {
			return false;
		}
	}

	public function RangedPower() {
//		if (!$this->isActive()) return 0; -- disabled - it prevents counter-attacks
		if ($this->ranged!=-1) return $this->ranged;

		$power = 0;
		if ($this->getWeapon()) {
			$power += $this->getWeapon()->getRanged();
		}
		if ($this->getEquipment()) {
			if ($this->getEquipment()->getRanged() > $power) {
				$power = $this->getEquipment()->getRanged();
			}
		}

		// all the below only adds if we have some ranged power to start with
		if ($power<=0) return 0;

		$power += $this->ExperienceBonus($power);

		// TODO: heavy armour should reduce this quite a bit

		if ($this->isNoble) {
			$this->ranged = $power;
		}else {
			$fighters = $this->getAllInUnit()->count();
			if ($fighters>1) {
				$this->ranged = $power * pow($fighters, 0.96)/$fighters;
			} else {
				$this->ranged = $power;
			}
		}
		return $this->ranged;
	}

	public function MeleePower() {
//		if (!$this->isActive()) return 0; -- disabled - it prevents counter-attacks
		if ($this->melee!=-1) return $this->melee;

		$power = 0;
		if ($this->getWeapon()) {
			$power += $this->getWeapon()->getMelee();
		} else {
			// improvised weapons
			$power += 5;
		}
		if ($this->getEquipment()) {
			if ($this->getEquipment()->getName() != 'Lance') {
				$power += $this->getEquipment()->getMelee();
			}
		}
		if ($this->getMount()) {
			$power += $this->getMount()->getMelee();
		}
		if ($power>0) {
			$power += $this->ExperienceBonus($power);
		}

		// TODO: heavy armour should reduce this a little

		if ($this->isNoble) {
			$this->melee = $power;
		} else {
			$fighters = $this->getAllInUnit()->count();
			if ($fighters>1) {
				$this->melee = $power * pow($fighters, 0.96)/$fighters;
			} else {
				$this->melee = $power;
			}
		}
		return $this->melee;
	}

	public function ChargePower() {
//		if (!$this->isActive()) return 0; -- disabled - it prevents counter-attacks

		if (!$this->getMount()) {
			return 0;
		}
		if ($this->getEquipment()) {
			$power = $this->getEquipment()->getMelee();
		}
		if ($this->getMount()) {
			$power += $this->getMount()->getMelee();
		}
		$power += $this->ExperienceBonus($power);

		$this->charge = $power;
		return $this->charge;
	}

	public function DefensePower($melee = true) {
//		if (!$this->getAlive() || $this->isWounded()) return 0;
		if ($this->defense!=-1) return $this->defense;

		$power = 5; // basic defense power which represents luck, instinctive dodging, etc.
		if ($this->getArmour()) {
			$power += $this->getArmour()->getDefense();
		}
		if ($this->getEquipment()) {
			if ($this->getEquipment()->getName() != 'Pavise') {
				$power += $this->getEquipment()->getDefense();
			} elseif ($this->hasMount()) {
				$power += $this->getEquipment()->getDefense()/10; #It isn't worthless, but it can't be used effectively.
			} elseif ($melee) {
				$power += $this->getEquipment()->getDefense()/5;
			} else {
				$power += $this->getEquipment()->getDefense();
			}
		}
		if ($this->getMount()) {
			$power += $this->getMount()->getDefense();
		}

		$power += $this->ExperienceBonus($power);
		$this->defense = $power; // defense does NOT scale down with number of men in the unit
		return $this->defense;
	}

	private function ExperienceBonus($power) {
		$bonus = sqrt($this->getExperience()*5);
		return min($power/2, $bonus);
	}


	public function onPreRemove() {
		if ($this->getUnit()) {
			$this->getUnit()->removeSoldier($this);
		}
		if ($this->getCharacter()) {
			$this->getCharacter()->removeSoldiersOld($this);
		}
		if ($this->getBase()) {
			$this->getBase()->removeSoldiersOld($this);
		}
		if ($this->getLiege()) {
			$this->getLiege()->removeSoldiersGiven($this);
		}
	}
	
}
