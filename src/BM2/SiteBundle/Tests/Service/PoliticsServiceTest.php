<?php

use BM2\SiteBundle\Entity\Character;


class PoliticsServiceTest extends \Codeception\TestCase\Test {

	protected $em;
	protected $politics;
	protected $alice;
	protected $realm;

	function _before() {
		$this->em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->politics = $this->getModule('Symfony2')->container->get('politics');

		$this->alice = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Alice Kepler');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Character", $this->alice, "test character not found");
		$this->realm = $this->em->getRepository('BM2SiteBundle:Realm')->findOneByName('Keplerstan');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Realm", $this->realm, "test realm not found");
	}


	public function testHierarchy() {
		$david = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('David Stanis');
		$eve = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Eve');

		$this->assertTrue($this->politics->isSuperior($david, $this->alice));
		$this->assertFalse($this->politics->isSuperior($eve, $this->alice));

	}

	public function testSettlementOwnerChange() {
		$eve = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Eve');
		$emp = $this->em->getRepository('BM2SiteBundle:Realm')->findOneByName('Emp');

		$estate = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Sumidale');
		$estate->setOwner($this->alice);
		$estate->setRealm($this->realm);

		$this->politics->changeSettlementOwner($estate, $eve, 'take');
		$this->assertEquals($estate->getOwner(), $eve);
		$this->assertEquals($estate->getRealm(), $this->realm);

		$this->politics->changeSettlementOwner($estate, $this->alice, 'grant');
		$this->assertEquals($estate->getOwner(), $this->alice);
		$this->assertEquals($estate->getRealm(), $this->realm);

		$this->politics->changeSettlementRealm($estate, $emp, 'change');
		$this->assertEquals($estate->getOwner(), $this->alice);
		$this->assertEquals($estate->getRealm(), $emp);

		$this->politics->changeSettlementRealm($estate, $this->realm, 'subrealm');
		$this->assertEquals($estate->getRealm(), $this->realm);

		$this->politics->changeSettlementRealm($estate, $emp, 'take');
		$this->assertEquals($estate->getRealm(), $emp);

		$this->politics->changeSettlementRealm($estate, $this->realm, 'fail');
		$this->assertEquals($estate->getRealm(), $this->realm);

		$this->politics->changeSettlementRealm($estate, $emp, 'update');
		$this->assertEquals($estate->getRealm(), $emp);

		$this->politics->changeSettlementRealm($estate, $this->realm, 'grant');
		$this->assertEquals($estate->getRealm(), $this->realm);

	}

}
