<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Election;
use BM2\SiteBundle\Entity\RealmPosition;
use BM2\SiteBundle\Entity\Supply;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Symfony\Component\Stopwatch\Stopwatch;


class GameRunner {

	private $batchsize=200;
	private $maxtime=2400;

	private $em;
	private $appstate;
	private $logger;
	private $resolver;
	private $economy;
	private $politics;
	private $history;
	private $milman;
	private $battlerunner;
	private $interactions;
	private $geography;
	private $generator;
	private $rm;
	private $convman;
	private $pm;
	private $npc;
	private $cm;

	private $cycle=0;
	private $output=false;
	private $debug=false;
	private $limited=false;

	private $bandits_ok_distance = 50000;
	private $seen;

	public function __construct(EntityManager $em, AppState $appstate, Logger $logger, ActionResolution $resolver, Economy $economy, Politics $politics, History $history, MilitaryManager $milman, BattleRunner $battlerunner, Interactions $interactions, Geography $geography, Generator $generator, RealmManager $rm, ConversationManager $convman, PermissionManager $pm, NpcManager $npc, CharacterManager $cm) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->logger = $logger;
		$this->resolver = $resolver;
		$this->economy = $economy;
		$this->politics = $politics;
		$this->history = $history;
		$this->milman = $milman;
		$this->battlerunner = $battlerunner;
		$this->interactions = $interactions;
		$this->geography = $geography;
		$this->generator = $generator;
		$this->rm = $rm;
		$this->convman = $convman;
		$this->pm = $pm;
		$this->npc = $npc;
		$this->cm = $cm;

		$this->cycle = $this->appstate->getCycle();
		$this->speedmod = (float)$this->appstate->getGlobal('travel.speedmod', 1.0);
	}

	public function runCycle($type, $maxtime=1200, $timing=false, $debug=false, $output=false, $limited=false) {
		$this->maxtime=$maxtime;
		$this->output=$output;
		$this->debug=$debug;
		$this->limited=$limited;

		if ($timing) {
			$stopwatch = new Stopwatch();
		}

		$old = new \DateTime("-90 days");
		$query = $this->em->createQuery('DELETE FROM BM2SiteBundle:UserLog u WHERE u.ts < :old');
		$query->setParameter('old', $old);
		$query->execute();

		switch ($type) {
			case 'update':		$pattern = '/^update(.+)$/';
									$this->speedmod = 1/6; // since this happens every hour and not just every 6 hours
									break;
			case 'turn':
			default:				$pattern = '/^run(.+)Cycle$/';
									$this->speedmod = 1.0;
		}

		foreach (get_class_methods(__CLASS__) as $method_name) {
			if (preg_match($pattern, $method_name, $match)) {
				if ($timing) {
					$stopwatch->start($match[1]);
				}
				$complete = $this->$method_name();
				if ($timing) {
					$event = $stopwatch->stop($match[1]);
					$this->logger->info($match[1].": ".date("g:i:s").", ".($event->getDuration()/1000)." s, ".(round($event->getMemory()/1024)/1024)." MB");
				}
				if (!$complete) return false;
			}
		}

		return true;
	}

	public function nextCycle($next_day=true) {
		if ($next_day) {
			$this->appstate->setGlobal('cycle', ++$this->cycle);
			if ($this->cycle%360 == 0) {
				// new year !
				$this->eventNewYear();
			}
		}
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Setting s SET s.value=0 WHERE s.name LIKE :cycle');
		$query->setParameter('cycle', 'cycle.'.'%');
		$query->execute();
		$this->em->flush();
		return true;
	}

	/*
		IMPORTANT NOTICE:
		the order in which these methods are defined is the order in which they are resolved,
		due to the way get_class_methods() works !
		also, they HAVE to end in "Cycle" to be called.

		all of these return true if cycle complete, false otherwise
	*/

	# Due to the nature of GameRequests, we need them to be checked before anything else, as they can invalidate the results of other turn/update checks. Hence, theyr'e first in the list.
	public function runGameRequestCycle() {
		$last = $this->appstate->getGlobal('cycle.gamerequest', 0);
		if ($last==='complete') return true;
		$this->logger->info("Game Request Cycle...");

		$now = new \DateTime("now");
		$query = $this->em->createQuery('SELECT r FROM BM2SiteBundle:GameRequest r WHERE r.expires <= :now')->setParameter('now', $now);
		$result = $query->iterate();
		while ($row = $result->next()) {
			$allUnits = array();
			foreach($row[0]->getFromCharacter()->getUnits() as $unit) {
				if ($unit->getSupplier()==$row[0]->getToSettlement()) {
					$char = $row[0]->getFromCharacter();
					$this->logger->info("  Character ".$char->getName()." (".$char->getId().") may be using request for food...");
					# Character supplier matches target settlement, we need to see if this is still a valid food source.

					# Get all character realms.
					$myRealms = $char->findRealms();
					$settlements = new ArrayCollection;
					foreach ($char->getOwnedSettlements() as $settlement) {
						$settlements->add($settlement);
					}
					if ($char->getLiege()) {
						$liege = $char->getLiege();
						foreach ($liege->getOwnedSettlements() as $settlement) {
							if ($settlement->getPermissions()->getFeedSoldiers()) {
								$settlements->add($settlement);
							}
						}
					}

					$reqs = $char->getRequests();
					if ($reqs->count() > 1) {
						foreach ($reqs as $req) {
							if ($req->getType() === 'soldier.food' && $req->getAccepted()) {
								$settlements->add($req->getToSettlement());
							}
						}
					}
					if (!$settlements->contains($row[0]->getToSettlement())) {
						$row[0]->getToSettlement()->getSuppliedUnits()->remove($unit);
						$unit->setSupplier(null);
					}
				}
			}
			$this->em->remove($row[0]);
			# We're doing it this way as a direct delete request skips a lot of the doctrine cascades and relation updates.
			# Yes, this is slower than just a DQL delete, but it's also a bit more resilient and less likely to break things down the line.
		}
		$this->em->flush();
		$this->em->clear();

		$this->appstate->setGlobal('cycle.gamerequest', 'complete');
		return true;
	}

	public function runCharactersUpdatesCycle() {
		$last = $this->appstate->getGlobal('cycle.characters', 0);
		if ($last==='complete') return true;
		$this->logger->info("Characters Cycle...");

		// healing
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Character c SET c.wounded=0 WHERE c.wounded <= 10');
		$query->execute();
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Character c SET c.wounded=c.wounded-10 WHERE c.wounded > 10');
		$query->execute();

		$this->logger->info("  Checking for dead and slumbering characters that need sorting...");
		// NOTE: We're going to want to change this from c.system is null to something else, or build additional logic down the line, when we have more thant 'procd_inactive' as the system flag.
		$query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Character c WHERE (c.alive = false AND c.location IS NOT NULL AND (c.system IS NULL OR c.system <> :system)) OR (c.alive = true and c.slumbering = true AND (c.system IS NULL OR c.system <> :system))');
		$query->setParameter('system', 'procd_inactive');
		$result = $query->getResult();
		if (count($result) > 0) {
			$this->logger->info("  Sorting the dead from the slumbering...");
		} else {
			$this->logger->info("  No dead or slumbering found!");
		}
		$dead = [];
		$slumbered = [];
		$deadcount = 0;
		$knowndead = 0;
		$slumbercount = 0;
		$knownslumber = 0;
		$keeponslumbercount = 0;
		$via = null;
		$heir = null;
		foreach ($result as $character) {
			$this->seen = new ArrayCollection;
			list($heir, $via) = $this->findHeir($character);
			if (!$character->isAlive()) {
				$deadcount++;
				$dead[] = $character;
			} else if ($character->getSlumbering()) {
				$slumbercount++;
				$slumbered[] = $character;
			}
		}
		if ($deadcount+$slumbercount != 0) {
			$this->logger->info("  Sorting $deadcount dead and $slumbercount slumbering");
		}
		foreach ($dead as $character) {
			if ($character->getSystem() != 'procd_inactive') {
				$this->logger->info("  ".$character->getName().", ".$character->getId()." is under review, as dead.");
				$character->setLocation(NULL)->setInsideSettlement(null)->setTravel(null)->setProgress(null)->setSpeed(null);
				$this->logger->info("    Dead; removed from the map.");
				$captor = $character->getPrisonerOf();
				if ($captor) {
					$this->logger->info("    Captive. The dead are captive no more.");
					$character->setPrisonerOf(null);
					$captor->removePrisoner($character);
				}
				$this->logger->info("    Heir: ".($heir?$heir->getName():"(nobody)"));
				if ($character->getPositions()) {
					$this->logger->info("    Positions detected");
					foreach ($character->getPositions() as $position) {
						if ($position->getRuler()) {
							$this->logger->info("    ".$position->getName().", ".$position->getId().", is detected as ruler position.");
							if ($heir) {
								$this->logger->info("    ".$heir->getName()." inherits ".$position->getRealm()->getName());
								$this->cm->inheritRealm($position->getRealm(), $heir, $character, $via, 'death');
							} else {
								$this->logger->info("  No one inherits ".$position->getRealm()->getName());
								$this->cm->failInheritRealm($character, $position->getRealm(), 'death');
							}
							$this->logger->info("    Removing them from ".$position->getName());
							$position->removeHolder($character);
							$character->removePosition($position);
							$this->logger->info("    Removed.");
						} else if ($position->getInherit()) {
							if ($heir) {
								$this->logger->info("    ".$heir->getName()." inherits ".$position->getRealm()->getName());
								$this->cm->inheritPosition($position, $position->getRealm(), $heir, $character, $via, 'death');
							} else {
								$this->logger->info("    No one inherits ".$position->getName());
								$this->cm->failInheritPosition($character, $position, 'death');
							}
							$this->logger->info("    Removing them from ".$position->getName());
							$position->removeHolder($character);
							$character->removePosition($position);
							$this->logger->info("    Removed.");
						} else {
							$this->logger->info("    No inheritance. Removing them from ".$position->getName());
							$this->history->logEvent(
								$position->getRealm(),
								'event.position.death',
								array('%link-character%'=>$character->getId(), '%link-realmposition%'=>$position->getId()),
								History::LOW, true
							);
							$position->removeHolder($character);
							$character->removePosition($position);
							$this->logger->info("    Removed.");
						}
					}
				}
				$character->setSystem('procd_inactive');
				$this->logger->info("    Character set as known dead.");
			} else {
				$knowndead++;
			}
			$this->em->flush();
		}
		foreach ($slumbered as $character) {
			if ($character->getSystem() != 'procd_inactive') {
				$this->logger->info("  ".$character->getName().", ".$character->getId()." is under review, as slumbering.");
				$this->logger->info("    Heir: ".($heir?$heir->getName():"(nobody)"));
				if ($character->getPositions()) {
					foreach ($character->getPositions() as $position) {
						if ($position->getRuler()) {
							$this->logger->info("    ".$position->getName().", ".$position->getId().", is detected as ruler position.");
							if ($heir) {
								$this->logger->info("    ".$heir->getName()." inherits ".$position->getRealm()->getName());
								$this->cm->inheritRealm($position->getRealm(), $heir, $character, $via, 'slumber');
							} else {
								$this->logger->info("    No one inherits ".$position->getRealm()->getName());
								$this->cm->failInheritRealm($character, $position->getRealm(), 'slumber');
							}
							$this->logger->info("    Removing ".$character->getName()." from ".$position->getName());
							$position->removeHolder($character);
							$character->removePosition($position);
							$this->logger->info("    Removed.");
						} else if (!$position->getKeepOnSlumber() && $position->getInherit()) {
							$this->logger->info($position->getName().", ".$position->getId().", is detected as non-ruler, inherited position.");
							if ($heir) {
								$this->logger->info("    ".$heir->getName()." inherits ".$position->getName());
								$this->cm->inheritPosition($position->getRealm(), $heir, $character, $via, 'slumber');
							} else {
								$this->logger->info("    No one inherits ".$position->getName());
								$this->cm->failInheritPosition($character, $position, 'slumber');
							}
							$this->logger->info("    Removing ".$character->getName());
							$position->removeHolder($character);
							$character->removePosition($position);
							$this->logger->info("    Removed.");
						} else if (!$position->getKeepOnSlumber()) {
							$this->logger->info("    ".$position->getName().", ".$position->getId().", is detected as non-ruler, non-inherited position.");
							$this->logger->info("    Removing ".$character->getName());
							$this->cm->failInheritPosition($character, $position, 'slumber');
							$position->removeHolder($character);
							$character->removePosition($position);
							$this->logger->info("    Removed.");
						} else {
							$this->logger->info("    ".$position->getName().", ".$position->getId().", is detected as non-ruler position.");
							$this->logger->info("    ".$position->getName()." is set to keep on slumber.");
							$this->history->logEvent(
								$position->getRealm(),
								'event.position.inactivekept',
								array('%link-character%'=>$character->getId(), '%link-realmposition%'=>$position->getId()),
								History::LOW, true
							);
							$keeponslumbercount++;
						}
					}
				}
				if ($character->getHeadOfHouse()) {
					$this->logger->info("  Detectd character is head of house ID #".$character->getHeadOfHouse()->getId());
					$this->cm->houseInheritance($character, 'slumber');
				}
				foreach ($character->getAssociationMemberships() as $mbrshp) {
					$this->cm->assocInheritance($mbrshp);
				}
				$character->setSystem('procd_inactive');
				$this->logger->info("  Character set as known slumber.");
			} else {
				$knownslumber++;
			}
			$this->em->flush();
		}
		if ($keeponslumbercount > 0) {
			$this->logger->info("  $keeponslumbercount positions kept on slumber!");
		}
		$this->logger->info("  Counted $knownslumber known slumberers and $knowndead known dead.");
		$this->appstate->setGlobal('cycle.characters', 'complete');
		$this->em->flush();
		$this->em->clear();
		return true;
	}

	public function runNPCCycle() {
		$last = $this->appstate->getGlobal('cycle.npcs', 0);
		if ($last==='complete') return true;
		$this->logger->info("NPC Cycle...");

		$query = $this->em->createQuery('SELECT count(u.id) FROM BM2SiteBundle:User u WHERE u.account_level > 0');
		$players = $query->getSingleScalarResult();
		# $want = ceil($players/8);
		$want = 0;

		$active_npcs = $this->em->createQuery('SELECT count(c) FROM BM2SiteBundle:Character c WHERE c.npc = true AND c.alive = true')->getSingleScalarResult();
		$cullability = $this->em->createQuery('SELECT count(c) FROM BM2SiteBundle:Character c WHERE c.npc = true AND c.alive = true and c.user IS NULL')->getSingleScalarResult();

		$this->logger->info("  We want $want NPCs for $players players, we have $active_npcs");
		if (0 < $active_npcs AND $active_npcs < $want) {
			$npc = $this->npc->createNPC();
			$this->logger->info("  Created NPC ".$npc->getName());
		} else if ($active_npcs > $want AND $cullability > 0) {
			# The greater than 2 is there to keep this from happening every single turn. We don't care about a couple extra.
			$cullcount = $active_npcs - $want;
			$culled = 0;
			$this->logger->info("  Too many NPCs, attempting to cull $cullcount NPCs");
			$this->logger->info("  If players have NPC's already, it's not possible to cull them, so don't freak out if you see this every turn.");

			while ($culled < $cullcount) {
				$query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Character c WHERE c.npc = true AND c.alive = true AND c.user IS NULL');
				foreach ($query->getResult() as $potentialculling) {
					if ($cullcount > $culled) {
						$potentialculling->setAlive('FALSE');
						$culled++;
						$this->logger->info("NPC ".$potentialculling->getName()." has been culled");
					}
					if ($cullcount == $culled) {
						$this->logger->info("Bandit population is within acceptable levels. ".$potentialculling->getName()." lives to see another day.");
					}
				}
			}
			if ($culled > 0) {
				$this->logger->info("  It was not possible to conduct the needed cullings this turn.");
			}
		}

		if ($active_npcs > 0) {
			$this->logger->info("  Proceeding to check for recyclable NPCs...");
			$query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Character c WHERE c.npc = true');
			foreach ($query->getResult() as $npc) {
				if ($npc->isAlive()) {
					$this->npc->checkTroops($npc);
				}
				# This used to run all the time, but we don't care about resetting them if we already have too many.
				if ($active_npcs <= $want) {
					$this->npc->checkTimeouts($npc);
				}
			}

			$this->logger->info("  Proceeding to check for complaining bandit solders...");
			$query = $this->em->createQuery('SELECT s as soldier, c as character FROM BM2SiteBundle:Soldier s JOIN s.character c JOIN s.home h JOIN h.geo_data g WHERE c.npc = true AND s.alive = true AND s.distance_home > :okdistance');
			$query->setParameter('okdistance', $this->bandits_ok_distance);
			$this->logger->info("  ".count($query->getResult())." bandit soldiers complaining");
			$deserters = array();
			foreach ($query->getResult() as $row) {
				$index = $row['soldier']->getCharacter()->getId();
				if (!isset($deserters[$index])) {
					$deserters[$index] = array('character'=>$row['soldier']->getCharacter(), 'soldiers'=>$row['soldier']->getCharacter()->getLivingSoldiers()->count(), 'gone'=>0, 'complaining'=>0);
				}

				$deserters[$index]['complaining']++;
				$chance = ($row['soldier']->getDistanceHome() - $this->bandits_ok_distance)/5000;
				if ($row['soldier']->getDistanceHome() > $this->bandits_ok_distance*2) {
					$chance += ($row['soldier']->getDistanceHome() - $this->bandits_ok_distance*2)/2000;
				}
				// TODO: set even lower for now until we fix the problem where soldiers seem to come from far away regions
				//$chance = sqrt($chance); // because this runs every turn, leaving it high would lead to immediate loss
				$chance = sqrt($chance/10); // because this runs every turn, leaving it high would lead to immediate loss
				if (rand(0,100)<$chance) {
					$this->milman->disband($row['soldier'], $row['soldier']->getCharacter());
					$deserters[$index]['gone']++;
				}
			}

			foreach ($deserters as $des) {
				if ($des['complaining'] > 0) {
					if ($des['gone'] >= 10 || $des['gone'] > $des['soldiers']*0.1) {
						$importance = HISTORY::HIGH;
					} elseif ($des['gone'] > 0) {
						$importance = HISTORY::MEDIUM;
					} else {
						$importance = HISTORY::LOW;
					}
					$this->history->logEvent(
						$des['character'],
						'event.character.desertions',
						array('%complaining%'=>$des['complaining'], '%gone%'=>$des['gone']),
						$importance, false, 15
					);
				}
			}
		} else {
			$this->logger->info("  No active NPCs.");
		}

		$this->appstate->setGlobal('cycle.npcs', 'complete');
		$this->em->flush();
		$this->em->clear();

		return true;
	}

	public function runSoldierUpdatesCycle() {
		$last = $this->appstate->getGlobal('cycle.soldiers', 0);
		if ($last==='complete') return true;
		$date = date("Y-m-d H:i:s");
		$this->logger->info("$date -- Soldiers update...");

		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.locked=false');
		$query->execute();

		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Entourage e SET e.locked=false');
		$query->execute();

		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Settlement s SET s.recruited=0');
		$query->execute();

		// dead are rotting (to prevent running-around-with-a-thousand-dead abuses)
		$this->logger->info("rotting...");
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.hungry = s.hungry +1 where s.alive = false');
		$rotting = $query->execute();

		$query = $this->em->createQuery('DELETE FROM BM2SiteBundle:Soldier s WHERE s.alive = false AND s.hungry > 40');
		$deleted = $query->execute();
		$this->logger->info("  $rotting soldiers rotting, $deleted were deleted");

		$this->em->flush();

		// militia
		// dead militia is auto-buried
		// need to manually delete this because the cascade doesn't work if I delete by DQL, we also use the opportunity to clean up orphaned records
		$query = $this->em->createQuery('DELETE FROM BM2SiteBundle:SoldierLog l WHERE l.soldier IS NULL OR l.soldier IN (SELECT s.id FROM BM2SiteBundle:Soldier s WHERE s.base IS NOT NULL AND s.alive=false)');
		$query->execute();
		$this->em->flush();
		$query = $this->em->createQuery('DELETE FROM BM2SiteBundle:Soldier s WHERE s.base IS NOT NULL AND s.alive=false');
		$query->execute();

		// routed militia - for now, just return them
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.routed = false WHERE s.routed = true AND s.character IS NULL');
		$query->execute();

		// militia auto-resupply
		$query = $this->em->createQuery('SELECT s FROM BM2SiteBundle:Soldier s WHERE s.base IS NOT NULL AND s.alive=true AND s.wounded=0 AND s.routed=false AND
			(s.has_weapon=false OR s.has_armour=false OR s.has_equipment=false)');
		$iterableResult = $query->iterate();
		$i=1;
		while ($row = $iterableResult->next()) {
			$soldier = $row[0];
			$this->milman->resupply($soldier, $soldier->getBase());

			if (($i++ % $this->batchsize) == 0) {
				$this->em->flush();
				$this->em->clear();
			}
		}
		$this->em->flush();
		$this->em->clear();

		// wounded troops: heal or die
		$date = date("Y-m-d H:i:s");
		$this->logger->info("$date --   Heal or die...");
		$query = $this->em->createQuery('SELECT s FROM BM2SiteBundle:Soldier s WHERE s.wounded > 0');
		$iterableResult = $query->iterate();
		$i=1;
		while ($row = $iterableResult->next()) {
			$soldier = $row[0];
			$soldier->HealOrDie();
			if (($i++ % $this->batchsize) == 0) {
				$this->em->flush();
				$this->em->clear();
			}
		}
		$this->em->flush();
		$this->em->clear();

		$date = date("Y-m-d H:i:s");
		$this->logger->info("$date --   Processing units of slumberers...");
		$query = $this->em->createQuery('SELECT u FROM BM2SiteBundle:Unit u JOIN u.character c WHERE c.slumbering = true');
		$result = $query->getResult();
		foreach ($result as $unit) {
			if ($unit->getSettlement()) {
				$this->milman->returnUnitHome($unit, 'slumber', $unit->getCharacter(), true);
			} else {
				foreach ($unit->getSoldiers() as $soldier) {
					$this->milman->disband($soldier);
				}
				$this->milman->disbandUnit($unit, true);
			}
		}
		$this->em->flush();

		$date = date("Y-m-d H:i:s");
		$this->logger->info("$date --   Checking for disbandable entourage.");
		$disband_entourage = 0;
		$query = $this->em->createQuery('SELECT e, c, DATE_DIFF(CURRENT_DATE(), c.last_access) as days FROM BM2SiteBundle:Entourage e JOIN e.character c WHERE c.slumbering = true');
		$iterableResult = $query->iterate();
		$i=1;
		while ($row = $iterableResult->next()) {
			// meet the most stupid array return data setup imaginable - first return row is different, yeay!
			$e = array_shift($row);
			$entourage = $e[0];
			if (isset($e['days'])) {
				$days = $e['days'];
			} else {
				$d = array_shift($row);
				$days = $d['days'];
			}
			if (rand(0,200) < ($days-20)) {
				$disband_entourage++;
				$this->milman->disbandEntourage($entourage, $entourage->getCharacter());
			}

			if (($i++ % $this->batchsize) == 0) {
				$this->em->flush();
			}
		}
		$this->em->flush();

		if ($disband_entourage > 0) {
			$date = date("Y-m-d H:i:s");
			$this->logger->info("$date --   Disbanded $disband_entourage entourage.");
		}

		// Update Soldier travel times.
		$this->logger->info("  Deducting a day from soldier travel times...");
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.travel_days = (s.travel_days - 1) WHERE s.travel_days IS NOT NULL');
		$query->execute();

		// Update soldier recruit training times. This will also set the training times for units, so this and the above affect whether travel starts same day or next (I'm going with next day).
		$date = date("Y-m-d H:i:s");
		$this->logger->info("$date --   Checking on recruits...");
		$query = $this->em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s WHERE s.id > 0');
		foreach ($query->getResult() as $settlement) {
			if (!$settlement->getSiege() || !$settlement->getSiege()->getEncircled()) {
				$this->milman->TrainingCycle($settlement);
			}
		}

		// Update soldier arrivals to units based on travel times being at or below zero.
		$date = date("Y-m-d H:i:s");
		$this->logger->info("$date --   Checking if soldiers have arrived...");
		$count = 0;
		$query = $this->em->createQuery('SELECT s FROM BM2SiteBundle:Soldier s WHERE s.travel_days <= 0');
		$units = [];
		foreach ($query->getResult() as $soldier) {
			$count++;
			$soldier->setTravelDays(null);
			$soldier->setDestination(null);
			if (!in_array($soldier->getUnit()->getId(), $units)) {
				$units[] = $soldier->getUnit()->getId();
			}
		}
		if ($count) {
			foreach ($units as $each) {
				$unit = $this->em->getRepository('BM2SiteBundle:Unit')->findOneById($each);
				if ($unit && ($character = $unit->getCharacter())) {
					$this->history->logEvent(
						$character,
						'event.military.soldierarrivals',
						array('%link-unit%'=>$unit->getId()),
						History::MEDIUM, false, 30
					);
				} else {
					if (!$unit) {
						$this->logger->alert("No unit found for ".$unit);
					}
					# We can also reach this because the character wasn't found, which can happen when a soldier arrives to a leaderless unit, which can happen for any number of legit reasons.
				}
			}
		}
		$this->em->flush();

		// Update Unit travel times.
		$this->logger->info("  Deducting a day from unit travel times...");
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Unit u SET u.travel_days = (u.travel_days - 1) WHERE u.travel_days IS NOT NULL');
		$query->execute();

		// Update Unit arrivals based on travel times being at or below zero.
		$date = date("Y-m-d H:i:s");
		$this->logger->info("$date --   Checking if units have arrived...");
		$count = 0;
		$query = $this->em->createQuery('SELECT u FROM BM2SiteBundle:Unit u WHERE u.travel_days <= 0');
		$units = [];
		foreach ($query->getResult() as $unit) {
			$count++;
			$unit->setTravelDays(null);
			if ($unit->getDestination()=='base') {
				$units[] = $unit;
			}
			$unit->setDestination(null);
		}
		if ($count) {
			foreach ($units as $unit) {
				if ($settlement = $unit->getSettlement()) {
					$this->history->logEvent(
						$settlement,
						'event.military.unitreturns',
						array('%link-unit%'=>$unit->getId()),
						History::MEDIUM, false, 30
					);
					$owner = $settlement->getOwner();
					if ($owner) {
						$this->history->openLog($unit, $owner);
					}
					$steward = $settlement->getSteward();
					if ($steward) {
						$this->history->openLog($unit, $steward);
					}
					$marshal = $unit->getMarshal();
					if ($marshal) {
						$this->history->openLog($unit, $marshal);
					}
				} else {
					# Somehow this unit is being returned to somewhere but doesn't have a settlement assigned????
				}
			}
		}
		$this->em->flush();

		$date = date("Y-m-d H:i:s");
		$this->logger->info("$date --   Checking if units have gotten supplies...");
		$done = false;
		$query = $this->em->createQuery('SELECT r FROM BM2SiteBundle:Resupply r WHERE r.travel_days <= 1');
		$iterableResult = $query->iterate();
		while (!$done) {
			$this->em->clear();
			$row = $iterableResult->next();
			if ($row===false) {
				break;
			}
			$resupply = $row[0];
			$unit = $resupply->getUnit();
			$encircled = false;
			if ($unit->getCharacter()) {
				$char = $unit->getCharacter();
				if ($char->getInsideSettlement()) {
					$here = $char->getInsideSettlement();
					if ($here->getSiege() && $here->getSiege()->getEncircled()) {
						$encircled = true;
					}
				}
			}
			if (!$encircled && $unit->getSettlement() && $unit->getSupplier() !== $unit->getSettlement()) {
				$here = $unit->getSettlement();
				if ($here->getSiege() && $here->getSiege()->getEncircled()) {
					$encircled = true;
				}
			}
			if (!$encircled) {
				$found = false;
				if ($unit->getSupplies()) {
					foreach ($unit->getSupplies() as $supply) {
						if ($supply->getType() === $resupply->getType()) {
							$found = true;
							$orig = $supply->getQuantity();
							$supply->setQuantity($orig+$resupply->getQuantity());
							$date = date("Y-m-d H:i:s");
							$this->logger->info("$date --   Unit ".$unit->getId()." had supplies, and got ".$resupply->getQuantity()." more food...");
							break;
						}
					}
				}
				if (!$found) {
					$supply = new Supply();
					$this->em->persist($supply);
					$supply->setUnit($unit);
					$supply->setType($resupply->getType());
					$supply->setQuantity($resupply->getQuantity());
					$date = date("Y-m-d H:i:s");
					$this->logger->info("$date --   Unit ".$unit->getId()." had no supplies, but got ".$resupply->getQuantity()." food...");
				}
			} else {
				$date = date("Y-m-d H:i:s");
				$this->logger->info("$date --   Unit ".$unit->getId()." is encircled, and thus skipped..");
			}
			#TODO: Give the food to the attackers.
			$this->em->remove($resupply);
			$this->em->flush();
		}
		$date = date("Y-m-d H:i:s");
		$this->logger->info("$date --   Checking if units have food to eat...");
		$query = $this->em->createQuery('SELECT u FROM BM2SiteBundle:Unit u WHERE u.id > 0');
		$iterableResult = $query->iterate();
		$fed = 0;
		$starved = 0;
		$killed = 0;
		$done = false;
		while (!$done) {
			$this->em->clear();
			$row = $iterableResult->next();
			if ($row===false) {
				break;
			}
			$unit = $row[0];
			$living = $unit->getLivingSoldiers();
			$count = $living->count();
			if ($count < 1) {
				# No soldiers to feed. Skip!
				continue;
			}
			$char = $unit->getCharacter();
			$food = 0;
			$fsupply = false;
			foreach ($unit->getSupplies() as $fsupply) {
				if ($fsupply->getType() === 'food') {
					$food = $fsupply->getQuantity();
					break;
				}
			}
			$date = date("Y-m-d H:i:s");
			if ($fsupply) {
				$this->logger->info("$date --   Unit ".$unit->getId()." initial food quantity: ".$food." from ".$fsupply->getId()." from unit ".$fsupply->getUnit()->getId()." and soldier count of ".$count);
			} else {
				$this->logger->info("$date --   Unit ".$unit->getId()." initial food quantity: ".$food." and soldier count of ".$count);
			}

			if ($count <= $food) {
				$short = 0;
			} else {
				$need = $count - $food;
				$date = date("Y-m-d H:i:s");
				$this->logger->info("$date --   Need ".$need." more food");
				if ($char) {
					$food_followers = $char->getEntourage()->filter(function($entry) {
						return ($entry->getType()->getName()=='follower' && $entry->isAlive() && !$entry->getEquipment() && $entry->getSupply()>0);
					})->toArray();
					if (!empty($food_followers)) {
						$this->logger->info("Checking followers...");
						foreach ($food_followers as $ent) {
							if ($ent->getSupply() > $need) {
								$supply2 = $ent->getSupply()-$need;
								$need = 0;
								$ent->setSupply($supply2);
								break;
							} else {
								$need = $need - $food;
								$ent->setSupply(0);
							}
						}
					}
				}
				if ($need > 0) {
					$short = $need;
				} else {
					$short = 0;
				}
				$date = date("Y-m-d H:i:s");
				$this->logger->info("$date --   Final short of ".$short);
			}
			$available = $count-$short;
			if ($available > 0) {
				$var = $available/$count;
			} else {
				$var = 0;
			}
			$date = date("Y-m-d H:i:s");
			$this->logger->info("$date --   Available food of ".$available." from a count of ".$count." less a short of ".$short);
			$dead = 0;
			$myfed = 0;
			$mystarved = 0;
			if ($var <= 0.9) {
				$starve = 1 - $var;
				$char = $unit->getCharacter();
				if ($char) {
					$severity = round(min($starve*6, 6)); # Soldiers starve at a rate of 6 hunger per day max. No food? Starve in 15 days.
					$this->history->openLog($unit, $char);
				} else {
					$severity = round(min($starve*4, 4)); # Militia starve slower, 4 per day. Starve in 22.5 days.
					$where = $unit->getSettlement();
					if ($where) {
						$owner = $where->getOwner();
						if ($owner) {
							$this->history->openLog($unit, $owner);
						}
						$steward = $where->getSteward();
						if ($steward) {
							$this->history->openLog($unit, $steward);
						}
						$marshal = $unit->getMarshal();
						if ($marshal) {
							$this->history->openLog($unit, $marshal);
						}
					}
				}
				if ($severity < 2) {
					$this->history->logEvent(
						$unit,
						'event.unit.starvation.light',
						array(),
						History::MEDIUM, false, 30
					);
				} elseif ($severity < 4) {
					$this->history->logEvent(
						$unit,
						'event.unit.starvation.medium',
						array(),
						History::MEDIUM, false, 30
					);
				} else {
					$this->history->logEvent(
						$unit,
						'event.unit.starvation.high',
						array(),
						History::MEDIUM, false, 30
					);
				}
				foreach ($living as $soldier) {
					$soldier->makeHungry($severity);
					// soldiers can take several days of starvation without danger of death, but slightly less than militia (because they move around, etc.)
					if ($soldier->getHungry() > 90 && rand(90, 180) < $soldier->getHungry()) {
						$soldier->kill();
						$this->history->addToSoldierLog($soldier, 'starved');
						$killed++;
						$dead++;
					} else {
						$starved++;
						$mystarved++;
					}
				}
				if ($dead > 0) {
					$this->history->logEvent(
						$unit,
						'event.unit.starvation.death',
						array("%i%"=>$dead),
						History::MEDIUM, false, 30
					);
					if ($unit->getCharacter()) {
						$this->history->logEvent(
							$unit->getCharacter(),
							'event.unit.starvation.death',
							array("%link-unit%"=>$unit->getId()),
							History::HIGH, false, 30
						);
					}
				}
			} else {
				foreach ($living as $soldier) {
					$soldier->feed();
					$fed++;
					$myfed++;
				}
			}
			$left = 0;
			if ($fsupply) {
				$left = $food-$count;
				if ($left < 0) {
					$fsupply->setQuantity(0);
					$left = 0;
				} else {
					$fsupply->setQuantity($left);
				}
			}
			$this->em->flush();
			$date = date("Y-m-d H:i:s");
			$id = $unit->getId();
			$this->logger->info("$date --     Unit $id - Soldiers $count - Var $var - Food $food - Leftover of $left - Fed $myfed - Starved $mystarved - Killed $dead");
		}
		$date = date("Y-m-d H:i:s");
		$this->logger->info("$date --     Fed $fed - Starved $starved - Killed $killed");

		// Update Unit resupply travel times.
		$date = date("Y-m-d H:i:s");
		$this->logger->info("$date --   Deducting a day from unit resupply times...");
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Resupply r SET r.travel_days = (r.travel_days - 1) WHERE r.travel_days IS NOT NULL');
		$query->execute();

		$this->appstate->setGlobal('cycle.soldiers', 'complete');
		$this->em->flush();
		$this->em->clear();
		return true;
	}

	public function updateActions() {
		return $this->abstractActionsCycle(true);
	}

	public function runActionsCycle() {
		return $this->abstractActionsCycle(false);
	}

	private function abstractActionsCycle($hourly) {
		$last = $this->appstate->getGlobal('cycle.action', 0);
		if ($last==='complete') return true;
		$last=(int)$last;
		$this->logger->info("Actions Cycle...");

		if ($hourly) {
			$querystring = 'SELECT a FROM BM2SiteBundle:Action a WHERE a.id>:last AND a.hourly = true ORDER BY a.id ASC';
		} else {
			$querystring = 'SELECT a FROM BM2SiteBundle:Action a WHERE a.id>:last ORDER BY a.id ASC';
		}
		$query = $this->em->createQuery($querystring);
		$query->setParameter('last', $last);
		$iterableResult = $query->iterate();

		$time_start = microtime(true);
		$done = false;
		$complete = false;
		$i=1;
		while (!$done) {
			$row = $iterableResult->next();
			if ($row===false) {
				$done=true;
				$complete=true;
				break;
			}
			$action = $row[0];
			$lastid=$action->getId();
			$this->resolver->update($action);

			if (($i++ % $this->batchsize) == 0) {
				$this->appstate->setGlobal('cycle.action', $lastid);
				$this->em->flush();
			}
			$time_spent = microtime(true)-$time_start;
			if ($time_spent > $this->maxtime) {
				$this->logger->alert("maximum execution time reached");
				$done=true;
			}
		}

		if ($complete) {
			$this->appstate->setGlobal('cycle.action', 'complete');
		} else {
			$this->appstate->setGlobal('cycle.action', $lastid);
		}

		$this->em->flush();
		$this->em->clear();
		return $complete;
	}

	public function runResupplyCycle() {
		$last = $this->appstate->getGlobal('cycle.resupply', 0);
		if ($last==='complete') return true;
        	$last=(int)$last;
		$this->logger->info("Resupply Cycle...");

		$max_supply = $this->appstate->getGlobal('supply.max_value', 800);
		$max_items = $this->appstate->getGlobal('supply.max_items', 15);
		$max_food = $this->appstate->getGlobal('supply.max_food', 100);

		$query = $this->em->createQuery('SELECT e FROM BM2SiteBundle:Entourage e JOIN e.type t JOIN e.character c JOIN c.inside_settlement s WHERE c.prisoner_of IS NULL AND c.slumbering = false and c.travel is null and e.id>:last ORDER BY e.id ASC');
		$query->setParameter('last', 0);
		$iterableResult = $query->iterate();

		$time_start = microtime(true);
		$done = false;
		$complete = false;
		while (!$done) {
			$row = $iterableResult->next();
			if ($row===false) {
				$done=true;
				$complete=true;
				break;
			}
			$follower = $row[0];
			$lastid=$follower->getId();
			$settlement = $follower->getCharacter()->getInsideSettlement();

			if ($follower->getEquipment()) {
				// check if our equipment available here and we have resupply permission
				$provider = $settlement->getBuildingByType($follower->getEquipment()->getProvider());
				if ($provider && $provider->isActive()) {
					if ($this->pm->checkSettlementPermission($settlement, $follower->getCharacter(), 'resupply')) {
						$gain = 6; // add the equivalent of 6 work-hours if we have permission
					} else {
						$gain = 1; // add only 1 work-hour if we don't, representing scavenging and shady deals
					}
					// add the gain, but at most $max_items items total, no matter which type
					$follower->setSupply(min($max_supply, min($follower->getEquipment()->getResupplyCost()*$max_items, $follower->getSupply()+$gain)));
				}
			} else {
				if ($follower->getCharacter()->getTravelAtSea()) {
					// at sea, we actually have a minimal food collection, indicating fishing activities
					$follower->setSupply(min($max_food, $follower->getSupply()+1));
				} elseif ($this->pm->checkSettlementPermission($settlement, $follower->getCharacter(), 'resupply')) {
					// if we have resupply permissions, gathering food is very easy
					if ($settlement->getStarvation() < 0.01) {
						$follower->setSupply(min($max_food, $follower->getSupply()+5));
					} elseif ($settlement->getStarvation() < 0.1) {
						$follower->setSupply(min($max_food, $follower->getSupply()+4));
					} elseif ($settlement->getStarvation() < 0.2) {
						$follower->setSupply(min($max_food, $follower->getSupply()+3));
					} elseif ($settlement->getStarvation() < 0.5) {
						$follower->setSupply(min($max_food, $follower->getSupply()+2));
					} else {
						$follower->setSupply(min($max_food, $follower->getSupply()+1));
					}
				} else {
					// check if the settlement has food available
					if ($settlement->getStarvation() < 0.1) {
						$follower->setSupply(min($max_food, $follower->getSupply()+3));
					} elseif ($settlement->getStarvation() < 0.2) {
						$follower->setSupply(min($max_food, $follower->getSupply()+2));
					} elseif ($settlement->getStarvation() < 0.5) {
						$follower->setSupply(min($max_food, $follower->getSupply()+1));
					}
				}
			}
		}

		if ($complete) {
			$this->appstate->setGlobal('cycle.resupply', 'complete');
		} else {
			$this->appstate->setGlobal('cycle.resupply', $lastid);
		}

		$this->em->flush();
		$this->em->clear();
		return $complete;
	}

	public function runRealmsCycle() {
		$last = $this->appstate->getGlobal('cycle.realm', 0);
		if ($last==='complete') return true;
        	$last=(int)$last;
		$this->logger->info("Realms Cycle...");

		$timeout = new \DateTime("now");
		$timeout->sub(new \DateInterval("P7D"));

		$query = $this->em->createQuery('SELECT p FROM BM2SiteBundle:RealmPosition p JOIN p.realm r LEFT JOIN p.holders h WHERE r.active = true AND p.ruler = true AND h.id IS NULL AND p NOT IN (SELECT y FROM BM2SiteBundle:Election x JOIN x.position y WHERE x.closed=false) GROUP BY p');
		$result = $query->getResult();
		$this->logger->notice("  Checking for inactive realms...");
		# This one checks for realms that don't have rulers, while the next query checks for conversations.
		# Since they can both result in different situations that reveal abandoned realms, we check twice.
		foreach ($result as $position) {
			$members = $position->getRealm()->findMembers(true, true);
			if ($members->isEmpty()) {
				$this->logger->notice("  Empty ruler position for realm ".$position->getRealm()->getName());
				$this->logger->notice("  -- realm deserted, making inactive.");
				$realm = $position->getRealm();
				$this->rm->abandon($realm);
			}
		}
		$this->logger->info("  Checking for missing realm conversations...");

		$realmquery = $this->em->createQuery('SELECT r FROM BM2SiteBundle:Realm r WHERE r.active = true');
		$realms = $realmquery->getResult();
		foreach ($realms as $realm) {
			$convoquery = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Conversation c WHERE c.realm = :realm AND c.system IS NOT NULL');
			$convoquery->setParameter('realm', $realm);
			$convos = $convoquery->getResult();
			$announcements = false;
			$general = false;
			$deserted = false;
			$msguser = false;
			$members = $realm->findMembers(true, true);
			if ($convos) {
				foreach ($convos as $convo) {
					if ($convo->getSystem() == 'announcements') {
						$announcements = true;
					}
					if ($convo->getSystem() == 'general') {
						$general = true;
					}
				}
			}
			if (!$announcements) {
				#newConversation(Character $char, $recipients=null, $topic, $type, $content, Realm $realm = null, $system=null)
				$rulers = $realm->findRulers();
				if (!$rulers->isEmpty()) {
					foreach ($rulers as $ruler) {
						if ($ruler->isActive(true)) {
							$msguser = $ruler;
							break;
						}
					}
				} else {
					if (!$members->isEmpty()) {
						foreach ($members as $member) {
							if ($member->isActive(true)) {
								$msguser = $member;
								break;
							}
						}
					} else {
						$this->logger->notice("  ".$realm->getName()." deserted, making inactive.");
						$deserted = true;
						$this->rm->abandon($realm);
						$msguser = false;
					}
				}
				if ($msguser) {
					$topic = $realm->getName().' Announcements';
					$conversation = $this->convman->newConversation(null, $members, $topic, null, null, $realm, 'announcements');
					$this->logger->notice("  ".$realm->getName()." announcements created");
				}
			}
			if (!$general && !$deserted) {
				$rulers = $realm->findRulers();
				if (!$rulers->isEmpty()) {
					foreach ($rulers as $ruler) {
						if ($ruler->isActive(true)) {
							$msguser = $ruler;
							break;
						}
					}
				} else {
					if (!$members->isEmpty()) {
						foreach ($members as $member) {
							if ($member->isActive(true)) {
								$msguser = $member;
								break;
							}
						}
					}
				}
				if ($msguser) {
					$topic = $realm->getName().' General Discussion';
					$conversation = $this->convman->newConversation(null, $members, $topic, null, null, $realm, 'general');
					$this->logger->notice("  ".$realm->getName()." discussion created");
				}
			}
		}
		$this->appstate->setGlobal('cycle.realm', 'complete');
		$this->em->flush();
		$this->em->clear();

		return true;
	}

	public function runHousesCycle() {
		$last = $this->appstate->getGlobal('cycle.houses', 0);
		if ($last==='complete') return true;
        	$last=(int)$last;
		$this->logger->info("Houses Cycle...");

		$this->logger->info("  Checking for missing House conversations...");

		$query = $this->em->createQuery('SELECT h FROM BM2SiteBundle:House h WHERE h.active = true OR h.active IS NULL');

		foreach ($query->getResult() as $house) {
			$anno = false;
			$gen = false;

                	$criteria = Criteria::create()->where(Criteria::expr()->eq("system", "announcements"))->orWhere(Criteria::expr()->eq("system", "general"));
			$convs = $house->getConversations()->matching($criteria);
			if ($convs->count() > 0) {
				foreach ($convs as $conv) {
					if (!$anno && $conv->getSystem() == 'announcements') {
						$anno = true;
						continue;
					}
					if (!$gen && $conv->getSystem() == 'general') {
						$gen = true;
						continue;
					}
					if ($gen && $anno) {
						break;
					}
				}
			}
			if (!$anno) {
				$topic = $house->getName().' Announcements';
				$conversation = $this->convman->newConversation(null, null, $topic, null, null, $house, 'announcements');
				$this->logger->notice("  ".$house->getName()." announcements created");
			}
			if (!$gen) {
				$topic = $house->getName().' General Discussion';
				$conversation = $this->convman->newConversation(null, null, $topic, null, null, $house, 'general');
				$this->logger->notice("  ".$house->getName()." general discussion created");
			}
		}

		$this->appstate->setGlobal('cycle.houses', 'complete');
		$this->em->flush();
		$this->em->clear();

		return true;
	}

	public function runAssociationsCycle() {
		$last = $this->appstate->getGlobal('cycle.assocs', 0);
		if ($last==='complete') return true;
        	$last=(int)$last;
		$this->logger->info("Associations Cycle...");

		$this->logger->info("  Checking for missing Assoc conversations...");

		$query = $this->em->createQuery('SELECT a FROM BM2SiteBundle:Association a WHERE a.active = true OR a.active IS NULL');

		foreach ($query->getResult() as $assoc) {
			$anno = false;
			$gen = false;

                	$criteria = Criteria::create()->where(Criteria::expr()->eq("system", "announcements"))->orWhere(Criteria::expr()->eq("system", "general"));
			$convs = $assoc->getConversations()->matching($criteria);
			if ($convs->count() > 0) {
				foreach ($convs as $conv) {
					if (!$anno && $conv->getSystem() == 'announcements') {
						$anno = true;
						continue;
					}
					if (!$gen && $conv->getSystem() == 'general') {
						$gen = true;
						continue;
					}
					if ($gen && $anno) {
						break;
					}
				}
			}
			if (!$anno) {
				$topic = $assoc->getName().' Announcements';
				$conversation = $this->convman->newConversation(null, null, $topic, null, null, $assoc, 'announcements');
				$this->logger->notice("  ".$assoc->getName()." announcements created");
			}
			if (!$gen) {
				$topic = $assoc->getName().' General Discussion';
				$conversation = $this->convman->newConversation(null, null, $topic, null, null, $assoc, 'general');
				$this->logger->notice("  ".$assoc->getName()." general discussion created");
			}
		}

		$this->appstate->setGlobal('cycle.assocs', 'complete');
		$this->em->flush();
		$this->em->clear();

		return true;
	}

	public function runConversationsCleanup() {
		# This is run separately from the main turn command, and runs after it. It remains here because it is still primarily turn logic.
		# Ideally, this does nothing. If it does something though, it just means we caught a character that should or shouldn't be part of a conversation and fixed it.
		$lastRealm = $this->appstate->getGlobal('cycle.convs.realm', 0);
		$lastHouse = $this->appstate->getGlobal('cycle.convs.house', 0);
		$lastAssoc = $this->appstate->getGlobal('cycle.convs.assoc', 0);
		$lastRealm=(int)$lastRealm;
		$lastHouse=(int)$lastHouse;
		$lastAssoc=(int)$lastAssoc;
		$this->logger->info("Conversation Cycle...");
		$this->logger->info("  Updating realm conversation permissions...");
		$query = $this->em->createQuery("SELECT r from BM2SiteBundle:Realm r WHERE r.active = TRUE AND r.id > :last ORDER BY r.id ASC");
		$query->setParameters(['last'=>$lastRealm]);
		$added = 0;
		$total = 0;
		$removed = 0;
		$convs = 0;
		$iterableResult = $query->iterate();

		$done = false;
		$complete = false;
		while (!$done) {
			$row = $iterableResult->next();
			if ($row===false) {
				$done=true;
				$complete=true;
				break;
			}
			$realm = $row[0];
			$lastRealm = $realm->getId();
			$this->logger->info("  -- Updating ".$realm->getName()."...");
			$total++;
			$members = $realm->findMembers();
			foreach ($realm->getConversations() as $conv) {
				$rtn = $this->convman->updateMembers($conv, $members);
				$convs++;
				$removed += $rtn['removed']->count();
				$added += $rtn['added']->count();
			}
		}

		if ($complete) {
			$this->appstate->setGlobal('cycle.convs.realm', 'complete');
		} else {
			$this->appstate->setGlobal('cycle.convs.realm', $lastRealm);
		}
		$this->logger->info("  Result: ".$total." realms, ".$convs." conversations, ".$added." added permissions, ".$removed." removed permissions");
		$this->em->flush();
		$this->em->clear();

		$this->logger->info("  Updating house conversation permissions...");
		$query = $this->em->createQuery("SELECT h from BM2SiteBundle:House h WHERE (h.active = TRUE OR h.active IS NULL) AND h.id > :last ORDER BY h.id ASC");
		$query->setParameters(['last'=>$lastHouse]);
		$houses = $query->getResult();
		$added = 0;
		$total = 0;
		$removed = 0;
		$convs = 0;
		$iterableResult = $query->iterate();

		$done = false;
		$complete = false;
		while (!$done) {
			$row = $iterableResult->next();
			if ($row===false) {
				$done=true;
				$complete=true;
				break;
			}
			$house = $row[0];
			$lastHouse = $house->getId();
			$this->logger->info("  -- Updating ".$house->getName()."...");
			$total++;
			$members = $house->findAllActive();
			foreach ($house->getConversations() as $conv) {
				$rtn = $this->convman->updateMembers($conv, $members);
				$convs++;
				$removed += $rtn['removed']->count();
				$added += $rtn['added']->count();
			}
		}

		if ($complete) {
			$this->appstate->setGlobal('cycle.convs.house', 'complete');
		} else {
			$this->appstate->setGlobal('cycle.convs.house', $lastHouse);
		}
		$this->logger->info("  Result: ".$total." houses, ".$convs." conversations, ".$added." added permissions, ".$removed." removed permissions");
		$this->em->flush();
		$this->em->clear();

		$this->logger->info("  Updating association conversation permissions...");
		$query = $this->em->createQuery("SELECT a from BM2SiteBundle:Association a WHERE (a.active = TRUE OR a.active IS NULL) AND a.id > :last ORDER BY a.id ASC");
		$query->setParameters(['last'=>$lastAssoc]);
		$assocs = $query->getResult();
		$added = 0;
		$total = 0;
		$removed = 0;
		$convs = 0;
		$iterableResult = $query->iterate();

		$done = false;
		$complete = false;
		while (!$done) {
			$row = $iterableResult->next();
			if ($row===false) {
				$done=true;
				$complete=true;
				break;
			}
			$assoc = $row[0];
			$lastAssoc = $assoc->getId();
			$this->logger->info("  -- Updating ".$assoc->getName()."...");
			$total++;
			$members = $assoc->findAllMemberCharacters();
			foreach ($assoc->getConversations() as $conv) {
				$rtn = $this->convman->updateMembers($conv, $members);
				$convs++;
				$removed += $rtn['removed']->count();
				$added += $rtn['added']->count();
			}
		}
		$this->logger->info("  Result: ".$total." assocs, ".$convs." conversations, ".$added." added permissions, ".$removed." removed permissions");
		$this->em->flush();
		$this->em->clear();

		if ($complete) {
			$this->appstate->setGlobal('cycle.convs.assoc', 'complete');
		} else {
			$this->appstate->setGlobal('cycle.convs.assoc', $lastAssoc);
		}
		$this->logger->info("  Result: ".$total." associations, ".$convs." conversations, ".$added." added permissions, ".$removed." removed permissions");
		$this->em->flush();
		$this->em->clear();

		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Setting s SET s.value=0 WHERE s.name LIKE :cycle');
		$query->setParameter('cycle', 'cycle.convs.'.'%');
		$query->execute();
		return true;
	}

	public function runPositionsCycle() {
		$last = $this->appstate->getGlobal('cycle.positions', 0);
		if ($last==='complete') return true;
        	$last=(int)$last;
		$this->logger->info("Positions Cycle...");

		$this->logger->info("  Processing Finished Elections...");
		$query = $this->em->createQuery('SELECT e FROM BM2SiteBundle:Election e WHERE e.closed = false AND e.complete < :now');
		$query->setParameter('now', new \DateTime("now"));
		$seenpositions = [];

		/* The following 2 foreach cycles drop all incumbents from a position before an election is counted and then count all elections,
		ensuring that the old is removed before the new arrives, so we don't accidentally remove the new with the old.
		Mind you, this will only drop holders if the election has $routine = true set.
		Or rather, if the election was caused by the game itself. All other elections are ignored. --Andrew */

		foreach ($query->getResult() as $election) {
			$this->logger->info("-Reviewing election ".$election->getId());

			/* dropIncumbents will drop ALL incumbents, so we don't care to do this mutliple times for the same position--it's a waste of processing cycles.
			It's worth nothing that dropIncumbents only does anything on elections called by the game itself,
			Which you can see if you go look at the method in the realm manager. */

			if($election->getPosition()) {
				$this->logger->info("--Position detected");
				if(!in_array($election->getPosition()->getId(), $seenpositions)) {
					$this->rm->dropIncumbents($election);
					$seenpositions[] = $election->getPosition()->getId();
                                        $this->logger->info("---Dropped and tracked");
				} else {
                                        $this->logger->info("---Already saw it");
				}
				$this->em->flush(); #Otherwise we can end up with duplicate key errors from the database.
			}
			$this->rm->countElection($election);
                        $this->logger->info("--Counted.");
		}
		$this->logger->info("  Flushing Finished Elections...");
		$this->em->flush();

		/* The bulk of the following code does the following:
			1. Ensure all active realms have a ruler.
			2. Ensure all vacant AND elected positions have a holder.
			3. Ensure all positions that should have more than one holder do.
		These things will only happen if there is not already an election running for a given position though. */

		$this->logger->info("  Checking realm rulers, vacant electeds, and minholders...");
		$timeout = new \DateTime("now");
		$timeout->sub(new \DateInterval("P7D")); // hardcoded to 7 day intervals between election attempts
		$query = $this->em->createQuery('SELECT p FROM BM2SiteBundle:RealmPosition p JOIN p.realm r LEFT JOIN p.holders h WHERE r.active = true AND h.id IS NULL AND p NOT IN (SELECT y FROM BM2SiteBundle:Election x JOIN x.position y WHERE x.closed=false OR x.complete > :timeout) GROUP BY p');
		$query->setParameter('timeout', $timeout);
		$result = $query->getResult();
		foreach ($result as $position) {
			$members = $position->getRealm()->findMembers();
			$disablefurtherelections = false;
			$electionsneeded = 1;
			$counter = 0;
			if ($position->getRuler() && $position->getHolders()->count() == 0) {
				$this->logger->notice("  Empty ruler position for realm ".$position->getRealm()->getName());
				if (!$members->isEmpty()) {
					if ($position->getMinholders()) {
						$electionsneeded = $position->getMinholders() - $position->getHolders()->count();
					}
					while ($electionsneeded > 0) {
						$counter++;
						$this->logger->notice("  -- election triggered.");
						$electiontype = 'noruler';
						$election = $this->setupElection($position, $electiontype, false, $counter);

						$msg = "Automatic election number ".$counter." has been triggered for the position of ".$position->getName().". You are invited to vote - [vote:".$election->getId()."].";
						$systemflag = 'announcements';
						$this->postToRealm($position, $systemflag, $msg);
						$electionsneeded--;
					}
					$disablefurtherelections = true;
				}
			}
			if (!$position->getRuler() && $position->getHolders()->count() == 0 && $position->getElected() && !$position->getRetired() && !$disablefurtherelections) {
				if (!$members->isEmpty()) {
					$this->logger->notice("  Empty realm position of ".$position->getName()." for realm ".$position->getRealm()->getName());
					if ($position->getMinholders()) {
						$electionsneeded = $position->getMinholders() - $position->getHolders()->count();
					}
					while ($electionsneeded > 0) {
						$counter++;
						$this->logger->notice("  -- election ".$counter." triggered.");
						$electiontype = 'vacantelected';
						$election = $this->setupElection($position, $electiontype, false, $counter);

						$msg = "Automatic election number ".$counter." has been triggered for the elected position of ".$position->getName().". You are invited to vote - [vote:".$election->getId()."].";
						$systemflag = 'announcements';
						$this->postToRealm($position, $systemflag, $msg);
						$electionsneeded--;
					}
					$disablefurtherelections = true;
				}
			}
			if ($position->getHolders()->count() < $position->getMinholders() && $position->getElected() && !$position->getRetired() && !$disablefurtherelections) {
				if (!$members->isEmpty()) {
					$this->logger->notice("  Realm position of ".$position->getName()." for realm ".$position->getRealm()->getName()." needs more holders.");
					if ($position->getMinholders()) {
						$electionsneeded = $position->getMinholders() - $position->getHolders()->count();
					}
					while ($electionsneeded > 0) {
						$counter++;
						$electiontype = 'shortholders';
						$election = $this->setupElection($position, $electiontype, false, $counter);
						$this->logger->notice("  -- election ".$counter." triggered.");

						$msg = "Automatic election number ".$counter." has been triggered for the elected position of ".$position->getName().". You are invited to vote - [vote:".$election->getId()."].";
						$systemflag = 'announcements';
						$this->postToRealm($position, $systemflag, $msg);
						$electionsneeded--;
					}
				}
			}
		}
		$this->em->flush();

		$this->logger->info("  Checking for routine elections...");
		$cycle = $this->appstate->getCycle();
		$query = $this->em->createQuery("SELECT p FROM BM2SiteBundle:RealmPosition p JOIN p.realm r LEFT JOIN p.holders h WHERE r.active = true AND p.elected = true AND (p.retired = false OR p.retired IS NULL) AND p.cycle <= :cycle AND p.cycle IS NOT NULL AND h.id IS NOT NULL AND p NOT IN (SELECT y FROM BM2SiteBundle:Election x JOIN x.position y WHERE x.closed=false OR x.complete > :timeout) GROUP BY p");
		$query->setParameter('timeout', $timeout);
		$query->setParameter('cycle', $cycle);
		foreach ($query->getResult() as $position) {
			$this->logger->info("  Updating ".$position->getName()." cycle count.");
			switch ($position->getTerm()) {
				case '30':
					$this->logger->info("  -- Term 30 set, updating $cycle by 120.");
					$position->setCycle($cycle+120);
					break;
				case '90':
					$this->logger->info("  -- Term 90 set, updating $cycle by 360.");
					$position->setCycle($cycle+360);
					break;
				case '365':
					$this->logger->info("  -- Term 365 set, updating $cycle by 1440.");
					$position->setCycle($cycle+1440);
					break;
				case '0':
				default:
					$this->logger->info("  -- Term 0 set, updating cycle, year, and week to NULL.");
					$position->setYear(null);
					$position->setWeek(null);
					$position->setCycle(null);
					break;
			}
			$members = $position->getRealm()->findMembers();
			$this->logger->notice("  Calling election for ".$position->getName()." for realm ".$position->getRealm()->getName());
			$electionsneeded = 1;
			$counter = 0;
			$firstelection = true;
			if ($position->getMinholders()) {
				$electionsneeded = $position->getMinholders();
			}
			while ($electionsneeded > 0) {
				$counter++;
				$electiontype = 'routine';
				$election = $this->setupElection($position, $electiontype, true, $counter);
				$this->logger->notice("  -- election '.$counter.' triggered.");
				$msg = "Automatic election number ".$counter." has been triggered for the elected position of ".$position->getName().". You are invited to vote - [vote:".$election->getId()."].";
				$systemflag = 'announcements';
				$this->postToRealm($position, $systemflag, $msg);
				$electionsneeded--;
			}
		}

		$this->appstate->setGlobal('cycle.positions', 'complete');
		$this->em->flush();
		$this->em->clear();

		return true;
	}

	public function runSeaFoodCycle() {
		$last = $this->appstate->getGlobal('cycle.seafood', 0);
		if ($last==='complete') return true;
		$last=(int)$last;
		$this->logger->info("Sea Food Cycle...");

		$query = $this->em->createQuery("SELECT c FROM BM2SiteBundle:Character c, BM2SiteBundle:GeoData g JOIN g.biome b WHERE c.id > :last AND ST_Contains(g.poly, c.location) = true AND b.name IN ('ocean', 'water') ORDER BY c.id");
		$query->setParameter('last', 0);
		$iterableResult = $query->iterate();

		$time_start = microtime(true);
		$done = false;
		$complete = false;
		while (!$done) {
			$row = $iterableResult->next();
			if ($row===false) {
				$done=true;
				$complete=true;
				break;
			}

			$character = $row[0];

			//	a) troops eat food from camp followers
			// b) small chance of shipwreck and landing at nearby random beach (to prevent the eternal hiding at sea exploit I use myself)
			if (rand(0,100) == 25) {
				// shipwrecked !
				list($land_location, $ship_location) = $this->geography->findLandPoint($character->getLocation());
				if ($land_location) {
					$near = $this->geography->findNearestSettlementToPoint(new Point($land_location->getX(), $land_location->getY()));
					if ($near) {
						// FIXME: this can land me in ocean sometimes? Or simply doesn't work at all sometimes?
						$this->logger->info("  ".$character->getName()." has been shipwrecked, landing near ".$near[0]->getName()." at ".$land_location->getX()." / ".$land_location->getY());
						$character->setLocation($land_location);
						$character->setTravel(null)->setProgress(null)->setSpeed(null)->setTravelAtSea(false)->setTravelDisembark(false);
						$this->history->logEvent(
							$character,
							'event.travel.wreck',
							array('%link-settlement%'=>$near[0]->getId()),
							History::MEDIUM, false, 20
						);
					}
				}
			}
		}

		if ($complete) {
			$this->appstate->setGlobal('cycle.seafood', 'complete');
		} else {
			$this->appstate->setGlobal('cycle.seafood', $lastid);
		}

		$this->em->flush();
		$this->em->clear();
		return $complete;
	}

	public function findHeir(Character $character, Character $from=null) {
		// NOTE: This should match the implemenation on CharacterManager.php
		if (!$from) {
			$from = $character;
		}

		if ($this->seen->contains($character)) {
			// loops back to someone we've already checked
			return array(false, false);
		} else {
			$this->seen->add($character);
		}

		if ($heir = $character->getSuccessor()) {
			if ($heir->isActive(true)) {
				return array($heir, $from);
			} else {
				return $this->findHeir($heir, $from);
			}
		}
		return array(false, false);
	}

	public function eventNewYear() {
		$query = $this->em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s ORDER BY s.id ASC');
		$iterableResult = $query->iterate();

		$done = false;
		$complete = false;
		$i=1;
		while (!$done) {
			$row = $iterableResult->next();
			if ($row===false) {
				$done=true;
				$complete=true;
				break;
			}
			$settlement = $row[0];

			$peasant_kids = ceil($settlement->getPopulation()*0.02);
			$thrall_kids = round($settlement->getThralls()*0.01);
			$this->history->logEvent(
				$settlement,
				'event.settlement.newyear',
				array('%babies%'=>$peasant_kids+$thrall_kids),
				History::MEDIUM, false, 50
			);
			$settlement->setPopulation($settlement->getPopulation()+$peasant_kids);
			$settlement->setThralls($settlement->getThralls()+$thrall_kids);

			if (($i++ % $this->batchsize) == 0) {
				$this->em->flush();
			}
		}
		$this->em->flush();
	}


	public function getCycle() { return $this->cycle; }
	public function getDate() {
		// our in-game date - 6 days a week, 60 weeks a year
		$year = floor($this->cycle/360)+1;
		$week = floor($this->cycle%360/6)+1;
		$day = ($this->cycle%6)+1;
		return array('year'=>$year, 'week'=>$week, 'day'=>$day);
	}

	public function postToRealm(RealmPosition $position, $systemflag, $msg) {
		$query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Conversation c WHERE c.realm = :realm AND c.system = :system');
		switch ($systemflag) {
			case 'announcements':
				$query->setParameter('system', 'announcements');
				break;
			case 'general':
				$query->setParameter('system', 'general');
				break;
		}
		$query->setParameter('realm', $position->getRealm());
		$targetconvo = $query->getResult();

		foreach ($targetconvo as $topic) {
			$this->convman->writeMessage($topic, null, null, $msg, 'system');
		}

	}

	public function setupElection(RealmPosition $position, $electiontype=null, $routine=false, $counter=null) {
		$election = new Election;
		$election->setRealm($position->getRealm());
		$election->setPosition($position);
		$election->setOwner(null);
		$election->setRoutine($routine);
		$election->setClosed(false);
		if ($position->getElectiontype()) {
			$election->setMethod($position->getElectiontype());
		} else {
			$election->setMethod('banner');
		}
		$complete = new \DateTime("now");
		$complete->add(new \DateInterval("P7D"));
		$election->setComplete($complete);
		$election->setName("Election number ".$counter." for ".$position->getName());
		switch ($electiontype) {
			case 'noruler':
				$election->setDescription('The realm has been found to be without a ruler and an election has automatically been triggered.');
				break;
			case 'vacantelected':
				$election->setDescription('This elected position has been found to have no holders so an election has been called to correct this. Please be aware that multiple elections may have been called for this election, and that each election determines a different position holder.');
				break;
			case 'shortholders':
				$election->setDescription('This elected position has been found to have an inadequate number of holders and an election has been called. Please be aware that multiple elections may have been called for this election, and that each election determines a different position holder.');
				break;
			case 'routine':
				$election->setDescription('The previous term for this position has come to a close, so an election has been called to determine who will hold it next. Please be aware that multiple elections may have been called for this election, and that each election determines a different position holder.');
				break;
		}
		$this->em->persist($election);
		$this->em->flush();
		return $election;
	}

	public function Progress($part) {
		$entity = 'BM2\SiteBundle\Entity\\'.ucfirst($part);
		$last = $this->appstate->getGlobal('cycle.'.$part);
		$flush = false;
		if (!$last) {
			$this->appstate->setGlobal('cycle.'.$part, 0);
			$last=0; $flush=true;
		}
		if ($flush) { $this->em->flush(); }

		if (class_exists($entity)) {
			$query = $this->em->createQuery('SELECT count(a.id) FROM '.$entity.' a');
			$total = $query->getSingleScalarResult();
			if ($last==='complete') {
				$done=$total;
			} else {
				$query = $this->em->createQuery('SELECT count(a.id) FROM '.$entity.' a WHERE a.id <= :last');
				$query->setParameter('last', $last);
				$done = $query->getSingleScalarResult();
			}
		} else {
			$total=1;
			if ($last==='complete') {
				$done=1;
			} else {
				$done=0;
			}
		}
		return array($total, $done);
	}
}
