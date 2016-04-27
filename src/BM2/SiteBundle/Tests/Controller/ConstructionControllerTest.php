<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;

class ConstructionControllerTest extends IntegrationTestCase {

	public function testRoads() {
		$this->access_character('Eve');

		$crawler = $this->client->request('GET', '/en/actions/roads');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "roads page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Road Construction")')->count(), 'roads page content failure');

		/* TODO: functional testing */	
	}

	public function testFeatures() {
		$this->access_character('Eve');
		$signpost = $this->em->getRepository('BM2SiteBundle:FeatureType')->findOneByName('signpost');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\FeatureType", $signpost);


		$crawler = $this->client->request('GET', '/en/actions/features');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "features page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Bridges And Features")')->count(), 'features page content failure');
		$this->assertEquals(0, $crawler->filter('html:contains("estimated remaining")')->count());

		/* functional testing */		
		$buttonCrawlerNode = $crawler->selectButton('submit');
		$form = $buttonCrawlerNode->form();
		$scout = $this->em->getRepository('BM2SiteBundle:EntourageType')->findOneByName('scout');
		$this->assertNotNull($scout, "scout not found");

		$form['featureconstruction[new][workers]'] = 3.0;
		$form['featureconstruction[new][type]'] = $signpost->getId();
		$form['featureconstruction[new][name]'] = 'here be dragons';
		$form['featureconstruction[new][location_x]'] = 800;
		$form['featureconstruction[new][location_y]'] = 600;

		$crawler = $this->client->submit($form);
		$this->assertTrue($this->client->getResponse()->isSuccessful());
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Sign Post")')->count());
		$this->assertGreaterThan(0, $crawler->filter('html:contains("estimated remaining")')->count());


		$buttonCrawlerNode = $crawler->selectButton('submit');
		$form = $buttonCrawlerNode->form();
		$feature = $this->em->getRepository('BM2SiteBundle:GeoFeature')->findOneByName('here be dragons');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\GeoFeature", $feature, "not persisted correctly?");
		$form['featureconstruction[existing]['.$feature->getId().']'] = 4.0;
		$crawler = $this->client->submit($form);
		$this->assertTrue($this->client->getResponse()->isSuccessful());
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Sign Post")')->count());
		$this->assertGreaterThan(0, $crawler->filter('html:contains("estimated remaining")')->count());
	}

	public function testBuildings() {
		$this->access_character('Eve');

		$crawler = $this->client->request('GET', '/en/actions/buildings');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "buildings page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Available For Construction")')->count(), 'buildings page content failure');

		/* TODO: functional testing */		
	}

}
