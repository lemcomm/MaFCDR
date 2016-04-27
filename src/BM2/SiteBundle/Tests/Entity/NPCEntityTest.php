<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\NPC;


class NPCEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\NPC');
	}


	public function testExtras() {
		$npc = new NPC();
		$npc->setAlive(true)->setWounded(0)->setHungry(0)->setLocked(false);

		$this->assertTrue($npc->isAlive());
		$this->assertTrue($npc->isActive());
		$this->assertFalse($npc->isHungry());
		$this->assertFalse($npc->isLocked());
		$this->assertFalse($npc->isRouted());

		$npc->wound(5);
		$this->assertTrue($npc->isAlive());
		$this->assertFalse($npc->isActive());

		$npc->setAlive(true)->setWounded(0)->setHungry(0)->setLocked(false);
		$npc->makeHungry(10);
		$this->assertTrue($npc->isAlive());
		$this->assertTrue($npc->isActive());
		$this->assertTrue($npc->isHungry());
		$npc->feed();
		$this->assertTrue($npc->isHungry());
		$npc->feed();
		$this->assertFalse($npc->isHungry());

		$npc->setAlive(true)->setWounded(0)->setHungry(0)->setLocked(false);
		$npc->kill();
		$this->assertFalse($npc->isAlive());
		$this->assertFalse($npc->isActive());

		$npc->setAlive(true)->setWounded(0)->setHungry(0)->setLocked(false);
		$npc->HealOrDie();
		$this->assertTrue($npc->isAlive());
		$npc->setWounded(1000);
		$npc->HealOrDie();
		$this->assertFalse($npc->isAlive());

		$npc->setExperience(0);
		$npc->gainExperience(5);
		$this->assertEquals(5, $npc->getExperience());

		$npc->setHungry(1);
		$npc->feed();
		$this->assertEquals(0, $npc->getHungry());

		$npc->setHungry(-10);
		$npc->feed();
		$this->assertEquals(0, $npc->getHungry());
	}
}
