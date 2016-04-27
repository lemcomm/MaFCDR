<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\EntourageType;


class LoadEntourageData extends AbstractFixture implements OrderedFixtureInterface {

	private $entourage = array(
		'follower'          => array('train' => 50, 'provider' =>'Inn'),
		'herald'            => array('train' =>100, 'provider' =>'School'),
		'merchant'          => array('train' =>120, 'provider' =>'Market'),
		'priest'            => array('train' =>150, 'provider' =>'Temple'),
		'prospector'        => array('train' =>200, 'provider' =>'Library'),
		'scholar'           => array('train' =>300, 'provider' =>'University'),
		'scout'             => array('train' => 65, 'provider' =>'Inn'),
		'spy'               => array('train' =>500, 'provider' =>'Academy'),
		'translator'        => array('train' =>125, 'provider' =>'School'),
	);


	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 15; // requires LoadBuildingData.php
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		foreach ($this->entourage as $name=>$data) {
			$type = new EntourageType();
			$type->setName($name);
			$type->setTraining($data['train']);
			if ($data['provider']) {
				$provider = $this->getReference('buildingtype: '.strtolower($data['provider']));
				if ($provider) {
					$type->setProvider($provider);
				} else {
					echo "can't find ".$data['provider']." needed by $name.\n";
				}
			}
			$manager->persist($type);
		}
		$manager->flush();
	}
}
