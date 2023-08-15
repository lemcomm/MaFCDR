<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugRealmPosCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:debug:realmpos')
			->setDescription('Debug command for giving a character a realm position')
			->addArgument('c', InputArgument::REQUIRED, 'Which character are we appointing? Character::id.')
			->addArgument('r', InputArgument::REQUIRED, 'Which position are they getting appointed to? RealmPosition::id.')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$c = $input->getArgument('c');
		$r = $input->getArgument('r');
		$output->writeln("Looking for Character #".$c);
		$char = $em->getRepository('BM2SiteBundle:Character')->findOneById($c);
		$output->writeln("Looking for RealmPosition #".$r);
		$rpos = $em->getRepository('BM2SiteBundle:RealmPosition')->findOneById($r);

		if ($rpos && $char) {
			$rpos->addHolder($char);
			$char->addPosition($rpos);
			$em->flush();
			$output->writeln("Character ".$char->getName()." added to RealmPosition #".$r);
		} else {
			$output->writeln("Bad inputs?");
		}

	}
}
