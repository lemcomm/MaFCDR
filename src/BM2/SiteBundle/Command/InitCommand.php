<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\Building;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * @codeCoverageIgnore
 */
class InitCommand extends ContainerAwareCommand {

	private $em;

	protected function configure() {
		$this
			->setName('maf:init')
			->setDescription('Initialize a new game world and set some default values')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->em = $this->getContainer()->get('doctrine')->getManager();
		$food = $this->em->getRepository('BM2SiteBundle:ResourceType')->findOneByName("food");

		$game = $this->getContainer()->get('game_runner');      
		$settlements = $this->em->getRepository('BM2SiteBundle:Settlement')->findAll();
		foreach ($settlements as $settlement) {
			echo ".";
			$settlement->setPopulation($settlement->findResource($food)->getAmount()*2);

			foreach ($game->autoBuildings($settlement) as $autobuilding) {
				$building = new Building();
				$building->setType($autobuilding);
				$building->setSettlement($settlement);
				$building->setActive(true);
				$building->setCondition(0);
				$building->setWorkers(0)->setResupply(0)->setCurrentSpeed(1.0)->setFocus(0);
				$this->em->persist($building);
			}			
		}
		echo "\n";
		$this->em->flush();
	}


}
