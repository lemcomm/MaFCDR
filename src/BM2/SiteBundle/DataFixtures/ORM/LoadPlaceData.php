<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\PlaceType;

class LoadPlaceData extends AbstractFixture implements OrderedFixtureInterface {

	private $placetypes = array(
		'academy'	=> array('requires' => 'academy', 'visible' => false),
		'arena'		=> array('requires' => 'lord', 'visible' => true),
		'capital'	=> array('requires' => 'ruler', 'visible' => true),
		'castle'	=> array('requires' => 'castle', 'visible' => true),
		'cave'		=> array('visible' => false),
		'fort'		=> array('requires' => 'fort', 'visible' => true),
		'home'		=> array('requires' => 'dynasty head', 'visible' => false),
		'inn'		=> array('visible' => true),
		'library'	=> array('visible' => false),
		'monument'	=> array('visible' => true),
		'plaza'		=> array('requires' => 'lord', 'visible' => true),
		'portal' 	=> array('requires' => 'magic', 'visible' => false),
		'passage'	=> array('requires' => 'warren', 'visible' => false),
		'tavern'	=> array('visible' => false)
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
			$type->setVisible(data['visible']);
			$manager->persist($type);
		}
		$manager->flush();
	}
}
