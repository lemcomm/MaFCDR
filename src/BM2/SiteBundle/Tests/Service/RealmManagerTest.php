<?php


class RealmManagerTest extends \Codeception\TestCase\Test {

	protected $em;
	protected $rm;
	protected $alice;
	protected $realm;

	function _before() {
		$this->em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->rm = $this->getModule('Symfony2')->container->get('realm_manager');

		$this->alice = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Alice Kepler');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Character", $this->alice, "test character not found");
		$this->realm = $this->em->getRepository('BM2SiteBundle:Realm')->findOneByName('Keplerstan');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Realm", $this->realm, "test realm not found");
	}


	public function testCreate() {
		$newrealm = $this->rm->create('Test Realm', 'The Realm of Testing', 3, $this->alice);

		$this->assertInstanceOf("BM2\SiteBundle\Entity\Realm", $newrealm);
		$this->assertEquals($newrealm->getName(), 'Test Realm');
		$this->assertContains($this->alice, $newrealm->findMembers());
		$this->assertContains($newrealm, $this->alice->findRealms());
		$this->assertContains($this->alice, $newrealm->findRulers());
	}


	public function testSubCreate() {
		$newsub = $this->rm->subcreate('Test Sub', 'the smaller realm of little testing', 2, $this->alice, $this->alice, $this->realm);

		$this->assertInstanceOf("BM2\SiteBundle\Entity\Realm", $newsub);
		$this->assertEquals($newsub->getName(), 'Test Sub');
		$this->assertEquals($newsub->getSuperior(), $this->realm);
		$this->assertContains($this->alice, $newsub->findRulers());
	}

}
