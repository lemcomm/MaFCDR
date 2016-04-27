<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ProcessDailyCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:process:daily')
			->setDescription('Run the once-per-day updates')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();

		$query = $em->createQuery('UPDATE BM2SiteBundle:User u SET u.new_chars_limit = u.new_chars_limit +1 WHERE u.new_chars_limit < 10');
		$query->execute();
		$em->flush();
	}

}
