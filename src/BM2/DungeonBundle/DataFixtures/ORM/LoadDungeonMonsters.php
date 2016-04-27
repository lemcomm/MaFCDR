<?php

namespace BM2\DungeonBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\DungeonBundle\Entity\DungeonMonsterType;


class LoadDungeonMonsters extends AbstractFixture implements OrderedFixtureInterface {

	private $monsters = array(
		// animals
		'arachnid'	=> array('power' => 25, 'attacks' =>  1, 'defense' => 30, 'wounds' => 3, 'mindepth' => 2, 'class' => array('animal', 'melee'), 'areas' => array('cave', 'ruin', 'dungeon')),
		'bat'			=> array('power' => 40, 'attacks' =>  1, 'defense' => 40, 'wounds' => 1, 'mindepth' => 2, 'class' => array('animal', 'fly'), 'areas' => array('cave', 'ruin')),
		'beetle'		=> array('power' => 10, 'attacks' =>  3, 'defense' => 10, 'wounds' => 4, 'mindepth' => 1, 'class' => array('animal', 'swarm', 'poison'), 'areas' => array('cave', 'wild')),
		'bear'		=> array('power' => 50, 'attacks' =>  1, 'defense' => 25, 'wounds' => 2, 'mindepth' => 0, 'class' => array('animal', 'melee', 'solo'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon')),
		'cat'			=> array('power' => 40, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'solo', 'stealth'), 'areas' => array('wild', 'ruin')),
		'eagle'		=> array('power' => 40, 'attacks' =>  1, 'defense' => 25, 'wounds' => 2, 'mindepth' => 1, 'class' => array('animal', 'fly'), 'areas' => array('wild', 'ruin')),
		'snake'		=> array('power' => 30, 'attacks' =>  1, 'defense' => 10, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'poison'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon')),
		'wolf'		=> array('power' => 25, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'melee', 'pack'), 'areas' => array('cave', 'wild', 'ruin')),
		'wolfb'		=> array('power' => 60, 'attacks' =>  1, 'defense' => 40, 'wounds' => 2, 'mindepth' => 1, 'class' => array('animal', 'melee', 'pack'), 'areas' => array('cave', 'wild', 'dungeon')),


		// humans
		'bandit'		=> array('power' => 60, 'attacks' =>  1, 'defense' => 40, 'wounds' => 1, 'mindepth' => 1, 'class' => array('human', 'melee'), 'areas' => array('cave', 'ruin', 'dungeon')),
		'bandita'	=> array('power' => 50, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 1, 'class' => array('human', 'ranged'), 'areas' => array('cave', 'ruin', 'dungeon')),
		'banditb'	=> array('power' => 75, 'attacks' =>  1, 'defense' => 60, 'wounds' => 2, 'mindepth' => 2, 'class' => array('human', 'melee', 'solo'), 'areas' => array('cave', 'ruin', 'dungeon')),
		'crazy'		=> array('power' => 20, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 0, 'class' => array('human', 'melee'), 'areas' => array('cave', 'ruin')),
		'lost'		=> array('power' => 80, 'attacks' =>  2, 'defense' => 80, 'wounds' => 3, 'mindepth' => 4, 'class' => array('human', 'melee', 'solo'), 'areas' => array('ruin', 'dungeon')),


		// monsters
		'barghest'	=> array('power' => 90, 'attacks' =>  1, 'defense' => 50, 'wounds' => 3, 'mindepth' => 3, 'class' => array('monster', 'melee'), 'areas' => array('cave', 'dungeon')),
		'ogre'		=> array('power' => 80, 'attacks' =>  1, 'defense' => 60, 'wounds' => 4, 'mindepth' => 4, 'class' => array('monster', 'melee'), 'areas' => array('ruin', 'dungeon')),
		'troglodyte'=> array('power' => 80, 'attacks' =>  2, 'defense' => 50, 'wounds' => 3, 'mindepth' => 4, 'class' => array('monster', 'melee'), 'areas' => array('cave', 'wild')),
		'wyvern'		=> array('power' =>150, 'attacks' =>  2, 'defense' => 80, 'wounds' => 2, 'mindepth' => 5, 'class' => array('monster', 'fly'), 'areas' => array('wild')),
	);

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 1000; // or anywhere, really
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		foreach ($this->monsters as $name=>$data) {
			$type = new DungeonMonsterType;
			$type->setName($name);
			$type->setClass($data['class']);
			$type->setAreas($data['areas']);
			$type->setMinDepth($data['mindepth']);
			$type->setPower($data['power']);
			$type->setAttacks($data['attacks']);
			$type->setDefense($data['defense']);
			$type->setWounds($data['wounds']);
			$manager->persist($type);
		}

		$manager->flush();
	}
}
