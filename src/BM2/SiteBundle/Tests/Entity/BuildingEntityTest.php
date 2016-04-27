<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\Building;
use BM2\SiteBundle\Entity\BuildingType;
use BM2\SiteBundle\Entity\Settlement;


class BuildingEntityTest extends GenericEntityTest {

	private $bank_type;
	private $bank;
	private $tailor;

	public function setUp() {
		parent::setUp();

		$settlement = new Settlement();
		$settlement->setPopulation(5000);

		$bank_type = new BuildingType();
		$bank_type->setName('Bank')->setPerPeople(2000)->setBuildHours(100);
		$this->bank = new Building();
		$this->bank->setType($bank_type);
		$this->bank->setSettlement($settlement);		
		$this->bank->startConstruction(20);

		$tailor_type = new BuildingType();
		$tailor_type->setName('Tailor')->setPerPeople(150);
		$this->tailor = new Building();
		$this->tailor->setType($tailor_type);
		$this->tailor->setSettlement($settlement);		
	}

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\Building');
	}

	public function testConstruction() {
		$this->assertNull($this->bank->getId());
		$this->assertEquals(20, $this->bank->getWorkers(), "Bank should have 20 workers");
		$this->assertEquals(-100, $this->bank->getCondition(), "build hours wrong");
		$this->assertFalse($this->bank->isActive(), "bank should not be active");
	}

	public function testTailor() {
		$this->tailor->setActive(false);
		$this->assertEquals(0, $this->tailor->getEmployees(), "tailor should not yet have employees");
		$this->tailor->setActive(true);
		$this->assertEquals(45, $this->tailor->getEmployees(), "tailor should have 35 employees");
		$this->tailor->getSettlement()->setPopulation(10000);
		$this->assertEquals(81, $this->tailor->getEmployees(), "tailor should now have 58 employees");
	}

	public function testBank() {
		$this->bank->setActive(true);
		$this->assertEquals(9, $this->bank->getEmployees(), "bank should have 9 employees");
		$this->tailor->getSettlement()->setPopulation(10000);
		$this->assertEquals(13, $this->bank->getEmployees(), "bank should now have 12 employees");
	}

	public function testAbandon() {
		$this->bank->setActive(true);
		$this->assertTrue($this->bank->isActive());
		$this->bank->abandon();
		$this->assertFalse($this->bank->isActive());
		$this->assertEquals(-1, $this->bank->getCondition());
		$this->assertEquals(0, $this->bank->getWorkers());
	}

}

