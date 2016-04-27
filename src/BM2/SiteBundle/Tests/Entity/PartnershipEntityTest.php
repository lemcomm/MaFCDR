<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\Partnership;
use BM2\SiteBundle\Entity\Character;



class PartnershipEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\Partnership');
	}

	public function testExtras() {
		$partnership = new Partnership;
		$husband = new Character;
		$husband->setName('husband');
		$wife = new Character;
		$wife->setName('wife');

		$partnership->addPartner($husband)->addPartner($wife);
		$this->assertEquals($wife, $partnership->getOtherPartner($husband));
		$this->assertEquals($husband, $partnership->getOtherPartner($wife));

		// this tests a failure that should never happen, but we test it to get 100% coverage
		$partnership = new Partnership;
		$this->assertFalse($partnership->getOtherPartner($husband));
	}
}

