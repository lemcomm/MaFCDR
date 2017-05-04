<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Mercenaries;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;


class NpcManager {

	protected $em;
	protected $logger;
	protected $generator;
	protected $geo;
	protected $history;
	protected $cm;

	protected $mercenary_titles = array(
		"Scorpions", "Brawlers", "Marauders", "Slayers", "Hawks", "Lions", "Mashers", "Bandits", "Bulls",
		"Dragons", "Tigers", "Pirates", "Reapers", "Wild Men", "Red Swords", "Silver Tunics", "Peasant Liberation Front",
		"Wasps", "Panthers", "Predators", "Hunters", "Devoted", "Serpents", "Shadows"
		);

	public function __construct(EntityManager $em, Logger $logger, Generator $generator, Geography $geo, History $history, CharacterManager $cm) {
		$this->em = $em;
		$this->logger = $logger;
		$this->generator = $generator;
		$this->geo = $geo;
		$this->history = $history;
		$this->cm = $cm;
	}


	public function getAvailableNPCs() {
		return $this->em->getRepository('BM2SiteBundle:Character')->findBy(array('npc'=>true, 'alive'=>true, 'user'=>null));
	}

	public function createNPC() {
		if (rand(0,100)<40) {
			$male = false;
		} else {
			$male = true;
		}
		$npc = new Character;
		$npc->setGeneration(1);
		$this->setBaseValues($npc);
		$this->logger->info("creating new NPC ".$npc->getName());
		$npc->setCreated(new \DateTime("now"));
		$npc->setLastAccess(new \DateTime("now"));
		$npc->setMale($male);

		$this->em->persist($npc);
		$this->em->flush($npc); // because the below needs this flushed

		$this->history->logEvent($npc, 'event.character.created');
		$this->history->openLog($npc, $npc);

		return $npc;
	}

	private function setBaseValues(Character $npc) {
		$npc->setAlive(true)->setSlumbering(true)->setNpc(true);
		$npc->setVisibility(5)->setSpottingDistance(100);
		$npc->setList(1);
		$npc->setName($this->generator->randomName(null, $npc->getMale()));
		if ($npc->getUser() && $npc->getUser()->getCurrentCharacter()==$npc) {
			$npc->getUser()->setCurrentCharacter(null);
		}
		$npc->setUser(null);
		$npc->setTravelLocked(false)->setTravelEnter(false)->setTravelAtSea(false)->setTravelDisembark(false);
		$npc->setSpecial(false);
		$npc->setWounded(0);
		$npc->setGold(0);
		$npc->setGenome('__');
	}

	public function spawnNPC(Character $npc) {
		// find a place to spawn him
		list($x, $y, $geodata) = $this->geo->findRandomPoint();
		if ($x===false) {
			// can't find a valid random point
			$this->logger->error("cannot find valid point for new NPC");
			return false;
		}
		$npc->setLocation(new Point($x, $y));
		$npc->setInsideSettlement(null);
		$npc->setProgress(null)->setSpeed(null)->setTravel(null);


		// create unit of soldiers
		// FIXME: this should somehow depend on the average militia size in the region we're spawning in
		$total_visual_size = min( rand(100, 300), rand(100, 300) );

		// FIXME: this should be randomly selected from equipment available in the nearby area or something.
		$weapons = $this->em->createQuery("SELECT COUNT(e) FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'weapon' and e.resupply_cost <= 200")->getSingleScalarResult();
		$armours = $this->em->createQuery("SELECT COUNT(e) FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'armour' and e.resupply_cost <= 200")->getSingleScalarResult();
		$items = $this->em->createQuery("SELECT COUNT(e) FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'equipment' and e.resupply_cost <= 300")->getSingleScalarResult();
		$group = 1;

		while ($total_visual_size > 0) {
			$weapon = $this->em->createQuery("SELECT e FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'weapon'")
						->setFirstResult(rand(0, $weapons-1))->setMaxResults(1)->getSingleResult();
			if (rand(0,10)==0) {
				$armour = null;
			} else {
				$armour = $this->em->createQuery("SELECT e FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'armour'")
							->setFirstResult(rand(0, $armours-1))->setMaxResults(1)->getSingleResult();
			}
			if (rand(0,10)<6) {
				$item = null;
			} else {
				$item = $this->em->createQuery("SELECT e FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'equipment'")
							->setFirstResult(rand(0, $items-1))->setMaxResults(1)->getSingleResult();
			}

			$count = min(rand(5,40), $total_visual_size);
			if ($weapon->getTrainingRequired() > 50) {
				$count = ceil($count * 0.75);
			}
			if ($armour && $armour->getTrainingRequired() > 40) {
				$count = ceil($count * 0.5);
			}
			if ($item && $item->getTrainingRequired() > 50) {
				$count = ceil($count * 0.5);
			}
			// find a random home location by taking the nearest settlement to a point near our spawn location
			$nearest = $this->geo->findNearestSettlementToPoint(new Point($x + rand(-5000,5000), $y + rand(-5000,5000)));
			$home=array_shift($nearest);
			for ($i=0;$i<$count;$i++) {
				$soldier = $this->generator->randomSoldier($weapon, $armour, $item);
				if ($soldier) {
					$soldier->setGroup($group);
					$soldier->setCharacter($npc);
					$npc->addSoldier($soldier);
					$total_visual_size -= $soldier->getVisualSize();
					$soldier->setHome($home); // setting this here because otherwise the generator will (try to) take it from settlement stockpile
				} else {
					$this->logger->error("failed to create NPC soldier with ".($weapon?$weapon->getName():"%").", ".($armour?$armour->getName():"%").", ".($item?$item->getName():"%")." - home: ".($home?$home->getName():"%"));
				}
			}
			$group++;
		}

		// add a random number of scouts and camp followers
		$scouts = rand(0, 10);
		if ($scouts>0) {
			$scout_type = $this->em->getRepository('BM2SiteBundle:EntourageType')->findOneByName('scout');
			for ($i=0; $i < $scouts; $i++) {
				$scout = $this->generator->randomEntourageMember($scout_type);
				$scout->setCharacter($npc);
				$npc->addEntourage($scout);
			}
		}


		$followers = min(rand(0, 6), rand(0,6));
		if ($followers>0) {
			$follower_type = $this->em->getRepository('BM2SiteBundle:EntourageType')->findOneByName('follower');
			for ($i=0; $i < $followers; $i++) {
				$follower = $this->generator->randomEntourageMember($follower_type);
				$follower->setCharacter($npc);
				$npc->addEntourage($follower);
			}
		}

		// TODO: what about history? wipe it?
		$this->history->openLog($npc, $npc);

		return true;
	}


	public function checkTimeouts(Character $npc) {
		if ($npc->isAlive() && $npc->getSlumbering()) {
			// living but inactive NPCs will be opened up again for playing
			$this->logger->info("NPC ".$npc->getName()." has gone inactive, freeing him up");
			if ($npc->getUser() && $npc->getUser()->getCurrentCharacter()==$npc) {
				$npc->getUser()->setCurrentCharacter(null);
			}
			$npc->setUser(null);
		} else if (!$npc->isAlive() && $npc->getLastAccess()->diff(new \DateTime("now"), true)->days > 7) {
			// dead NPCs return to pool one week after last access
			$this->logger->info("NPC ".$npc->getName()." is dead and buried, respawning him");
			$this->setBaseValues($npc);
			$npc->setLocation(null)->setInsideSettlement(null)->setProgress(null)->setSpeed(null)->setTravel(null);
		}
	}

	public function checkTroops(Character $npc) {
		// NPCs without troops die
		if ($npc->getLivingSoldiers()->isEmpty()) {
			$this->cm->kill($npc, null, false, 'npcdeath');
		}
	}


	public function createMercenaries() {
		$group = new Mercenaries;
		$group->setWait(0);
		$this->relocateMercenaries($group);
		$near = $this->geo->findNearestSettlementToPoint($group->getLocation());
		$place = $near[0]->getName();
		$pick = rand(0, count($this->mercenary_titles)-1);
		$group->setName($place." ".$this->mercenary_titles[$pick]);

		// randomly select equipment
		$weapons = $this->em->createQuery("SELECT COUNT(e) FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'weapon'")->getSingleScalarResult();
		$armours = $this->em->createQuery("SELECT COUNT(e) FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'armour'")->getSingleScalarResult();
		$items = $this->em->createQuery("SELECT COUNT(e) FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'equipment'")->getSingleScalarResult();

		$weapon = $this->em->createQuery("SELECT e FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'weapon'")
					->setFirstResult(rand(0, $weapons-1))->setMaxResults(1)->getSingleResult();
		if (rand(0,10)==0) {
			$armour = null;
		} else {
			$armour = $this->em->createQuery("SELECT e FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'armour'")
						->setFirstResult(rand(0, $armours-1))->setMaxResults(1)->getSingleResult();
		}
		if (rand(0,10)<6) {
			$item = null;
		} else {
			$item = $this->em->createQuery("SELECT e FROM BM2SiteBundle:EquipmentType e WHERE e.type = 'equipment'")
						->setFirstResult(rand(0, $items-1))->setMaxResults(1)->getSingleResult();
		}

		$group->setWeapon($weapon);
		$group->setArmour($armour);
		$group->setEquipment($item);

		$count = min(rand(50,100), rand(60, 120));
		if ($weapon->getTrainingRequired() > 50) {
			$count = ceil($count * 0.75);
		}
		if ($armour && $armour->getTrainingRequired() > 40) {
			$count = ceil($count * 0.5);
		}
		if ($item && $item->getTrainingRequired() > 50) {
			$count = ceil($count * 0.5);
		}

		$group->setMinMen($count);
		$group->setMaxMen(round($count * rand(120, 180)/100 ));

		$group->setXp(rand(10, 50));

		// determine price
		$train = $weapon->getTrainingRequired();
		if ($armour) {
            $train+=$armour->getTrainingRequired();
        }
		if ($item) {
            $train+=$item->getTrainingRequired();
        }

		$value = $train + $group->getXp() * 2 + rand(0,50);
		$group->setPrice($value/250);

		$group->setActive(true);
		$this->em->persist($group);

		return $group;
	}


	public function hireMercenaries(Character $char, Mercenaries $group, $men) {
		$men = max(min($men, $group->getMaxMen()), $group->getMinMen());
		$total_cost = ceil($men * $group->getPrice());

		if ($total_cost > $char->getGold()) {
			return false;
		}
		if ($men < 1) {
			// should never happen, but does due to a separate bug, hotfixing/preventing it here:
			return false;
		}
		$char->setGold($char->getGold() - $total_cost);

		for ($i=0; $i<$men; $i++) {
			if ($soldier = $this->generator->randomSoldier($group->getWeapon(), $group->getArmour(), $group->getEquipment())) {
				$this->history->addToSoldierLog($soldier, 'hired', array('%link-character%'=>$char->getId()));
				$char->addSoldier($soldier);
				$soldier->setCharacter($char);
				$group->addSoldier($soldier);
				$soldier->setMercenary($group);
				$soldier->setExperience($group->getXp());
			}
		}
		$group->setHiredBy($char)->setWait(0);

		// reduce our available men
		$group->setMinMen($group->getMinMen() - $men);
		$group->setMaxMen($group->getMaxMen() - $men);

		return true;
	}


	public function payMercenaries(Mercenaries $group) {
		$cost = $group->getTotalPrice();

		if ($cost <= 0) {
			// the group is over, should be freed
			$this->freeMercenaries($group);
			return true;
		}
		if ($group->getHiredBy()->getGold() < $cost) {
			// can't pay them anymore
			$this->history->logEvent( 
				$group->getHiredBy(),
				'event.character.mercs.cantpay',
				array('%cost%'=>$cost, '%link-mercenaries%'=>$group->getId()),
				HISTORY::MEDIUM, false, 15
			);
			$this->freeMercenaries($group);
			return false;
		} else {
			$group->getHiredBy()->setGold($group->getHiredBy()->getGold() - $cost);
			$this->history->logEvent( 
				$group->getHiredBy(),
				'event.character.mercs.paid',
				array('%cost%'=>$cost, '%link-mercenaries%'=>$group->getId()),
				HISTORY::LOW, false, 10
			);
			// TODO: with more gold, we attract more people, so every time we get paid, we increase our size a little
			// this is a very primitive algorithm
			$group->setMinMen($group->getMinMen()+1);
			$group->setMaxMen($group->getMaxMen()+rand(1,2));
			return true;
		}
	}

	public function freeMercenaries(Mercenaries $group) {
		// move soldiers back into group
		$men = $group->countLiving();
		foreach ($group->getSoldiers() as $s) {
			if ($s->getCharacter()) {
				$s->getCharacter()->removeSoldier($s);
			}
			$this->em->remove($s);
		}
		$group->setMinMen($group->getMinMen() + $men + rand(0,10));
		$group->setMaxMen($group->getMaxMen() + $men + rand(0,10));
		if ($group->getMinMen() > $group->getMaxMen() * 0.9) {
			$group->setMinMen(floor($group->getMaxMen() * 0.9));
		}

		if ($group->getMinMen() < rand(10,25)) {
			// too few survivors, let's go home
			$group->setActive(false);
			$group->setHiredBy(null);
			return false;
		}

		// 50-50 chance of staying put or moving to a new random location
		if ($group->getHiredBy() && $group->getHiredBy()->getLocation() && rand(0,100) < 50) {
			$group->setLocation($group->getHiredBy()->getLocation());
		} else {
			$this->relocateMercenaries($group);
		}
		$group->setHiredBy(null);
		return true;
	}

	public function relocateMercenaries(Mercenaries $group) {
		list($x, $y, $geodata) = $this->geo->findRandomPoint();
		if ($x===false) {
			// can't find a valid random point
			$this->logger->error("cannot find valid point for new mercenary group");
			return false;
		}
		$group->setLocation(new Point($x, $y));
		$group->setWait(0);
	}

}

