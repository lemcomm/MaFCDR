<?php


class GameRunnerServiceTest extends \Codeception\TestCase\Test {

	protected $em;
	protected $game;

	function _before() {
		$this->em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->game = $this->getModule('Symfony2')->container->get('game_runner');
	}


	public function testRun() {
		$complete = $this->game->runCycle('turn');

		// this is really very minimal testing...
		$this->assertTrue($complete);
	}

	// TODO: test individual parts here with mock data...

}
