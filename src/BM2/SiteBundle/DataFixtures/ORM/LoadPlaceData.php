<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\PlaceType;

class LoadPlaceData extends AbstractFixture implements OrderedFixtureInterface {

	private $placetypes = array(
		'capital',
		'tavern',
		'home',
		'fort',
		'portal' 	=> array('requires' => 'magic'),
		'passage'	=> array('requires' => 'warren')
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
			$type->setRequires($data['requires']);
			$manager->persist($type);
		}
		$manager->flush();
	}
}
