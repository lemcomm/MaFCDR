<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\FeatureType;

class FeatureTypeEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\FeatureType');
	}

	public function testExtras() {
		$type = new FeatureType;
		$type->setName('test');
		$this->assertEquals('feature.test', $type->getNametrans());
	}

}

