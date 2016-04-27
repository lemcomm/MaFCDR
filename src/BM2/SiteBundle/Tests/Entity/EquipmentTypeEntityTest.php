<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\EquipmentType;


class EquipmentTypeEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\EquipmentType');
	}

	public function testExtras() {
		$type = new EquipmentType;
		$type->setName('test');
		$this->assertEquals('item.test', $type->getNametrans());
	}
}

