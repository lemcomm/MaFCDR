<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\Culture;


class CultureEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\Culture');
	}

	public function testOthers() {
		$culture = new Culture;
		$culture->setName('atlantian');
		$this->assertEquals("culture.atlantian", (string)$culture);
	}

}


