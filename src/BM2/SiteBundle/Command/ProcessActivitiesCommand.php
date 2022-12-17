<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ProcessActivitiesCommand extends ContainerAwareCommand {

	protected $em;
	protected $output;

	protected function configure() {
		$this
			->setName('maf:process:activities')
			->setDescription('Run activity runners')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$actMan = $this->getContainer()->get('activity_manager');
		$this->output = $output;
		$actMan->runAll();
		$this->output->writeln("...activities complete");
	}


}
