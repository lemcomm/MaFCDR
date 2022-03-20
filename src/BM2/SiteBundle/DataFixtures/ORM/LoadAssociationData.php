<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\AssociationType;

class LoadAssociationData extends AbstractFixture implements OrderedFixtureInterface {

	private $assoctypes = array(
		'academy',
		'association',
		'brotherhood',
		'company',
		'corps',
		'cult',
		'faith',
		'guild',
		'order',
		'religion',
		'sect',
		'society',
		'temple',
	);

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 1; // or anywhere really
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		# Load association types.
		foreach ($this->assoctypes as $name) {
			$type = $manager->getRepository('BM2SiteBundle:AssociationType')->findOneByName($name);
			if (!$type) {
				$type = new AssociationType();
				$manager->persist($type);
				$type->setName($name);
			}
		}
		$manager->flush();
	}
}
