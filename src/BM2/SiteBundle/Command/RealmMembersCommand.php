<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class RealmMembersCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:realm:members')
			->setDescription('Get the memberlist of a realm')
			->addArgument('realm', InputArgument::REQUIRED, 'realm name or id')
			->addOption('debug', 'd', InputOption::VALUE_NONE, 'output debug information')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$r = $input->getArgument('realm');

		if (intval($r)) { 
			$realm = $em->getRepository('BM2SiteBundle:Realm')->find(intval($r));
		} else {
			$realm=false;
		}
		if (!$realm) {
			$realm = $em->getRepository('BM2SiteBundle:Realm')->findOneByName($r);
		}

		if ($realm) {
			$output->writeln("Members of realm ".$realm->getName().":");
			foreach ($realm->findMembers() as $char) {
				$output->writeln("* ".$char->getName()." (".$char->getId().")");
			}
		} else {
			$output->writeln("cannot find realm $r"); 
		}

	}


}
