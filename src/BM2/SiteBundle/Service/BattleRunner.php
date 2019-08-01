<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Battle;
use BM2\SiteBundle\Entity\BattleGroup;
use BM2\SiteBundle\Entity\BattleParticipant;
use BM2\SiteBundle\Entity\BattleReport;
use BM2\SiteBundle\Entity\BattleReportGroup;
use BM2\SiteBundle\Entity\BattleReportStage;
use BM2\SiteBundle\Entity\BattleReportCharacter;
use BM2\SiteBundle\Entity\Soldier;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Action;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;


class BattleRunner {

	private $em;
	private $logger;
	private $history;
	private $geo;
	private $character_manager;
	private $npc_manager;
	private $interactions;
	private $warman;

	private $battle;
	private $debug=0;

	private $report;
	private $nobility;
	private $battlesize=1;
	private $defense_bonus=0;


	public function __construct(EntityManager $em, Logger $logger, History $history, Geography $geo, CharacterManager $character_manager, NpcManager $npc_manager, Interactions $interactions, WarManager $war_manager) {
		$this->em = $em;
		$this->logger = $logger;
		$this->history = $history;
		$this->geo = $geo;
		$this->character_manager = $character_manager;
		$this->npc_manager = $npc_manager;
		$this->interactions = $interactions;
		$this->war_manager = $war_manager;
	}

	public function enableLog($level=10) {
		$this->debug=$level;
	}
	public function disableLog() {
		$this->debug=0;
	}

	public function getLastReport() {
		return $this->report;
	}

	public function run(Battle $battle, $cycle) {
		$this->battle = $battle;
		$this->log(1, "Battle ".$battle->getId()."\n");

		$siege = $battle->getIsSiege();
		$assault = false;
		$sortie = false;
		$some_inside = false;
		$some_outside = false;
		$char_count = 0;
		$slumberers = 0;
		
		foreach ($battle->getGroups() as $group) {
			foreach ($group->getCharacters() as $char) {
				if ($char->getSlumbering() == true) {
					$slumberers++;
				}
				$char_count++;
			}
		}
		$this->log(15, "Found ".$char_count." characters and ".$slumberers." slumberers\n");
		$xp_ratio = $slumberers/$char_count;
		if ($xp_ratio < 0.1) {
			$xp_mod = 1;
		} else if ($xp_ratio < 0.2) {
			$xp_mod = 0.5;
		} else if ($xp_ratio < 0.3) {
			$xp_mod = 0.2;
		} else if ($xp_ratio < 0.5) {
			$xp_mod = 0.1;
		} else {
			$xp_mod = 0;
		}
		$this->log(15, "XP modifier set to ".$xp_mod." with ".$char_count." characters and ".$slumberers." slumberers\n");

		$this->report = new BattleReport;
		$assault = false;
		switch ($battle->getType()) {
			case 'siegesortie':
				$this->report->setSiege(FALSE);
				$this->report->setAssault(FALSE);
				$this->report->setSortie(TRUE);
				$this->report->setUrban(FALSE);
				if ($battle->getSiege()->getStage() > 1) {
					$location = array('key'=>'battle.location.sortie', 'id'=>$battle->getSettlement()->getId(), 'name'=>$battle->getSettlement()->getName());
				} else {
					$location = array('key'=>'battle.location.of', 'id'=>$battle->getSettlement()->getId(), 'name'=>$battle->getSettlement()->getName());
				}
				break;
			case 'siegeassault':
				$this->report->setSiege(FALSE);
				$this->report->setAssault(TRUE);
				$this->report->setSortie(FALSE);
				$this->report->setUrban(FALSE);
				if ($battle->getSiege()->getStage() > 1 && $battle->getSiege()->getStage() == $battle->getSiege()->getMaxStage()) {
					$location = array('key'=>'battle.location.castle', 'id'=>$battle->getSettlement()->getId(), 'name'=>$battle->getSettlement()->getName());
				} else {
					$location = array('key'=>'battle.location.assault', 'id'=>$battle->getSettlement()->getId(), 'name'=>$battle->getSettlement()->getName());
				}
				$assault = true;
				# TODO: Expand this to figure out other stages.
				foreach ($battle->getDefenseBuildings() as $building) {
					$this->report->addDefenseBuilding($building); 
				}
				$this->defense_bonus = $battle->getDefenseBonus();
				break;
			case 'urban':
				$this->report->setSiege(FALSE);
				$this->report->setAssault(FALSE);
				$this->report->setSortie(FALSE);
				$this->report->setUrban(TRUE);
				$location = array('key'=>'battle.location.of', 'id'=>$battle->getSettlement()->getId(), 'name'=>$battle->getSettlement()->getName());
				break;
			case 'field':
				$this->report->setSiege(FALSE);
				$this->report->setAssault(FALSE);
				$this->report->setSortie(FALSE);
				$this->report->setUrban(FALSE);
				$loc = $this->geo->locationName($battle->getLocation());
				$location = array('key'=>'battle.location.'.$loc['key'], 'id'=>$loc['entity']->getId(), 'name'=>$loc['entity']->getName());
				break;
		}
		
		$this->report->setCycle($cycle);
		$this->report->setLocation($battle->getLocation());
		$this->report->setSettlement($battle->getSettlement());
		$this->report->setWar($battle->getWar());
		$this->report->setLocationName($location);

		$this->report->setCompleted(false);
		$this->report->setDebug("");
		$this->em->persist($this->report);
		$this->em->flush(); // because we need the id below

		$this->log(15, "populating characters and locking...\n");
		$characters = array();
		$regionType = false;
		foreach ($battle->getGroups() as $group) {
			foreach ($group->getCharacters() as $char) {
				$characters[] = $char->getId();
				$char->setBattling(true);
				if (!$regionType) {
					$regionType = $this->geo->findMyRegion($char)->getBiome()->getName(); #We're hijacking this loop to grab the region type for later calculations.
				}
			}
			
		}
		$this->em->flush(); #So we don't have doctrine entity lock failures, we need the above battling flag set. It also gives us an easy way to check which characters we need to check below.

		$this->log(25, "checking mercenaries...\n");
		$query = $this->em->createQuery('SELECT m FROM BM2SiteBundle:Mercenaries m WHERE m.hired_by IN (:chars)');
		$query->setParameter('chars', $characters);
		foreach ($query->getResult() as $mercs) {
			// FIXME: there should be a check here that if the enemy is tiny, we don't ask for much gold, i.e. a max value
			// sadly, we know enemy numbers only after prepare(), right below. :-(
			$this->npc_manager->payMercenaries($mercs);
		}

		$this->log(15, "preparing...\n");
				
		if ($this->prepare($battle)) {
			// the main call to actually run the battle:
			$this->log(15, "resolving...\n");
			$this->resolveBattle($xp_mod);
		} else {
			// if there are no soldiers in the battle
			$this->log(1, "failed battle\n");
			foreach ($battle->getGroups() as $group) {
				foreach ($group->getCharacters() as $char) {
					$this->history->logEvent(
						$char,
						'battle.failed',
						array(),
						History::MEDIUM, false, 20
					);
					// put winners inside settlement to prevent exploiting this.
					if ($battle->getSettlement()) {
						$char->setInsideSettlement($battle->getSettlement());
					}
				}
			}
		}

		// cleaning up
		// TODO: maybe here we could copy the soldier log to the character, so people get more detailed battle reports? could be with temporary events
		foreach ($this->nobility as $noble) {
			$noble->getCharacter()->removeSoldier($noble);
		}

		# TODO: Adapt this for when sieges have reached their conclusion, and pass which side was victorious to a different function to closeout the siege properly.
		if (!$battle->getSiege()) {
			foreach ($battle->getGroups() as $group) {
				$this->war_manager->disbandGroup($group, $battlesize);
				# Battlesize is passed so we don't have to call addRegroupAction separately. Sieges don't have a regroup and are handled separately, so it doesn't matter for them.
			}
		}
		$this->em->remove($battle);
	}


	// FIXME: apparently, if you fight someone with no troops, you fight your own soldiers?

	private function prepare(Battle $battle) {
		$starting=array();
		$combatworthygroups = 0;
		$enemy=null;
		$this->nobility = new ArrayCollection;
		foreach ($battle->getGroups() as $group) {
			$group->setupSoldiers();
			$this->addNobility($group);

			$types=array();
			foreach ($group->getSoldiers() as $soldier) {
				if ($soldier->getExperience()<=5) {
					$soldier->addXP(2);
				} else {
					$soldier->addXP(1);					
				}
				$type = $soldier->getType();
				if (isset($types[$type])) {
					$types[$type]++;
				} else {
					$types[$type] = 1;
				}
			}
			$combatworthy=false;
			$troops = array();
			$this->log(3, "Totals in this group:\n");
			foreach ($types as $type=>$number) {
				$this->log(3, $type.": $number \n");
				$troops[$type] = $number;
				$combatworthy=true;
			}
			if ($combatworthy) {
				$combatworthygroups++;
			}
			$starting[$combatworthy] = $troops;
		}

		// FIXME: in huge battles, this can potentially take, like, FOREVER :-(
		if ($combatworthygroups>1) {

			# Only siege assaults get defense bonuses.
			if ($this->defense_bonus) {
				$this->log(10, "Defense Bonus / Fortification: ".$this->defense_bonus."\n");
			}

			foreach ($battle->getGroups() as $group) {
				$mysize = $group->getVisualSize();
				foreach ($group->getReinforcedBy() as $reinforcement) {
					$mysize += $reinforcement->getVisualSize();
				}

				if ($group->isDefender() && $this->defense_bonus) {
					// if we're on defense, we feel like we're more
					$mysize *= 1 + ($this->defense_bonus/200);
				}

				$enemies = $group->getEnemies();
				foreach ($enemies as $enemy) {
					$enemysize += $enemy->getVisualSize();
				}
				$mod = sqrt($mysize / $enemysize);

				$this->log(3, "Group #".$group->getId().", visual size $mysize.\n");

				$this->battlesize = min($mysize, $enemysize);
				
				$group_report = new BattleReportGroup();
				$this->em->persist($group_report);
				$this->em->flush(); # Because we need to set this immediately to keep tracking things simple. 
				# Right now, this makes this slower, but when battles take actual time to happen, this will be very nice to already have.
				$this->report->addGroup($group_report); # attach group report to main report
				$group->setActiveReport($group_report); # attack the group report to the battle group

				foreach ($group->getCharacters() as $char) {
					$this->character_manager->addAchievement($char, 'battlesize', $this->battlesize);
					$charReport = new BattleReportCharacter()
					$this->em->persist($charReport);
					$charReport->setBattleReportGroup($group_report);
					$charReport->setStanding(true)->setWounded(false)->setKilled(false);
					$this->em->flush()
					$char->setActiveReport($charReport);
					$group_report->addCharacter($charReport);
				}

				$base_morale = 50;
				// defense bonuses:
				if ($group == $battle->getPrimaryDefender() or $battle->getPrimaryDefender()->getReinforcedBy()->contains($group)) {
					if ($battle->getType = 'siegeassault') {
						$base_morale += $this->defense_bonus/2;
						$base_morale += 10; 
					}
				}
				$this->log(10, "Base morale: $base_morale, mod = $mod\n");

				foreach ($group->getSoldiers() as $soldier) {
					// starting morale: my power, defenses and relative sizes
					$power = $soldier->RangedPower() + $soldier->MeleePower() + $soldier->DefensePower();

					if ($group->isDefender() && $battle->getDefenseBonus()) {
						$soldier->setFortified(true);
					}
					if ($soldier->isNoble()) {
						$this->character_manager->addAchievement($soldier->getCharacter(), 'battles');
						$morale = $base_morale * 1.5;
					} else {
						$this->history->addToSoldierLog($soldier, 'battle', array("%link-battle%"=>$this->report->getId()));
						$morale = $base_morale;
					}
					if ($soldier->getDistanceHome() > 10000) {
						// 50km = -10 / 100 km = -14 / 200 km = -20 / 500 km = -32
						$distance_mod = sqrt(($soldier->getDistanceHome()-10000)/500);
					} else {
						$distance_mod = 0;
					}
					$soldier->setMorale(($morale + $power) * $mod - $distance_mod);

					$soldier->resetCasualties();
				}
			}
			$this->em->flush(); # Save all active reports for characters, and all character reports to their group reports.
			$this->report->setStart($starting);
			return true;
		} else {
			return false;
		}
	}

	private function addNobility(BattleGroup $group) {
		foreach ($group->getCharacters() as $char) {
			// TODO: might make this actual buy options, instead of hardcoded
			$weapon = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('sword');
			$armour = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('plate armour');
			$horse = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('war horse');

			$noble = new Soldier();
			$noble->setWeapon($weapon)->setArmour($armour)->setEquipment($horse);
			$noble->setNoble(true);
			$noble->setName($char->getName());
			$noble->setLocked(false)->setRouted(false)->setAlive(true);
			$noble->setHungry(0)->setWounded(0); // FIXME: this is not actually correct, but if we start them with the wound level of the noble, they will dodge combat by being considered inactive right away!
			$noble->setExperience(1000)->setTraining(0);

			$noble->setCharacter($char);
			$group->getSoldiers()->add($noble);
			$this->nobility->add($noble);
		}
	}


	private function resolveBattleNew($xpMod) {
		$phase = 1; # Initial value.
		$combat = true;
		$this->log(20, "...starting phases...\n");
		while ($combat) {
			$this->prepareRound($phase);
			# Main combat loop, go!
			# TODO: Make $battle->getRangedPhases() actually a thing.
			if ($battle->getRangedPhases() <= $phase) {
				$combat = $this->runStage('ranged', $xpMod);
			} else {
				$combat = $this->runStage('normal', $xpMod);
			}
		}
	}

	private function runStage($type = 'normal') {
		$groups = $this->battle->getGroups();
		foreach ($groups as $group) {
			$shots = 0; # Ranged attack attempts
			$strikes = 0; # Melee attack attempts
			$rangedhits = 0;
			$meleehits = 0;
			$routed = 0;

			$stageResult=array(); # Initialize this for later use. At the end of this loop, we commit this data to $stageReport->setData($stageResult);
			$stageReport = new BattleReportStage; # Generate new stage report.
			$this->em->persist($stageReport);
			$stageReport->setRound($phase);
			$stageReport->setGroupReport($group->getActiveReport());
			$this->em->flush();
			$group->getActiveReport()->addCombatStage($stageReport);

			$enemycollection = array();
			foreach ($group->getEnemies() as $enemygroup) {
				$enemycollection[] = $enemygroup->getActiveSoldiers();
			}
			$enemies = $enemy_collection->count();

			if (($battle->getPrimaryDefender() == $group) OR ($battle->getPrimaryAttacker() == $group)) {
				$this->log(5, "group ".$group->getActiveReport()->getId()." (".($group->getAttacker()?"attacker":"defender").") - ".$group->getFightingSoldiers()->count()." left, $enemies targets\n");
			} else {
				$this->log(5, "group ".$group->getActiveReport()->getId()." (Reinforcing group ".$group->getReinforcing()->getActiveReport()->getId().") - ".$group->getFightingSoldiers()->count()." left, $enemies targets\n");
			}

			# TODO: Move all of this out of the runStage and into resolveBattleNew so we don't have to run this each time.
			$rangedPenalty = 1; # Default of no penalty. Yes, 1 is no penalty. It's a multiplier.
			if ($battle->getType() == 'siegeassault' AND ($battle->getPrimaryAttacker() == $group OR $group->getReinforcing() == $battle->getPrimaryAttacker()) {
					$rangedPenalty *= 0.3; # Defenders in seiges get a blanket defensive boost as they're harder to hit being on the walls. This affects the determination of a hit, not how bad the hit is.
			}
			switch ($regionType) {
				case 'scrub':
					$rangedPenalty *=0.8;
					break;
				case 'thin scrub':
					$rangedPenalty *=0.9;
					break;
				case 'marsh':
					$rangedPenalty *=0.8;
					break;
				case 'forest':
					$rangedPenalty *=0.7;
					break;
				case 'dense forest':
					$rangedPenalty *=0.5;
					break;
				case 'rock':
					$rangedPenalty *=0.9;
					break;
				case 'snow':
					$rangedPenalty *=0.6;
					break;
			}
			if ($type == 'ranged') {
				foreach ($group->getFightingSoldiers() as $soldier) {
					if ($soldier->RangedPower()>0) {
						// ranged soldier - fire!
						$result=false;
						$this->log(10, $soldier->getName()." (".$soldier->getType().") fires - ");
						$target = $this->getRandomSoldier($enemy_collection);
						if ($target) {
							$shots++;
							$bonus = sqrt($enemies); // easier to hit if there are many enemies
							if (rand(0,100)<min(95*$rangedPenalty,($soldier->RangedPower()+$bonus)*$rangedPenalty)) {
								// target hit
								$rangedhits++;
								$result = $this->RangedHit($soldier, $target, $xp_mod);
								if (isset($results[$result])) {
									$results[$result]++;
								} else {
									$results[$result] = 1;
								}
								if ($result=='kill'||$result=='capture') {
									$enemies--;
									$enemy_collection->removeElement($target);
								}
								// special results for nobles
								if ($target->isNoble() && in_array($result, array('kill','capture'))) {
									foreach ($group->getEnemies() as $enemygroup) {
										if (!$enemygroup->getReinforcing) {
											$enemy = $enemygroup->getLocalId();
										}
									}

									if ($result=='capture') {
										$extra = array(
											'what' => 'ranged.'.$result,
											'by' => $soldier->getCharacter()->getId()
										);
									} else {
										$extra = array('what'=>'ranged.'.$result);
									}
									$extra['who'] = $target->getCharacter()->getId();
									$extras[] = $extra;
								}

							} else {
								// missed
								$this->log(10, "missed\n");
							}
							if ($soldier->getEquipment() && $soldier->getEquipment()->getName() == 'javelin') {
								if ($soldier->getWeapon() && !$soldier->getWeapon()->getName() == 'longbow') {
									// one-shot weapon, that only longbowmen will use by default in this phase
									// TODO: Better logic that determines this, for when we add new weapons.
									$soldier->dropEquipment();
								}
							}
						} else {
							$this->log(10, "no more targets\n");
						}
					}
				}
				if ($enemies > 0 && $hits > 0) {
					// morale damage - a function of how much fire we are taking
					// yes, this makes hits count several times - morale reduction above and twice here (since they're also always a shot)
					// we also double the effective morale of a soldier (after damage), because even a single hit triggers this test
					// and we don't want it to be overwhelming
					$moraledamage = ($shots+$hits*2) / $enemies;
					$this->log(10, "morale damage: $moraledamage\n");
					$total = 0; $count = 0;
					foreach ($group->getEnemies() as $enemygroup) {
						foreach ($enemygroup->getActiveSoldiers() as $soldier) { 
							if ($soldier->isFortified()) {
								$soldier->reduceMorale($moraledamage/2);
							} else {
								$soldier->reduceMorale($moraledamage);
							}
							$total += $soldier->getMorale();
							$count++;
							$this->log(50, $soldier->getName()." (".$soldier->getType()."): morale ".round($soldier->getMorale()));
							if ($soldier->getMorale()*2 < rand(0,100)) { 
								$this->log(50, " - panics");
								$soldier->setRouted(true);
								$this->history->addToSoldierLog($soldier, 'routed.ranged');
								$routed++;
							}
							$this->log(50, "\n");
						}
					}
					$this->log(10, "==> avg. morale: ".round($total/max(1,$count))."\n");
				}

				$stageResult = array('shots'=>$shots, 'hits'=>$hits, 'results'=>$results, 'routed'=>$routed, 'extra'=>$extras);
			} # End of Ranged combat rules.
			if ($type == 'normal') {
			
			}
		$stageReport->setData($stageResult); # Commit this stage's results to the combat report.
		}
	}

	# The below is temporarily duplicated so I can have a working and static copy of it.
	private function resolveMeleePhase($xp_mod) {
		// melee combat (several rounds)
		$round=0;
		$melee=array();
		$fighting = true;
		while ($fighting) {
			$round++;
			$this->log(5, "\nround $round:\n");
			$melee[$round]=array();
			$extras=array();

			$this->prepareRound();
			foreach ($this->battle->getGroups() as $group) {
				$melee[$round][$group->getLocalId()] = array();
				$attackers = $group->getFightingSoldiers()->count();
				$melee[$round][$group->getLocalId()]['alive'] = $attackers;
				$this->log(5, "group ".$group->getLocalId()." (".($group->getAttacker()?"attacker":"defender").") - $attackers left\n");
				$enemycollection = array();
				foreach ($group->getEnemies() as $enemygroup) {
					$enemycollection[] = $enemygroup->getActiveSoldiers();
				}
				$enemies = $enemy_collection->count();
				$bonus = sqrt($enemies);
				foreach ($group->getFightingSoldiers() as $soldier) {
					$result=false; $target=null;
					if ($soldier->isRanged()) {
						// continue firing on enemy targets, but at a reduced to-hit chance
						// TODO: friendly fire !
						$this->log(10, $soldier->getName()." (".$soldier->getType().") fires - ");
						if (rand(0,100)<min(75,($soldier->RangedPower()+$bonus)*0.5)) {
							// hit someone
							$target = $this->getRandomSoldier($enemy_collection);
							if ($target) {
								$result = $this->RangedHit($soldier, $target, $xp_mod, 'melee');
							} else {
								// no more targets
								$this->log(10, "no more targets\n");
							}
						} else {
							// missed
							$this->log(10, "missed\n");
						}
					} else {
						// melee unit
						$this->log(10, $soldier->getName()." (".$soldier->getType().") attacks ");
						$target = $this->getRandomSoldier($enemy_collection);
						if ($target) {
							$result = $this->MeleeAttack($soldier, $target, $xp_mod, $round);
						} else {
							// no more targets
							$this->log(10, "but finds no target\n");
						}
					}
					if ($result) {
						if ($result=='kill'||$result=='capture') {
							$enemies--;
							$enemy_collection->removeElement($target);
						}
						if (isset($melee[$round][$group->getLocalId()][$result])) {
							$melee[$round][$group->getLocalId()][$result]++;
						} else {
							$melee[$round][$group->getLocalId()][$result] = 1;
						}

						// special results for nobles
						if ($target->isNoble() && in_array($result, array('kill','capture'))) {
							foreach ($group->getEnemies() as $enemygroup) {
								if (!$enemygroup->getReinforcing) {
									$enemy = $enemygroup->getLocalId();
								}
							}

							if (!isset($extras[$enemy])) {
							  $extras[$enemy] = array();
							}

							if ($result=='capture' || $soldier->isNoble()) {
								$extra = array(
									'what' => 'noble.'.$result,
									'by' => $soldier->getCharacter()->getId()
								);
							} else {
								$extra = array('what'=>'mortal.'.$result);
							}

							$extra['who'] = $target->getCharacter()->getId();
							$extras[$enemy][] = $extra;
						}
					}
				}
			}

			$this->log(10, "morale checks:\n");
			foreach ($this->battle->getGroups() as $group) {
				foreach ($group->getEnemies() as $enemygroup) {
					if (!$enemygroup->getReinforcing) {
						$enemy = $enemygroup->getLocalId();
					}
				}
				$melee[$round][$enemy]['panic'] = 0;
				$count_us = $group->getActiveSoldiers()->count();
				foreach ($group->getReinforcedBy() as $reinforcement) {
					$count_us += $reinforcement->getActiveSoldiers()->count();
				}
				$enemies = $group->getEnemies();
				foreach ($enemies as $enemygroup) {
					$count_enemy += $enemygroup->getActiveSoldiers()->count();
				}
				$mod = sqrt($mysize / $enemysize);

				foreach ($group->getActiveSoldiers() as $soldier) {
					// still alive? check for panic
					if ($count_enemy > 0) {
						$ratio = $count_us / $count_enemy;
						if ($ratio > 10) {
							$mod = 0.95;
						} elseif ($ratio > 5) {
							$mod = 0.9;
						} elseif ($ratio > 2) {
							$mod = 0.8;
						} elseif ($ratio > 0.5) {
							$mod = 0.75;
						} elseif ($ratio > 0.25) {
							$mod = 0.65;
						} elseif ($ratio > 0.15) {
							$mod = 0.6;
						} elseif ($ratio > 0.1) {
							$mod = 0.5;
						} else {
							$mod = 0.4;
						}
					} else {
						// no enemies left
						$mod = 0.99;
					}

					if ($soldier->getAttacks()==0) {
						// we did not get attacked this round
						$mod = min(0.99, $mod+0.1);
					}
					$soldier->setMorale($soldier->getMorale() * $mod);
					if ($soldier->getMorale() < rand(0,100)) {
						$melee[$round][$enemy]['panic']++;
						$this->log(10, $soldier->getName()." (".$soldier->getType()."): ($mod) morale ".round($soldier->getMorale())." - panics\n");
						$soldier->setRouted(true);
						$count_us--;
						$this->history->addToSoldierLog($soldier, 'routed.melee');
					} else {
						$this->log(20, $soldier->getName()." (".$soldier->getType()."): ($mod) morale ".round($soldier->getMorale())."\n");
					}
				}

				foreach ($extras as $index=>$data) {
					$melee[$round][$index]['extra'] = $data;
				}

				if ($count_us==0) {
					$fighting = false;
				}
			}
		}
		return $melee;
	}


	private function resolveBattle($xp_mod) {
		// TODO: Siege battles (attacks on fortifications) should give 2 or even 3 (depending on towers, especially) ranged rounds,
		//			but this requires changes to the battle report format as well, and backwards compatability is a bitch...
		$this->log(20, "...ranged phase...\n");
		$ranged = $this->resolveRangedPhase($xp_mod);
		$this->log(20, "...melee phase...\n");
		$melee = $this->resolveMeleePhase($xp_mod);
		$this->report->setCombat(array('ranged'=>$ranged, 'melee'=>$melee));

		$this->log(20, "...pursuit phase...\n");
		$hunt = $this->resolvePursuitPhase($xp_mod);
		$this->report->setHunt($hunt);

		$this->log(3, "survivors:\n");
		$this->prepareRound(); // to update the isFighting setting correctly
		$survivors=array();
		foreach ($this->battle->getGroups() as $group) {

			foreach ($group->getSoldiers() as $soldier) {
				if ($soldier->getCasualties() > 0) {
					$this->history->addToSoldierLog($soldier, 'casualties', array("%nr%"=>$soldier->getCasualties()));
				}
			}

			$types=array();
			foreach ($group->getActiveSoldiers() as $soldier) {
				$soldier->gainExperience(2*$xp_mod);

				$type = $soldier->getType();
				if (isset($types[$type])) {
					$types[$type]++;
				} else {
					$types[$type]=1;
				}
			}

			$troops = array();
			$this->log(3, "Total survivors in this group:\n");
			foreach ($types as $type=>$number) {
				$this->log(3, "$type: $number \n");
				$troops[$type] = $number;
			}
			$survivors[$group->getLocalId()] = $troops;
		}

		$noblefates=array();
		$allnobles=array();
		$all_groups = $this->battle->getGroups();
		$this->log(2, "Fate of First Ones:\n");
		foreach ($this->battle->getGroups() as $group) {
			$noblegroup=array();
			$my_survivors = $group->getActiveSoldiers()->filter(
				function($entry) {
					return (!$entry->isNoble());
				}
			)->count();
			if ($my_survivors > 0) {
				$victory = true;
			} else {
				$victory = false;
			}
			foreach ($group->getSoldiers() as $soldier) {
				if ($soldier->isNoble()) {
					$id = $soldier->getCharacter()->getId();
					$allnobles[] = $soldier->getCharacter(); // store these here, because in some cases below they get removed from battlegroups
					if (!$soldier->isAlive()) {
						$noblegroup[$id]='killed';
						// remove from BG or the kill() could trigger false "battle failed" messages
						$group->removeCharacter($soldier->getCharacter());
						$soldier->getCharacter()->removeBattlegroup($group);
						// FIXME: how do we get the killer ?
						$this->character_manager->kill($soldier->getCharacter(), null, false, 'death2');
					} elseif ($soldier->getCharacter()->isPrisoner()) {
						$noblegroup[$id]='captured';
						// remove from BG or the imprison_complete() could trigger false "battle failed" messages
						$group->removeCharacter($soldier->getCharacter());
						$soldier->getCharacter()->removeBattlegroup($group);
						$this->character_manager->imprison_complete($soldier->getCharacter());
					} elseif ($soldier->isWounded()) {
						$noblegroup[$id]='wounded';
					} elseif ($soldier->isActive()) {
						if ($victory) {
							$noblegroup[$id]='victory';
							// victorious attackers get to enter the settlement
							if ($this->battle->getSettlement() && $this->battle->getAttacker()->getCharacters()->contains($soldier->getCharacter())) {
								$this->interactions->characterEnterSettlement($soldier->getCharacter(), $this->battle->getSettlement(), true);
							}
						} else {
							$noblegroup[$id]='retreat';
						}
					} else {
						$noblegroup[$id]='retreat';
					}
					// defeated losers could be forced out
					if ($noblegroup[$id]!='victory') {
						if ($this->battle->getSettlement() && $soldier->getCharacter()->getInsideSettlement()) {
							$this->interactions->characterLeaveSettlement($soldier->getCharacter(), true);
						}
					}
					$this->log(2, $soldier->getCharacter()->getName().': '.$noblegroup[$id]." (".$soldier->getWounded()."/".$soldier->getCharacter()->getWounded()." wounds)\n");
				}
			}
			$noblefates[$group->getLocalId()] = $noblegroup;
		}

		$this->report->setFinish(array('survivors'=>$survivors, 'nobles'=>$noblefates));	
		$this->log(1, "Battle finished, report #".$this->report->getId()."\n");

		foreach ($allnobles as $char) {
			$this->history->logEvent(
				$char,
				'battle.participated',
				array('%link-battle%'=>$this->report->getId()),
				History::HIGH
			);
		}

		if ($this->battle->getSettlement()) {
			$this->history->logEvent(
				$this->battle->getSettlement(),
				'event.settlement.battle',
				array('%link-battle%'=>$this->report->getId()),
				History::MEDIUM
			);
		}

		$this->report->setCompleted(true);
		// FIXME: why does it work with this enabled, and fails without (soldiers not updated) ???
		$this->em->flush();
		$this->log(1, "unlocking characters...\n");
		foreach ($allnobles as $noble) {
			$noble->setActiveReport(null); #Unset active report.
			$noble->setBattling(false);
		}
		foreach ($all_groups as $group) {
			$group->setActiveReport(null); #Unset active report.
		|
		$this->em->flush();
		$this->log(1, "unlocked...\n");
		unset($allnobles);
	}

	private function resolveMeleePhase($xp_mod) {
		// melee combat (several rounds)
		$round=0;
		$melee=array();
		$fighting = true;
		while ($fighting) {
			$round++;
			$this->log(5, "\nround $round:\n");
			$melee[$round]=array();
			$extras=array();

			$this->prepareRound();
			foreach ($this->battle->getGroups() as $group) {
				$melee[$round][$group->getLocalId()] = array();
				$attackers = $group->getFightingSoldiers()->count();
				$melee[$round][$group->getLocalId()]['alive'] = $attackers;
				$this->log(5, "group ".$group->getLocalId()." (".($group->getAttacker()?"attacker":"defender").") - $attackers left\n");
				$enemycollection = array();
				foreach ($group->getEnemies() as $enemygroup) {
					$enemycollection[] = $enemygroup->getActiveSoldiers();
				}
				$enemies = $enemy_collection->count();
				$bonus = sqrt($enemies);
				foreach ($group->getFightingSoldiers() as $soldier) {
					$result=false; $target=null;
					if ($soldier->isRanged()) {
						// continue firing on enemy targets, but at a reduced to-hit chance
						// TODO: friendly fire !
						$this->log(10, $soldier->getName()." (".$soldier->getType().") fires - ");
						if (rand(0,100)<min(75,($soldier->RangedPower()+$bonus)*0.5)) {
							// hit someone
							$target = $this->getRandomSoldier($enemy_collection);
							if ($target) {
								$result = $this->RangedHit($soldier, $target, $xp_mod, 'melee');
							} else {
								// no more targets
								$this->log(10, "no more targets\n");
							}
						} else {
							// missed
							$this->log(10, "missed\n");
						}
					} else {
						// melee unit
						$this->log(10, $soldier->getName()." (".$soldier->getType().") attacks ");
						$target = $this->getRandomSoldier($enemy_collection);
						if ($target) {
							$result = $this->MeleeAttack($soldier, $target, $xp_mod, $round);
						} else {
							// no more targets
							$this->log(10, "but finds no target\n");
						}
					}
					if ($result) {
						if ($result=='kill'||$result=='capture') {
							$enemies--;
							$enemy_collection->removeElement($target);
						}
						if (isset($melee[$round][$group->getLocalId()][$result])) {
							$melee[$round][$group->getLocalId()][$result]++;
						} else {
							$melee[$round][$group->getLocalId()][$result] = 1;
						}

						// special results for nobles
						if ($target->isNoble() && in_array($result, array('kill','capture'))) {
							foreach ($group->getEnemies() as $enemygroup) {
								if (!$enemygroup->getReinforcing) {
									$enemy = $enemygroup->getLocalId();
								}
							}

							if (!isset($extras[$enemy])) {
							  $extras[$enemy] = array();
							}

							if ($result=='capture' || $soldier->isNoble()) {
								$extra = array(
									'what' => 'noble.'.$result,
									'by' => $soldier->getCharacter()->getId()
								);
							} else {
								$extra = array('what'=>'mortal.'.$result);
							}

							$extra['who'] = $target->getCharacter()->getId();
							$extras[$enemy][] = $extra;
						}
					}
				}
			}

			$this->log(10, "morale checks:\n");
			foreach ($this->battle->getGroups() as $group) {
				foreach ($group->getEnemies() as $enemygroup) {
					if (!$enemygroup->getReinforcing) {
						$enemy = $enemygroup->getLocalId();
					}
				}
				$melee[$round][$enemy]['panic'] = 0;
				$count_us = $group->getActiveSoldiers()->count();
				foreach ($group->getReinforcedBy() as $reinforcement) {
					$count_us += $reinforcement->getActiveSoldiers()->count();
				}
				$enemies = $group->getEnemies();
				foreach ($enemies as $enemygroup) {
					$count_enemy += $enemygroup->getActiveSoldiers()->count();
				}
				$mod = sqrt($mysize / $enemysize);

				foreach ($group->getActiveSoldiers() as $soldier) {
					// still alive? check for panic
					if ($count_enemy > 0) {
						$ratio = $count_us / $count_enemy;
						if ($ratio > 10) {
							$mod = 0.95;
						} elseif ($ratio > 5) {
							$mod = 0.9;
						} elseif ($ratio > 2) {
							$mod = 0.8;
						} elseif ($ratio > 0.5) {
							$mod = 0.75;
						} elseif ($ratio > 0.25) {
							$mod = 0.65;
						} elseif ($ratio > 0.15) {
							$mod = 0.6;
						} elseif ($ratio > 0.1) {
							$mod = 0.5;
						} else {
							$mod = 0.4;
						}
					} else {
						// no enemies left
						$mod = 0.99;
					}

					if ($soldier->getAttacks()==0) {
						// we did not get attacked this round
						$mod = min(0.99, $mod+0.1);
					}
					$soldier->setMorale($soldier->getMorale() * $mod);
					if ($soldier->getMorale() < rand(0,100)) {
						$melee[$round][$enemy]['panic']++;
						$this->log(10, $soldier->getName()." (".$soldier->getType()."): ($mod) morale ".round($soldier->getMorale())." - panics\n");
						$soldier->setRouted(true);
						$count_us--;
						$this->history->addToSoldierLog($soldier, 'routed.melee');
					} else {
						$this->log(20, $soldier->getName()." (".$soldier->getType()."): ($mod) morale ".round($soldier->getMorale())."\n");
					}
				}

				foreach ($extras as $index=>$data) {
					$melee[$round][$index]['extra'] = $data;
				}

				if ($count_us==0) {
					$fighting = false;
				}
			}
		}
		return $melee;
	}


	private function resolvePursuitPhase($xp_mod) {
		// hunting down retreating enemies
		$this->log(5, "\nhunting them down:\n");
		$hunt=array();

		$fleeing_entourage = array();
		foreach ($this->battle->getGroups() as $group) {
			if ($group->getFightingSoldiers()->count()==0) {
				$this->log(10, "group is retreating:\n");
				foreach ($group->getCharacters() as $char) {
					$this->log(10, "character ".$char->getName());
					$count=0;
					foreach ($char->getLivingEntourage() as $e) {
						$fleeing_entourage[] = $e;
						$count++;
					}
					$this->log(10, "$count entourage\n");
				}
			}
		}
		$this->log(10, count($fleeing_entourage)." entourage are on the run.\n");

		foreach ($this->battle->getGroups() as $group) {
			$hunt[$group->getLocalId()]=array('killed'=>0,'entkilled'=>0);
			$this->prepareRound(); // called again each group to update the fighting status of all enemies
			$enemy_collection = array();
			foreach ($enemies as $enemygroup) {
				$enemy_collection[] = $enemygroup->getRoutedSoldiers();
			}
			foreach ($group->getFightingSoldiers() as $soldier) {
				$target = $this->getRandomSoldier($enemy_collection);
				$hitchance = 0; // safety-catch, it should be set in all cases further down
				if ($target) {
					if ($soldier->RangedPower() > $soldier->MeleePower()) {
						$hitchance = 10+round($soldier->RangedPower()/2);
						$power = $soldier->RangedPower()*0.75;
					} else {
						// chance of catching up with a fleeing enemy
						if ($soldier->getEquipment() && in_array($soldier->getEquipment()->getName(), array('horse', 'war horse'))) {
							$hitchance = 50;
						} else {
							$hitchance = 30;
						}
						$hitchance = max(5, $hitchance - $soldier->DefensePower()/5); // heavy armour cannot hunt so well
						$power = $soldier->MeleePower()*0.75;
					}
					if ($target->getEquipment() && in_array($target->getEquipment()->getName(), array('horse', 'war horse'))) {
						$hitmod = 0.5;
					} else {
						$hitmod = 1.0;
					}

					$evade = min(75, round($target->getExperience()/10 + 5*sqrt($target->getExperience())) ); // 5 = 12% / 20 = 24% / 50 = 40% / 100 = 60%

					if (rand(0,100) < $hitchance * $hitmod && rand(0,100) > $evade) {
						// hit someone!
						$this->log(10, $soldier->getName()." (".$soldier->getType().") caught up with ".$target->getName()." (".$target->getType().") - ");
						if (rand(0,$power) > rand(0,$target->DefensePower())) {
							$result = $this->resolveDamage($soldier, $target, $xp_mod, $power, 'escape');
							if ($result) {
								$hunt[$group->getLocalId()]['killed']++;
								if ($result == 'killed') {
									// FIXME: This apparently doesn't work? At least once I saw a killed noble being attacked again
									$enemy_collection->removeElement($target);
								}
							} else {
								$target->addAttack(4);
							}
						} else {
							// no damage
							$this->log(10, "no damage\n");
						}
					}
				} else {
					// no more targets
				}

				// also attack enemy entourage
				if (!empty($fleeing_entourage)) {
					$this->log(10, "... now attacking entourage - ");
					if (rand(0,100) < $hitchance) {
						// yepp, we got one
						$i = rand(0,count($fleeing_entourage)-1);
						$target = $fleeing_entourage[$i];
						$this->log(10, "slaughters ".$target->getName()." (".$target->getType()->getName().")\n");
						// TODO: log this!
						$target->kill();
						$hunt[$group->getLocalId()]['entkilled']++;
						array_splice($fleeing_entourage, $i, 1);
					} else {
						$this->log(10, "didn't hit (chance was $hitchance)\n");
					}
				}
			}

		}

		// routed consequences (throwing away weapons, shields, etc...)
		$shield = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('shield');
		foreach ($this->battle->getGroups() as $group) {
			$this->log(10, "routed group:\n");
			$hunt[$group->getLocalId()]['dropped']=0;
			foreach ($group->getRoutedSoldiers() as $soldier) {
				if ($soldier->isNoble()) continue; // noble characters don't throw away anything
				// throw away your shield - very likely
				if ($soldier->getEquipment() && $soldier->getEquipment() == $shield) {
					if (rand(0,100)<80) {
						$soldier->dropEquipment();
						$this->history->addToSoldierLog($soldier, 'dropped.shield');
						$this->log(10, $soldier->getName()." (".$soldier->getType()."): drops shield\n");
						$hunt[$group->getLocalId()]['dropped']++;
					}
				}
				// throw away your weapon - depends on weapon
				if ($soldier->getWeapon()) {
					switch ($soldier->getWeapon()->getName()) {
						case 'spear':		$chance = 40; break;
						case 'pike':		$chance = 50; break;
						case 'longbow':	$chance = 30; break;
						default:				$chance = 20;
					}
					if (rand(0,100)<$chance) {
						$soldier->dropWeapon();
						$this->history->addToSoldierLog($soldier, 'dropped.weapon');
						$this->log(10, $soldier->getName()." (".$soldier->getType()."): drops weapon\n");
						$hunt[$group->getLocalId()]['dropped']++;
					}
				}
			}
		}

		return $hunt;
	}



	// TODO: attacks on mounted soldiers could kill the horse instead

	private function MeleeAttack(Soldier $soldier, Soldier $target, $xp_mod, $round) {
		$soldier->gainExperience(1*$xp_mod);
		$result='miss';

		$defense = $target->DefensePower();
		if ($target->isFortified()) {
			$defense += $this->battle->getDefenseBonus()/$round;
		}

		$attack = $soldier->MeleePower();
		if ($soldier->isFortified()) {
			$attack += $this->battle->getDefenseBonus()/($round*2);
		}

		$this->log(10, $target->getName()." (".$target->getType().") - ");
		$this->log(15, (round($attack*10)/10)." vs. ".(round($defense*10)/10)." - ");
		if (rand(0,$attack) > rand(0,$defense)) {
			// defense penetrated
			$result = $this->resolveDamage($soldier, $target, $xp_mod, $attack, 'melee');
			$soldier->gainExperience(($result=='kill'?2:1)*$xp_mod);
		} else {
			// armour saved our target
			$this->log(10, "no damage\n");
			$result='fail';
		}
		$target->addAttack(5);
		$this->equipmentDamage($soldier, $target);

		return $result;
	}

	private function RangedHit(Soldier $soldier, Soldier $target, $xp_mod, $phase='ranged') {
		$soldier->gainExperience(1*$xp_mod);
		$result='miss';

		$defense = $target->DefensePower();
		if ($target->isFortified()) {
			if ($phase=='ranged') {
				$defense += $this->battle->getDefenseBonus();
			} else {
				$defense += $this->battle->getDefenseBonus()/2;
			}
		}

		$attack = $soldier->RangedPower();
		if ($soldier->isFortified()) {
			// small bonus to attack to simulate towers height advantage, etc.
			$attack += $this->battle->getDefenseBonus()/5;
		}

		$this->log(10, "hits ".$target->getName()." (".$target->getType().") - (".round($attack)." vs. ".round($defense).") = ");
		if (rand(0,$attack) > rand(0,$defense)) {
			// defense penetrated
			$result = $this->resolveDamage($soldier, $target, $xp_mod, $attack, 'ranged');
		} else {
			// armour saved our target
			$this->log(10, "no damage\n");
			$result='fail';
		}

		$target->addAttack(2);
		$this->equipmentDamage($soldier, $target);

		return $result;
	}

	private function equipmentDamage(Soldier $attacker, Soldier $target) {
		// small chance of armour or item damage - 10-30% per hit and then also depending on the item - 3%-14% - for total chances of ca. 1%-5% per hit
		if (rand(0,100)<15) {
			if ($attacker->getWeapon()) {
				$resilience = 30 - 3*sqrt($attacker->getWeapon()->getMelee() + $attacker->getWeapon()->getRanged());
				if (rand(0,100)<$resilience) {
					$attacker->dropWeapon();
					$this->log(10, "attacker weapon damaged\n");
				}
			}
		}
		if (rand(0,100)<10) {
			if ($target->getWeapon()) {
				$resilience = 30 - 3*sqrt($target->getWeapon()->getMelee() + $target->getWeapon()->getRanged());
				if (rand(0,100)<$resilience) {
					$target->dropWeapon();
					$this->log(10, "weapon damaged\n");
				}
			}
		}
		if (rand(0,100)<30) {
			if ($target->getArmour()) {
				$resilience = 30 - 3*sqrt($target->getArmour()->getDefense());
				if (rand(0,100)<$resilience) {
					$target->dropArmour();
					$this->log(10, "armour damaged\n");
				}
			}
		}
		if (rand(0,100)<25) {
			if ($target->getEquipment() && $target->getEquipment()->getDefense()>0) {
				$resilience = sqrt($target->getEquipment()->getDefense());
				if (rand(0,100)<$resilience) {
					$target->dropEquipment();
					$this->log(10, "equipment damaged\n");
				}
			}
		}
	}

	private function resolveDamage(Soldier $soldier, Soldier $target, $xp_mod, $power, $phase) {
		// this checks for penetration again AND low-damage weapons have lower lethality AND wounded targets die more easily
		if (rand(0,$power) > rand(0,max(1,$target->DefensePower() - $target->getWounded(true)))) {
			// penetrated again = kill
			switch ($phase) {
				case 'ranged':	$surrender = 60; break;
				case 'hunt':	$surrender = 85; break;
				case 'melee':	
				default:	$surrender = 75; break;
			}
			// nobles can surrender and be captured instead of dying - if their attacker belongs to a noble
			if ($target->isNoble() && !$target->getCharacter()->isNPC() && rand(0,100) < $surrender && $soldier->getCharacter()) {
				$this->log(10, "captured\n");
				$this->character_manager->imprison_prepare($target->getCharacter(), $soldier->getCharacter());
				$this->history->logEvent($target->getCharacter(), 'event.character.capture', array('%link-character%'=>$soldier->getCharacter()->getId()), History::HIGH, true);
				$result='capture';
				$this->character_manager->addAchievement($soldier->getCharacter(), 'captures');
			} else {
				if ($soldier->isNoble()) {
					if ($target->isNoble()) {
						$this->character_manager->addAchievement($soldier->getCharacter(), 'kills.nobles');
					} else {
						$this->character_manager->addAchievement($soldier->getCharacter(), 'kills.soldiers');
					}
				}
				$this->log(10, "killed\n");
				$target->kill();
				$this->history->addToSoldierLog($target, 'killed');
				$result='kill';
			}
		} else {
			$this->log(10, "wounded\n");
			$target->wound(rand(max(1, round($power/10)), $power));
			$this->history->addToSoldierLog($target, 'wounded.'.$phase);
			$result='wound';
			$target->gainExperience(1*$xp_mod); // it hurts, but it is a teaching experience...
		}
		
		$soldier->addCasualty();

		// FIXME: these need to take unit sizes into account!
		// FIXME: maybe we can optimize this by counting morale damage per unit and looping over all soldiers only once?!?!
		// every casualty reduces the morale of other soldiers in the same unit
		foreach ($target->getUnit() as $s) { $s->reduceMorale(1); }
		// enemy casualties make us happy - +5 for the killer, +1 for everyone in his unit
		foreach ($soldier->getUnit() as $s) { $s->gainMorale(1); }
		$soldier->gainMorale(4); // this is +5 because the above includes myself

		// FIXME: since nobles can be wounded more than once, this can/will count them multiple times
		return $result;
	}

	private function prepareRound($phase) {
		// store who is active, because this changes with hits and would give the first group to resolve the initiative while we want things to be resolved simultaneously
		foreach ($this->battle->getGroups() as $group) {
			foreach ($group->getSoldiers() as $soldier) {
				$soldier->setFighting($soldier->isActive());
				$soldier->resetAttacks();
			}
		}
	}

	public function addLootToken() {
		// TODO: dead and retreat-with-drop should add stuff to a loot pile that those left standing can plunder or something
	}

	public function log($level, $text) {
		if ($this->report) {
			$this->report->setDebug($this->report->getDebug().$text);			
		}
		if ($level <= $this->debug) {
			$this->logger->info($text);
		}
	}


	private function getRandomSoldier($group, $retry = 0) {
		$max = $group->count();
		$index = rand(1, $max);
		$target = $group->first();
		for ($i=1;$i<$index-2;$i++) {
			$target = $group->next();
		}
		if ($target && rand(10,25) <= $target->getAttacks()) {
			// too crowded around the target, can't attack it
			if ($retry<3) {
				// retry to find another target
				return $this->getRandomSoldier($group, $retry+1);
			} else {
				$target->setMorale($target->getMorale()-1); // overkill morale effect
				return null;
			}
		}
		return $target;
	}

}
