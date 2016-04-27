<?php

use BM2\SiteBundle\Entity\Action;

class ActionResolutionTest extends \Codeception\TestCase\Test {

	protected $em;
	protected $ar;
	protected $appstate;

	function _before() {
		$this->em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->ar = $this->getModule('Symfony2')->container->get('action_resolution');
		$this->appstate = $this->getModule('Symfony2')->container->get('app_state');
	}

	public function testRename() {
		$village = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Thabeholan');
		$this->assertNotNull($village);
		$owner = $village->getOwner();
		$this->assertNotNull($owner);

		$act = new Action;
		$act->setType('settlement.rename')->setCharacter($owner)->setTargetSettlement($village);
		$act->setStringValue("new name");
		$owner->addAction($act);
		$act->setBlockTravel(false);

		$result = $this->ar->queue($act, true);
		$this->assertTrue($result['success']);
		$this->assertFalse($result['immediate']);
//		$this->em->flush(); // FIXME: this fails with the weirdest error message -- if I need it, this appears to work: $this->codeGuy->flushToDatabase();

		$this->assertTrue($owner->getActions()->contains($act)); // FIXME: this fails and I'm not sure why

		$result = $this->ar->resolve($act);
		$this->assertTrue($result);
		$this->assertEquals("new name", $village->getName());
		$this->assertFalse($owner->getActions()->contains($act));


		$act = new Action;
		$act->setType('settlement.rename')->setCharacter($owner)->setTargetSettlement($village);
		$act->setStringValue("second test");
		$act->setBlockTravel(false);

		$this->appstate->setGlobal('immediateActions', true);
		$result = $this->ar->queue($act);
		$this->assertTrue($result['success']);
		$this->assertTrue($result['immediate']);
//		$this->em->flush(); // FIXME: this fails with the weirdest error message
		$this->assertEquals("second test", $village->getName());
		$this->assertFalse($owner->getActions()->contains($act));
	}


}
