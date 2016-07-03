<?php

namespace BM2\DungeonBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\DungeonBundle\Entity\DungeonMonsterType;


class LoadDungeonMonsters extends AbstractFixture implements OrderedFixtureInterface {

	private $monsters = array(
		// animals
		'arachnid'	=> array('power' => 25, 'attacks' =>  1, 'defense' => 30, 'wounds' => 3, 'mindepth' => 2, 'class' => array('animal', 'melee'), 'areas' => array('cave', 'ruin', 'dungeon', 'citadel', 'lab', 'mausoleum', 'shipgrave')),
		'bat'		=> array('power' => 40, 'attacks' =>  1, 'defense' => 40, 'wounds' => 1, 'mindepth' => 2, 'class' => array('animal', 'fly', 'melee'), 'areas' => array('cave', 'ruin', 'citadel', 'lab', 'mausoleum', 'shipgrave')),
		'beetle'	=> array('power' => 10, 'attacks' =>  3, 'defense' => 10, 'wounds' => 4, 'mindepth' => 1, 'class' => array('animal', 'swarm', 'poison'), 'areas' => array('cave', 'wild', 'lab', 'mausoleum')),
		'bear'		=> array('power' => 50, 'attacks' =>  1, 'defense' => 25, 'wounds' => 2, 'mindepth' => 0, 'class' => array('animal', 'melee', 'solo'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon', 'lab', 'glade')),
		'cat'		=> array('power' => 40, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'solo', 'stealth'), 'areas' => array('wild', 'ruin', 'glade', 'lab', 'shipgrave')),
		'eagle'		=> array('power' => 40, 'attacks' =>  1, 'defense' => 25, 'wounds' => 2, 'mindepth' => 1, 'class' => array('animal', 'fly', 'melee'), 'areas' => array('wild', 'ruin', 'glade', 'lab')),
		'mongrel'	=> array('power' => 20, 'attacks' =>  1, 'defense' => 10, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'solo', 'melee', 'pack'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon', 'glade', 'citadel', 'hold', 'lab', 'mausoleum', 'flooded', 'shipgrave'))
		'rat'		=> array('power' =>  5, 'attacks' =>  1, 'defense' =>  5, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'swim', 'melee'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon', 'glade', 'citadel', 'hold', 'lab', 'roguefort', 'mausoleum', 'flooded', 'shipgrave')),
		'snake'		=> array('power' => 30, 'attacks' =>  1, 'defense' => 10, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'melee', 'poison'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon', 'lab', 'glade', 'mausoleum', 'flooded')),
		'wolf'		=> array('power' => 25, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'melee', 'pack'), 'areas' => array('cave', 'wild', 'ruin',, 'lab', 'flooded')),
		'wolfb'		=> array('power' => 60, 'attacks' =>  1, 'defense' => 40, 'wounds' => 2, 'mindepth' => 1, 'class' => array('animal', 'melee', 'pack'), 'areas' => array('cave', 'wild', 'dungeon', 'citadel', 'lab', 'flooded')),

		// beasts
		'dog'		=> array('power' => 45, 'attacks' =>  1, 'defense' => 15, 'wounds' => 1, 'mindepth' => 0, 'class' => array('animal', 'solo', 'melee'), 'areas' => array('cave', 'ruin', 'dungeon', 'citadel', 'hold', 'roguefort', 'lab'))
		'falcon'	=> array('power' => 25, 'attacks' =>  1, 'defense' => 10, 'wounds' => 2, 'mindepth' => 1, 'class' => array('animal', 'fly', 'melee'), 'areas' => array('wild', 'ruin', 'glade', 'lab', 'citadel', 'roguefort', 'flooded', 'shipgrave')),
		'hawk'		=> array('power' => 40, 'attacks' =>  1, 'defense' => 25, 'wounds' => 2, 'mindepth' => 2, 'class' => array('animal', 'fly', 'melee'), 'areas' => array('wild', 'ruin', 'glade', 'lab', 'flooded', 'shipgrave')),
		
		// humans
		'bandit'	=> array('power' => 60, 'attacks' =>  1, 'defense' => 40, 'wounds' => 1, 'mindepth' => 1, 'class' => array('human', 'melee'), 'areas' => array('cave', 'ruin', 'dungeon', 'hold', 'citadel', 'lab', 'mausoleum', 'flooded', 'shipgrave')),
		'bandita'	=> array('power' => 50, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 1, 'class' => array('human', 'mixed'), 'areas' => array('cave', 'ruin', 'dungeon', 'hold', 'lab', 'mausoleum', 'flooded', 'shipgrave')),
		'banditb'	=> array('power' => 75, 'attacks' =>  1, 'defense' => 60, 'wounds' => 2, 'mindepth' => 2, 'class' => array('human', 'melee', 'solo'), 'areas' => array('cave', 'ruin', 'dungeon', 'hold', 'lab', 'mausoleum', 'flooded', 'shipgrave')),
		'banditg'	=> array('power' => 60, 'attacks' =>  1, 'defense' => 40, 'wounds' => 1, 'mindepth' => 1, 'class' => array('human', 'mixed', 'handler'), 'areas' => array('cave', 'ruin', 'dungeon', 'hold', 'citadel', 'lab', 'mausoleum', 'flooded', 'shipgrave')),
		'crazy'		=> array('power' => 20, 'attacks' =>  1, 'defense' => 20, 'wounds' => 1, 'mindepth' => 0, 'class' => array('human', 'melee'), 'areas' => array('cave', 'ruin', 'dungeon', 'lab', 'wild', 'glade', 'mausoleum', 'flooded', 'shipgrave')),
		'monhunter'	=> array('power' => 90, 'attacks' =>  1, 'defense' => 45, 'wounds' => 2, 'mindepth' => 4, 'class' => array('human', 'ranged', 'group', 'swim', 'poison'), 'areas' => array('cave', 'ruin', 'dungeon', 'lab', 'wild', 'glade', 'citadel', 'mausoleum', 'flooded', 'shipgrave')),
		'npcdungeoneer'	=> array('power' => 65, 'attacks' =>  1, 'defense' => 80, 'wounds' => 3, 'mindepth' => 4, 'class' => array('human', 'melee', 'group', 'swim'), 'areas' => array('cave', 'ruin', 'dungeon', 'lab', 'wild', 'glade', 'citadel', 'mausoleum', 'flooded', 'shipgrave')),
		'shaman'	=> array('power' => 30, 'attacks' =>  3, 'defense' => 40, 'wounds' => 2, 'mindepth' => 3, 'class' => array('human', 'mixed', 'swim', 'solo'), 'areas' => array('cave', 'dungeon', 'ruin', 'glade', 'lab', 'wild', 'citadel', 'hold', 'mausoleum', 'flooded', 'shipgrave'))
		'lost'		=> array('power' => 80, 'attacks' =>  2, 'defense' => 80, 'wounds' => 3, 'mindepth' => 4, 'class' => array('human', 'melee', 'solo'), 'areas' => array('ruin', 'dungeon', 'hold', 'citadel', 'glade', 'lab', 'mausoleum', 'flooded', 'shipgrave')),
		'villager'	=> array('power' => 15, 'attacks' =>  1, 'defense' => 15, 'wounds' => 1, 'mindepth' => 0, 'class' => array('human', 'melee', 'swim'), 'areas' => array('cave', 'ruin', 'dungeon', 'hold', 'citadel', 'lab', 'mausoleum', 'flooded', 'shipgrave')),
		
		// monsters
		'barghest'	=> array('power' => 90, 'attacks' =>  1, 'defense' => 50, 'wounds' => 3, 'mindepth' => 3, 'class' => array('monster', 'melee'), 'areas' => array('cave', 'dungeon', 'citadel', 'glade', 'lab', 'mausoleum', 'shipgrave')),
		'chimera'	=> array('power' => 75, 'attacks' =>  3, 'defense' => 45, 'wounds' => 3, 'mindepth' => 4, 'class' => array('monster', 'mixed', 'fly', 'swim', 'poison', 'solo'), 'areas' => array('cave', 'lab', 'wild', 'citadel', 'glade', 'dungeon', 'mausoleum', 'shipgrave'))
		'dragon'	=> array('power' =>250, 'attacks' =>  3, 'defense' =>150, 'wounds' => 6, 'mindepth' => 8, 'class' => array('monster', 'solo', 'mixed', 'fly', 'swim', 'boss'), 'areas' => array('cave', 'lab', 'dungeon', 'citadel'))
		'ogre'		=> array('power' => 80, 'attacks' =>  1, 'defense' => 60, 'wounds' => 4, 'mindepth' => 4, 'class' => array('monster', 'melee'), 'areas' => array('ruin', 'dungeon', 'lab', 'shipgrave')),
		'slime'		=> array('power' =>  5, 'attacks' =>  1, 'defense' =>  1, 'wounds' => 6, 'mindepth' => 0, 'class' => array('monster', 'slime', 'melee', 'pack'), 'areas' => array('cave', 'wild', 'ruin', 'dungeon', 'glade', 'citadel', 'hold', 'lab', 'roguefort', 'mausoleum', 'flooded', 'shipgrave')),
		'troglodyte'	=> array('power' => 80, 'attacks' =>  2, 'defense' => 50, 'wounds' => 3, 'mindepth' => 4, 'class' => array('monster', 'melee'), 'areas' => array('cave', 'wild', 'lab', 'flooded', 'shipgrave')),
		'wyvern'	=> array('power' =>150, 'attacks' =>  2, 'defense' => 80, 'wounds' => 2, 'mindepth' => 5, 'class' => array('monster', 'fly'), 'areas' => array('wild', 'lab', 'flooded', 'shipgrave')),
	
		// Rogue Fortress
		'fortguard'	=> array('power' => 30, 'attacks' =>  1, 'defense' => 30, 'wounds' => 1, 'mindepth' => 0, 'class' => array('human', 'melee'), 'areas' => array('roguefort', 'lab'))
		'fortguarda'	=> array('power' => 45, 'attacks' =>  1, 'defense' => 30, 'wounds' => 1, 'mindepth' => 1, 'class' => array('human', 'ranged'), 'areas' => array('roguefort', 'lab'))
		'fortguardc'	=> array('power' => 75, 'attacks' =>  2, 'defense' => 35, 'wounds' => 4, 'mindepth' => 5, 'class' => array('human', 'melee', 'indiv'), 'areas' => array('roguefort', 'lab'))
		'fortguardh'	=> array('power' => 40, 'attacks' =>  1, 'defense' => 80, 'wounds' => 3, 'mindepth' => 4, 'class' => array('human', 'melee'), 'areas' => array('roguefort', 'lab'))
		'fortguardv'	=> array('power' => 60, 'attacks' =>  2, 'defense' => 60, 'wounds' => 3, 'mindepth' => 3, 'class' => array('human', 'mixed', 'solo'), 'areas' => array('roguefort', 'lab'))
		'fortguardhv'	=> array('power' => 90, 'attacks' =>  2, 'defense' => 80, 'wounds' => 4, 'mindepth' => 6, 'class' => array('human', 'mixed', 'indiv'), 'areas' => array('roguefort', 'lab'))
		'fortcavalry'	=> array('power' => 35, 'attacks' =>  2, 'defense' => 40, 'wounds' => 2, 'mindepth' => 1, 'class' => array('human', 'melee'), 'areas' => array('roguefort', 'lab'))
		'fortcavalryc'	=> array('power' => 90, 'attacks' =>  2, 'defense' => 45, 'wounds' => 4, 'mindepth' => 5, 'class' => array('human', 'mixed', 'mounted'), 'areas' => array('roguefort', 'lab'))
		'fortcavalryh'	=> array('power' => 50, 'attacks' =>  2, 'defense' => 85, 'wounds' => 3, 'mindepth' => 4, 'class' => array('human', 'melee', 'mounted'), 'areas' => array('roguefort', 'lab'))
		'fortcavalrya'	=> array('power' => 30, 'attacks' =>  2, 'defense' => 30, 'wounds' => 2, 'mindepth' => 2, 'class' => array('human', 'ranged', 'mounted,'), 'areas' => array('roguefort', 'lab'))
		'fortlesslord'	=> array('power' => 80, 'attacks' =>  3, 'defense' => 80, 'wounds' => 6, 'mindepth' => 6, 'class' => array('human', 'mixed', 'solo'), 'areas' => array('roguefort')),
		'fortlord'	=> array('power' => 95, 'attacks' =>  4, 'defense' => 95, 'wounds' => 9, 'mindepth' => 8, 'class' => array('human', 'mixed', 'indiv', 'boss'), 'areas' => array('roguefort')),
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
