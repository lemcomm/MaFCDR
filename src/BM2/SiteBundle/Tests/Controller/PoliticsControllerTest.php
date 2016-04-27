<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;


class PoliticsControllerTest extends IntegrationTestCase {


	public function testIndex() {
		$this->access_character('Eve');

		$crawler = $this->client->request('GET', '/en/politics');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "politics page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Hierarchy")')->count(), 'politics page content failure');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("are a vassal of")')->count(), 'politics page content failure');

		$link = $crawler->selectLink('Oath Of Fealty')->link();
		$this->assertNotNull($link, 'oath link missing');
		$this->assertEquals('http://localhost/en/politics/oath', $link->getUri(), 'oath link wrong');


		$this->access_character('Dave');

		$crawler = $this->client->request('GET', '/en/politics');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("are the ruler of")')->count(), 'politics page content failure');		
	}

	public function testHierarchy() {
		$this->access_character('Dave');

		$crawler = $this->client->request('GET', '/en/politics/hierarchy');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "hierarchy page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Dave")')->count(), 'hierarchy page content failure');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Eve")')->count(), 'hierarchy page content failure');
	}

	public function testOath() {
		$this->access_character('Alice');
		$crawler = $this->client->request('GET', '/en/politics/oath');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "oath page failed to load");

		$this->access_character('Eve');
		$crawler = $this->client->request('GET', '/en/politics/breakoath');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "break oath page failed to load");

		// TODO: functional testing
	}

	public function testSuccessor() {
		$this->access_character('Dave');

		$crawler = $this->client->request('GET', '/en/politics/successor');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "successor page failed to load");

		// TODO: functional testing
	}


	public function testPartners() {
		$this->access_character('Dave');

		$crawler = $this->client->request('GET', '/en/politics/partners');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "partners page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("New Relationships")')->count(), 'partners page content failure');

		// TODO: functional testing
	}


}
