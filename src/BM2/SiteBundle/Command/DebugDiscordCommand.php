<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DebugDiscordCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:debug:discord')
			->setDescription('Debug the discord push with a battle report')
			->addArgument('i', InputArgument::REQUIRED, 'Which report are we pushing? BattleReport::id.')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$id = $input->getArgument('i');
		$output->writeln("Looking for BattleReport #".$id);
		$entity = $em->getRepository("BM2SiteBundle:BattleReport")->findOneById($id);
		$this->getContainer()->get('notification_manager')->spoolBattle($entity, 5);
	}


}
