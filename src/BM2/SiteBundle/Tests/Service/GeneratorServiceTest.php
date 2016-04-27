<?php

class GeneratorServiceTest extends \Codeception\TestCase\Test {

	protected $generator;
	protected $em;

	function _before() {
		$this->em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->generator = $this->getModule('Symfony2')->container->get('generator');
	}

	function testSoldier() {
		$axe = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('axe');
		$this->assertNotNull($axe);
		$soldier = $this->generator->randomSoldier($axe, null, null);

		$this->assertEquals($axe, $soldier->getWeapon());
		$this->assertNull($soldier->getArmour());
		$this->assertEquals(0, $soldier->getExperience());

		$sword = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('sword');
		$this->assertNotNull($sword);
		$chain = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('chainmail');
		$this->assertNotNull($chain);
		$horse = $this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('horse');
		$this->assertNotNull($horse);
		$knight = $this->generator->randomSoldier($sword, $chain, $horse);
		$this->assertEquals(0, $knight->getExperience());
		$this->assertNotNull($knight->getWeapon());
		$this->assertNotNull($knight->getArmour());
		$this->assertNotNull($knight->getEquipment());
		$this->assertEquals('sword', $knight->getWeapon()->getName());
		$this->assertEquals('chainmail', $knight->getArmour()->getName());
		$this->assertEquals('horse', $knight->getEquipment()->getName());

		$village = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Averick');
		$this->assertNotNull($village, 'no village found');

		$soldier = $this->generator->randomSoldier($sword, null, null, $village);
		$this->assertNull($soldier);

		$village = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Thabeholan');
		$this->assertNotNull($village, 'no village found');
		$soldier = $this->generator->randomSoldier($axe, null, null, $village);
		$this->assertNotNull($soldier);
		$this->assertEquals($axe, $soldier->getWeapon());
		$this->assertNull($soldier->getArmour());
		$this->assertEquals(0, $soldier->getExperience());
		$this->assertEquals($soldier->getBase(), $village);
	}

	public function testEntourage() {
		$scout_type = $this->em->getRepository('BM2SiteBundle:EntourageType')->findOneByName('scout');
		$scout = $this->generator->randomEntourageMember($scout_type);
		$this->assertEquals($scout_type, $scout->getType());
		$this->assertObjectHasAttribute('action', $scout);
		$this->assertNotNull($scout->getName());
	}

}
