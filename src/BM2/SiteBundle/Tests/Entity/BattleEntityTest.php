<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\Battle;
use BM2\SiteBundle\Entity\BattleGroup;


class BattleEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\Battle');
	}

	public function testOthers() {
		$battle = new Battle;
		$this->assertEquals("battle", $battle->getName());
		$this->assertNull($battle->getAttacker());
		$this->assertNull($battle->getDefender());

		$a = new BattleGroup;
		$a->setAttacker(true);
		$a->setBattle($battle);
		$battle->addGroup($a);

		$b = new BattleGroup;
		$b->setAttacker(false);
		$b->setBattle($battle);
		$battle->addGroup($b);

		$this->assertEquals($a, $battle->getAttacker());
		$this->assertEquals($b, $battle->getDefender());
	}

}
