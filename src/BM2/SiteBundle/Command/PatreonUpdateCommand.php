<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Service\History;
use Doctrine\Common\Collections\ArrayCollection;
use Patreon\API as PAPI;
use Patreon\OAuth as POA;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class PatreonUpdateCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:patreon:update')
			->setDescription('Updates all patron information via the Patreon API')
			->addArgument('timing', InputArgument::OPTIONAL, 'Seconds to wait between API calls')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$pm = $this->getContainer()->get('payment_manager');
		$timing = $input->getArgument('timing');
		if (!$timing) {
			$timing = 0;
		}

		list($free, $patron, $active, $credits, $expired, $storage, $banned) = $pm->paymentCycle(true);
		$output->writeln("$free free accounts");
		$output->writeln("$patron patron accounts");
		$output->writeln("$storage accounts moved into storage");
		$output->writeln("$credits credits collected from $active users");
		$output->writeln("$expired accounts with insufficient credits");
		$output->writeln("$banned accounts banned and set to level 0");

		return true;
	}
}
