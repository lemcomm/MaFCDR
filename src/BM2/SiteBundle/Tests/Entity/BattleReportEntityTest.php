<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\BattleReport;

class BattleReportEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\BattleReport');
	}

	public function testOthers() {
		$br = new BattleReport;
		$this->assertEquals("battle", $br->getName());
	}

}
