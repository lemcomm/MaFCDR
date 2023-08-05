<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Battle;
use BM2\SiteBundle\Entity\BattleGroup;
use BM2\SiteBundle\Entity\BattleParticipant;
use BM2\SiteBundle\Entity\BattleReport;
use BM2\SiteBundle\Entity\BattleReportGroup;
use BM2\SiteBundle\Entity\BattleReportStage;
use BM2\SiteBundle\Entity\BattleReportCharacter;
use BM2\SiteBundle\Entity\BattleReportObserver;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Soldier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;


class BattleRunner {

	/*
	NOTE: There's a bunch of code in here that is "live" but not actually called relating to 2D battles.
	*/

	# Symfony Service variables.
	private $em;
	private $logger;
	private $history;
	private $geo;
	private $character_manager;
	private $npc_manager;
	private $interactions;
	private $warman;
	private $actman;
	private $politics;
	private $helper;
	private $combat;

	# Preset values.
	private $defaultOffset = 135;
	private $battleSeparation = 270;
	/*
	Going to talk about these abit as they determine things. Offset is the absolute value from zero for each of the two primary sides.
	In the case of defenders this is also the positive value for where the "walls" are.

	*/

	# The following variables are used all over this class, in multiple functions, sometimes as far as 4 or 5 functions deep.
	private $battle;
	private $regionType;
	private $xpMod;
	private $debug=0;

	private $siegeFinale;
	private $defMinContacts;
	private $defUsedContacts = 0;
	private $defCurrentContacts = 0;
	private $defSlain;
	private $attMinContacts;
	private $attUsedContacts = 0;
	private $attCurrentContacts = 0;
	private $attSlain;

	private $report;
	private $nobility;
	private $battlesize=1;
	private $defenseBonus=0;


	public function __construct(EntityManager $em, Logger $logger, History $history, Geography $geo, CharacterManager $character_manager, NpcManager $npc_manager, Interactions $interactions, WarManager $war_manager, ActivityManager $actman, Politics $politics, MilitaryManager $milman, HelperService $helper, CombatManager $combat) {
		$this->em = $em;
		$this->logger = $logger;
		$this->history = $history;
		$this->geo = $geo;
		$this->character_manager = $character_manager;
		$this->npc_manager = $npc_manager;
		$this->interactions = $interactions;
		$this->warman = $war_manager;
		$this->actman = $actman;
		$this->politics = $politics;
		$this->milman = $milman;
		$this->helper = $helper;
		$this->combat = $combat;
	}

	public function enableLog($level=9999) {
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
		if ($char_count > 0) {
			$xpRatio = $slumberers/$char_count;
		} else {
			$xpRatio = 1;
		}
		if ($xpRatio < 0.1) {
			$xpMod = 1;
		} else if ($xpRatio < 0.2) {
			$xpMod = 0.5;
		} else if ($xpRatio < 0.3) {
			$xpMod = 0.2;
		} else if ($xpRatio < 0.5) {
			$xpMod = 0.1;
		} else {
			$xpMod = 0;
		}
		$this->xpMod = $xpMod;
		$this->log(15, "XP modifier set to ".$xpMod." with ".$char_count." characters and ".$slumberers." slumberers\n");

		$this->report = new BattleReport;
		$assault = false;
		$this->report->setAssault(FALSE);
		$this->report->setSortie(FALSE);
		$this->report->setUrban(FALSE);
		$myStage = NULL;
		$maxStage = NULL;
		$place = $battle->getPlace();
		$type = $battle->getType();
		if (in_array($battle->getType(), ['siegesortie', 'siegeassault']) && !$battle->getSiege()) {
			# Ideally, it shouldn't be possible to have a siege battle without a siege, but just in case...
			$type = 'field';
		}
		switch ($type) {
			case 'siegesortie':
				$this->report->setSortie(TRUE);
				$myStage = $battle->getSiege()->getStage();
				$maxStage = $battle->getSiege()->getMaxStage();
				if ($place) {
					if ($myStage > 1) {
						$location = array('key'=>'battle.location.sortie', 'id'=>$battle->getPlace()->getId(), 'name'=>$battle->getPlace()->getName());
					} else {
						$location = array('key'=>'battle.location.of', 'id'=>$battle->getPlace()->getId(), 'name'=>$battle->getPlace()->getName());
					}
				} else {
					if ($myStage > 1) {
						$location = array('key'=>'battle.location.sortie', 'id'=>$battle->getSettlement()->getId(), 'name'=>$battle->getSettlement()->getName());
					} else {
						$location = array('key'=>'battle.location.of', 'id'=>$battle->getSettlement()->getId(), 'name'=>$battle->getSettlement()->getName());
					}
				}
				$this->siegeFinale = FALSE;
				break;
			case 'siegeassault':
				$this->report->setAssault(TRUE);
				$myStage = $battle->getSiege()->getStage();
				$maxStage = $battle->getSiege()->getMaxStage();
				if ($place) {
					if ($myStage > 2 && $myStage == $maxStage) {
						$location = array('key'=>'battle.location.castle', 'id'=>$battle->getPlace()->getId(), 'name'=>$battle->getPlace()->getName());
						$this->siegeFinale = TRUE;
					} else {
						$location = array('key'=>'battle.location.assault', 'id'=>$battle->getPlace()->getId(), 'name'=>$battle->getPlace()->getName());
						$this->siegeFinale = FALSE;
					}
				} else {
					if ($myStage > 2 && $myStage == $maxStage) {
						$location = array('key'=>'battle.location.castle', 'id'=>$battle->getSettlement()->getId(), 'name'=>$battle->getSettlement()->getName());
						$this->siegeFinale = TRUE;
					} else {
						$location = array('key'=>'battle.location.assault', 'id'=>$battle->getSettlement()->getId(), 'name'=>$battle->getSettlement()->getName());
						$this->siegeFinale = FALSE;
					}
				}
				$assault = true;
				if (!$place) {
					# So, this looks a bit weird, but stone stuff counts during stages 1 and 2, while wood stuff and moats only count during stage 1. Stage 3 gives you the fortress, and stage 4 gives the citadel bonus.
					# If you're wondering why this looks different from how we figure out the max stage, that's because the final stage works differently.
					foreach ($battle->getDefenseBuildings() as $building) {
						switch (strtolower($building->getType()->getName())) {
							case 'stone wall': # 10 points
							case 'stone towers': # 5 points
							case 'stone castle': # 5 points
								if ($myStage < 3) {
									$this->log(10, "Debug: ".$building->getType()->getName()." for score of ".$building->getDefenseScore()."\n");
									$this->report->addDefenseBuilding($building->getType());
									$this->defenseBonus += $building->getDefenseScore();
								}
								break;
							case 'palisade': # 10 points
							case 'empty moat': # 5 points
							case 'filled moat': # 5 points
							case 'wood wall': # 10 points
							case 'wood towers': # 5 points
							case 'wood castle': # 5 points
								if ($myStage < 2) {
									$this->log(10, "Debug: ".$building->getType()->getName()." for score of ".$building->getDefenseScore()."\n");
									$this->report->addDefenseBuilding($building->getType());
									$this->defenseBonus += $building->getDefenseScore();
								}
								break;
							case 'fortress': # 50 points
								if ($myStage == 3) {
									$this->log(10, "Debug: ".$building->getType()->getName()." for score of ".$building->getDefenseScore()."\n");
									$this->report->addDefenseBuilding($building->getType());
									$this->defenseBonus += $building->getDefenseScore();
								}
								break;
							case 'citadel': # 70 points
								if ($myStage == 4) {
									$this->log(10, "Debug: ".$building->getType()->getName()." for score of ".$building->getDefenseScore()."\n");
									$this->report->addDefenseBuilding($building->getType());
									$this->defenseBonus += $building->getDefenseScore();
								}
								break;
							default:
								# Seats of power are all 5 pts each.
								# Apothercary and alchemist are also 5.
								# This grants up to 30 points.
								$this->log(10, "Debug: ".$building->getType()->getName()." for score of ".$building->getDefenseScore()."\n");
								$this->report->addDefenseBuilding($building->getType()); #Yes, this means Alchemists, and Seats of Governance ALWAYS give their bonus, if they exist.
								$this->defenseBonus += $building->getDefenseScore();
								break;
						}
					}
				}
				break;
			case 'urban':
				$this->report->setUrban(TRUE);
				if ($place) {
					$location = array('key'=>'battle.location.of', 'id'=>$battle->getPlace()->getId(), 'name'=>$battle->getPlace()->getName());
				} else {
					$location = array('key'=>'battle.location.of', 'id'=>$battle->getSettlement()->getId(), 'name'=>$battle->getSettlement()->getName());
				}
				$this->siegeFinale = FALSE;
				break;
			case 'field':
			default:
				$loc = $this->geo->locationName($battle->getLocation());
				$location = array('key'=>'battle.location.'.$loc['key'], 'id'=>$loc['entity']->getId(), 'name'=>$loc['entity']->getName());
				$this->siegeFinale = FALSE;
				break;
		}

		$this->report->setCycle($cycle);
		$this->report->setLocation($battle->getLocation());
		$this->report->setSettlement($battle->getSettlement());
		$this->report->setPlace($battle->getPlace());
		$this->report->setWar($battle->getWar());
		$this->report->setLocationName($location);

		$this->report->setCompleted(false);
		$this->report->setDebug("");
		$this->em->persist($this->report);
		$this->em->flush(); // because we need the report ID below to set associations

		$this->log(15, "populating characters and locking...\n");
		$characters = array();
		$this->regionType = false;
		foreach ($battle->getGroups() as $group) {
			foreach ($group->getCharacters() as $char) {
				$characters[] = $char->getId();
				$char->setBattling(true);
				if (!$this->regionType) {
					if ($myRegion = $this->geo->findMyRegion($char)) {
						$this->regionType = $myRegion->getBiome()->getName(); #We're hijacking this loop to grab the region type for later calculations.
					} else {
						$this->regionType = 'grassland'; # Because apparently this can happen... :\
					}
				}
			}

		}
		$this->em->flush(); #So we don't have doctrine entity lock failures, we need the above battling flag set. It also gives us an easy way to check which characters we need to check below.

		$this->log(15, "preparing...\n");

		$preparations = $this->prepare();
		if ($preparations[0] === 'success') {
			$this->helper->addObservers($battle, $this->report);
			$this->em->flush();
			// the main call to actually run the battle:
			$this->log(15, "Resolving Battle...\n");
			$this->resolveBattle($myStage, $maxStage);
			$this->log(15, "Post Battle Cleanup...\n");
			$victor = $this->concludeBattle();
			$victorReport = $victor->getActiveReport();
		} else {
			// if there are no soldiers in the battle
			$this->log(1, "failed battle\n");
			if ($battle->getSiege()) {
				$victor = $preparations[1];
				if ($victor instanceof BattleGroup) {
					$victorReport = $victor->getActiveReport();
				} else {
					$victorReport = false;
				}
			}
			foreach ($battle->getGroups() as $group) {
				foreach ($group->getCharacters() as $char) {
					$this->history->logEvent(
						$char,
						'battle.failed',
						array(),
						History::MEDIUM, false, 20
					);
					$char->setActiveReport(null); #Unset active report.
					$char->setBattling(false);
				}
				$group->setActiveReport(null);
			}
		}

		# Remove actions related to this battle.
		$this->log(15, "Removing related actions...\n");
		foreach ($battle->getGroups() as $group) {
			foreach ($group->getRelatedActions() as $act) {
				$relevantActs = ['military.battle', 'siege.sortie', 'siege.assault'];
				if (in_array($act->getType(), $relevantActs)) {
					$this->em->remove($act);
				}
			}
		}

		// TODO: maybe here we could copy the soldier log to the character, so people get more detailed battle reports? could be with temporary events
		$this->log(15, "Removing temporary character associations...\n");
		foreach ($this->nobility as $noble) {
			$noble->getCharacter()->removeSoldiersOld($noble);
		}

		# TODO: Adapt this for when sieges have reached their conclusion, and pass which side was victorious to a different function to closeout the siege properly.
		if (!$battle->getSiege()) {
			$this->log(15, "Regular battle detected, Nulling primary battle groups...\n");
			if ($battle->getPrimaryDefender()) {
				$battle->setPrimaryDefender(NULL);
			}
			if ($battle->getPrimaryAttacker()) {
				$battle->setPrimaryAttacker(NULL);
			}
			$this->log(15, "Jittering characters and disbanding groups...\n");
			foreach ($battle->getGroups() as $group) {
				// to avoid people being trapped by overlapping battles - we move a tiny bit after a battle if travel is set
				// 0.05 is 5% of a day's journey, or about 25% of an hourly journey - or about 500m base speed, modified for character speed
				foreach ($group->getCharacters() as $char) {
					if ($char->getTravel()) {
						$char->setProgress(min(1.0, $char->getProgress() + $char->getSpeed() * 0.05));
					}
				}
				$this->warman->disbandGroup($group, $this->battlesize);
				# Battlesize is passed so we don't have to call addRegroupAction separately. Sieges don't have a regroup and are handled separately, so it doesn't matter for them.
			}
		} else {
			$this->log(1, "Siege battle detected, progressing siege...\n");
			$this->log(1, "Siege ID: ".$battle->getSiege()->getId()."\n");
			$this->log(1, "Battle ID: ".$battle->getId()."\n");
			if ($victorReport) {
				$this->log(1, "Victor ID: ".$victorReport->getId()." (".($victor->getAttacker()?"attacker":"defender").")\n");
			}
			$this->log(1, "preparations: ".$preparations[0]."\n");
			$this->log(1, "report ID: ".$this->report->getId()."\n");
			# Pass the siege ID, which side won, and in the event of a battle failure, the preparation reesults (This lets us pass failures and prematurely end sieges.)
			$this->em->flush();
			if ($victor) {
				$this->progressSiege($battle, $victor, $preparations[0]);
			}
		}
		$this->em->flush();
		$this->em->remove($battle);
		$this->history->evaluateBattle($this->report);
	}

	private function prepare() {
		$battle = $this->battle;
		$combatworthygroups = 0;
		$enemy=null;
		$this->nobility = new ArrayCollection;

		if ($battle->getSiege()) {
			$siege = $battle->getSiege();
			$attGroup = $siege->getAttacker();
			$defGroup = NULL;
			$haveAttacker = FALSE;
			$haveDefender = FALSE;
		} else {
			$siege = FALSE;
			$attGroup = $battle->getPrimaryAttacker();
			$defGroup = $battle->getPrimaryDefender();
		}
		$totalCount = 0;
		foreach ($battle->getGroups() as $group) {
			if ($siege && $defGroup == NULL) {
				if ($group != $attGroup && !$group->getReinforcing()) {
					$defGroup = $group;
				}
			}

			$groupReport = new BattleReportGroup();
			$this->em->persist($groupReport);
			$this->report->addGroup($groupReport); # attach group report to main report
			$groupReport->setBattleReport($this->report); # attach main report to this group report
			$group->setActiveReport($groupReport); # attach the group report to the battle group

			$group->setupSoldiers();
			$this->addNobility($group);

			$types=array();
			$groupCount = 0;
			foreach ($group->getSoldiers() as $soldier) {
				$groupCount++;
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
			$totalCount += $groupCount;
			$groupReport->setCount($groupCount);
			$combatworthy=false;
			$troops = array();
			$this->log(3, "Totals in this group:\n");
			$some = false;
			foreach ($types as $type=>$number) {
				$this->log(3, $type.": $number \n");
				$some = true;
				$troops[$type] = $number;
				$combatworthy=true;
			}
			if (!$some) {
				$this->log(3, "(none) \n");
			}
			if ($combatworthy && !$group->getReinforcing()) {
				# Groups that are reinforcing don't represent a primary combatant, and if we don't have atleast 2 primary combatants, there's no point.
				# TODO: Add a check to make sure we don't have groups reinforcing another group that's no longer in the battle.
				$combatworthygroups++;
				if ($battle->getSiege()) {
					if ($siege->getAttacker() == $group) {
						$haveAttacker = TRUE;
					} else if ($siege->getDefender() == $group) {
						$haveDefender = TRUE;
					}
				}
			}
			$groupReport->setStart($troops);
		}
		$this->report->setCount($totalCount);
		$this->em->flush();

		// FIXME: in huge battles, this can potentially take, like, FOREVER :-(
		if ($combatworthygroups>1) {

			# Only siege assaults get defense bonuses.
			if ($this->defenseBonus) {
				$this->log(10, "Defense Bonus / Fortification: ".$this->defenseBonus."\n");
			}

			foreach ($battle->getGroups() as $group) {
				$mysize = $group->getVisualSize();
				if ($group->getReinforcedBy()) {
					foreach ($group->getReinforcedBy() as $reinforcement) {
						$mysize += $reinforcement->getVisualSize();
					}
				}

				/*
				if ($battle->getSiege() && !$this->siegeFinale && $group == $attGroup) {
					$totalAttackers = $group->getActiveMeleeSoldiers()->count();
					if ($group->getReinforcedBy()) {
						foreach ($group->getReinforcedBy() as $reinforcers) {
							$totalAttackers += $reinforcers->getActiveMeleeSoldiers()->count();
						}
					}
					$this->attMinContacts = floor($totalAttackers/4);
					$this->defMinContacts = floor(($totalAttackers/4*1.2));
				}
				*/
				if ($battle->getSiege() && ($battle->getSiege()->getAttacker() != $group && !$battle->getSiege()->getAttacker()->getReinforcedBy()->contains($group))) {
					// if we're on defense, we feel like we're more
					$mysize *= 1 + ($this->defenseBonus/200);
				}

				$enemies = $group->getEnemies();
				$enemysize = 0;
				foreach ($enemies as $enemy) {
					$enemysize += $enemy->getVisualSize();
				}
				$mod = sqrt($mysize / $enemysize);

				$this->log(3, "Group #".$group->getActiveReport()->getId().", visual size $mysize.\n");

				$this->battlesize = min($mysize, $enemysize);

				foreach ($group->getCharacters() as $char) {
					$this->character_manager->addAchievement($char, 'battlesize', $this->battlesize);
					$charReport = new BattleReportCharacter();
					$this->em->persist($charReport);
					$charReport->setGroupReport($group->getActiveReport());
					$charReport->setStanding(true)->setWounded(false)->setKilled(false)->setAttacks(0)->setKills(0)->setHitsTaken(0)->setHitsMade(0);
					$this->em->flush();
					$charReport->setCharacter($char);
					$char->setActiveReport($charReport);
					$group->getActiveReport()->addCharacter($charReport);
				}

				$base_morale = 50;
				// defense bonuses:
				if ($group == $battle->getPrimaryDefender() or $battle->getPrimaryDefender()->getReinforcedBy()->contains($group)) {
					if ($battle->getType = 'siegeassault') {
						$base_morale += $this->defenseBonus/2;
						$base_morale += 10;
					}
				}
				$this->log(10, "Base morale: $base_morale, mod = $mod\n");

				foreach ($group->getSoldiers() as $soldier) {
					// starting morale: my power, defenses and relative sizes
					$power = $this->combat->RangedPower($soldier, true) + $this->combat->MeleePower($soldier, true) + $this->combat->DefensePower($soldier, true);

					if ($battle->getSiege() && ($battle->getSiege()->getAttacker() != $group && !$battle->getSiege()->getAttacker()->getReinforcedBy()->contains($group))) {
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
			return ['success', true];
		} else {
			if ($battle->getSiege()) {
				if ($haveAttacker) {
					return ['haveAttacker', $siege->getAttacker()];
				} elseif ($haveDefender) {
					return ['haveDefender', $siege->getDefender()];
				}
			}
			return ['failed', false];
		}
	}

	private function addNobility(BattleGroup $group) {
		foreach ($group->getCharacters() as $char) {
			// TODO: might make this actual buy options, instead of hardcoded
			$weapon = $char->getWeapon();
			if (!$weapon) {
				$weapon = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('sword');
			}
			$armour = $char->getArmour();
			if (!$armour) {
				$armour = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('plate armour');
			}
			$equipment = $char->getEquipment();
			if (!$equipment) {
				$equipemnt = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('war horse');
			}

			$noble = new Soldier();
			$noble->setWeapon($weapon)->setArmour($armour)->setEquipment($equipment);
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

	private function resolveBattle($myStage, $maxStage) {
		$battle = $this->battle;
		$phase = 1; # Initial value.
		$combat = true; # Initial value.
		$this->log(20, "Calculating ranged penalties...\n");
		$rangedPenalty = 1; # Default of no penalty. Yes, 1 is no penalty. It's a multiplier.
		switch ($this->regionType) {
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
		if ($battle->getType() == 'urban') {
			$rangedPenalty = 0.3;
		}
		$doRanged = TRUE;
		if ($myStage > 1 && $myStage == $maxStage) {
			$doRanged = FALSE; #Final siege battle, no ranged phase!
			$this->log(20, "...final siege battle detected, skipping ranged phase...\n\n");
		} else {
			$this->log(20, "Ranged Penalty: ".$rangedPenalty."\n\n");
		}
		#$this->prepareBattlefield();
		$this->log(20, "...starting phases...\n");
		while ($combat) {
			$this->prepareRound();
			# Main combat loop, go!
			# TODO: Expand this for multiple ranged phases.
			if ($phase === 1 && $doRanged) {
				$this->log(20, "...ranged phase...\n");
				$combat = $this->runStage('ranged', $rangedPenalty, $phase, $doRanged);
				$phase++;
			} else {
				$this->log(20, "...melee phase...\n");
				$combat = $this->runStage('normal', $rangedPenalty, $phase, $doRanged);
				$phase++;
			}
		}
		$this->log(20, "...hunt phase...\n");
		$hunt = $this->runStage('hunt', $rangedPenalty, $phase, $doRanged);
	}

	private function prepareRound() {
		// store who is active, because this changes with hits and would give the first group to resolve the initiative while we want things to be resolved simultaneously
		foreach ($this->battle->getGroups() as $group) {
			foreach ($group->getSoldiers() as $soldier) {
				$soldier->setFighting($soldier->isActive());
				$soldier->resetAttacks();
			}
		}
		// Updated siege assault contact scores. When we have siege engines, this will get ridiculously simpler to calculate. Defenders always get it slightly easier.
		/* Or it would've been if this wasn't garbage.
		if ($this->battle->getType() == 'siegeassault') {
			$newAttContacts = $this->attCurrentContacts - $this->attSlain;
			$newDefContacts = $this->defCurrentContacts - $this->defSlain;
 			if ($newAttContacts < $this->attMinContacts) {
				$this->attCurrentContacts = $this->attMinContacts;
			} else {
				$this->attCurrentContacts = $newAttContacts;
			}
			if ($newDefContacts < $this->defMinContacts) {
				if ($newDefContacts < $this->attCurrentContacts) {
					$this->defCurrentContacts = $this->attCurrentContacts*1.3;
				} else {
					$this->defCurrentContacts = $newDefContacts;
				}
			}
			$this->defUsedContacts = 0;
			$this->attusedContacts = 0;
		}
		*/
		$this->em->flush();

	}

	private function prepareBattlefield() {
		$battle = $this->battle;
		if ($battle->getType() === 'siegesortie') {
			$siege = $battle->getSiege();
		} elseif ($battle->getType() === 'siegeassault') {
			$siege = $battle->getSiege();
		} elseif ($battle->getType() === 'urban') {
			$siege = false;
		}
		$posX = $this->defaultOffset;
		$negX = 0 - $this->defaultOffset;
		if ($siege) {
			$inside = $battle->findInsideGroups();
			$iCount = $inside->count();
			$outside = $battle->findOutsideGroups();
			$oCount = $outside->count();
			$highY = 0;
			$count = 1;
			foreach ($inside as $group) {
				list($highY, $count) = $this->deplyGroup($group, $posX, $highY, false, $count, $iCount);

				/* Fancy logic follows for more than 2 sided battles.

				These'll be fun for multiple reasons, largely because we'll ahve to rotate entire formations.

				For now, none of these ;)
				$highY = 0;
				$lowY = 0;
				if ($iCount == 1) {
					$this->deployGroup($group, $posX, false); #We don't need the return.
				} else {
					if ($group === $siege->getPrimaryDefender()) {
						$newHigh = $this->deployGroup($group, $posX, false);
					} else {
						$offsetX = $posX+$this->battleSeparation;
						$newHigh = $this->deployGroup($group, $offsetX, false);
					}
					if ($newHigh > $highY) {
						$highY = $newHigh;
					}
				} */
			}
			$count = 1; #Each side retains a separate count.
			foreach ($outside as $group) {
				list($highY, $count) = $this->deployGroup($group, $negX, $highY, true, $count, $oCount);
			}
		} else {
			$groups = $battle->getGroups();
			$tCount = $groups->count(); # Total count.
			foreach ($groups as $group) {
				list($highY, $count) = $this->deplyGroup($group, $posX, $highY, false, $count, $tCount);
				$invet = !$invert;
			}
		}
	}

	private function deployGroup($group, $startX, $highY, $invert, $gCount, $tGCount, $angle = null) {
		/*
		group is the group we're depling.
		startX is the initial x position we're working from.
		highY lets us ensure separation on 3+ group battles.
		invert tells it to increment or decrement X coordinates to space properly.
		gCount is the total group number so far on this side.
		tGCount is the total group count for this side.

		Collectively, these let us keep all the deployment logic in here.
		*/
		$highY = 0;
		$setup = [
			1 => [
				'count' => 1,
				'sep' => 0,
			],
			2 => [
				'count' => 1,
				'sep' => 0,
			],
			3 => [
				'count' => 1,
				'sep' => 0,
			],
			4 => [
				'count' => 1,
				'sep' => 0,
			],
			5 => [
				'count' => 1,
				'sep' => 0,
			],
			6 => [
				'count' => 1,
				'sep' => 0,
			],
			7 => [
				'count' => 1,
				'sep' => 0,
			],
		];
		foreach ($group->getUnits() as $unit) {
			$count = $setup[$line]['count'];
			$line = $unit->getSettings()->getLine();
			if ($invert) {
				$xPos = $startX - ($line*20);
			} else {
				$xPos = $startX + ($line*20);
			}
			if ($count === 1) {
				$yPos = $setup[$line]['sep'];
				$setup[$line]['sep'] = $yPos + 20;
			} elseif ($count % 2 === 0) {
				$yPos = $setup[$line]['sep'];
				$setup[$line]['sep'] = $yPos*-1;
			} else {
				$yPos = $setup[$line]['sep'];
				$setup[$line]['sep'] = ($yPos*-1)+20;
			}
			$setup[$line]['count'] = $count+1;
			if ($angle === null) {
				$unit->setXPos($xPos);
				$unit->setYPos($yPos);
			}
			if ($iCount > 2) {
				# Handle vertical offsets for future deployment.
				# We only need this if we have to work out angled deployments.
				if ($yPos > 0 && $yPos > $highY) {
					$highY = $yPos;
				}
			}
		}
		$gCount++;
		return [$highY. $gCount];
	}

	private function rotateCoords($x, $y, $focus, $angle) {
		# Do some math!
	}

	private function runStage($type, $rangedPenaltyStart, $phase, $doRanged) {
		$groups = $this->battle->getGroups();
		$battle = $this->battle;
		foreach ($groups as $group) {
			$shots = 0; # Ranged attack attempts
			$strikes = 0; # Melee attack attempts
			$rangedHits = 0;
			$routed = 0;
			$capture = 0;
			$chargeCapture = 0;
			$wound = 0;
			$chargeWound = 0;
			$kill = 0;
			$chargeKill = 0;
			$fail = 0;
			$chargeFail =0;
			$missed = 0;
			$crowded = 0;
			$staredDeath = 0;
			$noTargets = 0;
			#$attSlain = $this->attSlain; # For Sieges.
			#$defSlain = $this->defSlain; # For Sieges.
			$extras = array();
			$rangedPenalty = $rangedPenaltyStart; #We need each group to reset their rangedPenalty and defenseBonus.
			$defBonus = $this->defenseBonus;
			# The below is partially commented out until we fully add in the battle contact and siege weapon systems.
			if ($battle->getType() == 'siegeassault') {
				if ($battle->getPrimaryAttacker() == $group OR $group->getReinforcing() == $battle->getPrimaryAttacker()) {
					$rangedPenalty = 1; # TODO: Make this dynamic. Right now this can lead to weird scenarios in regions with higher penalties where the defenders are actually easier to hit.
					$siegeAttacker = TRUE;
					#$usedContacts = 0;
					#$currentContacts = $this->attCurrentContacts;
				} else {
					$defBonus = 0; # Siege defenders use pre-determined rangedPenalty.
					$siegeAttacker = FALSE;
					#$usedContacts = 0;
					#$currentContacts = $this->defCurrentContacts;
				}
			}
			if ($type != 'hunt') {
				$stageResult=array(); # Initialize this for later use. At the end of this loop, we commit this data to $stageReport->setData($stageResult);
				$stageReport = new BattleReportStage; # Generate new stage report.
				$this->em->persist($stageReport);
				$stageReport->setRound($phase);
				$stageReport->setGroupReport($group->getActiveReport());
				$this->em->flush();
				$group->getActiveReport()->addCombatStage($stageReport);

				$enemyCollection = new ArrayCollection;
				foreach ($group->getEnemies() as $enemygroup) {
					foreach ($enemygroup->getActiveSoldiers() as $soldier) {
						$enemyCollection->add($soldier);
					}
				}
				$enemies = $enemyCollection->count();
				$attackers = $group->getFightingSoldiers()->count();

				if (($battle->getPrimaryDefender() == $group) OR ($battle->getPrimaryAttacker() == $group)) {
					$this->log(5, "group ".$group->getActiveReport()->getId()." (".($group->getAttacker()?"attacker":"defender").") - ".$attackers." left, $enemies targets\n");
				} else {
					$this->log(5, "group ".$group->getActiveReport()->getId()." (Reinforcing group ".$group->getReinforcing()->getActiveReport()->getId().") - ".$attackers." left, $enemies targets\n");
				}

				$results = array();
			}

			/*

			Ranged Phase Combat Handling Code

			*/
			if ($type == 'ranged') {
				$bonus = sqrt($enemies); // easier to hit if there are many enemies
				foreach ($group->getFightingSoldiers() as $soldier) {
					if ($this->combat->RangedPower($soldier, true) > 0) {
						// ranged soldier - fire!
						$result=false;
						$this->log(10, $soldier->getName()." (".$soldier->getType().") fires - ");
						$target = $this->getRandomSoldier($enemyCollection);
						if ($target) {
							$shots++;
							$rPower = $this->combat->RangedPower($soldier, true);
							if ($this->combat->RangedRoll($defBonus, $rangedPenalty, $bonus, 95)) {
								// target hit
								$rangedHits++;
								list($result, $logs) = $this->combat->RangedHit($soldier, $target, $rPower, false, true, $this->xpMod, $defBonus);
								foreach ($logs as $each) {
									$this->log(10, $each);
								}
								if ($result=='fail') {
									$fail++;
								} elseif ($result=='wound') {
									$wound++;
								} elseif ($result=='capture') {
									$capture++;
								} elseif ($result=='kill') {
									$kill++;
								}
								if ($result=='kill'||$result=='capture') {
									$enemies--;
									$enemyCollection->removeElement($target);
								}
								// special results for nobles
								if ($target->isNoble() && in_array($result, array('kill','capture'))) {
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
								$missed++;
							}
							# Remove this check after the Battle 2.0 update and 2D maps are added.
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
				if ($enemies > 0 && $rangedHits > 0) {
					// morale damage - a function of how much fire we are taking
					// yes, this makes hits count several times - morale reduction above and twice here (since they're also always a shot)
					// we also double the effective morale of a soldier (after damage), because even a single hit triggers this test
					// and we don't want it to be overwhelming
					$moraledamage = ($shots+$rangedHits*2) / $enemies;
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
								if ($soldier->isNoble()) {
									$this->log(50, " - has no fear\n");
									$staredDeath++;
								} else {
									$this->log(50, " - panics\n");
									$soldier->setRouted(true);
									$this->history->addToSoldierLog($soldier, 'routed.ranged');
									$routed++;
								}
							} else {
								$this->log(50, " - has resolve\n");
							}
							$this->log(50, "\n");
						}
					}
					$this->log(10, "==> avg. morale: ".round($total/max(1,$count))."\n");
				}

				$stageResult = array('shots'=>$shots, 'rangedHits'=>$rangedHits, 'fail'=>$fail, 'wound'=>$wound, 'capture'=>$capture, 'kill'=>$kill, 'routed'=>$routed, 'stared'=>$staredDeath);
			}
			/*

			End of Ranged Phase Combat Handling Code

			*/
			/*

			Melee Phase Combat Handling Code

			*/
			if ($type == 'normal') {
				$bonus = sqrt($enemies);
				foreach ($group->getFightingSoldiers() as $soldier) {
					$result = false;
					$target = false;
					$counter = null;
					if ($doRanged && $phase == 2 && $soldier->isLancer() && $this->battle->getType() == 'field') {
						// Lancers will always perform a cavalry charge in the opening melee phase!
						// A cavalry charge can only happen if there is a ranged phase (meaning, there is ground to fire/charge across)
						$this->log(10, $soldier->getName()." (Lancer) attacks ");
						$target = $this->getRandomSoldier($enemyCollection);
						$counter = 'charge';
						if ($target) {
							$noTargets = 0;
							$strikes++;
							list($result, $logs) = $this->combat->ChargeAttack($soldier, $target, false, true, $this->xpMod, $this->defenseBonus);
							foreach ($logs as $each) {
								$this->log(10, $each);
							}
						} else {
							// no more targets
							$this->log(10, "but finds no target\n");
							$noTargets++;
						}
					} else if ($soldier->isRanged() && $doRanged) {
						// Continure firing with a reduced hit chance in regular battle. If we skipped the ranged phase due to this being the last battle in a siege, we forego ranged combat to pure melee instead.
						// TODO: friendly fire !
						$this->log(10, $soldier->getName()." (".$soldier->getType().") fires - ");

						$target = $this->getRandomSoldier($enemyCollection);
						if ($target) {
							$noTargets = 0;
							$shots++;
							$rPower = $this->combat->RangedPower($soldier, true);
							if ($this->combat->RangedRoll($defBonus, $rangedPenalty, $bonus)) {
								$rangedHits++;
								list($result, $logs) = $this->combat->RangedHit($soldier, $target, $rPower, false, true, $this->xpMod, $defBonus);
								foreach ($logs as $each) {
									$this->log(10, $each);
								}
							} else {
								$missed++;
								$this->log(10, "missed\n");
							}
						} else {
							// no more targets
							$this->log(10, "but finds no target\n");
							$noTargets++;
						}
					} else {
						// We are either in a siege assault and we have contact points left, OR we are not in a siege assault. We are a melee unit or ranged unit with melee capabilities in final siege battle.
						$this->log(10, $soldier->getName()." (".$soldier->getType().") attacks ");
						$target = $this->getRandomSoldier($enemyCollection);
						$counter = 'melee';
						if ($target) {
							$noTargets = 0;
							$strikes++;
							$mPower = $this->combat->MeleePower($soldier, true);
							list($result, $logs) = $this->combat->MeleeAttack($soldier, $target, $mPower, false, true, $this->xpMod, $this->defenseBonus); // Basically, an attack of opportunity.
							foreach ($logs as $each) {
								$this->log(10, $each);
							}
							/*
							if ($battle->getType() == 'siegeassault') {
								$usedContacts++;
								if ($result=='kill'||$result=='capture') {
									if (!$siegeAttacker) {
										$attSlain++;
									} else {
										$defSlain++;
									}
								}
							}
							*/
						} else {
							// no more targets
							$this->log(10, "but finds no target\n");
							$noTargets++;
						}
					}
					if ($counter && strpos($result, ' ') !== false) {
						$results = explode(' ', $result);
						$result = $results[0];
						$result2 = $counter . $results[1];
					} else {
						$result2 = false;
					}
					if ($result) {
						if ($result=='kill'||$result=='capture') {
							$enemies--;
							$enemyCollection->removeElement($target);
						}
						if ($result=='fail') {
							$fail++;
						} elseif ($result=='wound') {
							$wound++;
						} elseif ($result=='capture') {
							$capture++;
						} elseif ($result=='kill') {
							$kill++;
						}

						// special results for nobles
						if ($target->isNoble() && in_array($result, array('kill','capture'))) {
							if ($result=='capture' || $soldier->isNoble()) {
								$extra = array(
									'what' => 'noble.'.$result,
									'by' => $soldier->getCharacter()->getId()
								);
							} else {
								$extra = array('what'=>'mortal.'.$result);
							}

							$extra['who'] = $target->getCharacter()->getId();
							$extras[] = $extra;
						}
					} else {
						$noTargets++;
						/*
						if ($battle->getType() == 'siegeassault' && $usedContacts >= $currentContacts) {
							$crowded++; #Frontline is too crowded in the siege.
						} else {
							$noTargets++; #Just couldn't hit the target :(
						}
						*/
					}
					if ($result2) {
						if ($result2=='chargewound') {
							$chargeWound++;
						} elseif ($result2=='chargecapture') {
							$chargeCapture++;
						} elseif ($result2=='chargekill') {
							$chargeKill++;
						}
					}
					if ($noTargets > 4) {
						$this->log(10, "Unable to locate viable targets -- skipping further calculations\n");
						break;
					}
				}
				$stageResult = array('alive'=>$attackers, 'shots'=>$shots, 'rangedHits'=>$rangedHits, 'strikes'=>$strikes, 'misses'=>$missed, 'notarget'=>$noTargets, 'crowded'=>$crowded, 'fail'=>$fail, 'wound'=>$wound, 'capture'=>$capture, 'kill'=>$kill, 'chargefail' => $chargeFail, 'chargewound'=>$chargeWound, 'chargecapture'=>$chargeCapture, 'chargekill'=>$chargeKill);
			}
			if ($type != 'hunt') { # Check that we're in either Ranged or Melee Phase
				$stageReport->setData($stageResult); # Commit this stage's results to the combat report.
				$stageReport->setExtra($extras); # Commit this foolery because storing it in data is going to be chaos incarnate.
			}
			/*
			$this->defSlain += $defSlain;
			$this->attSlain += $attSlain;
			if ($battle->getType() == 'siegeassault') {
				if ($siegeAttacker) {
					$this->log(10, "Used ".$usedContacts." contacts.\n");
					$this->attUsedContacts += $usedContacts;
				} else {
					$this->log(10, "Used ".$usedContacts." contacts.\n");
					$this->defUsedContacts += $usedContacts;
				}
			}
			*/
		}
		/*

		Ranged & Melee Phase Morale Handling Code

		*/
		# In order to support legacy melee morale handling, we need to break this apart. First, refactor it. Second, rework the ranged morale into it and give them both a distinct area.
		if ($type == 'normal') {
			foreach ($groups as $group) {
				$this->log(10, "morale checks:\n");
				$stageResult = $group->getActiveReport()->getCombatStages()->last(); #getCombatStages always returns these in round ascending order. Thus, the latest one will be last. :)
				$routed = 0;

				$countUs = $group->getActiveSoldiers()->count();
				foreach ($group->getReinforcedBy() as $reinforcement) {
					$countUs += $reinforcement->getActiveSoldiers()->count();
				}
				$countEnemy = 0;
				$enemies = $group->getEnemies();
				foreach ($enemies as $enemygroup) {
					$countEnemy += $enemygroup->getActiveSoldiers()->count();
				}
				foreach ($group->getActiveSoldiers() as $soldier) {
					// still alive? check for panic
					if ($countEnemy > 0) {
						$ratio = $countUs / $countEnemy;
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
						if ($soldier->isNoble()) {
							$this->log(10, $soldier->getName()." (".$soldier->getType()."): ($mod) morale ".round($soldier->getMorale())." - has no fear\n");
							$staredDeath++;
						} else {
							$routed++;
							$this->log(10, $soldier->getName()." (".$soldier->getType()."): ($mod) morale ".round($soldier->getMorale())." - panics\n");
							$soldier->setRouted(true);
							$countUs--;
							$this->history->addToSoldierLog($soldier, 'routed.melee');
						}
					} else {
						$this->log(20, $soldier->getName()." (".$soldier->getType()."): ($mod) morale ".round($soldier->getMorale())."\n");
					}
				}
				$combatResults = $stageResult->getData(); # CFetch original array.
				$combatResults['routed'] = $routed; # Append routed info.
				$combatResults['stared'] = $staredDeath;
				$stageResult->setData($combatResults); # Add routed to array and save.
			}
		}

		if ($type != 'hunt') {
			# Check if we're still fighting.
			$firstOrderCount = 0; # Count of active enemy soldiers
			$secondOrderCount = 0; # Count of acitve soldiers of enemy's enemies.
			foreach ($groups as $group) {
				$reverseCheck = false;
				foreach ($group->getEnemies() as $enemyGroup) {
					$firstOrderCount += $enemyGroup->getActiveSoldiers()->count();
					if (!$reverseCheck) {
						foreach ($enemyGroup->getEnemies() as $secondOrder) {
							$secondOrderCount += $secondOrder->getActiveSoldiers()->count();
						}
						$reverseCheck = true;
					}
				}
				break; # We only actually need any one group to start from.
			}

			if ($firstOrderCount == 0 OR $secondOrderCount == 0) {
				return false; # Fighting has ended.
			} else {
				return true; # Fighting continues.
			}
		} else {
			# Hunt down remaining enemies. Hunt comes after all other phases.

			$fleeing_entourage = array();
			$countEntourage = 0; #All fleeing entourage.
			$countSoldiers = 0; #All fleeing soldiers.
			$shield = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('shield');
			foreach ($groups as $group) {
				$huntResult = array();
				$groupReport = $group->getActiveReport(); # After it's built, the $huntResult array is saved via $groupReport->setHunt($huntResult);
				if ($group->getFightingSoldiers()->count()==0) {
					$this->log(10, "group is retreating:\n");
					$countGroup=0;
					foreach ($group->getCharacters() as $char) {
						$this->log(10, "character ".$char->getName());
						$count=0; #Entourage per character.
						foreach ($char->getLivingEntourage() as $e) {
							$fleeing_entourage[] = $e;
							$count++;
							$countGroup++;
							$countEntourage++;
						}
						$this->log(10, " $count entourage\n");
					}
					$groupReport->setHunt(array('entourage'=>$countGroup));
				}
			}
			$this->em->flush();
			$this->log(10, count($fleeing_entourage)." entourage are on the run.\n");

			foreach ($groups as $group) {
				$groupReport = $group->getActiveReport();
				# For the life of me, I don't remember why I added this next bit.
				if($groupReport->getHunt()) {
					$huntReport = $groupReport->getHunt();
				} else {
					$huntReport = array('killed'=>0, 'entkilled'=>0, 'dropped'=>0);
				}
				$this->prepareRound(); // called again each group to update the fighting status of all enemies

				$enemyCollection = new ArrayCollection;
				foreach ($group->getEnemies() as $enemygroup) {
					foreach ($enemygroup->getRoutedSoldiers() as $soldier) {
						$enemyCollection->add($soldier);
						$countSoldiers++;
					}
				}

				foreach ($group->getFightingSoldiers() as $soldier) {
					$target = $this->getRandomSoldier($enemyCollection);
					$hitchance = 0; // safety-catch, it should be set in all cases further down
					if ($target) {
						if ($this->combat->RangedPower($soldier, true) > $this->combat->MeleePower($soldier, true)) {
							$hitchance = 10+round($this->combat->RangedPower($soldier, true)/2);
							$power = $this->combat->RangedPower($soldier, true)*0.75;
						} else {
							// chance of catching up with a fleeing enemy
							if ($soldier->getEquipment() && in_array($soldier->getEquipment()->getName(), array('horse', 'war horse'))) {
								$hitchance = 50;
							} else {
								$hitchance = 30;
							}
							$hitchance = max(5, $hitchance - $this->combat->DefensePower($soldier, true)/5); // heavy armour cannot hunt so well
							$power = $this->combat->MeleePower($soldier, true)*0.75;
						}
						if ($target->getEquipment() && in_array($target->getEquipment()->getName(), array('horse', 'war horse'))) {
							$hitmod = 0.5;
						} else {
							$hitmod = 1.0;
						}

						$evade = min(75, round($target->getExperience()/10 + 5*sqrt($target->getExperience())) ); // 5 = 12% / 20 = 24% / 50 = 40% / 100 = 60%

						# Ranged penalty is used here to simulate the terrain advantages that retreating soldiers get to evasion. :)
						if (rand(0,100) < $hitchance * $hitmod && rand(0,100) > $evade/$rangedPenalty) {
							// hit someone!
							$this->log(10, $soldier->getName()." (".$soldier->getType().") caught up with ".$target->getName()." (".$target->getType().") - ");
							if (rand(0,$power) > rand(0,$this->combat->DefensePower($target, true))) {
								$result = $this->combat->resolveDamage($soldier, $target, $power, 'battle', 'escape');
								if ($result) {
									$huntReport['killed']++;
									if ($result == 'killed') {
										// FIXME: This apparently doesn't work? At least once I saw a killed noble being attacked again
										$enemyCollection->removeElement($target);
									}
								} else {
									$target->addAttack(4);
								}
							} else {
								// no damage, check for dropping gear
								$this->log(10, "no damage\n");
								if ($target->isNoble()) continue; // noble characters don't throw away anything
								// throw away your shield - very likely
								if ($target->getEquipment() && $target->getEquipment() == $shield) {
									if (rand(0,100)<80) {
										$target->dropEquipment();
										$this->history->addToSoldierLog($target, 'dropped.shield');
										$this->log(10, $target->getName()." (".$target->getType()."): drops shield\n");
										$huntReport['dropped']++;
									}
								}
								// throw away your weapon - depends on weapon
								if ($target->getWeapon()) {
									switch ($target->getWeapon()->getName()) {
										case 'spear':		$chance = 40; break;
										case 'pike':		$chance = 50; break;
										case 'longbow':	$chance = 30; break;
										default:				$chance = 20;
									}
									if (rand(0,100)<$chance) {
										$target->dropWeapon();
										$this->history->addToSoldierLog($target, 'dropped.weapon');
										$this->log(10, $target->getName()." (".$target->getType()."): drops weapon\n");
										$huntReport['dropped']++;
									}
								}
							}
						}
					} else if (!empty($fleeing_entourage)) {
						# No routed soldiers? Try for an entourage.
						$this->log(10, "... now attacking entourage - ");
						if (rand(0,100) < $hitchance) {
							// yepp, we got one
							$i = rand(0,count($fleeing_entourage)-1);
							$target = $fleeing_entourage[$i];
							$this->log(10, "slaughters ".$target->getName()." (".$target->getType()->getName().")\n");
							// TODO: log this!
							$target->kill();
							$huntReport['entkilled']++;
							array_splice($fleeing_entourage, $i, 1);
						} else {
							$this->log(10, "didn't hit (chance was $hitchance)\n");
						}
					}
				}
				$groupReport->setHunt($huntReport);
			}
			$this->em->flush();
			return true;
		}
	}

	private function concludeBattle() {
		$battle = $this->battle;
		$this->log(3, "survivors:\n");
		$this->prepareRound(); // to update the isFighting setting correctly
		$survivors=array();
		foreach ($battle->getGroups() as $group) {
			$this->log(5, "Evaluating ".$group->getActiveReport()->getId()." (".($group->getAttacker()?"attacker":"defender").") for survivors...\n");
			foreach ($group->getSoldiers() as $soldier) {
				if ($soldier->getCasualties() > 0) {
					$this->history->addToSoldierLog($soldier, 'casualties', array("%nr%"=>$soldier->getCasualties()));
				}
			}

			$types=array();
			foreach ($group->getActiveSoldiers() as $soldier) {
				$soldier->gainExperience(2*$this->xpMod);

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
			$group->getActiveReport()->setFinish($troops);
		}

		$allNobles=array();

		$allGroups = $this->battle->getGroups();
		$this->log(2, "Fate of First Ones:\n");
		$primaryVictor = null;
		foreach ($allGroups as $group) {
			$nobleGroup=array();
			$my_survivors = $group->getActiveSoldiers()->count();
			if ($my_survivors > 0) {
				$this->log(5, "Group ".$group->getActiveReport()->getId()." (".($group->getAttacker()?"attacker":"defender").") has survivors, and is victor.\n");
				$victory = true;
				if (!$primaryVictor) {
					$this->log(5, "Considering ".$group->getActiveReport()->getId()." (".($group->getAttacker()?"attacker":"defender").") as primary victor.\n");
					# Because it's handy to know who won, primarily for sieges.
					# TODO: Rework for more than 2 sides. This should be really easy. Just checking to see if we have soldiers and finding our top-level group.
					if ($battle->getPrimaryAttacker() == $group) {
						$primaryVictor = $group;
						$this->log(5, $group->getActiveReport()->getId()." (".($group->getAttacker()?"attacker":"defender").") ID'd as primary attacker and primary victor.\n");
					} elseif ($battle->getPrimaryDefender() == $group) {
						$primaryVictor = $group;
						$this->log(5, $group->getActiveReport()->getId()." (".($group->getAttacker()?"attacker":"defender").") ID'd as primary defender and primary victor.\n");
					} elseif ($battle->getPrimaryAttacker()->getReinforcedBy->contains($group) || $battle->getPrimaryDefender()->getReinforcedBy->contains($group)) {
						$primaryVictor = $group->getReinforcing();
						$this->log(5, $group->getActiveReport()->getId()." (".($group->getAttacker()?"attacker":"defender").") ID'd as primary victor but is reninforcing group #".$primaryVictor()->getActiveReport()->getId()." (".($primaryVictor->getAttacker()?"attacker":"defender").").\n");
					} else {
						# I have so many questions about how you ended up here...
					}
				}
			} else {
				$victory = false;
			}
			foreach ($group->getSoldiers() as $soldier) {
				if ($soldier->isNoble()) {
					$id = $soldier->getCharacter()->getId();
					$allNobles[] = $soldier->getCharacter(); // store these here, because in some cases below they get removed from battlegroups
					if (!$soldier->isAlive()) {
						$nobleGroup[$id]='killed';
						// remove from BG or the kill() could trigger false "battle failed" messages
						$group->removeCharacter($soldier->getCharacter());
						$soldier->getCharacter()->removeBattlegroup($group);
						// FIXME: how do we get the killer ?
						$this->character_manager->kill($soldier->getCharacter(), null, false, 'death2');
					} elseif ($soldier->getCharacter()->isPrisoner()) {
						$nobleGroup[$id]='captured';
						// remove from BG or the imprison_complete() could trigger false "battle failed" messages
						$group->removeCharacter($soldier->getCharacter());
						$soldier->getCharacter()->removeBattlegroup($group);
						$this->character_manager->imprison_complete($soldier->getCharacter());
					} elseif ($soldier->isWounded()) {
						$nobleGroup[$id]='wounded';
					} elseif ($soldier->isActive()) {
						if ($victory) {
							$nobleGroup[$id]='victory';
						} else {
							$nobleGroup[$id]='retreat';
						}
					} else {
						$nobleGroup[$id]='retreat';
					}
					// defeated losers could be forced out
					if ($nobleGroup[$id]!='victory') {
						if ($this->battle->getType()=='urban' && $soldier->getCharacter()->getInsideSettlement()) {
							$this->interactions->characterLeaveSettlement($soldier->getCharacter(), true);
						}
					}
					$this->log(2, $soldier->getCharacter()->getName().': '.$nobleGroup[$id]." (".$soldier->getWounded()."/".$soldier->getCharacter()->getWounded()." wounds)\n");
				}
			}
			$group->getActiveReport()->setFates($nobleGroup);
		}

		$this->log(1, "Battle finished, report #".$this->report->getId()."\n");

		foreach ($allNobles as $char) {
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
		$this->em->flush();
		$this->log(1, "unlocking characters...\n");
		foreach ($allNobles as $noble) {
			$noble->setActiveReport(null); #Unset active report.
			$noble->setBattling(false);
		}
		foreach ($allGroups as $group) {
			$group->setActiveReport(null); #Unset active report.
		}
		$this->em->flush();
		$this->log(1, "unlocked...\n");
		unset($allNobles);
		$this->log(5, "concludeBattle returning ".$primaryVictor->getId()." (".($primaryVictor->getAttacker()?"attacker":"defender").") as primary victor.\n");
		return $primaryVictor;
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

	private function addNobleResult($noble, $result, $enemy) {
		# TODO: This is primarily for later, when we have time to implement this.
		$report = $noble->getActiveReport();
		if ($result == 'fail' || $result == 'wound' || $result == 'capture' || $result =='kill') {
			if ($report->getAttacks()) {
				$report->setAttacks($report->getAttacks()+1);
			} else {
				$report->setAttacks(1);
			}
			if ($result == 'wound' || $result == 'capture') {
				if ($report->getHitsMade()) {
					$report->setHitsMade($report->getHitsMade()+1);
				} else {
					$report->setHitsMade(1);
				}
			}
			if ($result == 'kill') {
				if ($report->getKills()) {
					$report->setKills($report->getKills()+1);
				} else {
					$report->setKills(1);
				}
			}
		} else {
			if ($report->getHitsTaken()) {
				$report->setHitsTaken($report->getHitsTaken()+1);
			} else {
				$report->setHitsTaken(1);
			}
			if ($result == 'captured') {
				$report->setCaptured(true);
				$report->setCapturedBy($enemy);
			}
			if ($result == 'killed') {
				$report->setKilled(true);
				$repot->setKilledBy($enemy);
			}
		}
	}

	private function progressSiege(Battle $battle, BattleGroup $victor = null, $flag) {
		$siege = $battle->getSiege();
		$report = $this->report;
		$current = $siege->getStage();
		$max = $siege->getMaxStage();
		$assault = FALSE;
		$sortie = FALSE;
		$bypass = FALSE;
		$completed = FALSE;
		if ($battle->getType() === 'siegeassault') {
			$assault = TRUE;
			$this->log(1, "PS: Siege assualt\n");
		} elseif ($battle->getType() === 'siegesortie') {
			$sortie = TRUE;
			$this->log(1, "PS: Siege sortie\n");
		}
		$attacker = $battle->getPrimaryAttacker();
		if ($flag === 'haveAttacker') {
			$victor = $siege->getAttacker();
			$bypass = TRUE; #Defenders failed to muster any defenders.
			$this->log(1, "PS: Bypass defenders. Default victory to attackers\n");
		} elseif ($flag === 'haveDefender') {
			$victor = $siege->getDefender();
			$bypass = TRUE; #Attackers failed to muster any attackers.
			$this->log(1, "PS: Bypass attackers. Default victory to defenders\n");
		}
		if ($siege->getSettlement()) {
			$target = $siege->getSettlement();
		} else {
			$target = $siege->getPlace();
		}
		if ($assault) {
			$this->log(1, "PS: Attacker matches victor and this is an assault.\n");
			if ($current < $max && !$bypass) {
				# Siege moves forward
				$siege->setStage($current+1);
				$this->log(1, "PS: Incrememnting stage.\n");
				# "After the [link], the siege has advanced in favor of the attackers"
				$this->history->logEvent(
					$target,
					'siege.advance.attacker',
					array('%link-battle%'=>$report->getId()),
					History::MEDIUM, true, 20
				);
				foreach ($siege->getGroups() as $group) {
					foreach ($group->getCharacters() as $char) {
						$this->history->logEvent(
							$char,
							'siege.advance.attacker',
							array(),
							History::MEDIUM, false, 20
						);
					}
				}
			}
			if ($current == $max || $bypass) {
				$this->log(1, "PS: Max stage reached or bypass flag set due to failed defense.\n");
				$completed = TRUE;
				# Siege is over, attackers win.
				if (!$bypass) {
					# "After the defenders failed to muster troops in [link], the siege concluded in attacker victory."
					$this->history->logEvent(
						$target,
						'siege.victor.attacker',
						array(),
						History::MEDIUM, false
					);
				} else {
					$this->log(1, "PS: Bypassed!\n");
					# "After the [link], the siege concluded in an attacker victory."
					$this->history->logEvent(
						$target,
						'siege.bypass.attacker',
						array('%link-battle%'=>$report->getId()),
						History::MEDIUM, false
					);
					foreach ($victor->getCharacters() as $char) {
						$this->history->logEvent(
							$char,
							'battle.failed',
							array(),
							History::MEDIUM, false, 20
						);
					}
				}

			}
		} elseif ($sortie) {
			$this->log(1, "PS: Attacker is not victor. This must be a sortie by the defenders.\n");
			if ($current > 1 && !$bypass) {
				# Siege moves backwards.
				$siege->setStage($current-1);
				$this->log(1, "PS: Decrementing stage.\n");
				# "After the [link], the siege has advanced in favor of the defenders"
				$this->history->logEvent(
					$target,
					'siege.advance.defender',
					array('%link-battle%'=>$report->getId()),
					History::MEDIUM, true, 20
				);
				foreach ($siege->getGroups() as $group) {
					foreach ($group->getCharacters() as $char) {
						$this->history->logEvent(
							$char,
							'siege.advance.defender',
							array(),
							History::MEDIUM, false, 20
						);
					}
				}
			}
			if ($current <= 1 || $bypass) {
				$this->log(1, "PS: Minimum stage reached or bypass flag set due to failure by siege attackers to muster any force. Siege broken.\n");
				$completed = TRUE;
				# Siege is over, defender victory.
				if ($bypass) {
					# "After the attackers failed to muster troops in [link], the siege concluded in defender victory."
					$this->log(1, "PS: Bypassed!\n");
					$this->history->logEvent(
						$target,
						'siege.victor.defender',
						array(),
						History::MEDIUM, false
					);
				} else {
					# "After the [link], the siege concluded in a defender victory."
					$this->history->logEvent(
						$target,
						'siege.bypass.defender',
						array('%link-battle%'=>$report->getId()),
						History::MEDIUM, false
					);
					foreach ($victor->getCharacters() as $char) {
						$this->history->logEvent(
							$char,
							'battle.failed',
							array(),
							History::MEDIUM, false, 20
						);
					}
				}
			}
		}
		# Yes, this means that if attackers lose an assault or defenders lose a sortie, nothing changes. This is intentional.
		$battle->setPrimaryAttacker(NULL);
		$battle->setPrimaryDefender(NULL);
		$this->log(1, "PS: Unset primary flags!\n");
		foreach ($siege->getGroups() as $group) {
			$group->setBattle(NULL);
		}
		$this->log(1, "PS: Unset group battle associations!\n");

		if ($completed) {
			$this->log(1, "PS: Siege completed, running completion cycle.\n");
			$realm = $siege->getRealm();
			if ($assault) {
				if ($target instanceof Settlement) {
					$this->log(1, "PS: Target is settlement\n");
					foreach ($victor->getCharacters() as $char) {
							# Force move victorious attackers inside the settlement.
							$this->interactions->characterEnterSettlement($char, $target, true);
							$this->log(1, "PS: ".$char->getName()." moved inside ".$target->getName().". \n");
					}
					$leader = $victor->getLeader();
					if (!$leader) {
						$this->log(1, "PS: No leader! Finding one at random!. \n");
						$leader = $victor->getCharacters()->first(); #Get one at random.
					}
					if ($leader) {
						$this->politics->changeSettlementOccupier($leader, $target, $realm);
						$this->log(1, "PS: Occupant set to ".$leader->getName()." \n");
					}
				} else {
					$this->log(1, "PS: Target is place\n");
					foreach ($victor->getCharacters() as $char) {
							# Force move victorious attackers inside the place.
							$this->interactions->characterEnterPlace($char, $target, true);
							$this->log(1, "PS: ".$char->getName()." moved inside ".$target->getName().". \n");
					}
					$leader = $victor->getLeader();
					if (!$leader) {
						$this->log(1, "PS: No leader! Finding one at random!. \n");
						$leader = $victor->getCharacters()->first(); #Get one at random.
					}
					if ($leader) {
						$this->politics->changePlaceOccupier($leader, $target, $realm);
						$this->log(1, "PS: Occupant set to ".$leader->getName()." \n");
					}
					foreach ($target->getUnits() as $unit) {
						$this->milman->returnUnitHome($unit, 'defenselost', $victor->getLeader());
						$this->log(1, "PS: ".$unit->getId()." sent home. \n");
						$this->history->logEvent(
							$unit,
							'event.unit.defenselost2',
							array("%link-place%"=>$target->getId()),
							History::HIGH, true
						);
					}
				}
			}
			$this->em->flush();
			$this->log(1, "PS: Passing siege to disbandSiege function\n");
			$this->warman->disbandSiege($siege, null, TRUE);
		}
		$this->em->flush();

	}

}
