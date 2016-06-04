<?php 

namespace BM2\SiteBundle\Entity;


class Soldier extends NPC {

	protected $morale=0;
	protected $is_fortified=false;
	protected $ranged=-1, $melee=-1, $defense=-1;
	protected $isNoble = false;
	protected $isFighting = false;
	protected $attacks = 0;
	protected $casualties = 0;


	public function __toString() {
		$base = $this->getBase()?$this->getBase()->getId():"%";
		$char = $this->getCharacter()?$this->getCharacter()->getId():"%";
		return "soldier #{$this->id} ({$this->getName()}, {$this->getType()}, base $base, char $char)";
	}

	public function isSoldier() {
		return true;
	}


	public function isActive($include_routed=false) {
		if (!$this->isAlive()) return false;
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

	public function getUnit() {
		if ($this->getCharacter()) {
			return $this->getCharacter()->getSoldiers();
		} elseif ($this->getBase()) {
			return $this->getBase()->getSoldiers();
		} else {
			return null;
		}
	}

	public function getGroupName() {
		if ($this->group!==null) {
			$groups = range('a','z');
			return $groups[$this->group];
		} else {
			return '';
		}

	}

	public function getType() {
		if ($this->isNoble) return 'noble';
		if (!$this->weapon && !$this->armour && !$this->equipment) return 'rabble';

		$def = 0;
		if ($this->armour) { $def += $this->armour->getDefense(); }
		if ($this->equipment) { $def += $this->equipment->getDefense(); }

		if ($this->equipment && ($this->equipment->getName()=='horse' || $this->equipment->getName()=='war horse') ) {
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
	public function getTrainedWeapon() {
		return $this->weapon;
	}
	public function getTrainedArmour() {
		return $this->armour;
	}
	public function getTrainedEquipment() {
		return $this->equipment;
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

	public function cleanOffers() {
		if ($this->getOfferedAs()) {
			$this->getOfferedAs()->removeSoldier($this);
			$this->setOfferedAs(null);
		}
	}

	public function isRanged() {
		if ($this->getWeapon() && $this->getWeapon()->getRanged() > $this->getWeapon()->getMelee()) {
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
			$power += $this->getEquipment()->getRanged();
		}

		// all the below only adds if we have some ranged power to start with
		if ($power<=0) return 0;

		$power += $this->ExperienceBonus($power);

		// TODO: heavy armour should reduce this quite a bit

		$fighters = $this->getUnit()->count();
		if ($fighters>1) {
			$this->ranged = $power * pow($fighters, 0.96)/$fighters;
		} else {
			$this->ranged = $power;
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
			$power += $this->getEquipment()->getMelee();
		}
		if ($power>0) {
			$power += $this->ExperienceBonus($power);
		}

		// TODO: heavy armour should reduce this a little

		$fighters = $this->getUnit()->count();
		if ($fighters>1) {
			$this->melee = $power * pow($fighters, 0.96)/$fighters;
		} else {
			$this->melee = $power;
		}
		return $this->melee;
	}

	public function DefensePower() {
//		if (!$this->getAlive() || $this->isWounded()) return 0;
		if ($this->defense!=-1) return $this->defense;

		$power = 5; // basic defense power which represents luck, instinctive dodging, etc.
		if ($this->getArmour()) {
			$power += $this->getArmour()->getDefense();
		}
		if ($this->getEquipment()) {
			$power += $this->getEquipment()->getDefense();
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
		$this->cleanOffers();
		if ($this->getCharacter()) {
			$this->getCharacter()->removeSoldier($this);
		}
		if ($this->getBase()) {
			$this->getBase()->removeSoldier($this);
		}
		if ($this->getLiege()) {
			$this->getLiege()->removeSoldier($this);			
		}
		if ($this->getMercenary()) {
			$this->getMercenary()->removeSoldier($this);
		}
	}

}
