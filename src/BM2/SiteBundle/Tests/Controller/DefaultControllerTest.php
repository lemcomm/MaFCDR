<?php

namespace BM2\SiteBundle\Tests\Controller;

use BM2\SiteBundle\Tests\IntegrationTestCase;

class DefaultControllerTest extends IntegrationTestCase {

	public function testBoringPages() {
		$pages = array('/en/', '/en/about', '/en/manual', '/en/contact', '/en/credits', '/en/devcontact', '/en/register/', '/en/paymentconcept');

		foreach ($pages as $page) {
			$crawler = $this->client->request('GET', $page);

			$this->assertTrue($this->client->getResponse()->isSuccessful(), "page $page failed to load");
			$this->assertGreaterThan(0, $crawler->filter('div.symfony-content')->count(), "page $page has no content");
		}
	}

	public function testRedirect() {
		$crawler = $this->client->request('GET', '/xx/about');

		$this->assertTrue($this->client->getResponse()->isRedirect(), 'should be redirected');
		$this->assertTrue($this->client->getResponse()->isRedirect('/en/about'), 'should be redirected to english about page');

	}


	public function testRegistration() {
		$this->client->followRedirects();
		$crawler = $this->client->request('GET', '/en/register/');

		$this->assertTrue($this->client->getResponse()->isSuccessful());
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Registration")')->count());

		$buttonCrawlerNode = $crawler->selectButton('register_submit');
		$form = $buttonCrawlerNode->form();

		$form['fos_user_registration_form[username]'] = 'Tempus';
		$form['fos_user_registration_form[email]'] = 'tempus@lemuria.org';
		$form['fos_user_registration_form[plainPassword][first]'] = '12345678';
		$form['fos_user_registration_form[plainPassword][second]'] = '12345678';

		$crawler = $this->client->submit($form);
		$this->assertTrue($this->client->getResponse()->isSuccessful());
		$this->assertGreaterThan(0, $crawler->filter('html:contains("Registration")')->count());
		$this->assertGreaterThan(0, $crawler->filter('html:contains("First Steps")')->count());

		$tempus = $this->em->getRepository('BM2SiteBundle:User')->findOneByUsername('Tempus');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\User", $tempus);
	}
}
