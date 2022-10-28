<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\ActivityParticipant;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;


class CombatManager {

	/*
	This service exists purely to prevent code duplication and circlic service requiremenets.
	Things that should exist in multiple services but can't due to circlic loading should be here.
	*/

	protected $em;
	protected $helper;
	protected $charMan;
	protected $history;

	public function __construct(EntityManager $em, HelperService $helper, CharacterManager $charMan, History $history) {
		$this->em = $em;
		$this->helper = $helper;
		$this->charMan = $charMan;
		$this->history = $history;
	}

	public function ChargeAttack($me, $target, $act=false, $battle=false, $xpMod = 1, $defBonus = null) {
		if ($battle) {
			if ($soldier->isNoble() && $soldier->getWeapon()) {
				$this->helper->trainSkill($soldier->getCharacter(), $soldier->getEquipment()->getSkill(), $xpMod);
			} else {
				$soldier->gainExperience(1*$xpMod);
			}
			$type = 'battle';
		} elseif ($act) {
			$type = 'act';
		}
		$logs = [];
		$result='miss';

		$attack = $this->ChargePower($soldier, true);
		$defense = $this->DefensePower($target, $battle)*0.75;

		$eWep = $target->getWeapon();
		if ($eWep->getType()->getSkill()->getCategory()->getName() == 'polearms') {
			$antiCav = True;
		} else {
			$antiCav = False;
		}


		$logs[] = $target->getName()." (".$target->getType().") - ";
		$logs[] = (round($attack*10)/10)." vs. ".(round($defense*10)/10)." - ";
		if (rand(0,$attack) > rand(0,$defense)) {
			// defense penetrated
			$result = $this->resolveDamage($soldier, $target, $attack, $type, 'charge', $antiCav, $xpMod, $defBonus);
			if ($soldier->isNoble() && $soldier->getWeapon()) {
				$this->helper->trainSkill($soldier->getCharacter(), $soldier->getWeapon()->getSkill(), $xpMod);
			} else {
				$soldier->gainExperience(($result=='kill'?2:1)*$xpMod);
			}
		} else {
			// armour saved our target
			$logs[] = "no damage\n";
			$result='fail';
		}
		$target->addAttack(5);
		$sublogs = $this->equipmentDamage($soldier, $target, 'charge', $antiCav);
		foreach ($sublogs as $each) {
			$logs[] = $each;
		}

		return [$result, $logs];
	}

	public function ChargePower($me, $sol = false) {
		$noble = false;
		if ($sol) {
			if ($me->isNoble()) {
				return 156;
			}
		} elseif ($me instanceof ActivityParticipant) {
			$me = $me->getCharacter();
		}
		$power = 0;
		if (!$me->getMount()) {
			return 0;
		} else {
			$power += $me->getMount()->getMelee();
		}
		if ($me->getEquipment()) {
			$power += $me->getEquipment()->getMelee();
		}
		$power += $me->ExperienceBonus($power);

		return $power;
	}

	public function DefensePower($me, $sol = false, $melee = true) {
		$noble = false;
		# $sol is just a bypass for "Is this a soldier instance" or not.
		if ($sol) {
			if ($melee) {
				if ($me->defense!=-1) return $me->defense;
			} else {
				if ($me->rDefense!=-1) return $me->rDefense;
			}
			if ($me->isNoble()) {
				$noble = true;
			}
		} elseif ($me instanceof ActivityParticipant) {
			$me = $me->getCharacter();
		}

		$eqpt = $me->getEquipment();
		if ($noble) {
			# Only for battles.
			$power = 100;
			if ($me->getMount()) {
				$power += 38;
			}
			if ($eqpt && $eqpt->getName() != 'Pavise') {
				$power += 32;
			} elseif ($me->getMount()) {
				$power += 7;
			}  elseif ($melee) {
				$power += 13;
			} else {
				$power += 63;
			}
			if ($melee) {
				$me->defense = $power;
			} else {
				$me->rDefense = $power;
			}
			return $power;
		}

		$power = 5; // basic defense power which represents luck, instinctive dodging, etc.
		if ($me->getArmour()) {
			$power += $me->getArmour()->getDefense();
		}
		if ($me->getEquipment()) {
			if ($me->getEquipment()->getName() != 'Pavise') {
				$power += $this->getEquipment()->getDefense();
			} elseif ($me->getMount()) {
				$power += $me->getEquipment()->getDefense()/10; #It isn't worthless, but it can't be used effectively.
			} elseif ($melee) {
				$power += $me->getEquipment()->getDefense()/5;
			} else {
				$power += $me->getEquipment()->getDefense();
			}
		}
		if ($me->getMount()) {
			$power += $me->getMount()->getDefense();
		}

		$power += $me->ExperienceBonus($power);
		if ($sol) {
			if ($melee) {
				$me->updateDefensePower($power); // defense does NOT scale down with number of men in the unit
			} else {
				$me->updateRDefensePower($power);
			}
		}
		return $power;
	}

	public function equipmentDamage($attacker, $target) {
		// small chance of armour or item damage - 10-30% per hit and then also depending on the item - 3%-14% - for total chances of ca. 1%-5% per hit
		$logs = [];
		if (rand(0,100)<15) {
			if ($attacker->getWeapon()) {
				$resilience = 30 - 3*sqrt($attacker->getWeapon()->getMelee() + $attacker->getWeapon()->getRanged());
				if (rand(0,100)<$resilience) {
					$attacker->dropWeapon();
					$logs[] = "attacker weapon damaged\n";
				}
			}
		}
		if (rand(0,100)<10) {
			if ($target->getWeapon()) {
				$resilience = 30 - 3*sqrt($target->getWeapon()->getMelee() + $target->getWeapon()->getRanged());
				if (rand(0,100)<$resilience) {
					$target->dropWeapon();
					$logs[] = "weapon damaged\n";
				}
			}
		}
		if (rand(0,100)<30) {
			if ($target->getArmour()) {
				$resilience = 30 - 3*sqrt($target->getArmour()->getDefense());
				if (rand(0,100)<$resilience) {
					$target->dropArmour();
					$logs[] = "armour damaged\n";
				}
			}
		}
		if (rand(0,100)<25) {
			if ($target->getEquipment() && $target->getEquipment()->getDefense()>0) {
				$resilience = sqrt($target->getEquipment()->getDefense());
				if (rand(0,100)<$resilience) {
					$target->dropEquipment();
					$logs[] = "equipment damaged\n";
				}
			}
		}
		return $logs;
	}

	public function MeleeAttack($me, $target, $mPower, $act=false, $battle=false, $xpMod = 1, $defBonus = null) {
		if ($battle) {
			if ($me->isNoble() && $me->getWeapon()) {
				$this->helper->trainSkill($me->getCharacter(), $me->getWeapon()->getSkill(), $xpMod);
			} else {
				$me->gainExperience(1*$xpMod);
			}
			$type = 'battle';
		} elseif ($act) {
			$type = 'act';
		}
		$logs = [];
		$result='miss';

		if ($act && $act->getWeaponOnly()) {
			$defense = 0;
		} else {
			$defense = $this->DefensePower($target, $battle);
		}
		$attack = $mPower;

		if ($battle) {
			if ($target->isFortified()) {
				$defense += $defBonus;
			}
			if ($me->isFortified()) {
				$attack += ($defBonus/2);
			}
		}

		$logs[] = $target->getName()." (".$target->getType().") - ";
		$logs[] = (round($attack*10)/10)." vs. ".(round($defense*10)/10)." - ";
		if (rand(0,$attack) > rand(0,$defense)) {
			// defense penetrated
			$result = $this->resolveDamage($me, $target, $attack, $type, 'melee');
			if ($me->isNoble() && $me->getWeapon()) {
				$this->helper->trainSkill($me->getCharacter(), $me->getWeapon()->getSkill(), $xpMod);
			} else {
				$me->gainExperience(($result=='kill'?2:1)*$xpMod);
			}
		} else {
			// armour saved our target
			$logs[] = "no damage\n";
			$result='fail';
		}
		if ($battle) {
			$target->addAttack(5);
			$this->equipmentDamage($me, $target);
		}

		return [$result, $logs];
	}

	public function MeleePower($me, $sol = false) {
		$noble = false;
		# $sol is just a bypass for "Is this a soldier instance" or not.
		if ($sol) {
			if ($me->MeleePower() != -1) return $me->MeleePower();
			if ($me->isNoble()) {
				$noble = true;
			}
		} elseif ($me instanceof ActivityParticipant) {
			$me = $me->getCharacter();
		}

		$power = 0;
		$hasW = false;
		$hasM = false;
		$hasE = false;
		if ($me->getWeapon()) {
			if ($mPower = $me->getWeapon()->getMelee() > 0) {
				$hasW = true;
				$power += $mPower;
			}
		} else {
			// improvised weapons
			$power += 5;
		}
		if ($me->getEquipment()) {
			if ($me->getEquipment()->getName() != 'Lance') {
				$power += $me->getEquipment()->getMelee();
				$hasE = true;
			}
		}
		if ($me->getMount()) {
			$power += $me->getMount()->getMelee();
			$hasM = false;
		}
		if ($noble) {
			# Only for battles.
			$power = 0;
			if ($hasW) {
				$power += 112;
			}
			if ($hasM) {
				$power += 32;
			}
			if ($hasE) {
				$power += 12;
			}
			return $power;
		}
		if ($power>0) {
			$power += $me->ExperienceBonus($power);
		}

		// TODO: heavy armour should reduce this a little
		if ($sol) {
			$fighters = $sol->getAllInUnit()->count();
			if ($fighters>1) {
				$sol->updateMeleePower($power * pow($fighters, 0.96)/$fighters);
			} else {
				$sol->updateMeleePower($power);
			}
		}
		return $power;
	}

	public function MeleeRoll($defBonus = 0, $rangedPenalty = 1, $rangedBonus = 0, $base = 95) {
		if (rand(0,100+$defBonus)<max($base*$rangedPenalty,$rangedBonus*$rangedPenalty)) {
			return true;
		} else {
			return false;
		}
	}

	public function RangedHit($me, $target, $rPower, $act=false, $battle=false, $xpMod = 1, $defBonus = null) {
		if ($battle) {
			if ($me->isNoble() && $me->getWeapon()) {
				if (in_array($me->getType(), ['armoured archer', 'archer'])) {
					$this->helper->trainSkill($me->getCharacter(), $me->getWeapon()->getSkill(), $xpMod);
				} else {
					if ($soldier->getEquipment()) {
						$this->helper->trainSkill($me->getCharacter(), $me->getEquipment()->getSkill(), $xpMod);
					}
				}
			} else {
				$me->gainExperience(1*$xpMod);
			}
			$type = 'battle';
		} elseif ($act) {
			$type = $me->getActivity()->getType()->getName();
		}
		$logs = [];
		$result='miss';

		if ($act && $act->getWeaponOnly()) {
			$defense = 0;
		} else {
			$defense = $this->DefensePower($target, $battle, false);
		}
		$attack = $rPower;

		if ($battle) {
			if ($target->isFortified()) {
				$defense += $defBonus;
			}
			if ($me->isFortified()) {
				// small bonus to attack to simulate towers height advantage, etc.
				$attack += $defBonus/5;
			}
		}

		$logs[] = "hits ".$target->getName()." (".$target->getType().") - (".round($attack)." vs. ".round($defense).") = ";

		if (rand(0,$attack) > rand(0,$defense)) {
			// defense penetrated
			$result = $this->resolveDamage($me, $target, $attack, $type, 'ranged');
		} else {
			// armour saved our target
			$logs[] = "no damage\n";
			$result='fail';
		}

		if ($battle) {
			$target->addAttack(2);
			$this->equipmentDamage($me, $target);
		}
		return [$result, $logs];
	}

	public function RangedPower($me, $sol = false) {
		$noble = false;
		# $sol is just a bypass for "Is this a soldier instance" or not.
		if ($sol) {
			if ($me->RangedPower() != -1) return $me->RangedPower();
			if ($me->isNoble()) {
				$noble = true;
			}
		} elseif ($me instanceof ActivityParticipant) {
			$me = $me->getCharacter(); #for stndardizing the getEquipment type calls.
		}
//		if (!$this->isActive()) return 0; -- disabled - it prevents counter-attacks

		$power = 0;
		$hasW = false;
		$hasE = false;
		if ($me->getWeapon()) {
			if ($rPower = $me->getWeapon()->getRanged()) {
				$hasW = true;
				$power += $rPower;
			}
		}
		if ($me->getEquipment()) {
			if ($me->getEquipment()->getRanged() > $power) {
				$power = $me->getEquipment()->getRanged();
				$hasE = true;
			}
		}

		// all the below only adds if we have some ranged power to start with
		if ($power<=0) return 0;

		if ($noble) {
			# Only for battles.
			$power = 0;
			if ($hasW) {
				$power += 112;
			} elseif ($hasE) {
				$power += 81;
			}
			return $power;
		}

		$power += $me->ExperienceBonus($power);

		// TODO: heavy armour should reduce this quite a bit

		if ($sol) {
			$fighters = $sol->getAllInUnit()->count();
			if ($fighters>1) {
				$sol->updateRangedPower($power * pow($fighters, 0.96)/$fighters);
			} else {
				$sol->updateRangedPower($power);
			}
		}

		return $power;
	}

	public function RangedRoll($defBonus = 0, $rangedPenalty = 1, $rangedBonus = 0, $base = 75) {
		if (rand(0-$defBonus,100)<max($base*$rangedPenalty,$rangedBonus*$rangedPenalty)) {
			return true;
		} else {
			return false;
		}
	}

	public function resolveDamage($me, $target, $power, $type, $phase = null, $antiCav = false, $xpMod = 1, $defBonus = null) {
		// this checks for penetration again AND low-damage weapons have lower lethality AND wounded targets die more easily
		// TODO: attacks on mounted soldiers could kill the horse instead
		$logs = [];
		if ($type === 'battle') {
			if (rand(0,$power) > rand(0,max(1,$this->DefensePower($target, true) - $target->getWounded(true)))) {
				// penetrated again = kill
				switch ($phase) {
					case 'charge':  $surrender = 90; break;
					case 'ranged':	$surrender = 60; break;
					case 'hunt':	$surrender = 85; break;
					case 'melee':
					default:	$surrender = 75; break;
				}
				// nobles can surrender and be captured instead of dying - if their attacker belongs to a noble
				if (($soldier->getMount() && $target->getMount() && rand(0,100) < 50) || $soldier->getMount() && !$target->getMount() && rand(0,100) < 70) {
					$logs[] = "killed mount & wounded\n";
					$target->wound(rand(max(1, round($power/10)), $power));
					$target->dropMount();
					$this->history->addToSoldierLog($target, 'wounded.'.$phase);
					$result='wound';
				} else if ($target->isNoble() && !$target->getCharacter()->isNPC() && rand(0,100) < $surrender && $soldier->getCharacter()) {
					$logs[] = "captured\n";
					$this->charMan->imprison_prepare($target->getCharacter(), $soldier->getCharacter());
					$this->history->logEvent($target->getCharacter(), 'event.character.capture', array('%link-character%'=>$soldier->getCharacter()->getId()), History::HIGH, true);
					$result='capture';
					$this->charMan->addAchievement($soldier->getCharacter(), 'captures');
				} else {
					if ($soldier->isNoble()) {
						if ($target->isNoble()) {
							$this->charMan->addAchievement($soldier->getCharacter(), 'kills.nobles');
						} else {
							$this->charMan->addAchievement($soldier->getCharacter(), 'kills.soldiers');
						}
					}
					$logs[] = "killed\n";
					$target->kill();
					$this->history->addToSoldierLog($target, 'killed');
					$result='kill';
				}
			} else {
				$logs[] = "wounded\n";
				$target->wound(rand(max(1, round($power/10)), $power));
				$this->history->addToSoldierLog($target, 'wounded.'.$phase);
				$result='wound';
				$target->gainExperience(1*$this->xpMod); // it hurts, but it is a teaching experience...
			}
			if ($antiCav) {
				$tPower = $this->MeleePower($target, true);
				list($innerResult, $sublogs) = $this->MeleeAttack($target, $soldier, $tPower, false, true, $xpMod, $defBonus); // Basically, an attack of opportunity.
				foreach ($sublogs as $each) {
					$logs[] = $each;
				}
				$result = $result . " " . $innerResult;
			} else {
				$innerResult = null;
			}

			$soldier->addCasualty();

			// FIXME: these need to take unit sizes into account!
			// FIXME: maybe we can optimize this by counting morale damage per unit and looping over all soldiers only once?!?!
			// every casualty reduces the morale of other soldiers in the same unit
			foreach ($target->getAllInUnit() as $s) { $s->reduceMorale(1); }
			// enemy casualties make us happy - +5 for the killer, +1 for everyone in his unit
			foreach ($soldier->getAllInUnit() as $s) { $s->gainMorale(1); }
			$soldier->gainMorale(4); // this is +5 because the above includes myself

			// FIXME: since nobles can be wounded more than once, this can/will count them multiple times
		}
		return [$result, $logs];
	}



}
