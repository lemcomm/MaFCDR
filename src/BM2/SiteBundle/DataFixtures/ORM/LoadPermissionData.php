<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\Permission;


class LoadPermissionData extends AbstractFixture implements OrderedFixtureInterface {

	private $permissions = array(
		'settlement' => array(
			'visit'    	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.visit', 'desc'=>'perm.desc.visit'),
			'docks'    	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.docks', 'desc'=>'perm.desc.docks'),
			'describe'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.describe', 'desc'=>'perm.desc.describe'),
			'resupply'	=> array('use_value'=>true, 'reserve'=>false, 'translation'=>'perm.resupply', 'desc'=>'perm.desc.resupply'),
			'mobilize'	=> array('use_value'=>true, 'reserve'=>true, 'translation'=>'perm.mobilize', 'desc'=>'perm.desc.mobilize'),
			'construct'	=> array('use_value'=>false, 'reserve'=>true, 'translation'=>'perm.construct', 'desc'=>'perm.desc.construct'),
			'recruit'	=> array('use_value'=>true, 'reserve'=>false, 'translation'=>'perm.recruit', 'desc'=>'perm.desc.recruit'),
			'trade'    	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.trade', 'desc'=>'perm.desc.trade'),
			'placeinside'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.placeinside', 'desc'=>'perm.desc.placeinside'),
			'placeoutside'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.placeoutside', 'desc'=>'perm.desc.placeoutside'),
			'units'		=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.units', 'desc'=>'perm.desc.units')
		),
		'realm' => array(
			'expel'   	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.expel', 'desc'=>'perm.desc.expel'),
			'describe'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.realmdescribe', 'desc'=>'perm.desc.realmdescribe'),
			'diplomacy'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.diplomacy', 'desc'=>'perm.desc.diplomacy'),
			'laws'		=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.laws', 'desc'=>'perm.desc.laws'),
			'positions'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.positions', 'desc'=>'perm.desc.positions'),
			'wars'		=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.wars', 'desc'=>'perm.desc.wars')
		),
		'place' => array(
			'see'		=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.see', 'desc'=>'perm.desc.see'),
			'manage'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.manage', 'desc'=>'perm.desc.manage'),
			'visit'		=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.placevisit', 'desc'=>'perm.desc.placevisit'),
			'docks'		=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.placedocks', 'desc'=>'perm.desc.placedocks'),
			'describe'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.placedescribe', 'desc'=>'perm.desc.placedescribe'),
			'resupply'	=> array('use_value'=>true, 'reserve'=>false, 'translation'=>'perm.placeresupply', 'desc'=>'perm.desc.placeresupply'),
			'mobilize'	=> array('use_value'=>true, 'reserve'=>true, 'translation'=>'perm.placemobilize', 'desc'=>'perm.desc.placemobilize'),
			'construct'	=> array('use_value'=>false, 'reserve'=>true, 'translation'=>'perm.placeconstruct', 'desc'=>'perm.desc.placeconstruct')
		)
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
		foreach ($this->permissions as $class=>$members) {
			foreach ($members as $name=>$data) {
				$perm = $manager->getRepository('BM2SiteBundle:Permission')->findOneBy(array('name'=>$name, 'class'=>$class));
				if (!$perm) {
					$perm = new Permission();
					$manager->persist($perm);
				}
				$perm->setName($name);
				$perm->setTranslationString($data['translation']);
				$perm->setDescription($data['desc']);
				$perm->setClass($class);
				$perm->setUseValue($data['use_value']);
				$perm->setUseReserve($data['reserve']);
				$manager->persist($perm);
			}
		}
		$manager->flush();
	}
}
