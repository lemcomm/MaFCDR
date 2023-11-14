<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\ActivityParticipant;
use BM2\SiteBundle\Entity\EquipmentType;
use BM2\SiteBundle\Entity\Settlement;
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
			if ($me->isNoble() && $me->getWeapon()) {
				$this->helper->trainSkill($me->getCharacter(), $me->getEquipment()->getSkill(), $xpMod);
			} else {
				$me->gainExperience(1*$xpMod);
			}
			$type = 'battle';
		} elseif ($act) {
			$type = 'act';
		}
		$logs = [];
		$result='miss';

		$attack = $this->ChargePower($me, true);
		$defense = $this->DefensePower($target, $battle)*0.75;

		$eWep = $target->getWeapon();
		if ($eWep->getType()->getSkill()->getCategory()->getName() === 'polearms') {
			$counterType = 'antiCav';
		} else {
			$counterType = False;
		}


		$logs[] = $target->getName()." (".$target->getType().") - ";
		$logs[] = (round($attack*10)/10)." vs. ".(round($defense*10)/10)." - ";
		if (rand(0,$attack) > rand(0,$defense)) {
			// defense penetrated
			$result = $this->resolveDamage($me, $target, $attack, $type, 'charge', $counterType, $xpMod, $defBonus);
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
		$target->addAttack(5);
		$sublogs = $this->equipmentDamage($me, $target);
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
			} else {
				$mod = $me->hungerMod();
			}
		} elseif ($me instanceof ActivityParticipant) {
			$me = $me->getCharacter();
			$mod = 1;
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

		return $power*$mod;
	}

	public function DefensePower($me, $sol = false, $melee = true) {
		$noble = false;
		# $sol is just a bypass for "Is this a soldier instance" or not.
		if ($sol) {
			if ($melee) {
				if ($me->DefensePower()!=-1) return $me->DefensePower();
			} else {
				if ($me->RDefensePower()!=-1) return $me->RDefensePower();
			}
			if ($me->isNoble()) {
				$noble = true;
				$mod = 1;
			} else {
				$mod = $me->hungerMod();
			}
		} elseif ($me instanceof ActivityParticipant) {
			$me = $me->getCharacter();
			$mod = 1;
		}

		$eqpt = $me->getEquipment();
		if ($noble) {
			# Only for battles.
			$power = 120;
			if ($me->getMount()) {
				$power += 48;
			}
			if ($eqpt && $eqpt->getName() != 'Pavise') {
				$power += 32;
			} elseif ($me->getMount()) {
				$power += 7;
			} elseif ($melee) {
				$power += 13;
			} else {
				$power += 63;
			}
			if ($melee) {
				$me->updateDefensePower($power);
			} else {
				$me->updateRDefensePower($power);
			}
			return $power;
		}

		$power = 5; // basic defense power which represents luck, instinctive dodging, etc.
		if ($me->getArmour()) {
			$power += $me->getArmour()->getDefense();
		}
		if ($me->getEquipment()) {
			if ($me->getEquipment()->getName() != 'Pavise') {
				$power += $me->getEquipment()->getDefense();
			} elseif ($me->getMount()) {
				$power += 0; #It's basically a portable wall. Not usable on horseback.
			} elseif ($melee) {
				$power += $me->getEquipment()->getDefense()/10;
			} else {
				$power += $me->getEquipment()->getDefense();
			}
		}
		if ($me->getMount()) {
			$power += $me->getMount()->getDefense();
		}

		if ($sol) {
			$power += $me->ExperienceBonus($power);
			if ($melee) {
				$me->updateDefensePower($power); // defense does NOT scale down with number of men in the unit
			} else {
				$me->updateRDefensePower($power);
			}
		}
		return $power*$mod;
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
		if ($attacker->getWeapon()) {
			$wpnSkill = $attacker->getWeapon()->getSkill()->getCategory()->getName();
		} else {
			$wpnSkill = false;
		}
		if ($target->getEquipment() && (rand(0,100)<25 || $wpnSkill === 'axes')) {
			$eqpName = $target->getEquipment()->getName();
			if ($eqpName === 'shield') {
				$target->dropEquipment();
				$logs[] = "equipment damaged\n";
			} elseif ($eqpName === 'pavise' && rand(1,8) < 2) {
				$target->dropEquipment();
				$logs[] = "equipment damaged\n";
			} elseif ($target->getEquipment() && $target->getEquipment()->getDefense()>0) {
				$resilience = sqrt($target->getEquipment()->getDefense());
				if (rand(0,100)<$resilience) {
					$target->dropEquipment();
					$logs[] = "equipment damaged\n";
				}
			}
		}
		return $logs;
	}

	public function MeleeAttack($me, $target, $mPower, $act=false, $battle=false, $xpMod = 1, $defBonus = 0, $enableCounter = true) {
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
			$defense = $defBonus;
		} else {
			$defense = $this->DefensePower($target, $battle);
		}
		$attack = $mPower;

		$counterType = false;
		if ($battle) {
			if ($target->isFortified()) {
				$defense += $defBonus;
			}
			if ($me->isFortified()) {
				$attack += ($defBonus/2);
			}
			$eqpt = $target->getEquipment();
			if (!$target->getMount() && $eqpt && $eqpt->getName() === 'shield') {
				$counterType = 'lightShield';
			}
		}

		$logs[] = $target->getName()." (".$target->getType().") - ";
		$logs[] = (round($attack*10)/10)." vs. ".(round($defense*10)/10)." - ";
		if (rand(0,$attack) > rand(0,$defense)) {
			// defense penetrated
			list($result, $sublogs) = $this->resolveDamage($me, $target, $attack, $type, 'melee', $counterType);
			foreach ($sublogs as $each) {
				$logs[] = $each;
			}
			if ((($type === 'battle' && $me->isNoble()) || $type === 'act') && $me->getWeapon()) {
				$this->helper->trainSkill($me->getCharacter(), $me->getWeapon()->getSkill(), $xpMod);
			} else {
				$me->gainExperience(($result=='kill'?2:1)*$xpMod);
			}
		} else {
			// armour saved our target
			$logs[] = "no damage\n";
			$result='fail';
			// out attack failed, do they get a counter?
			if ($enableCounter && $counterType) {
				$tPower = $this->MeleePower($target, true);
				list($innerResult, $sublogs) = $this->MeleeAttack($target, $me, $tPower, false, true, $xpMod, $defBonus, false);
				foreach ($sublogs as $each) {
					$logs[] = $each;
				}
				$result = $result . " " . $counterType . $innerResult;
			}
		}
		if ($battle) {
			$target->addAttack(5);
			$this->equipmentDamage($me, $target);
		}

		return [$result, $logs];
	}

	public function MeleePower($me, $sol = false, EquipmentType $weapon = null, $groupSize = 1) {
		$noble = false;
		$act = false;
		# $sol is just a bypass for "Is this a soldier instance" or not.
		if ($sol) {
			if ($me->MeleePower() != -1) return $me->MeleePower();
			if ($me->isNoble()) {
				$noble = true;
				$mod = 1;
			} else {
				$mod = $me->hungerMod();
			}
		} elseif ($me instanceof ActivityParticipant) {
			$act = $me->getActivity();
			$me = $me->getCharacter();
			$mod = 1;
		}

		$power = 0;
		$hasW = false;
		$hasM = false;
		$hasE = false;
		if ($weapon === null) {
			$weapon = $me->getWeapon();
		}
		if ($weapon !== null) {
			$mPower = $weapon->getMelee();
			if ($mPower > 0) {
				$hasW = true;
				$power += $mPower;
			}
		} else {
			// improvised weapons
			$power += 5;
		}
		if ((!$act || !$act->getWeaponOnly()) && $me->getEquipment()) {
			if ($me->getEquipment()->getName() != 'Lance') {
				$power += $me->getEquipment()->getMelee();
				$hasE = true;
			}
		}
		if ((!$act || !$act->getWeaponOnly()) && $me->getMount()) {
			$power += $me->getMount()->getMelee();
			$hasM = true;
		}
		if ($act) {
			$skill = $me->findSkill($weapon->getSkill());
			if ($skill) {
				$score = $skill->getScore();
			} else {
				$score = 0;
			}
			$power += min(sqrt($score*5), $power/2); # Same as the soldier object's ExperienceBonus func.
			return $power;
		} elseif ($noble) {
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
		# If either above the above ifs compare as true we don't get here, so this is technically an else/if regardless.
		if ($power>0) {
			$power += $me->ExperienceBonus($power);
		}

		// TODO: heavy armour should reduce this a little
		if ($sol) {
			if ($groupSize>1) {
				$me->updateMeleePower($power * pow($groupSize, 0.96)/$groupSize);
			} else {
				$me->updateMeleePower($power);
			}
		}
		return $power*$mod;
	}

	public function MeleeRoll($defBonus = 0, $rangedPenalty = 1, $rangedBonus = 0, $base = 95) {
		if (rand(0,100+$defBonus)<max($base*$rangedPenalty,$rangedBonus*$rangedPenalty)) {
			return true;
		} else {
			return false;
		}
	}

	public function RangedHit($me, $target, $rPower, $act=false, $battle=false, $xpMod = 1, $defBonus = 0) {
		if ($battle) {
			if ($me->isNoble() && $me->getWeapon()) {
				if (in_array($me->getType(), ['armoured archer', 'archer'])) {
					$this->helper->trainSkill($me->getCharacter(), $me->getWeapon()->getSkill(), $xpMod);
				} else {
					if ($me->getEquipment()) {
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
			$defense = $defBonus;
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
			list($result, $sublogs) = $this->resolveDamage($me, $target, $attack, $type, 'ranged');
			foreach ($sublogs as $each) {
				$logs[] = $each;
			}
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

	public function RangedPower($me, $sol = false, EquipmentType $weapon = null, $groupSize = 1) {
		$noble = false;
		# $sol is just a bypass for "Is this a soldier instance" or not.
		if ($sol) {
			if ($me->RangedPower() != -1) return $me->RangedPower();
			if ($me->isNoble()) {
				$noble = true;
				$mod = 1;
			} else {
				$mod = $me->hungerMod();
			}
			$act = false;
		} elseif ($me instanceof ActivityParticipant) {
			$act = $me->getActivity();
			$me = $me->getCharacter(); #for stndardizing the getEquipment type calls.
			$mod = 1;
		}
//		if (!$this->isActive()) return 0; -- disabled - it prevents counter-attacks

		$power = 0;
		$hasW = false;
		$hasE = false;
		$recurve = false;
		if ($weapon === null) {
			$weapon = $me->getWeapon();
		}
		if ($weapon !== null) {
			if ($rPower = $weapon->getRanged()) {
				$hasW = true;
				if ($me->getMount() && $weapon->getName() === 'recurve bow') {
					$power = $rPower*2;
					$recurve = true;
				} else {
					$power = $rPower;
				}
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

		if ($act) {
			$skill = $me->findSkill($weapon->getSkill());
			if ($skill) {
				$score = $skill->getScore();
			} else {
				$score = 0;
			}
			$power += min(sqrt($score*5), $power/2); # Same as the soldier object's ExperienceBonus func.
			return $power;
		} elseif ($noble) {
			# Only for battles.
			$power = 0;
			if ($hasW) {
				$power += 112;
			} elseif ($hasE) {
				$power += 81;
			}
			if ($recurve) {
				$power += 50;
			}
			return $power;
		}
		# If either above the above ifs compare as true we don't get here, so this is technically an else/if regardless.
		if ($power>0) {
			$power += $me->ExperienceBonus($power);
		}

		// TODO: heavy armour should reduce this quite a bit

		if ($sol) {
			if ($groupSize>1) {
				$me->updateRangedPower($power * pow($groupSize, 0.96)/$groupSize);
			} else {
				$me->updateRangedPower($power);
			}
		}

		return $power*$mod;
	}

	public function RangedRoll($defBonus = 0, $rangedPenalty = 1, $rangedBonus = 0, $base = 75) {
		if (rand(0,100+$defBonus)<max($base*$rangedPenalty,$rangedBonus*$rangedPenalty)) {
			return true;
		} else {
			return false;
		}
	}

	public function resolveDamage($me, $target, $power, $type, $phase = null, $counterType = false, $xpMod = 1, $defBonus = null) {
		// this checks for penetration again AND low-damage weapons have lower lethality AND wounded targets die more easily
		$logs = [];
		if ($type === 'battle') {
			$battle = true;
		} else {
			$battle = false;
		}
		$attScore = rand(0,$power);
		if ($attScore > rand(0,max(1,$this->DefensePower($target, $battle) - $target->getWounded(true)))) {
			// penetrated again = kill
			switch ($phase) {
				case 'charge':  $surrender = 50; break;
				case 'ranged':	$surrender = 60; break;
				case 'hunt':	$surrender = 95; break;
				case 'melee':
				default:	$surrender = 75; break;
			}
			// nobles can surrender and be captured instead of dying - if their attacker belongs to a noble
			$random = rand(1,100);
			if ($battle) {
				$resolved = false;
				if ($target->getMount() && (($me->getMount() && $random < 50) || (!$me->getMount() && $random < 70))) {
					$logs[] = "killed mount & wounded\n";
					#$target->wound($this->calculateWound($power));
					$target->dropMount();
					$this->history->addToSoldierLog($target, 'wounded.' . $phase);
					$result = 'wound';
					$resolved = true;
				}
				if (!$resolved) {
					$myNoble = false;
					if ($me->getCharacter()) {
						# We are our noble.
						$myNoble = $me->getCharacter();
					} elseif ($me->getUnit()) {
						# If you're not a character you should have a unit but...
						$unit = $me->getUnit();
						if ($unit->getCharacter()) {
							$myNoble = $unit->getCharacter();
						} elseif ($unit->getSettlement()) {
							/** @var Settlement $loc */
							$loc = $unit->getSettlement();
							if ($loc->getOccupant()) {
								# Settlement is occupied.
								$myNoble = $loc->getOccupant();
							} elseif ($loc->getOwner()) {
								# Settlement is not occupied, has owner.
								$myNoble = $loc->getOwner();
							} elseif ($loc->getSteward()) {
								# Settlement is not occupied, no owner, has steward.
								$myNoble = $loc->getSteward();
							}
						}
					}
					if ($target->isNoble() && $random < $surrender && $myNoble) {
						$logs[] = "captured\n";
						$this->charMan->imprison_prepare($target->getCharacter(), $myNoble);
						$this->history->logEvent($target->getCharacter(), 'event.character.capture', ['%link-character%' => $myNoble->getId()], History::HIGH, true);
						$result = 'capture';
						$this->charMan->addAchievement($myNoble, 'captures');
					} else {
						if ($me->isNoble()) {
							if ($target->isNoble()) {
								$this->charMan->addAchievement($me->getCharacter(), 'kills.nobles');
							} else {
								$this->charMan->addAchievement($me->getCharacter(), 'kills.soldiers');
							}
						}
						$logs[] = "killed\n";
						$target->kill();
						$this->history->addToSoldierLog($target, 'killed');
						$result = 'kill';
					}
				}
			} else {
				$result='kill';
			}

		} else {
			if ($battle) {
				$logs[] = "wounded\n";
				$target->wound($this->calculateWound($power));
				$this->history->addToSoldierLog($target, 'wounded.'.$phase);
				$target->gainExperience(1*$xpMod); // it hurts, but it is a teaching experience...
			}
			$result='wound';
		}
		if ($battle && $counterType) {
			# Attacks of opportunity, to make some gear more interesting to use. :D
			if ($counterType === 'antiCav') {
				$tPower = $this->MeleePower($target, true);
				list($innerResult, $sublogs) = $this->MeleeAttack($target, $me, $tPower, false, true, $xpMod, $defBonus);
				foreach ($sublogs as $each) {
					$logs[] = $each;
				}
				$result = $result . " " . $innerResult;
			}
		}
		if ($battle) {
			$me->addCasualty();

			// FIXME: these need to take unit sizes into account!
			// FIXME: maybe we can optimize this by counting morale damage per unit and looping over all soldiers only once?!?!
			// every casualty reduces the morale of other soldiers in the same unit
			foreach ($target->getAllInUnit() as $s) { $s->reduceMorale(1); }
			// enemy casualties make us happy - +5 for the killer, +1 for everyone in his unit
			foreach ($me->getAllInUnit() as $s) { $s->gainMorale(1); }
			$me->gainMorale(4); // this is +5 because the above includes myself

			// FIXME: since nobles can be wounded more than once, this can/will count them multiple times
		}

		return [$result, $logs];
	}

	public function calculateWound($power) {
		return round(rand(max(1, round($power/10)), $power)/3);
	}

}
