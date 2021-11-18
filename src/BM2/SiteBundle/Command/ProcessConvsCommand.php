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


class ProcessConvsCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:process:convs')
			->setDescription('Process Conversation Permission Updates (ideally does nothing)')
			->addOption('time', 't', InputOption::VALUE_NONE, 'output timing information')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
        	$logger = $this->getContainer()->get('logger');

		$game = $this->getContainer()->get('game_runner');
		$timing = $input->getOption('time');

		$complete = $game->runConversationsCleanup();
		if ($timing) {
			$stopwatch = new Stopwatch();
			$stopwatch->start('conv_cleanup');
		}
		if ($complete) {
			$logger->info("Conversation cleanup completed");
			$output->writeln("<info>Conversation cleanup completed</info>");
		} else {
			$logger->info("Conversation cleanup errored!");
			$output->writeln("<error>Conversation cleanup errored!</error>");
		}
		if ($timing) {
			$event = $stopwatch->stop('conv_cleanup');
			$logger->info("Conversation Cleanup: ".date("g:i:s").", ".($event->getDuration()/1000)." s, ".(round($event->getMemory()/1024)/1024)." MB");
		}
	}

}
