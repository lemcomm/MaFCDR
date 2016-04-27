<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\Entourage;


class EntourageEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\Entourage');
	}

	public function testOthers() {
		$e = new Entourage;

		$e->setTraining(10);
		$this->assertEquals(10, $e->getTraining());
	}
}
