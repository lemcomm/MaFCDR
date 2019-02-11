<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\PlaceType;

class LoadSiegeEquipmentData extends AbstractFixture implements OrderedFixtureInterface {

	private $placetypes = array(
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4),
		'catapult'	=> array('hours' => 40, 'ranged' => true, 'minimum' => 1, 'maximum' => 4)
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
		foreach ($this->placetypes as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:PlaceType')->findOneByName($name);
			if (!$type) {
				$type = new PlaceType();
				$manager->persist($type);
			}
			$type->setName($name);
			if ($data['requires']) {
				$type->setRequires($data['requires']);
			}
			$type->setVisible($data['visible']);
			$manager->persist($type);
		}
		$manager->flush();
	}
}
