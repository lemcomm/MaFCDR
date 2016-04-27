<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\Soldier;
use BM2\SiteBundle\Entity\EquipmentType;
use BM2\SiteBundle\Entity\Character;


class SoldierEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\Soldier');
	}

	public function testPower() {
		$char = new Character;

		$spear = new EquipmentType;
		$spear->setMelee(30);
		$leather = new EquipmentType;
		$leather->setDefense(20);
		$soldier = new Soldier;
		$soldier->setAlive(true)->setWounded(0);
		$soldier->setWeapon($spear)->setArmour($leather);
		$soldier->setCharacter($char); $char->getSoldiers()->add($soldier);

		$ranged = $soldier->RangedPower();
		$melee = $soldier->MeleePower();
		$defense = $soldier->DefensePower();
		$this->assertEquals(0, $ranged);
		$this->assertEquals(30, $melee);
		$this->assertEquals(25, $defense);

		for ($i=0;$i<20;$i++) {
			$soldier = new Soldier;
			$soldier->setAlive(true)->setWounded(0);
			$soldier->setWeapon($spear)->setArmour($leather);
			$soldier->setCharacter($char); $char->getSoldiers()->add($soldier);		
		}
		$ranged = $soldier->RangedPower();
		$melee = $soldier->MeleePower();
		$defense = $soldier->DefensePower();
		$this->assertEquals(0, $ranged);
		$this->assertEquals(2656, round($melee*100));
		$this->assertEquals(25, $defense);
	}
}
