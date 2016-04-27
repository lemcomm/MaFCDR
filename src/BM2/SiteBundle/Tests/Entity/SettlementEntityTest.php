<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Character;


class SettlementEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\Settlement');
	}

	public function testConvenienceMethods() {
		$village = new Settlement();
		$village->setPopulation(100);
		$village->setName("Dumboville");
		$this->assertEquals('Dumboville', $village->getNameWithOwner());
		$owner = new Character;
		$owner->setName("Dumbo");
		$village->setOwner($owner);
		$this->assertEquals('Dumboville (Dumbo)', $village->getNameWithOwner());
	}

	public function testType() {
		$village = new Settlement();
		$village->setPopulation(20);
		$this->assertEquals('settlement.size.1', $village->getType(), "settlement size mismatch");
		$village->setPopulation(150);
		$this->assertEquals('settlement.size.2', $village->getType(), "settlement size mismatch");
		$village->setPopulation(400);
		$this->assertEquals('settlement.size.3', $village->getType(), "settlement size mismatch");
		$village->setPopulation(600);
		$this->assertEquals('settlement.size.4', $village->getType(), "settlement size mismatch");
		$village->setPopulation(1500);
		$this->assertEquals('settlement.size.5', $village->getType(), "settlement size mismatch");
		$village->setPopulation(4000);
		$this->assertEquals('settlement.size.6', $village->getType(), "settlement size mismatch");
		$village->setPopulation(8000);
		$this->assertEquals('settlement.size.7', $village->getType(), "settlement size mismatch");
		$village->setPopulation(16000);
		$this->assertEquals('settlement.size.8', $village->getType(), "settlement size mismatch");
		$village->setPopulation(40000);
		$this->assertEquals('settlement.size.9', $village->getType(), "settlement size mismatch");
		$village->setPopulation(80000);
		$this->assertEquals('settlement.size.10', $village->getType(), "settlement size mismatch");
		$village->setPopulation(150000);
		$this->assertEquals('settlement.size.11', $village->getType(), "settlement size mismatch");
		$village->setPopulation(1000000);
		$this->assertEquals('settlement.size.11', $village->getType(), "settlement size mismatch");
	}

	public function testTraining() {
		$village = new Settlement();
		$village->setPopulation(100);
		$this->assertEquals(1600, round($village->getTrainingPoints()*100), "training points calculation wrong");
		$this->assertEquals(sqrt(5), $village->getSingleTrainingPoints(), "training points calculation wrong");

		$village->setPopulation(2500);
		$this->assertEquals(21600, round($village->getTrainingPoints()*100), "training points calculation wrong");
		$this->assertEquals(sqrt(25), $village->getSingleTrainingPoints(), "training points calculation wrong");
	}

	public function testRecruitment() {
		$village = new Settlement();
		$village->setPopulation(100);
		$this->assertEquals(10, $village->getRecruitLimit(), "recruit limit calculation wrong");
		$village->setRecruited(5);
		$this->assertEquals(5, $village->getRecruitLimit(), "recruit limit calculation wrong");
		$this->assertEquals(10, $village->getRecruitLimit(true), "recruit limit calculation wrong");
	}

}
