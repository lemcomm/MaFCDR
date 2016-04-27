<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;


class RealmControllerTest extends IntegrationTestCase {


	public function testView() {
		$this->access_character('Eve');
		$realm = $this->em->getRepository('BM2SiteBundle:Realm')->findOneByName('Keplerstan');
		$this->assertNotNull($realm, "test realm not found");

		$uri = $this->router->generate('bm2_realm', array('id' => $realm->getId()));
		$crawler = $this->client->request('GET', $uri);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "realm page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Keplerstan")')->count(), 'realm page content failure');
	}

	public function testNew() {
		$this->access_character('Fred');

		$crawler = $this->client->request('GET', '/en/realm/new');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "new realm page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Create Realm")')->count(), 'new realm page content failure');

		$buttonCrawlerNode = $crawler->selectButton('submit');
		$form = $buttonCrawlerNode->form();
		$form['realmcreation[name]'] = "Test Realm";
		$form['realmcreation[formalname]'] = "The Kingdom of Testing";
		$form['realmcreation[type]']->select(6);

		$crawler = $this->client->submit($form);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "creation submit failed");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Manage Realm")')->count(), 'creation did not lead to manage realm page');

		$test = $this->em->getRepository('BM2SiteBundle:Realm')->findOneByName('Test Realm');
		$this->assertNotNull($test, "Test Realm not in database");
	}


	public function testManage() {
		$this->access_character('Dave');
		$realm = $this->em->getRepository('BM2SiteBundle:Realm')->findOneByName('Keplerstan');
		$this->assertNotNull($realm, "test realm not found");

		$uri = $this->router->generate('bm2_site_realm_manage', array('realm' => $realm->getId()));
		$crawler = $this->client->request('GET', $uri);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "manage realm page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Manage Realm")')->count(), 'manage realm page content failure');

		// TODO: functional testing
	}


	public function testPositions() {
		$this->access_character('Dave');
		$realm = $this->em->getRepository('BM2SiteBundle:Realm')->findOneByName('Keplerstan');
		$this->assertNotNull($realm, "test realm not found");

		$uri = $this->router->generate('bm2_site_realm_positions', array('realm' => $realm->getId()));
		$crawler = $this->client->request('GET', $uri);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "positions realm page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Realm Positions")')->count(), 'positions realm page content failure');

		// TODO: functional testing
	}

	public function testDiplomacy() {
		$this->access_character('Dave');
		$realm = $this->em->getRepository('BM2SiteBundle:Realm')->findOneByName('Keplerstan');
		$this->assertNotNull($realm, "test realm not found");

		$uri = $this->router->generate('bm2_site_realm_diplomacy', array('realm' => $realm->getId()));
		$crawler = $this->client->request('GET', $uri);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "diplomacy page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Diplomacy")')->count(), 'diplomacy page content failure');

		// TODO: functional testing
	}

	public function testHierarchy() {
		$this->access_character('Dave');
		$realm = $this->em->getRepository('BM2SiteBundle:Realm')->findOneByName('Keplerstan');
		$this->assertNotNull($realm, "test realm not found");

		$uri = $this->router->generate('bm2_site_realm_hierarchy', array('realm' => $realm->getId()));
		$crawler = $this->client->request('GET', $uri);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "realm hierarchy page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Hierarchy")')->count(), 'realm hierarchy page content failure');

		// TODO: functional testing
	}
}
