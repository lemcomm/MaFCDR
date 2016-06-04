<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\EquipmentType;


class LoadEquipmentData extends AbstractFixture implements OrderedFixtureInterface {

	private $equipment = array(
		'axe'               => array('type' => 'weapon',    'ranged' =>  0, 'melee' =>  10, 'defense' =>   0, 'train' => 20, 'resupply' => 20,	'provider' => 'Blacksmith',  'trainer' => 'Training Ground',		'icon' => 'items/streitaxt2.png'),
		'spear'             => array('type' => 'weapon',    'ranged' =>  0, 'melee' =>  20, 'defense' =>   0, 'train' => 30, 'resupply' => 30,	'provider' => 'Blacksmith',  'trainer' => 'Training Ground',		'icon' => 'items/spear2.png'),
		'pike'              => array('type' => 'weapon',    'ranged' =>  0, 'melee' =>  40, 'defense' =>   0, 'train' => 50, 'resupply' => 60,	'provider' => 'Weaponsmith',  'trainer' => 'Guardhouse',				'icon' => 'items/hellebarde2.png'),
		'mace'              => array('type' => 'weapon',    'ranged' =>  0, 'melee' =>  65, 'defense' =>   0, 'train' => 60, 'resupply' =>100,	'provider' => 'Weaponsmith',  'trainer' => 'Barracks',				'icon' => 'items/hellebarde2.png'),
		'sword'             => array('type' => 'weapon',    'ranged' =>  0, 'melee' =>  75, 'defense' =>   0, 'train' => 80, 'resupply' =>250,	'provider' => 'Bladesmith', 'trainer' => 'Barracks',					'icon' => 'items/schwert2.png'),
		'broadsword'        => array('type' => 'weapon',    'ranged' =>  0, 'melee' =>  90, 'defense' =>   0, 'train' => 90, 'resupply' =>350,	'provider' => 'Bladesmith', 'trainer' => 'Garrison',					'icon' => 'items/claymore2.png'),

		'shortbow'          => array('type' => 'weapon',    'ranged' => 40, 'melee' =>   0, 'defense' =>   0, 'train' => 50, 'resupply' => 50,	'provider' => 'Bowyer',      'trainer' => 'Archery Range',			'icon' => 'items/shortbow2.png'),
		'crossbow'          => array('type' => 'weapon',    'ranged' => 60, 'melee' =>   0, 'defense' =>   0, 'train' => 60, 'resupply' => 75,	'provider' => 'Bowyer',      'trainer' => 'Archery Range',			'icon' => 'items/armbrust2.png'),
		'longbow'           => array('type' => 'weapon',    'ranged' => 80, 'melee' =>   0, 'defense' =>   0, 'train' =>100, 'resupply' => 80,	'provider' => 'Bowyer',      'trainer' => 'Archery School',			'icon' => 'items/longbow2.png'),


		'cloth armour'      => array('type' => 'armour',    'ranged' =>  0, 'melee' =>   0, 'defense' =>  10, 'train' => 10,	'resupply' => 30,	'provider' => 'Tailor',				'trainer' => 'Training Ground',	'icon' => 'items/clotharmour2.png'),
		'leather armour'    => array('type' => 'armour',    'ranged' =>  0, 'melee' =>   0, 'defense' =>  20, 'train' => 20, 'resupply' => 50,	'provider' => 'Leather Tanner',	'trainer' => 'Guardhouse',			'icon' => 'items/leatherarmour2.png'),
		'scale armour'      => array('type' => 'armour',    'ranged' =>  0, 'melee' =>   0, 'defense' =>  40, 'train' => 30, 'resupply' =>100,	'provider' => 'Armourer',			'trainer' => 'Barracks',			'icon' => 'items/schuppenpanzer2.png'),
		'chainmail'         => array('type' => 'armour',    'ranged' =>  0, 'melee' =>   0, 'defense' =>  60, 'train' => 50, 'resupply' =>300,	'provider' => 'Armourer',			'trainer' => 'Garrison',			'icon' => 'items/kettenpanzer2.png'),
		'plate armour'      => array('type' => 'armour',    'ranged' =>  0, 'melee' =>   0, 'defense' =>  80, 'train' => 80, 'resupply' =>500,	'provider' => 'Heavy Armourer',	'trainer' => 'Wood Castle',		'icon' => 'items/plattenpanzer2.png'),

		'horse'             => array('type' => 'equipment', 'ranged' =>  0, 'melee' =>  20, 'defense' =>  20, 'train' => 60, 'resupply' =>300,	'provider' => 'Stables',     'trainer' => 'Barracks',					'icon' => 'items/packpferd2.png'),
		'war horse'         => array('type' => 'equipment', 'ranged' =>  0, 'melee' =>  25, 'defense' =>  30, 'train' =>100, 'resupply' =>800,	'provider' => 'Royal Mews',  'trainer' => 'Wood Castle',				'icon' => 'items/warhorse2.png'),
		'shield'            => array('type' => 'equipment', 'ranged' =>  0, 'melee' =>   0, 'defense' =>  25, 'train' => 40, 'resupply' => 40,	'provider' => 'Carpenter',   'trainer' => 'Guardhouse',				'icon' => 'items/shield2.png'),
		'javelin'           => array('type' => 'equipment', 'ranged' => 65, 'melee' =>  10, 'defense' =>   0, 'train' => 40, 'resupply' => 35,	'provider' => 'Weaponsmith', 'trainer' => 'Guardhouse',				'icon' => 'items/javelin2.png'),
		'short sword'       => array('type' => 'equipment', 'ranged' =>  0, 'melee' =>  10, 'defense' =>   5, 'train' => 40, 'resupply' => 50,	'provider' => 'Bladesmith', 'trainer' => 'Barracks',					'icon' => 'items/kurzschwert2.png'),
	);

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 10; // requires LoadBuildingData.php
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		foreach ($this->equipment as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:EquipmentType')->findOneByName($name);
			if (!$type) {
				$type = new EquipmentType();
				$manager->persist($type);
			}
			$type->setName($name);
			$type->setType($data['type']);
			if ($data['icon']) {
				$type->setIcon($data['icon']);
			}
			$type->setRanged($data['ranged'])->setMelee($data['melee'])->setDefense($data['defense']);
			$type->setTrainingRequired($data['train']);
			$type->setResupplyCost($data['resupply']);
			if ($data['provider']) {
				$provider = $this->getReference('buildingtype: '.strtolower($data['provider']));
				if ($provider) {
					$type->setProvider($provider);
				} else {
					echo "can't find ".$data['provider']." needed by $name.\n";
				}
			}
			if ($data['trainer']) {
				$trainer = $this->getReference('buildingtype: '.strtolower($data['trainer']));
				if ($trainer) {
					$type->setTrainer($trainer);
				} else {
					echo "can't find ".$data['trainer']." needed by $name.\n";
				}
			}
			$this->addReference('equipmenttype: '.strtolower($name), $type);            
		}
		$manager->flush();
	}
}
