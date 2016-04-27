<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;

class SettlementControllerTest extends IntegrationTestCase {


	public function testIndex() {
		$this->access_character('Eve');
		$keplerville = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Keplerville');

		$crawler = $this->client->request('GET', '/en/settlement/'.$keplerville->getId());
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "settlement page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Keplerville")')->count(), 'settlement page content failure');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Buildings")')->count(), 'settlement page content failure');

	}

	public function testSoldiers() {
		$this->access_character('Eve');
		$keplerville = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Keplerville');

		$crawler = $this->client->request('GET', '/en/settlement/'.$keplerville->getId().'/soldiers');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "settlement soldiers page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Soldiers Stationed In Keplerville")')->count(), 'settlement soldiers page content failure');

		// TODO: functional testing
	}

}
