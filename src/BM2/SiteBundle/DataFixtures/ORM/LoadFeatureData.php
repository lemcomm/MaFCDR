<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\FeatureType;


class LoadFeatureData extends AbstractFixture implements OrderedFixtureInterface {

	private $features = array(
		'settlement'    => array('hidden'=>true,    'work'=>0,     'icon'=>null,                           'icon_uc'=>null),
		'bridge'        => array('hidden'=>false,   'work'=>15000, 'icon'=>'rpg_map/bridge_stone1.svg',    'icon_uc'=>'rpg_map/bridge_stone1_outline.svg'),
		'tower'         => array('hidden'=>false,   'work'=>9000,  'icon'=>'rpg_map/watch_tower.svg',      'icon_uc'=>'rpg_map/watch_tower_outline.svg'),
		'borderpost'    => array('hidden'=>false,   'work'=>100,   'icon'=>'rpg_map/sign_post.svg',        'icon_uc'=>'rpg_map/sign_post_outline.svg'),
		'signpost'      => array('hidden'=>false,   'work'=>60,    'icon'=>'rpg_map/sign_crossroad.svg',   'icon_uc'=>'rpg_map/sign_crossroad_outline.svg'),
		'docks'         => array('hidden'=>false,   'work'=>10000, 'icon'=>'rpg_map/docks.svg',            'icon_uc'=>'rpg_map/docks_outline.svg'),
		'fort'		=> array('hidden'=>false,   'work'=>20000, 'icon'=>'rpg_map/fortress.svg',         'icon_uc'=>'rpg_map/fortress_outline.svg'),
		'tournament'	=> array('hidden'=>false,   'work'=>200,   'icon'=>'rpg_map/fort.svg',             'icon_uc'=>'rpg_map/fort_outline.svg'),
		'parade'	=> array('hidden'=>false,   'work'=>10000, 'icon'=>'rpg_map/arch.svg',             'icon_uc'=>'rpg_map/arch_outline.svg'),
		'graveyard'	=> array('hidden'=>false,   'work'=>2000,  'icon'=>'rpg_map/graveyard.svg',        'icon_uc'=>'rpg_map/graveyard_outline.svg'),
		'temple'	=> array('hidden'=>false,   'work'=>20000, 'icon'=>'rpg_map/cathedral.svg',        'icon_uc'=>'rpg_map/cathedral_outline.svg')
	);

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 1;
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		foreach ($this->features as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:SettlementType')->findOneByName($name)
			if (!$type) {
				$type = new FeatureType();
				$manager->persist($type);
			}
			$type->setName($name);
			$type->setHidden($data['hidden']);
			$type->setBuildHours($data['work']);
			$type->setIcon($data['icon'])->setIconUnderConstruction($data['icon_uc']);
			$manager->persist($type);
			$this->addReference('featuretype: '.strtolower($name), $type);            
		}
		$manager->flush();
	}
}
