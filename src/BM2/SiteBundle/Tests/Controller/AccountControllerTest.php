<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;


class AccountControllerTest extends IntegrationTestCase {

	public function testIndex() {
		$crawler = $this->client->request('GET', '/en/account');

		$this->assertTrue($this->client->getResponse()->isRedirect(), 'should be redirected');
		$this->assertTrue($this->client->getResponse()->isRedirect('http://localhost/en/login'), 'should be redirected to login page');
	}
	
	public function testLogin() {
		$crawler = $this->client->request('GET', '/en/login');
		$form = $crawler->selectButton('_submit')->form(array(
			'_username'  => $this->testuserdata['name'],
			'_password'  => $this->testuserdata['password']
			));		
		$this->client->submit($form);

		$this->assertTrue($this->client->getResponse()->isRedirect(), 'should be redirected');
		$this->assertTrue($this->client->getResponse()->isRedirect('http://localhost/en/account/'), 'should be redirected to en/account page');
		$crawler = $this->client->followRedirect();

		$this->assertGreaterThan(0, $crawler->filter('div.symfony-content')->count());
		$this->assertEquals('Account', $crawler->filter('div.symfony-content h2')->first()->text());
	}

	public function testAccountpage() {
		$this->login();

		$crawler = $this->client->request('GET', '/en/account');
		$this->assertFalse($this->client->getResponse()->isRedirect(), 'should not be redirected');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("'.$this->testuserdata['email'].'")')->count(), 'should contain my email');
	}

	public function testCharacterspage() {
		$this->login();

		$crawler = $this->client->request('GET', '/en/account/characters');
		$this->assertFalse($this->client->getResponse()->isRedirect(), 'should not be redirected');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Family Tree")')->count(), 'should contain family tree link');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Create Character")')->count(), 'should contain creation link');
	}

	public function testFamilytree() {
		$this->login();

		$this->client->followRedirects();
		$crawler = $this->client->request('GET', '/en/account/familytree');

		$this->assertTrue($this->client->getResponse()->isSuccessful(), "family tree page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Family Tree")')->count(), 'header wrong or missing');
		$this->assertGreaterThan(0, $crawler->filter('svg')->count(), 'svg image missing');
	}

	public function testCharacterCreation() {
		$this->login();

		$this->client->followRedirects();
		$crawler = $this->client->request('GET', '/en/account/newchar');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "family tree page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Create Character")')->count(), 'header wrong or missing');

		$buttonCrawlerNode = $crawler->selectButton('submit');
		$form = $buttonCrawlerNode->form();
		$form['charactercreation[name]'] = "Yolande";
		$form['charactercreation[gender]']->select('f');
		$bob = $this->getCharacterByName("Bob");
		$form['charactercreation[father]']->select($bob->getId());
		$dave = $this->getCharacterByName("Dave");
		$form['charactercreation[partner]']->select($dave->getId());

		$crawler = $this->client->submit($form);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "creation submit failed");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Character Placement")')->count(), 'creation did not lead to placement');

		$yolande = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Yolande');
		$this->assertNotNull($yolande, "Yolande not in database");
	}
}
