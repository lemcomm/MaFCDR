<?php

namespace Calitarus\MessagingBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use Calitarus\MessagingBundle\Entity\Flag;

class LoadDefaultFlags extends AbstractFixture implements OrderedFixtureInterface {

	private $flags = array("important", "act", "remind", "keep");

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
		foreach ($this->flags as $name) {
			$flag = new Flag;
			$flag->setName($name);
			$manager->persist($flag);
		}
		$manager->flush();
	}
}
