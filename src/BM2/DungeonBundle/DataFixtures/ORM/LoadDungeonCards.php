<?php

namespace BM2\DungeonBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\DungeonBundle\Entity\DungeonCardType;


class LoadDungeonCards extends AbstractFixture implements OrderedFixtureInterface {

	private $cards = array(
		// basic cards
		'basic.leave'		=> array('rarity' =>    0, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>false, 'dungeoneer'=>false)),
		'basic.rest'		=> array('rarity' =>  500, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>false, 'dungeoneer'=>false)),
		'basic.scout'		=> array('rarity' =>  750, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>false, 'dungeoneer'=>false)),
		'basic.plunder'		=> array('rarity' =>  800, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>true, 'dungeoneer'=>false)),
		'basic.fight'		=> array('rarity' => 1000, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),


		// common / usual cards
		'action.wait'		=> array('rarity' => 1000, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>false, 'dungeoneer'=>false)),
		'action.untrap1'	=> array('rarity' =>  600, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>true, 'dungeoneer'=>false)),
		'fight.double'		=> array('rarity' =>  500, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'fight.strong'		=> array('rarity' =>  500, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'fight.hit'		=> array('rarity' =>  500, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'fight.weak'		=> array('rarity' =>  650, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'fight.slime'		=> array('rarity' =>  550, 'monsterclass'=> 'slime', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false))


		// uncommon cards
		'fight.stealth'		=> array('rarity' =>  250, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'fight.sweep'		=> array('rarity' =>  400, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'fight.swing'		=> array('rarity' =>  350, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'fight.bomb1'		=> array('rarity' =>  250, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'scout.double'		=> array('rarity' =>  300, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>false, 'dungeoneer'=>false)),
		'action.heal1'		=> array('rarity' =>  400, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>false, 'dungeoneer'=>true)),
		'action.heal2'		=> array('rarity' =>  200, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>false, 'dungeoneer'=>true)),
		'action.distracta'	=> array('rarity' =>  400, 'monsterclass'=> 'animal', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'action.rest2'		=> array('rarity' =>  200, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>false, 'dungeoneer'=>false)),
		'action.untrap2'	=> array('rarity' =>  300, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>true, 'dungeoneer'=>false)),


		// rare cards
		'action.heal3'		=> array('rarity' =>  100, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>false, 'dungeoneer'=>true)),
		'action.rest3'		=> array('rarity' =>   75, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>false, 'dungeoneer'=>false)),
		'action.untrap3'	=> array('rarity' =>  100, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>true, 'dungeoneer'=>false)),
		'scout.tripple'		=> array('rarity' =>   50, 'monsterclass'=> '', 'target'=>array('monster'=>false, 'treasure'=>false, 'dungeoneer'=>false)),
		'fight.kill'		=> array('rarity' =>  100, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'fight.sure'		=> array('rarity' =>  100, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'fight.tripple'		=> array('rarity' =>  100, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),
		'fight.bomb2'		=> array('rarity' =>   75, 'monsterclass'=> '', 'target'=>array('monster'=>true, 'treasure'=>false, 'dungeoneer'=>false)),

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
		foreach ($this->cards as $name=>$data) {
			$type = new DungeonCardType;
			$type->setName($name);
			$type->setRarity($data['rarity']);
			$type->setMonsterClass($data['monsterclass']);
			$type->setTargetMonster($data['target']['monster']);
			$type->setTargetTreasure($data['target']['treasure']);
			$type->setTargetDungeoneer($data['target']['dungeoneer']);
			$manager->persist($type);
		}

		$manager->flush();
	}
}
