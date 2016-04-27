<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;


class QueueControllerTest extends IntegrationTestCase {


	public function testIndex() {
		$this->access_character('Eve');

		$crawler = $this->client->request('GET', '/en/queue');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "queue page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Actions Queue")')->count(), 'queue page content failure');


	}

}
