<?php

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;


class MilitaryServiceTest extends \Codeception\TestCase\Test {

	protected $em;
	protected $military;
	protected $generator;
	protected $character;
	protected $settlement;

	function _before() {
		$this->em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->military = $this->getModule('Symfony2')->container->get('military');
		$this->generator = $this->getModule('Symfony2')->container->get('generator');

		$this->character = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Alice Kepler');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Character", $this->character, "test character not found");
		$this->settlement = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Keplerville');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Settlement", $this->settlement, "test settlement not found");
	}


	public function testActions() {
		$bow = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('short bow');
		$hunter = $this->generator->randomSoldier($bow, null, null);
		$this->em->flush();

		$this->military->makeMilitia($hunter, $this->settlement);
		$this->assertNull($hunter->getCharacter());
		$this->assertNotNull($hunter->getBase());

		$this->military->makeSoldier($hunter, $this->character);
		$this->assertNotNull($hunter->getCharacter());
		$this->assertNull($hunter->getBase());

		$sword_type = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('sword');
		$plate_type = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('plate armour');
		$horse_type = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('horse');
	}


	public function testAssignCharacter() {
		$dave = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('David Stanis');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Character", $dave, "test character not found");
		$soldier = $this->character->getSoldiers()->first();
		$this->assertNotNull($soldier, "alice should have a soldier");

		$this->military->assign($soldier, $dave);

		$this->assertFalse($this->character->getSoldiers()->contains($soldier));
		$this->assertTrue($dave->getSoldiers()->contains($soldier));
		$this->assertTrue($soldier->getLocked());
	}

	public function testAssignBase() {
		$dave = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('David Stanis');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Character", $dave, "test character not found");
		$soldier = $this->settlement->getSoldiers()->first();
		$this->assertNotNull($soldier, "settlement should have a soldier");

		$this->military->assign($soldier, $dave);

		$this->assertFalse($this->settlement->getSoldiers()->contains($soldier));
		$this->assertTrue($dave->getSoldiers()->contains($soldier));
		$this->assertTrue($soldier->getLocked());
	}

	public function testDisband() {
		$alice = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Alice Kepler');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Character", $alice, "test character not found");
		$soldier = $alice->getSoldiers()->first();
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Soldier", $soldier, "test soldier not found");
		$id = $soldier->getId();

		$soldier->setHome($alice->getInsideSettlement());
		$this->em->flush();

		$count = $alice->getSoldiers()->count();
		$pop = $alice->getInsideSettlement()->getPopulation();
		$this->military->disband($soldier, $alice);
		$this->em->flush();

		$soldier = $this->em->getRepository('BM2SiteBundle:Soldier')->find($id);
		$this->assertNull($soldier);

		$this->assertEquals($pop+1, $alice->getInsideSettlement()->getPopulation());
	}

	public function testBury() {
		$alice = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Alice Kepler');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Character", $alice, "test character not found");
		$soldier = $alice->getSoldiers()->first();
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Soldier", $soldier, "test soldier not found");
		$id = $soldier->getId();

		$this->military->bury($soldier);
		$this->em->flush();

		$test = $this->em->getRepository('BM2SiteBundle:Soldier')->find($id);
		$this->assertNull($test);
	}
}
