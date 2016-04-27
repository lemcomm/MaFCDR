<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\RegionFamiliarity;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ProcessSpottingCommand extends AbstractProcessCommand {

	protected $em;
	protected $stopwatch;
	protected $opt_time;

	protected function configure() {
		$this
			->setName('maf:process:spotting')
			->setDescription('Generate spotting alarms')
			->addOption('time', 't', InputOption::VALUE_NONE, 'output timing information')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->output = $output;
		$this->opt_time = $input->getOption('time');
		$this->em = $this->getContainer()->get('doctrine')->getManager();

		$this->start('spotting');

		// TODO: clean up spot events older than 3 days (still which leaves up to 72 spot events per target!)
		$query = $this->em->createQuery('DELETE FROM BM2SiteBundle:SpotEvent s WHERE s.ts < :outdated');
		$outdated = new \DateTime("now");
		$outdated->sub(new \DateInterval("P3D"));
		$query->setParameter('outdated', $outdated);
		$query->execute();

		// outdate all past events before creating new ones below
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:SpotEvent s SET s.current = false');
		$query->execute();

		// TODO: review this, it might be overkill once we correctly set it when people go inactive
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Character c SET c.spotting_distance=0, c.visibility=5');
		$query->execute();

		$this->process('spot:update', 'Character');
		$this->process('spot:scouts', 'Character');
// not yet implemented:
//		$this->process('spot:towers', 'Character');

		$this->finish('spotting');
	}

}
