<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\BuildingType;
use BM2\SiteBundle\Entity\EntourageType;
use BM2\SiteBundle\Entity\EquipmentType;


class BuildingTypeEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\BuildingType');
	}

	public function testOthers() {
		$b = new BuildingType;
		$this->assertFalse($b->canFocus());

		$scout = new EntourageType;
		$b->addProvidesEntourage($scout);
		$this->assertTrue($b->canFocus());

		$axe = new EquipmentType;
		$b->addProvidesEquipment($axe);
		$this->assertTrue($b->canFocus());
	}


}
