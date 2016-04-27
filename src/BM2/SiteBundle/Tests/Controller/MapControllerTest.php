<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;


class MapControllerTest extends IntegrationTestCase {


	public function testMap() {
		$this->access_character('Alice');

		$crawler = $this->client->request('GET', '/en/map/');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "map page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Legend")')->count(), 'map page content failure');

		// TODO: function testing
	}


	public function testData() {
		$this->access_character('Alice');
		$crawler = $this->client->request('GET', '/en/map/data', array('type'=>'polygons', 'bbox'=>'0,0,5000,5000'), array(), array('CONTENT_TYPE'=>'application/json'));
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "map data failed to load");
		$this->assertTrue($this->client->getResponse()->headers->contains('Content-Type','application/json'));

		$crawler = $this->client->request('GET', '/en/map/data', array('type'=>'settlements', 'bbox'=>'0,0,5000,5000'), array(), array('CONTENT_TYPE'=>'application/json'));
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "map data failed to load");
		$this->assertTrue($this->client->getResponse()->headers->contains('Content-Type','application/json'));
		$this->expectOutputRegex('/.*Keplerville.*/');

		$crawler = $this->client->request('GET', '/en/map/data', array('type'=>'rivers', 'bbox'=>'0,0,5000,5000'), array(), array('CONTENT_TYPE'=>'application/json'));
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "map data failed to load");
		$this->assertTrue($this->client->getResponse()->headers->contains('Content-Type','application/json'));

		$crawler = $this->client->request('GET', '/en/map/data', array('type'=>'roads', 'bbox'=>'0,0,5000,5000'), array(), array('CONTENT_TYPE'=>'application/json'));
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "map data failed to load");
		$this->assertTrue($this->client->getResponse()->headers->contains('Content-Type','application/json'));

		$crawler = $this->client->request('GET', '/en/map/data', array('type'=>'features', 'bbox'=>'0,0,5000,5000'), array(), array('CONTENT_TYPE'=>'application/json'));
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "map data failed to load");
		$this->assertTrue($this->client->getResponse()->headers->contains('Content-Type','application/json'));

		$crawler = $this->client->request('GET', '/en/map/data', array('type'=>'characters', 'bbox'=>'0,0,5000,5000'), array(), array('CONTENT_TYPE'=>'application/json'));
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "map data failed to load");
		$this->assertTrue($this->client->getResponse()->headers->contains('Content-Type','application/json'));
		$this->expectOutputRegex('/.*Alice.*/');
		$this->expectOutputRegex('/.*Eve.*/');

		$crawler = $this->client->request('GET', '/en/map/data', array('type'=>'realms', 'bbox'=>'0,0,5000,5000'), array(), array('CONTENT_TYPE'=>'application/json'));
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "map data failed to load");
		$this->assertTrue($this->client->getResponse()->headers->contains('Content-Type','application/json'));
		$this->expectOutputRegex('/.*Keplerstan.*/');
	}
}
