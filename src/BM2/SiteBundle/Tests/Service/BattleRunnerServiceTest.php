<?php

use CrEOF\Spatial\PHP\Types\Geometry\Point;

use BM2\SiteBundle\Entity\Battle;
use BM2\SiteBundle\Entity\BattleGroup;

class BattleRunnerServiceTest extends \Codeception\TestCase\Test {

	protected $em;
	protected $br;

	function _before() {
		$this->em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->br = $this->getModule('Symfony2')->container->get('battle_runner');
		$this->appstate = $this->getModule('Symfony2')->container->get('app_state');
	}


	public function testBattle() {
		$battle = new Battle;
		$battle->setLocation(new Point(0,0));
		$battle->setSiege(false);
		$battle->setStarted(new \DateTime("now"));
		$battle->setComplete(new \DateTime("now"));
		$battle->setInitialComplete(new \DateTime("now"));

		$a = new BattleGroup;
		$a->setAttacker(true);
		$a->setBattle($battle);
		$battle->addGroup($a);

		$b = new BattleGroup;
		$b->setAttacker(false);
		$b->setBattle($battle);
		$battle->addGroup($b);

		$david = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('David Stanis');
		$eve = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Eve');

		$reports = count($this->em->getRepository('BM2SiteBundle:BattleReport')->findAll());

		$a->addCharacter($david);
		$david->addBattlegroup($a);
		$b->addCharacter($eve);
		$eve->addBattlegroup($b);

		// need this because battlerunner flushes
		$this->em->persist($battle);
		$this->em->persist($a);
		$this->em->persist($b);

		$this->br->enableLog();
		$this->br->run($battle, 1);
		$this->br->disableLog();

		$this->assertEquals($reports+1, count($this->em->getRepository('BM2SiteBundle:BattleReport')->findAll()));

	}


}
