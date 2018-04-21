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


class RunCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:run')
			->setDescription('Run various game parts')
			->addArgument('which', InputArgument::REQUIRED, 'which part to run - (turn, hourly)')
			->addOption('time', 't', InputOption::VALUE_NONE, 'output timing information')
			->addOption('debug', 'd', InputOption::VALUE_NONE, 'output debug information')
			->addOption('quiet', 'q', InputOption::VALUE_NONE, 'suppress console output')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
        $logger = $this->getContainer()->get('logger');

		$which = $input->getArgument('which');
		$game = $this->getContainer()->get('game_runner');      
		$opt_time = $input->getOption('time');
		$opt_debug = $input->getOption('debug');

		// go...
		switch ($which) {
			case 'hourly':
				$complete = $game->runCycle('update', 600, $opt_time, $opt_debug, $output);
				if ($complete) {
					$game->nextCycle(false);
					$logger->info("update complete");
					$output->writeln("<info>update complete</info>");
				} else {
					$logger->error("update error");
					$output->writeln("<error>update complete</error>");
					$this->sendNotification("update error", $which, $complete);
				}
				break;
			case 'turn':
				$output->writeln("<info>running turn:</info>");
				$complete = $game->runCycle('turn', 1200, $opt_time, $opt_debug, $output);
				if ($complete) {
					$game->nextCycle();
					$logger->info("turn complete");
					$output->writeln("<info>turn complete</info>");
				} else {
					$logger->error("turn error");
					$output->writeln("<error>turn complete</error>");
					$this->sendNotification("turn error", $which, $complete);
				}
				break;
		}
	}

	private function sendNotification($text, $which, $code) {
		$mailer = $this->getContainer()->get('mailer');
		$spool = NULL;
		if ($mailer->getTransport()->getSpool()) {
			$spool = $mailer->getTransport()->getSpool();
		}
		$transport = $this->getContainer()->get('swiftmailer.transport.real');

		$message = \Swift_Message::newInstance()
			 ->setSubject("[Might & Fealty] Error $code running $which")
			 ->setFrom('mafserver@lemuriacommunity.org')
			 ->setTo("mafteam@lemuriacommunity.org")
			 ->setBody($text);
		$mailer->send($message, $failed);		
		if ($spool) {
			$spool->flushQueue($transport);
		}
	}

}
