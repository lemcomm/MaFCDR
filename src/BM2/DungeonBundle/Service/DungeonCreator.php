<?php

namespace BM2\DungeonBundle\Service;

use Doctrine\ORM\EntityManager;
use Monolog\Logger;

use CrEOF\Spatial\PHP\Types\Geometry\Point;

use BM2\SiteBundle\Service\Geography;

use BM2\DungeonBundle\Entity\Dungeon;
use BM2\DungeonBundle\Entity\DungeonLevel;
use BM2\DungeonBundle\Entity\DungeonMonster;
use BM2\DungeonBundle\Entity\DungeonTreasure;
use BM2\DungeonBundle\Entity\DungeonParty;

class DungeonCreator {

	private $em;
	private $geo;
	private $logger;

	private $base_monstermod = 65;
	private $base_treasuremod = 25;


	public function __construct(EntityManager $em, Geography $geo, Logger $logger) {
		$this->em = $em;
		$this->geo = $geo;
		$this->logger = $logger;
	}


	public function createRandomDungeon() {
		$this->logger->info("creating new dungeon");
		$dungeon = new Dungeon;

		list($x, $y, $geodata) = $this->geo->findRandomPoint();
		if ($x===false) {
			// can't find a valid random point
			$this->logger->error("cannot find valid point for new dungeon");
			return false;
		}

		// for testing:
		$dungeon->setArea($this->RandomDungeonType($geodata->getBiome()->getName()));
		$dungeon->setLocation(new Point($x,$y));
		$dungeon->setGeoData($geodata);
		$dungeon->setTick(0)->setExplorationCount(0);

		$this->em->persist($dungeon);

		// create empty party, too, so we always have a party in the dungeon
		$party = new DungeonParty;
		$party->setDungeon($dungeon);
		$dungeon->setParty($party);
		$this->em->persist($party);

		return true;
	}


	public function createRandomLevel(Dungeon $dungeon, $depth) {
		$members = $dungeon->getParty()->getMembers()->count();
		$this->logger->info("creating new dungeon level at depth $depth for $members characters");
		$level = new DungeonLevel;
		$level->setDepth($depth);
		$level->setScoutLevel(0);
		$level->setDungeon($dungeon);
		$dungeon->addLevel($level);
		$dungeon->setExplorationCount($dungeon->getExplorationCount()+1);

		// create random monsters and treasures, based on depth and dungeon type
		$monster_points = round(pow($depth, 1.5) * $this->base_monstermod * pow($members, 0.8));
		$treasure_points = round(pow($depth-1, 1.8)  * $this->base_treasuremod * pow($members, 0.8));
		$this->logger->info("$monster_points monster points / $treasure_points treasure points");

		if ($members<3 && rand(0,100) < rand(0,50) {
			// may I introduce the suicide run! Hahahaha --Andrew
			$depth+3;
		}

		if ($members>4) {
			// treat dungeon as one level deeper for parties of 4-10, or we will get dozens of small monsters
			$depth++;
		}

		if ($members>10) {
			// treat dungeon as another level deeper for parties of 11-15 --Andrew
			$depth++;
		}

		if ($members>15) {
			// treat dungeon as another level deeper for parties of 16-19 --Andrew
			$depth++;
		}

		if ($members>20) {
			// treat dungeon as another level deeper for parties of 20-24 --Andrew
			$depth++;
		}

		if ($members>25) {
			// treat dungeon as another level deeper for parties of 25-29 --Andrew
			$depth++;
		}

		if ($members>30) {
			// treat dungeon as another level deeper for parties of 30 --Andrew
			$depth++;
		}

		$index = 0;
		while ($monster_points > 0) {
			list($monster,$size) = $this->RandomMonsterType($dungeon->getArea(), $depth);
			$max = min(ceil($monster_points / ($monster->getPoints()*$size/100)), ceil($depth*2.5));
			if (in_array('swarm', $monster->getClass())) {	
				$amount = 1;
			} elseif (in_array('pack', $monster->getClass())) {
				$amount = rand(ceil($max/2), $max);
			} elseif (in_array('solo', $monster->getClass())) {
				$amount = rand(ceil($max/4), ceil($max/2));
			} elseif (in_array('indiv', $monster->getClass())) {
				$amount = 1;
			} else {
				$amount = rand(ceil($max/4), $max);				
			}
			$this->logger->info("adding $amount (size $size) ".$monster->getName());

			$group = new DungeonMonster;
			$group->setNr(++$index);
			$group->setType($monster);
			$group->setAmount($amount)->setOriginalAmount($amount);
			$group->setSize($size);
			$group->setWounds(0)->setStunned(false);
			$group->setLevel($level);
			$level->addMonster($group);

			$this->em->persist($group);

			$monster_points -= $amount * $monster->getPoints()*$size/100;
		}

		$index = 0;
		while ($treasure_points > 0) {
			$value = max($depth*2, min($treasure_points, rand(ceil($treasure_points/3), $treasure_points*2)));
			$trap = max(0,rand(($depth*4)-15, round($value/2)));
			$hidden = rand(1, $depth);
			$this->logger->info("adding treasure worth $value, trap $trap, hidden $hidden");

			$treasure = new DungeonTreasure;
			$treasure->setNr(++$index);
			$treasure->setValue($value)->setTaken(0);
			$treasure->setTrap($trap);
			$treasure->setHidden($hidden);
			$treasure->setLevel($level);
			$level->addTreasure($treasure);

			$this->em->persist($treasure);

			$treasure_points -= $value;
		}


		$this->em->persist($level);
		return $level;
	}


	private function RandomDungeonType($biome) {
		// TODO: for testing we simply use randomness - later on we randomly determine based on biome: wild, ruin, dungeon, etc.; or by tieing in quests
		$pick = rand(0,200);
		
		if ($pick < 20) return 'glade';
		if ($pick < 40) return 'cave';
		if ($pick < 60) return 'wild';
		if ($pick < 80) return 'flooded';
		if ($pick < 100) return 'ruin';
		if ($pick < 120) return 'hold';
		if ($pick < 140) return 'mausoleum';
		if ($pick < 160) return 'lab';
		if ($pick < 180) return 'shipgrave';
		if ($pick < 190) return 'dungeon';
		if ($pick < 199) return 'citadel';
		return 'firstfort';
	}

	// TODO: incorporate a max_depth setting to the monster spawner.
	private function RandomMonsterType($area, $depth) {
		$query = $this->em->createQuery("SELECT t FROM DungeonBundle:DungeonMonsterType t WHERE t.areas LIKE :area AND t.min_depth <= :depth");
		$query->setParameters(array('area' => '%'.$area.'%', 'depth'=>$depth));
		$monsters = $query->getResult();
		$pick = array_rand($monsters);
		$type = $monsters[$pick];

		$depth -= $type->getMinDepth();

		// our size depends on the dungeon level only, at least for now
		// TODO: add the ability for monsters to randomly spawn at different sizes depending on their type. -- Andrew
		$roll = rand(0,100);
		if ($roll < 10-$depth) {
			$size = 50;
		} elseif ($roll < 25-($depth*2)) {
			$size = 80;
		} elseif ($roll < 80-($depth*3)) {
			$size = 100;
		} elseif ($roll < 100-($depth*4)) {
			$size = 120;
		} else {
			$size = 150;
		}

		return array($type, $size);
	}

}
