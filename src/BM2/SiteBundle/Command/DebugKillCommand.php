<?php

namespace BM2\SiteBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class DebugKillCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:debug:kill')
			->setDescription('Debug command for fixing failed deaths (by rerunning them)')
			->addArgument('c', InputArgument::REQUIRED, 'Which character are we killing? Character::id.')
			->addArgument('k', InputArgument::OPTIONAL, 'Who killed them? Character::id. Can be null.')
			->addArgument('m', InputArgument::OPTIONAL, 'Which message should we use for events? Text.')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
                $stopwatch = new Stopwatch();
		$id = $input->getArgument('c');
                $output->writeln("Looking for Character #".$id);
		$char = $em->getRepository('BM2SiteBundle:Character')->findOneById($id);
		$killer = $input->getArgument('k');
                $output->writeln("Looking for Killer #".$killer);
		if ($killer && $killer == 'null') {
			$killer = null;
		} elseif ($killer) {
			$killer = $em->getRepository('BM2SiteBundle:Character')->findOneById($killer);
		}
		$msg = $input->getArgument('m');

		$cm = $this->getContainer()->get('character_manager');

		if ($cm->kill($char, $killer, false, $msg)) {
	                $output->writeln('Character '.$char->getName().' ('.$id.') killed succesfully!');
		} else {
                	$output->writeln("Something went wrong");
		}

	}
}
