<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Building;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Election;
use BM2\SiteBundle\Entity\GeoData;
use BM2\SiteBundle\Entity\Setting;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Ship;
use Calitarus\MessagingBundle\Service\MessageManager;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use Doctrine\Common\Collections\ArrayCollection;
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
	private $military;
	private $battlerunner;
	private $interactions;
	private $geography;
	private $generator;
	private $rm;
	private $mm;
	private $pm;
	private $npc;

	private $cycle=0;
	private $output=false;
	private $debug=false;
	private $limited=false;

	private $bandits_ok_distance = 50000;

	public function __construct(EntityManager $em, AppState $appstate, Logger $logger, ActionResolution $resolver, Economy $economy, Politics $politics, History $history, Military $military, BattleRunner $battlerunner, Interactions $interactions, Geography $geography, Generator $generator, RealmManager $rm, MessageManager $mm, PermissionManager $pm, NpcManager $npc) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->logger = $logger;
		$this->resolver = $resolver;
		$this->economy = $economy;
		$this->politics = $politics;
		$this->history = $history;
		$this->military = $military;
		$this->battlerunner = $battlerunner;
		$this->interactions = $interactions;
		$this->geography = $geography;
		$this->generator = $generator;
		$this->rm = $rm;
		$this->mm = $mm;
		$this->pm = $pm;
		$this->npc = $npc;

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

	public function runCharactersUpdatesCycle() {
		$last = $this->appstate->getGlobal('cycle.characters', 0);
		if ($last==='complete') return true;
		$this->logger->info("characters update...");

		// healing
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Character c SET c.wounded=0 WHERE c.wounded <= 10');
		$query->execute();
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Character c SET c.wounded=c.wounded-10 WHERE c.wounded > 10');
		$query->execute();

		// TODO: this probably deserves its own update cycle soon...
		// message conversations update
		$query = $this->em->createQuery('SELECT c FROM MsgBundle:Conversation c WHERE c.app_reference IS NOT NULL');
		$iterableResult = $query->iterate();
		while ($row = $iterableResult->next()) {
			$conversation = $row[0];
			$this->mm->updateMembers($conversation);

			// 30 days after the last posting, realm conversations stop being auto-managed so people can leave and the conversation can be cleaned up
			$subquery = $this->em->createQuery('SELECT MAX(m.ts) FROM MsgBundle:Message m WHERE m.conversation = :conversation');
			$subquery->setParameter('conversation', $conversation);
			$last_message_ts = $subquery->getSingleScalarResult();
			if ($last_message_ts) {
				$last_message = new \DateTime($last_message_ts);
				$days = $last_message->diff(new \DateTime("now"), true)->days;
				if ($days > 30) { // FIXME: ugly hardcoded value
					$conversation->setAppReference(null);
				}
			}
		}

		$this->appstate->setGlobal('cycle.characters', 'complete');
		$this->em->flush();
		$this->em->clear();
		return true;
	}

	public function runNPCCycle() {
		$last = $this->appstate->getGlobal('cycle.npcs', 0);
		if ($last==='complete') return true;
		$this->logger->info("npcs update...");

		$query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Character c WHERE c.npc = true');
		foreach ($query->getResult() as $npc) {
			if ($npc->isAlive()) {
				$this->npc->checkTroops($npc);
			}
			$this->npc->checkTimeouts($npc);
		}

		$query = $this->em->createQuery('SELECT count(u.id) FROM BM2SiteBundle:User u WHERE u.account_level > 0');
		$players = $query->getSingleScalarResult();
		$want = ceil($players/8);

		$active_npcs = $this->em->createQuery('SELECT count(c) FROM BM2SiteBundle:Character c WHERE c.npc = true AND c.alive = true')->getSingleScalarResult();

		$this->logger->info("we want $want NPCs for $players players, we have $active_npcs");
		if ($active_npcs < $want) {
			$npc = $this->npc->createNPC();
			$this->logger->info("created NPC ".$npc->getName());
		}

		$query = $this->em->createQuery('SELECT s as soldier, c as character FROM BM2SiteBundle:Soldier s JOIN s.character c JOIN s.home h JOIN h.geo_data g WHERE c.npc = true AND s.alive = true AND s.distance_home > :okdistance');
		$query->setParameter('okdistance', $this->bandits_ok_distance);
		$this->logger->info(count($query->getResult())." bandit soldiers complaining");
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
				$this->military->disband($row['soldier'], $row['soldier']->getCharacter());
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

		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Mercenaries m SET m.wait = m.wait +1 WHERE m.active = true AND m.hired_by IS NULL');
		$query->execute();
		$query = $this->em->createQuery('SELECT m FROM BM2SiteBundle:Mercenaries m WHERE m.active = true AND m.hired_by IS NULL AND m.wait > 30');
		foreach ($query->getResult() as $mercs) {
			// enough waiting, let's move somewhere else
			$this->logger->info("Mercenary group ".$mercs->getName()." moving somewhere else.");
			$this->npc->relocateMercenaries($mercs);
		}



		if ($this->cycle % 6 == 0) {
			// once a week, pay for all hired mercenaries
			$query = $this->em->createQuery('SELECT m FROM BM2SiteBundle:Mercenaries m WHERE m.active = true AND m.hired_by IS NOT NULL');
			foreach ($query->getResult() as $mercs) {
				$this->npc->payMercenaries($mercs);
			}
		}

		$mercenaries = $this->em->createQuery('SELECT count(m) FROM BM2SiteBundle:Mercenaries m WHERE m.active = true')->getSingleScalarResult();
		$want = ceil($players/6);
		$this->logger->info("we want $want mercenary groups for $players players, we have $mercenaries");
		if ($mercenaries < $want) {
			$this->logger->info("creating new group.");
			$mercenary = $this->npc->createMercenaries();
			$this->logger->info("created mercenary group ".$mercenary->getName());
		}

		// TODO: what to do with inactive mercenary groups?

		$this->appstate->setGlobal('cycle.npcs', 'complete');
		$this->em->flush();
		$this->em->clear();

		return true;
	}

	public function runSoldierUpdatesCycle() {
		$last = $this->appstate->getGlobal('cycle.soldiers', 0);
		if ($last==='complete') return true;
		$this->logger->info("soldiers update...");

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
		$this->logger->info("$rotting soldiers rotting, $deleted were deleted");

		$this->em->flush();

		// assignment counters
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.assigned_since = :cycle where s.assigned_since = -1');
		$query->setParameter('cycle', $this->cycle);
		$query->execute();

		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.assigned_since = null, s.liege = null where s.assigned_since < :old');
		$query->setParameter('old', $this->cycle-50);
		$query->execute();

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
			$this->military->resupply($soldier, $soldier->getBase()); 

			if (($i++ % $this->batchsize) == 0) {
				$this->em->flush();
				$this->em->clear();
			}
		}
		$this->em->flush();
		$this->em->clear();

		// wounded troops: heal or die
		$this->logger->info("heal or die...");
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

		$this->logger->info("disbanding...");
		$disband_soldiers = 0;
		$query = $this->em->createQuery('SELECT s, c, DATE_DIFF(CURRENT_DATE(), c.last_access) as days FROM BM2SiteBundle:Soldier s JOIN s.character c WHERE c.slumbering = true');
		$iterableResult = $query->iterate();
		$i=1;
		while ($row = $iterableResult->next()) {
			// meet the most stupid array return data setup imaginable - first return row is different, yeay!
			$s = array_shift($row);
			$soldier = $s[0];
			if (isset($s['days'])) {
				$days = $s['days'];
			} else {
				$d = array_shift($row);
				$days = $d['days'];				
			}
			if (rand(0,250) < $days) {
				$disband_soldiers++;
				$this->military->disband($soldier, $soldier->getCharacter());
			}

			if (($i++ % $this->batchsize) == 0) {
				$this->em->flush();
			}
		}
		echo "done\n";
		$this->em->flush();

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
				$this->military->disbandEntourage($entourage, $entourage->getCharacter());
			}

			if (($i++ % $this->batchsize) == 0) {
				$this->em->flush();
			}
		}
		$this->em->flush();

		if ($disband_soldiers > 0 || $disband_entourage > 0) {
			$this->logger->info("disbanded $disband_soldiers soldiers and $disband_entourage entourage.");
		} 

		// clean out knight offers that have gone empty
		$query = $this->em->createQuery('SELECT o as offer, count(s) as soldiers FROM BM2SiteBundle:KnightOffer o LEFT JOIN o.soldiers s WHERE o.give_settlement=false GROUP BY o');
		foreach ($query->getResult() as $row) {
			if ($row['soldiers']==0) {
				$this->em->remove($row['offer']);
			}
		}

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
		$this->logger->info("actions...");

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
		$this->logger->info("resupply...");

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
		$this->logger->info("realms...");
		
		# This is just me being picky, but I like to define everything I need before I need it, even if it's not connected to the query yet.
		$timeout = new \DateTime("now");
		$timeout->sub(new \DateInterval("P15D")); // hardcoded to 15 day intervals between election attempts
		
		# Fixing a bug here that 
		$query = $this->em->createQuery('SELECT p FROM BM2SiteBundle:RealmPosition p JOIN p.realm r LEFT JOIN p.holders h WHERE r.active = true AND p.ruler = true AND h.id IS NULL AND p NOT IN (SELECT y FROM BM2SiteBundle:Election x JOIN x.position y WHERE x.closed=false OR x.complete < :timeout) GROUP BY p');
		$query->setParameter('timeout', $timeout);
		$result = $query->getResult();
		foreach ($result as $position) {
			$this->logger->notice("empty ruler position for realm ".$position->getRealm()->getName());
			$members = $position->getRealm()->findMembers();
			if ($members->isEmpty()) {
				$this->logger->notice("-- realm deserted, making inactive.");
				$realm = $position->getRealm();
				$this->rm->abandon($realm);
			} else {
				$this->logger->notice("-- election triggered.");
				$election = new Election;
				$election->setRealm($position->getRealm());
				$election->setPosition($position);
				$election->setOwner(null);
				$election->setClosed(false);

				$complete = new \DateTime("now");
				$complete->add(new \DateInterval("P3D"));
				$election->setComplete($complete);

				// TODO: translate strings here?
				$election->setName('Ruler Election');
				$election->setDescription('The realm has been found to be without ruler and an election has automatically been triggered.');

				// TODO: this should be stored in the position (and editable)
				$election->setMethod('banner');					
				$this->em->persist($election);
				$this->em->flush(); // must flush because we need the ID below

				// message to the realm channel
				$msg = "An automatic election has been triggered for the ruler position. You are invited to vote - [vote:".$election->getId()."].";
				// FIXME: this posts to all realm conversations! how do we find the "main" ??
				$query = $this->em->createQuery('SELECT c FROM MsgBundle:Conversation c WHERE c.app_reference = :realm');
				$query->setParameter('realm', $position->getRealm());
				foreach ($query->getResult() as $conversation) {
					$this->mm->writeMessage($conversation, null, $msg);
				}
			}
		}

		$query = $this->em->createQuery('SELECT r FROM BM2SiteBundle:Realm r LEFT JOIN r.conversations c WHERE r.active = true AND c.id IS NULL');
		$result = $query->getResult();
		foreach ($result as $realm) {
			$this->logger->notice("missing realm conversation for ".$realm->getName());

			// usually the ruler is the conversation owner, but if we don't have one, just pick the first realm member
			$rulers = $realm->findRulers();
			if (!$rulers->isEmpty()) {
				$msguser = $this->mm->getMsgUser($rulers->first());
			} else {
				$members = $realm->findMembers(true);
				if (!$members->isEmpty()) {
					$msguser = $this->mm->getMsgUser($members->first());
				} else {
					$this->logger->notice("-- realm deserted, making inactive.");
					$this->rm->abandon($realm);
					$msguser = false;
				}
			}
			if ($msguser) {
				list($meta,$conversation) = $this->mm->createConversation($msguser, $realm->getFormalName(), null, $realm);
				$this->em->flush(); // because the below needs this flushed
				$this->mm->updateMembers($conversation);
				$this->logger->notice("-- created");
			}
		}


		$this->appstate->setGlobal('cycle.realm', 'complete');
		$this->em->flush();
		$this->em->clear();

		return true;
	}


	public function runSeaFoodCycle() {
			$last = $this->appstate->getGlobal('cycle.seafood', 0);
			if ($last==='complete') return true;
			$last=(int)$last;
			$this->logger->info("sea food...");

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
				$soldiers = $character->getSoldiers()->count();
				$entourage = $character->getEntourage()->count();

				//	a) troops eat food from camp followers
				// b) small chance of shipwreck and landing at nearby random beach (to prevent the eternal hiding at sea exploit I use myself)
				if (rand(0,100) == 25) {
					// shipwrecked !
					list($land_location, $ship_location) = $this->geography->findLandPoint($character->getLocation());
					if ($land_location) {
						$near = $this->geography->findNearestSettlementToPoint(new Point($land_location->getX(), $land_location->getY()));
						if ($near) {
							// FIXME: this can land me in ocean sometimes? Or simply doesn't work at all sometimes?
							echo $character->getName()." has been shipwrecked, landing near ".$near[0]->getName()." at ".$land_location->getX()." / ".$land_location->getY()."\n";
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
				if ($character->getTravelAtSea()) {
					// we are still at sea, so let's eat some fish
					$this->economy->feedSoldiers($character, 2);
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
