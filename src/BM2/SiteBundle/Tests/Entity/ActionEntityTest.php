<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\Action;


class ActionEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\Action');
	}

	public function testOthers() {
		$act = new Action;
		$act->setType('test');
		$this->assertEquals("action  - test", (string)$act);
	}


}
