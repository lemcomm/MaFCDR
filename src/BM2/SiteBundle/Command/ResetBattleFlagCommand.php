<?php

namespace BM2\SiteBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

use BM2\SiteBundle\Entity\Settlement;

class ResetBattleFlagCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:reset:battles')
			->setDescription('Resets the battling flag, allowing battles to run again.')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$em->createQuery('UPDATE BM2SiteBundle:Setting s SET s.value = false WHERE s.name = :name')->setParameters(['name'=>'battling'])->execute();

		$output->writeln('Battle Flag Reset.');
	}
}
