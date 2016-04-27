<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\EventLog;
use BM2\SiteBundle\Entity\Event;


class CharacterEntityTest extends GenericEntityTest {

	private $alice, $bob;

	public function setUp() { 
		parent::setUp();

		$this->alice = new Character();
		$this->alice->setName('Alice')->setAlive(true)->setMale(false);
		$this->alice->setTravelLocked(false)->setProgress(0.25)->setSpeed(0.4);
		$this->alice->setSuccessor(null);

		$this->bob = new Character();
		$this->bob->setName('Bob')->setAlive(true)->setMale(true);
		$this->bob->setSuccessor($this->alice);

		$this->alice->setLiege($this->bob);
	}

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\Character');
	}

	public function testSimpleData() {
		$this->assertTrue($this->bob->isAlive());

		$this->assertEquals('female', $this->alice->getGender(), "Alice should be female");
		$this->assertEquals('male', $this->bob->getGender(), "Bob should be male");
		$this->assertEquals('gender.daughter', $this->alice->gender('son'));
		$this->assertEquals('gender.his', $this->bob->gender('his'));
		$this->assertEquals('gender.dummy', $this->bob->gender('dummy'));
		$this->assertEquals('gender.dummy', $this->alice->gender('dummy'));
	
		$this->assertEquals(false, $this->alice->isUltimate());
		$this->assertEquals(true, $this->bob->isUltimate());
		$this->assertTrue($this->bob->findRealms()->isEmpty());

		$this->assertNull($this->alice->getCreated());
		$this->assertFalse($this->alice->getTravelLocked());
		$this->assertEquals(0.25, $this->alice->getProgress());
		$this->assertEquals(0.4, $this->alice->getSpeed());

		$this->assertTrue($this->bob->getActions()->isEmpty());
		$this->assertNull($this->alice->getSuccessor());
		$this->assertEquals($this->alice, $this->bob->getSuccessor());
	}

	public function testParenting() {
		$this->alice->addChild($this->bob);
		$this->bob->addParent($this->alice);
		$this->assertContains($this->bob, $this->alice->getChildren(), "Bob should have become a child of Alice");
		$this->assertContains($this->alice, $this->bob->getParents(), "Alice should have become a parent of Bob");
		$this->alice->removeChild($this->bob);
		$this->bob->removeParent($this->alice);
		$this->assertNotContains($this->bob, $this->alice->getChildren(), "Bob should not be a child of Alice anymore");
		$this->assertNotContains($this->alice, $this->bob->getParents(), "Alice should not be a parent of Bob anymore");
	}

	public function testHierarchy() {
		$eve = new Character();
		$this->alice->setLiege($this->bob);
		$this->bob->setLiege($eve);
		$this->assertEquals($eve, $this->alice->findUltimate(), "evil eve should rule everyone");
	}

}
