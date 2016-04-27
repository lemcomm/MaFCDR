<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;
use BM2\SiteBundle\Entity\Character;


class CharacterControllerTest extends IntegrationTestCase {

	public function testCharacterAccess() {
		$this->login();

		$test_character = $this->test_user->getCharacters()->first();
		$this->assertInstanceOf("BM2SiteBundle:Character", $test_character, "test character does not exist");

		$crawler = $this->client->request('GET', '/en/account/play/'.$test_character->getId());
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "character play page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("'.$test_character->getName().'")')->count(), 'play page should contain my character name');
	}

	public function testCharacterProtection() {
		$test_character = $this->test_user->getCharacters()->first();
		$this->assertInstanceOf("BM2SiteBundle:Character", $test_character, "test character does not exist");
		$crawler = $this->client->request('GET', '/en/account/play/'.$test_character->getId());

		$this->assertTrue($this->client->getResponse()->isRedirect('http://localhost/en/login'), 'should be redirected to login page');
		// TODO: test session set, but how?
	}

	public function testCharacterStatus() {
		$this->access_character();

		$crawler = $this->client->request('GET', '/en/character');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "character status page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("'.$this->test_character->getName().'")')->count(), 'character status page should contain my character name');
	}

	public function testCharacterView() {
		$this->login();
		$test_character = $this->test_user->getCharacters()->first();
		$this->assertInstanceOf("BM2SiteBundle:Character", $test_character, "test character does not exist");

		$crawler = $this->client->request('GET', '/en/character/view/'.$test_character->getId());
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "character view page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("'.$test_character->getName().'")')->count(), 'view page should contain character name');
	}


	public function testStart() {
		$this->access_character('Alice');

		$this->client->followRedirects(false);
		$crawler = $this->client->request('GET', '/en/character/start');
		$this->assertTrue($this->client->getResponse()->isRedirect('/en/character/'), 'should be redirected to character page');


		$this->access_character('Carol');

		$crawler = $this->client->request('GET', '/en/character/start');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "start page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Character Placement")')->count(), 'start page content failure');

		// TODO: functional testing

		// TODO: verify our event log was created and we have access

	}

	public function testRenameAndKill() {
		$this->access_character('Bob');

		$crawler = $this->client->request('GET', '/en/character/rename');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "rename page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Rename")')->count(), 'rename page content failure');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("New Name")')->count(), 'rename page content failure');

		// functional testing
		$buttonCrawlerNode = $crawler->selectButton('submit');
		$form = $buttonCrawlerNode->form();
		$form['form[name]'] = "Zack";
		$crawler = $this->client->submit($form);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "rename submit failed");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("has been renamed")')->count(), 'rename failed');

		$crawler = $this->client->request('GET', '/en/character');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "character page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Status Of Zack")')->count(), 'rename did not hold');
	
		// kill - testing this here because then we can continue with Bob/Zack
		$crawler = $this->client->request('GET', '/en/character/kill');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "kill page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Kill")')->count(), 'kill page content failure');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("cannot be undone")')->count(), 'kill page content failure');

		// functional testing
		$buttonCrawlerNode = $crawler->selectButton('submit');
		$form = $buttonCrawlerNode->form();
		$form['form[sure]']->tick();
		$crawler = $this->client->submit($form);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "kill submit failed");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("is now dead")')->count(), 'kill failed');

		$crawler = $this->client->request('GET', '/en/character');
		$this->assertFalse($this->client->getResponse()->isSuccessful(), "character page should not load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("error.missing.soul")')->count(), 'death should be permanent');		

		// revive - we still need him for further testing
		$this->test_character->setAlive(true);
		$this->test_character->setName('Bob');
		$this->em->flush();
	}

	public function testTravel() {
		$this->access_character('Alice');

		$crawler = $this->client->request('GET', '/en/character/set_travel');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "clear travel page failed to load");

		$crawler = $this->client->request('GET', '/en/character/clear_travel');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "clear travel page failed to load");

		// TODO: functional testing
	}

	public function testHeraldry() {
		$this->access_character('Alice');

		$crawler = $this->client->request('GET', '/en/character/crest');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "heraldry page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Heraldry")')->count(), 'heraldry page content failure');

		// TODO: functional testing
	}

	public function testEntourage() {
		$this->access_character('Alice');

		$crawler = $this->client->request('GET', '/en/character/entourage');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "entourage page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Entourage")')->count(), 'entourage page content failure');

		// TODO: functional testing

	}


	public function testSoldiers() {
		$this->access_character('Eve');

		$crawler = $this->client->request('GET', '/en/character/soldiers');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "soldiers page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Soldiers")')->count(), 'soldiers page content failure');

		// TODO: functional testing

	}



}
