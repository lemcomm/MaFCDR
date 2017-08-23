<?php

namespace Calitarus\MessagingBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use Calitarus\MessagingBundle\Entity\Right;

class LoadRights extends AbstractFixture implements OrderedFixtureInterface {

	private $rights = array("owner", "participants_add", "participants_remove", "participants_edit", "write");

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
		foreach ($this->rights as $name) {
			$right = new Right;
			$right->setName($name);
			$manager->persist($right);
		}
		$manager->flush();
	}
}
