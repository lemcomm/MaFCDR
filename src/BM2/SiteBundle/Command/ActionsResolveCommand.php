<?php

namespace BM2\SiteBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * @codeCoverageIgnore
 */
class ActionsResolveCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:actions:progress')
			->setDescription('Run an action progress')
			->addOption('debug', 'd', InputOption::VALUE_NONE, 'output debug information')
		;
	}


	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$ar = $this->getContainer()->get('action_resolution');

		$ar->progress();
		# In order to be error tolerant, each action flushes upon completion. Meaning one error breaks one action.
	}

}
