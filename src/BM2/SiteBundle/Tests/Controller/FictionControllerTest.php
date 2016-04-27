<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;

class FictionControllerTest extends IntegrationTestCase {

	public function testContentPages() {
		$pages = array(
			'content/creation' => 'The Light was first, before',
			'content/fall' => 'And so he took the appearance of a young man',
			'content/lendan' => 'Discovered by explorers of the First Ones'
		);

		$crawler = $this->client->request('GET', '/en/fiction/index');
		$this->assertTrue($this->client->getResponse()->isSuccessful(), "index page failed to load");
		$this->assertGreaterThan(0, $crawler->filter('div.symfony-content')->count(), "index page has no content");
		$this->assertGreaterThan(0, $crawler->filter('html:contains("The following stories are all")')->count());

		foreach ($pages as $page=>$content) {
			$crawler = $this->client->request('GET', '/en/fiction/'.$page);
			$this->assertTrue($this->client->getResponse()->isSuccessful(), "page $page failed to load");
			$this->assertGreaterThan(0, $crawler->filter('div.fiction')->count(), "page $page has no content");
			$this->assertGreaterThan(0, $crawler->filter('html:contains("'.$content.'")')->count());
		}
	}

}
