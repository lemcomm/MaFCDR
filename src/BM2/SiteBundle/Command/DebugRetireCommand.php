<?php

namespace BM2\SiteBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class DebugRetireCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:debug:retire')
			->setDescription('Debug command for fixing failed retirements (by rerunning them)')
			->addArgument('c', InputArgument::REQUIRED, 'Which character are we killing? Character::id.')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$stopwatch = new Stopwatch();
		$id = $input->getArgument('c');
		$output->writeln("Looking for Character #".$id);
		$char = $em->getRepository('BM2SiteBundle:Character')->findOneById($id);

		$cm = $this->getContainer()->get('character_manager');

		if ($cm->retire($char)) {
			$output->writeln('Character '.$char->getName().' ('.$id.') retired succesfully!');
		} else {
			$output->writeln("Something went wrong");
		}

	}
}
