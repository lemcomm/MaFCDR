<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;


class InfoControllerTest extends IntegrationTestCase {


	public function testBuildingType() {
		$test = $this->em->getRepository('BM2SiteBundle:BuildingType')->findOneByName('Blacksmith');
		$this->assertNotNull($test, "blacksmith test building not found");
		$crawler = $this->client->request('GET', '/en/info/buildingtype/'.$test->getId());

		$this->assertTrue($this->client->getResponse()->isSuccessful(), "blacksmith failed to load");
		$this->assertGreaterThan(0, $crawler->filter('div.symfony-content')->count(), "blacksmith page has no content");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("'.$test->getName().'")')->count(), "blacksmith page has no info on blacksmith");
	}

	public function testFeatureType() {
		$test = $this->em->getRepository('BM2SiteBundle:FeatureType')->findOneByName('tower');
		$this->assertNotNull($test, "tower test building not found");
		$crawler = $this->client->request('GET', '/en/info/featuretype/'.$test->getId());

		$this->assertTrue($this->client->getResponse()->isSuccessful(), "tower failed to load");
		$this->assertGreaterThan(0, $crawler->filter('div.symfony-content')->count(), "tower page has no content");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("'.$test->getName().'")')->count(), "tower page has no info on tower");
	}

	public function testEntourageType() {
		$test = $this->em->getRepository('BM2SiteBundle:EntourageType')->findOneByName('scholar');
		$this->assertNotNull($test, "scholar test entourage not found");
		$crawler = $this->client->request('GET', '/en/info/entouragetype/'.$test->getId());

		$this->assertTrue($this->client->getResponse()->isSuccessful(), "scholer failed to load");
		$this->assertGreaterThan(0, $crawler->filter('div.symfony-content')->count(), "scholer page has no content");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("'.ucfirst($test->getName()).'")')->count(), "scholer page has no info on scholer");
	}

	public function testEquipmentType() {
		$test = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('sword');
		$this->assertNotNull($test, "sword test equipment not found");
		$crawler = $this->client->request('GET', '/en/info/equipmenttype/'.$test->getId());

		$this->assertTrue($this->client->getResponse()->isSuccessful(), "sword failed to load");
		$this->assertGreaterThan(0, $crawler->filter('div.symfony-content')->count(), "sword page has no content");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("'.ucfirst($test->getName()).'")')->count(), "sword page has no info on sword");
	}


	public function testFailures() {
		$crawler = $this->client->request('GET', '/en/info/buildingtype/0');
		$this->assertFalse($this->client->getResponse()->isSuccessful(), "page should not load");
		$crawler = $this->client->request('GET', '/en/info/featuretype/0');
		$this->assertFalse($this->client->getResponse()->isSuccessful(), "page should not load");
		$crawler = $this->client->request('GET', '/en/info/entouragetype/0');
		$this->assertFalse($this->client->getResponse()->isSuccessful(), "page should not load");
		$crawler = $this->client->request('GET', '/en/info/equipmenttype/0');
		$this->assertFalse($this->client->getResponse()->isSuccessful(), "page should not load");
	}
}
