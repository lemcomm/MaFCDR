<?php

namespace BM2\DungeonBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\DungeonBundle\Entity\DungeonMonsterType;


class LoadDungeonMonsters extends AbstractFixture implements OrderedFixtureInterface {

	private $monsters = array(
		// animals
		'arachnid'	=> array('power' => 25, 'attacks' =>  1, 'defense' => 30, 'wounds' => 3, 'mindepth' => 2, 'class' => array('animal', 'melee'), 'areas' => array('cave', 'ruin', 'dungeon', 'citadel', 'lab')),
		'bat'		=> array('power' => 40, 'attacks' =>  1, 'defense' => 40, 'wounds' => 1, 'mindepth' => 2, 'class' => array('animal', 'fly', 'melee'), 'areas' => array('cave', 'ruin', 'citadel', 'lab')),
		'beetle'	=> array('power' => 10, 'attacks' =>  3, 'defense' => 10, 'wounds' => 4, 'mindepth' => 1, 'class' => array('animal', 'swarm', 'poison'), 'areas' => array('cave', 'wild', 'lab')),
		'bear'		=> array('power' => 50, 'attacks' =>  1, 'defense' => 25, 'wounds' => 2, 'mindepth' => 0, 'class' => array('animal', 'melee', 'solo'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon', 'lab', 'glade')),
		'cat'		=> array('power' => 40, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'solo', 'stealth'), 'areas' => array('wild', 'ruin', 'glade', 'lab')),
		'eagle'		=> array('power' => 40, 'attacks' =>  1, 'defense' => 25, 'wounds' => 2, 'mindepth' => 1, 'class' => array('animal', 'fly', 'melee'), 'areas' => array('wild', 'ruin', 'glade', 'lab')),
		'mongrel'	=> array('power' => 20, 'attacks' =>  1, 'defense' => 10, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'solo', 'melee', 'pack'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon', 'glade', 'citadel', 'hold', 'lab'))
		'rat'		=> array('power' =>  5, 'attacks' =>  1, 'defense' =>  5, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'swim', 'melee'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon', 'glade', 'citadel', 'hold', 'lab', 'firstfort')),
		'snake'		=> array('power' => 30, 'attacks' =>  1, 'defense' => 10, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'melee', 'poison'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon', 'lab', 'glade')),
		'wolf'		=> array('power' => 25, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'melee', 'pack'), 'areas' => array('cave', 'wild', 'ruin',, 'lab')),
		'wolfb'		=> array('power' => 60, 'attacks' =>  1, 'defense' => 40, 'wounds' => 2, 'mindepth' => 1, 'class' => array('animal', 'melee', 'pack'), 'areas' => array('cave', 'wild', 'dungeon', 'citadel', 'lab')),

		// beasts
		'dog'		=> array('power' => 45, 'attacks' =>  1, 'defense' => 15, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'solo', 'melee'), 'areas' => array('cave', 'ruin', 'dungeon', 'citadel', 'hold', 'firstfort', 'lab'))
		'falcon'	=> array('power' => 25, 'attacks' =>  1, 'defense' => 10, 'wounds' => 2, 'mindepth' => 1, 'class' => array('animal', 'fly', 'melee'), 'areas' => array('wild', 'ruin', 'glade', 'lab', 'citadel', 'firstfort')),
		'hawk'		=> array('power' => 40, 'attacks' =>  1, 'defense' => 25, 'wounds' => 2, 'mindepth' => 2, 'class' => array('animal', 'fly', 'melee'), 'areas' => array('wild', 'ruin', 'glade', 'lab')),
		
		// humans
		'bandit'	=> array('power' => 60, 'attacks' =>  1, 'defense' => 40, 'wounds' => 1, 'mindepth' => 1, 'class' => array('human', 'melee'), 'areas' => array('cave', 'ruin', 'dungeon', 'hold', 'citadel', 'lab')),
		'bandita'	=> array('power' => 50, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 1, 'class' => array('human', 'mixed'), 'areas' => array('cave', 'ruin', 'dungeon', 'hold', 'lab')),
		'banditb'	=> array('power' => 75, 'attacks' =>  1, 'defense' => 60, 'wounds' => 2, 'mindepth' => 2, 'class' => array('human', 'melee', 'solo'), 'areas' => array('cave', 'ruin', 'dungeon', 'hold', 'lab')),
		'banditg'	=> array('power' => 60, 'attacks' =>  1, 'defense' => 40, 'wounds' => 1, 'mindepth' => 1, 'class' => array('human', 'mixed', 'handler'), 'areas' => array('cave', 'ruin', 'dungeon', 'hold', 'citadel', 'lab')),
		'crazy'		=> array('power' => 20, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 0, 'class' => array('human', 'melee'), 'areas' => array('cave', 'ruin', 'dungeon', 'lab', 'wild', 'glade')),
		'shaman'	=> array('power' => 30, 'attacks' =>  3, 'defense' => 40, 'wounds' => 2, 'mindepth' => 3, 'class' => array('human', 'mixed', 'swim', 'solo'), 'areas' => array('cave', 'dungeon', 'ruin', 'glade', 'lab', 'wild', 'citadel', 'hold'))
		'lost'		=> array('power' => 80, 'attacks' =>  2, 'defense' => 80, 'wounds' => 3, 'mindepth' => 4, 'class' => array('human', 'melee', 'solo'), 'areas' => array('ruin', 'dungeon', 'hold', 'citadel', 'glade', 'lab')),
		'villager'	=> array('power' => 15, 'attacks' =>  1, 'defense' => 15, 'wounds' => 1, 'mindepth' => 0, 'class' => array('human', 'melee', 'swim'), 'areas' => array('cave', 'ruin', 'dungeon', 'hold', 'citadel', 'lab')),
		
		// monsters
		'barghest'	=> array('power' => 90, 'attacks' =>  1, 'defense' => 50, 'wounds' => 3, 'mindepth' => 3, 'class' => array('monster', 'melee'), 'areas' => array('cave', 'dungeon', 'citadel', 'glade', 'lab')),
		'chimera'	=> array('power' => 75, 'attacks' =>  3, 'defense' => 45, 'wounds' => 3, 'mindepth' => 4, 'class' => array('monster', 'mixed', 'fly', 'swim', 'poison', 'solo'), 'areas' => array('cave', 'lab', 'wild', 'citadel', 'glade', 'dungeon'))
		'dragon'	=> array('power' =>250, 'attacks' =>  1, 'defense' =>100, 'wounds' => 8, 'mindepth' => 8, 'class' => array('monster', 'solo', 'mixed', 'fly', 'swim', 'boss'), 'areas' => array('cave', 'lab', 'dungeon', 'citadel'))
		'ogre'		=> array('power' => 80, 'attacks' =>  1, 'defense' => 60, 'wounds' => 4, 'mindepth' => 4, 'class' => array('monster', 'melee'), 'areas' => array('ruin', 'dungeon', 'lab')),
		'slime'		=> array('power' =>  5, 'attacks' =>  1, 'defense' =>  1, 'wounds' => 8, 'mindepth' => 0, 'class' => array('monster', 'slime', 'melee', 'pack'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon', 'glade', 'citadel', 'hold', 'lab', 'firstfort')),
		'troglodyte'	=> array('power' => 80, 'attacks' =>  2, 'defense' => 50, 'wounds' => 3, 'mindepth' => 4, 'class' => array('monster', 'melee'), 'areas' => array('cave', 'wild', 'lab')),
		'wyvern'	=> array('power' =>150, 'attacks' =>  2, 'defense' => 80, 'wounds' => 2, 'mindepth' => 5, 'class' => array('monster', 'fly'), 'areas' => array('wild', 'lab')),
	
		// First One Fortress
		'fortguard'	=> array('power' => 30, 'attacks' =>  1, 'defense' => 30, 'wounds' => 1, 'mindepth' => 0, 'class' => array('human', 'melee'), 'areas' => array('firstfort', 'lab'))
		'fortguarda'	=> array('power' => 45, 'attacks' =>  1, 'defense' => 30, 'wounds' => 1, 'mindepth' => 1, 'class' => array('human', 'ranged'), 'areas' => array('firstfort', 'lab'))
		'fortguardc'	=> array('power' => 75, 'attacks' =>  2, 'defense' => 35, 'wounds' => 4, 'mindepth' => 5, 'class' => array('human', 'melee', 'indiv'), 'areas' => array('firstfort', 'lab'))
		'fortguardh'	=> array('power' => 40, 'attacks' =>  1, 'defense' => 80, 'wounds' => 3, 'mindepth' => 4, 'class' => array('human', 'melee'), 'areas' => array('firstfort', 'lab'))
		'fortguardv'	=> array('power' => 60, 'attacks' =>  2, 'defense' => 60, 'wounds' => 3, 'mindepth' => 3, 'class' => array('human', 'mixed', 'solo'), 'areas' => array('firstfort', 'lab'))
		'fortguardhv'	=> array('power' => 90, 'attacks' =>  2, 'defense' => 80, 'wounds' => 4, 'mindepth' => 6, 'class' => array('human', 'mixed', 'indiv'), 'areas' => array('firstfort', 'lab'))
		'fortcavalry'	=> array('power' => 35, 'attacks' =>  2, 'defense' => 40, 'wounds' => 2, 'mindepth' => 1, 'class' => array('human', 'melee'), 'areas' => array('firstfort', 'lab'))
		'fortcavalryc'	=> array('power' => 90, 'attacks' =>  2, 'defense' => 45, 'wounds' => 4, 'mindepth' => 5, 'class' => array('human', 'mixed', 'mounted'), 'areas' => array('firstfort', 'lab'))
		'fortcavalryh'	=> array('power' => 50, 'attacks' =>  2, 'defense' => 85, 'wounds' => 3, 'mindepth' => 4, 'class' => array('human', 'melee', 'mounted'), 'areas' => array('firstfort', 'lab'))
		'fortcavalrya'	=> array('power' => 30, 'attacks' =>  2, 'defense' => 30, 'wounds' => 2, 'mindepth' => 2, 'class' => array('human', 'ranged', 'mounted,'), 'areas' => array('firstfort', 'lab'))
		'fortlesslord'	=> array('power' => 80, 'attacks' =>  4, 'defense' => 80, 'wounds' => 6, 'mindepth' => 6, 'class' => array('first', 'mixed', 'solo'), 'areas' => array('firstfort')),
		'fortlord'	=> array('power' =>100, 'attacks' =>  6, 'defense' =>100, 'wounds' =>10, 'mindepth' => 8, 'class' => array('first', 'mixed', 'indiv', 'boss'), 'areas' => array('firstfort')),
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
