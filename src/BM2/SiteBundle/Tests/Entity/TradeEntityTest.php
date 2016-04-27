<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\Trade;
use BM2\SiteBundle\Entity\Settlement;

class TradeEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\Trade');
	}

	public function testOthers() {
		$trade = new Trade;
		$a = new Settlement;
		$b = new Settlement;
		$trade->setSource($a);
		$trade->setDestination($b);
		$this->assertEquals("trade  - from  to ", (string)$trade);
	}

}

