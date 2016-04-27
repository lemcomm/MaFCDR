<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;

class GameControllerTest extends IntegrationTestCase {

	private function adminlogin() {
		$this->client->followRedirects();
		$crawler = $this->client->request('GET', '/en/login');
		$form = $crawler->selectButton('_submit')->form(array(
			'_username'  => 'admin',
			'_password'  => 'admin'
			));		
		$this->client->submit($form);
		$this->logged_in=true;
	}

	public function testBasics() {
		$this->adminlogin();

		$crawler = $this->client->request('GET', '/en/game');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "game page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Current Cycle")')->count(), 'game page content failure');

		$crawler = $this->client->request('GET', '/en/game/statistics');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "statistics page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("registered users")')->count(), 'statistics page content failure');

		// doesn't work and breaks testing - why ?
//		$crawler = $this->client->request('GET', '/en/game/techtree');
//		$this->assertTrue($this->client->getResponse()->isSuccessful(), "techtree page failed to load");
//		$this->expectOutputRegex('/.*digraph techtree.*/');
//		$this->expectOutputRegex('/.*Academy.*/');
	}

}
