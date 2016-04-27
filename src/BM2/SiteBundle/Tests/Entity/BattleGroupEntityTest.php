<?php

namespace BM2\SiteBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;

use BM2\SiteBundle\Entity\Battle;
use BM2\SiteBundle\Entity\BattleGroup;
use BM2\SiteBundle\Entity\Soldier;
use BM2\SiteBundle\Entity\Character;


class BattleGroupEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\BattleGroup');
	}

	public function testSetup() {
		$bg = new BattleGroup;

		$battle = new Battle;
		$bg->setBattle($battle);
		$battle->addGroup($bg);

		$char = new Character;
		$soldier = new Soldier;
		$soldier->setAlive(true)->setWounded(0)->setRouted(false);
		$soldier->setCharacter($char);
		$char->addSoldier($soldier);
		$bg->addCharacter($char);

		$bg->setupSoldiers();
		$this->assertFalse($bg->getSoldiers()->isEmpty());
		$this->assertTrue($bg->getSoldiers()->contains($soldier));

		$soldier->setAlive(false);
		$bg->setupSoldiers();
		$this->assertTrue($bg->getSoldiers()->isEmpty());
	}

	public function testOthers() {
		$bg = new BattleGroup;

		$battle = new Battle;
		$bg->setAttacker(true);
		$bg->setBattle($battle);
		$battle->addGroup($bg);

		$this->assertTrue($bg->isAttacker());

		$char = new Character;
		$soldier = new Soldier;
		$soldier->setAlive(true)->setWounded(0)->setRouted(false);
		$soldier->setCharacter($char);
		$char->addSoldier($soldier);
		$bg->addCharacter($char);

		$this->assertInternalType("array", $bg->getTroopsSummary());


	}


	public function testExceptionHandling() {
		$this->setExpectedException('Exception');
		$bg = new BattleGroup;
		$battle = new Battle;
		$bg->setBattle($battle);
		$battle->addGroup($bg);

		$bg->getEnemy();
	}
}
