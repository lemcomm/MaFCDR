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
		'cult',
		'guild',
		'order',
		'sect',
		'society',
	);

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 2; // must be after place data
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		# Load association types.
		foreach ($this->$assoctypes as $name) {
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