<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Stopwatch\Stopwatch;


class ProcessSlumbersCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:process:slumbers')
			->setDescription('Remove long time slumberers and double check positions are held by actives.')
			->addOption('time', 't', InputOption::VALUE_NONE, 'output timing information')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
        	$logger = $this->getContainer()->get('logger');
		$em = $this->getContainer()->get('doctrine')->getManager();

		$timing = $input->getOption('time');
		if ($timing) {
			$stopwatch = new Stopwatch();
			$stopwatch->start('slumbers_cleanup');
		}
		$cm = $this->getContainer()->get('character_manager');
		$logger->info("Slumbers cleanup started...");
		$output->writeln("<info>Slumbers cleanup started!</info>");

		$now = new \DateTime('now');
		$twomos = $now->modify('-42 days'); # Deliberately reduced from 60 days to 42 days --Andrew 20230728.
		$query = $em->createQuery('SELECT c FROM BM2SiteBundle:Character c WHERE c.last_access <= :date AND c.alive = true AND c.location IS NOT NULL AND (c.retired = false OR c.retired IS NULL)');
		$query->setParameters(['date'=>$twomos]);
		$result = $query->getResult();
		if (count($result) < 1) {
			$output->writeln("  No long term slumbering found.");
		} else {
			$current = 0;
			$limit = 25;
			$logger->info("  Clearing slumberers from before ".$twomos->format('Y-m-d H:i:s'));
			$output->writeln("<info>  Clearing slumberers from before ".$twomos->format('Y-m-d H:i:s'));
			foreach ($result as $char) {
				if ($current >= $limit) {
					$logger->info("  Proc limit hit.");
					$output->writeln("<info>Proc limit hit.</info>");
					break;
				}
				$logger->info("  ".$char->getName().", ".$char->getId()." is under review as long-term slumberer.");
				$output->writeln("<info>  ".$char->getName().", ".$char->getId()." is under review as long-term slumberer.</info>");
				// dynamically create when needed
				if (!$char->getBackground()) {
					$cm->newBackground($char);
				}
				$char->getBackground()->setRetirement('Forced into retirement by the Second Ones who eventually noticed their long term slumbering.');
				$cm->retire($char);
				$logger->info("  ".$char->getName().", ".$char->getId()." has been retired.");
				$output->writeln("<info>  ".$char->getName().", ".$char->getId()." has been retired</info>");
				$current++;
			}
		}
		$logger->info("Slumbers cleanup completed");
		$output->writeln("<info>Slumbers cleanup completed</info>");
		if ($timing) {
			$event = $stopwatch->stop('slumbers_cleanup');
			$logger->info("Slumbers Cleanup: ".date("g:i:s").", ".($event->getDuration()/1000)." s, ".(round($event->getMemory()/1024)/1024)." MB");
			$output->writeln("<info>Slumbers Cleanup: ".date("g:i:s").", ".($event->getDuration()/1000)." s, ".(round($event->getMemory()/1024)/1024)." MB</info>");
		}
	}

}
