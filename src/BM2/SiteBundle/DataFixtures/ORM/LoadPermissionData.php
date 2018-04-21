<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\Permission;


class LoadPermissionData extends AbstractFixture implements OrderedFixtureInterface {

	private $permissions = array(
		'settlement' => array(
			'visit'    	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.visit'),
			'docks'    	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.docks'),
			'describe'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.describe'),
			'resupply'	=> array('use_value'=>true, 'reserve'=>false, 'translation'=>'perm.resupply'),
			'mobilize'	=> array('use_value'=>true, 'reserve'=>true, 'translation'=>'perm.mobilize'),
			'construct'	=> array('use_value'=>false, 'reserve'=>true, 'translation'=>'perm.construct'),
			'recruit'	=> array('use_value'=>true, 'reserve'=>false, 'translation'=>'perm.recruit'),
			'trade'    	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.trade'),
			'placeinside'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.placeinside'),
			'placeoutside'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.placeoutside')
		),
		'realm' => array(
			'expel'   	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.expel'),
			'describe'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.realmdescribe'),
			'diplomacy'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.diplomacy'),
			'laws'		=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.laws'),
			'positions'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.positions'),
			'wars'		=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.wars')
		),
		'place' => array(
			'see'		=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.see'),
			'visit'		=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.placevisit'),
			'docks'		=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.placedocks'),
			'describe'	=> array('use_value'=>false, 'reserve'=>false, 'translation'=>'perm.placedescribe'),
			'resupply'	=> array('use_value'=>true, 'reserve'=>false, 'translation'=>'perm.placeresupply'),
			'mobilize'	=> array('use_value'=>true, 'reserve'=>true, 'translation'=>'perm.placemobilize'),
			'construct'	=> array('use_value'=>false, 'reserve'=>true, 'translation'=>'perm.placeconstruct')
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
				$perm->setClass($class);
				$perm->setUseValue($data['use_value']);
				$perm->setUseReserve($data['reserve']);
				$manager->persist($perm);
			}
		}
		$manager->flush();
	}
}
