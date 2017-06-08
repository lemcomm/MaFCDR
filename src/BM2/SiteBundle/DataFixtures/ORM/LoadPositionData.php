<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\PositionType;

class LoadPositionData extends AbstractFixture implements OrderedFixtureInterface {

	private $positiontypes = array(
		'advisory'		=> array('hidden' => false),
		'executive'		=> array('hidden' => false),
		'family'		=> array('hidden' => false),
		'foreign affairs'	=> array('hidden' => false),
		'history'		=> array('hidden' => false),
		'intelligence'		=> array('hidden' => false),
		'interior'		=> array('hidden' => false),
		'judicial'		=> array('hidden' => false),
		'legislative'		=> array('hidden' => false),
		'military'		=> array('hidden' => false),
		'revenue'		=> array('hidden' => false),
		'other'			=> array('hidden' => true)
	);
	
	/**
	 * {@inheritDoc}
	 */	 
	public function getOrder() {
		return 1; // or anywhere, really
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		foreach ($this->positiontypes as $name=>$data) {
			$type = new PositionType;
			$type->setName($name);
			$type->setHidden($data['hidden']);
			$manager->persist($type);
		}
		$manager->flush();
	}
}
