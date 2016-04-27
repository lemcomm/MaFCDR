<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\Permission;


class LoadPermissionData extends AbstractFixture implements OrderedFixtureInterface {

	private $permissions = array(
		'settlement' => array(
			'visit'    	=> array('use_value'=>false, 'use_reserve'=>false),
			'docks'    	=> array('use_value'=>false, 'use_reserve'=>false),
			'resupply'  => array('use_value'=>true, 'use_reserve'=>false),
			'mobilize'  => array('use_value'=>true, 'use_reserve'=>true),
			'construct' => array('use_value'=>false, 'use_reserve'=>true),
			'recruit'   => array('use_value'=>true, 'use_reserve'=>false),
			'trade'    	=> array('use_value'=>false, 'use_reserve'=>false),
		),
		'realm' => array(
			'expel'   	=> array('use_value'=>false, 'use_reserve'=>false),
			'diplomacy'	=> array('use_value'=>false, 'use_reserve'=>false),
			'laws'		=> array('use_value'=>false, 'use_reserve'=>false),
			'positions'	=> array('use_value'=>false, 'use_reserve'=>false),
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
				$perm = new Permission();
				$perm->setName($name);
				$perm->setTranslationString('perm.'.$name);
				$perm->setClass($class);
				$perm->setUseValue($data['use_value']);
				$perm->setUseReserve($data['use_reserve']);
				$manager->persist($perm);
			}
		}
		$manager->flush();
	}
}
