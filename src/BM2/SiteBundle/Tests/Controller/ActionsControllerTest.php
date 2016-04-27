<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;

class ActionsControllerTest extends IntegrationTestCase {

	public function testOptions() {
		$this->access_character('Eve');
		$crawler = $this->client->request('GET', '/en/actions/');

		$this->assertTrue($this->client->getResponse()->isSuccessful(), "actions page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Medium Village Of Keplerville")')->count(), 'actions page content failure');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("You already control this settlement.")')->count(), 'actions page content failure');

		$this->access_character('Alice');
		$crawler = $this->client->request('GET', '/en/actions/');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "actions page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Medium Village Of Keplerville")')->count(), 'actions page content failure');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Declare your lordship")')->count(), 'actions page content failure');

		$this->access_character('Dave');
		$crawler = $this->client->request('GET', '/en/actions/');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "actions page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("You are not near any settlement")')->count(), 'actions page content failure');
	}

	public function testTake() {
		$this->access_character('Eve');
		$keplerville = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Keplerville');
		$bob = $this->getCharacterByName("Bob");
		$eve = $this->getCharacterByName("Eve");

		$this->assertEquals($eve, $keplerville->getOwner(), "Keplerville should be owned by Eve");

		$crawler = $this->client->request('GET', '/en/actions/');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "actions page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Medium Village Of")')->count(), 'actions page content failure');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Keplerville")')->count(), 'actions page content failure');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("You already control this settlement.")')->count(), 'actions page content failure');

		$this->access_character('Bob');
		$crawler = $this->client->request('GET', '/en/actions/');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "actions page failed to load");
		$link = $crawler->selectLink('Take Control')->link();
		$crawler = $this->client->click($link);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "take control page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Take Control")')->count(), 'take control page content failure');

		$buttonCrawlerNode = $crawler->selectButton('submit');
		$crawler = $this->client->submit($buttonCrawlerNode->form());
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "take control page failed to submit");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("now in the process of taking control")')->count(), 'Keplerville should now be in takeover process.');

		$crawler = $this->client->request('GET', '/en/actions/');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "actions page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("take settlement")')->count(), 'take settlement action should be queued.');
	}

	public function testGrant() {
		$this->access_character('Eve');

		$crawler = $this->client->request('GET', '/en/actions/grant');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "grant page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Grant Control")')->count(), 'grant page content failure');

		$buttonCrawlerNode = $crawler->selectButton('submit');
		$form = $buttonCrawlerNode->form();
		$form['form[newowner]'] = $this->getCharacterByName("Bob")->getId();
		$crawler = $this->client->submit($form);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "grant page failed to submit");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("will soon be meeting with")')->count(), 'grant failed.');

		$crawler = $this->client->request('GET', '/en/actions/');
		$this->assertGreaterThan(0, $crawler->filter('div.queue:contains("grant settlement")')->count(), 'grant should be in action queue".');
	}

	public function testRename() {
		$this->access_character('Eve');

		$crawler = $this->client->request('GET', '/en/actions/rename');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "actions page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Rename Settlement")')->count(), 'rename page content failure');

		$buttonCrawlerNode = $crawler->selectButton('submit');
		$form = $buttonCrawlerNode->form();
		$form['form[name]'] = "Testing Rename";
		$crawler = $this->client->submit($form);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "rename page failed to submit");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("have been instructed to change the name")')->count(), 'rename failed.');

		$crawler = $this->client->request('GET', '/en/actions/');
		$this->assertGreaterThan(0, $crawler->filter('div.queue:contains("rename settlement")')->count(), 'rename should be in action queue".');

		$village = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName("Testing Rename");
		if ($village) {
			// strangely, the above seems to return null? weird.
			$village->setName("Keplerville");
			$this->em->flush();			
		}
	}

	public function testOffers() {
		$this->access_character('Eve');

		$crawler = $this->client->request('GET', '/en/actions/offers');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "offers page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Knights Offers")')->count(), 'offers page content failure');

		/* TODO: functional testing */
	}



	public function testTrade() {
		$this->access_character('Eve');

		$crawler = $this->client->request('GET', '/en/actions/trade');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "trade page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Outbound Trade")')->count(), 'trade page content failure');

		/* TODO: functional testing */
	}

	public function testForeignTrade() {
		$this->access_character('Bob');

		$crawler = $this->client->request('GET', '/en/actions/trade');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "trade page failed to load");
		$this->assertEquals(0, $crawler->filter('html:contains("Outbound Trade")')->count(), 'trade page content failure');

		/* TODO: functional testing */
	}


	public function testEntourage() {
		$this->access_character('Eve');
		$eve = $this->getCharacterByName('Eve');

		$crawler = $this->client->request('GET', '/en/actions/entourage');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "entourage page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Recruit Entourage")')->count(), 'entourage page content failure');

		$before = $eve->getEntourageOfType('scout')->count();
		$buttonCrawlerNode = $crawler->selectButton('submit');
		$form = $buttonCrawlerNode->form();
		$scout = $this->em->getRepository('BM2SiteBundle:EntourageType')->findOneByName('scout');
		$this->assertNotNull($scout, "scout not found");

		$form['recruitment[recruits]['.$scout->getId().']'] = 2;

		$crawler = $this->client->submit($form);
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "entourage recruitment submit failed");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("You have now recruited 2 scouts.")')->count(), 'entourage recruitment failed');

		$crawler = $this->client->request('GET', '/en/character/');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "status page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("'.($before+2).' scouts")')->count(), 'recruitment did not add up');
	}

	public function testSoldiers() {
		$this->access_character('Eve');

		$crawler = $this->client->request('GET', '/en/actions/soldiers');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "soldiers page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Train Soldiers")')->count(), 'soldiers page content failure');

		/* TODO: functional testing */		
	}


	public function testSettlementDefend() {
		$this->access_character('Eve');

		$crawler = $this->client->request('GET', '/en/actions/settlement/defend');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "defend page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Defend Settlement")')->count(), 'defend page content failure');

		/* TODO: functional testing */		
	}


	public function testSettlementAttack() {
		$this->access_character('Alice');

		$crawler = $this->client->request('GET', '/en/actions/settlement/loot');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "loot page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Loot Settlement")')->count(), 'loot page content failure');

/*
		// FIXME: still fails because there are no troops defending
		$crawler = $this->client->request('GET', '/en/actions/settlement/attack');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "attack page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Attack Settlement")')->count(), 'attack page content failure');
*/

		/* TODO: functional testing */		
	}


	public function testOthersAttack() {
		$this->access_character('Alice');

		$crawler = $this->client->request('GET', '/en/actions/nobles/attack');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "attack page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Initiate Battle")')->count(), 'attack page content failure');
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Eve")')->count(), 'attack page content failure');

		/* TODO: functional testing */		
	}


	/*
		militia management is actually handled in the settlement controller, so it's tested there
	*/

}
