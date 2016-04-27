<?php

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;

class CharacterManagerTest extends \Codeception\TestCase\Test {

	protected $em;
	protected $cm;
	protected $test_user;

	function _before() {
		$this->em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->cm = $this->getModule('Symfony2')->container->get('character_manager');
		$this->test_user = $this->em->getRepository('BM2SiteBundle:User')->findOneByUsername('admin');
		$this->assertNotNull($this->test_user);
	}


	public function testBirth() {
		$alice = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Alice Kepler');
		$this->assertNotNull($alice, "test character not found");
		$character = $this->cm->create($this->test_user, 'Newbie', 'm', true, null, $alice);

		$this->assertObjectHasAttribute('name', $character);
		$this->assertObjectHasAttribute('alive', $character);
		$this->assertTrue($character->isAlive());
		$this->assertEquals($alice->getGeneration()+1, $character->getGeneration());

		// TODO:
		// test if parentage is set correctly and if messages are generated correctly
	}

	public function testDeath() {
		// TODO:
		// test if dead, if messages and inheritance work correctly and soldiers are disbanded

		$frank = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Frank');
		$this->assertNotNull($frank, "test character not found");

		$this->cm->kill($frank);

		$this->assertFalse($frank->isAlive());
		$this->assertNull($frank->getLocation());
	}

}
