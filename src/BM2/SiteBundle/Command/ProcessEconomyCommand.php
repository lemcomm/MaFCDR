<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\RegionFamiliarity;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ProcessEconomyCommand extends AbstractProcessCommand {

	protected $em;
	protected $opt_time;

	protected function configure() {
		$this
			->setName('maf:process:economy')
			->setDescription('Process economy cycle and construction')
			->addOption('time', 't', InputOption::VALUE_NONE, 'output timing information')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->em = $this->getContainer()->get('doctrine')->getManager();
		$this->output = $output;
		$this->opt_time = $input->getOption('time');
		$this->cycle = $this->getContainer()->get('appstate')->getCycle();

		$this->start('economy');
		$this->ExtraEffects();
		$this->resetResupply();
		$this->fixWorkers();
		$this->em->flush();
		$this->process('economy', 'Settlement');
		$this->finish('economy');

		$this->start('construction');
		$this->process('construction:buildings', 'Settlement');
		$this->process('construction:features', 'GeoData');
		$this->process('construction:roads', 'GeoData');
		$this->finish('construction');

	}

	protected function ExtraEffects() {
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Settlement s SET s.war_fatigue = s.war_fatigue - 1 WHERE s.war_fatigue > 0');
		$query->execute();
	}


	protected function resetResupply() {
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:SettlementPermission p SET p.value_remaining = p.value WHERE p.value IS NOT NULL');
		$query->execute();
	}

	public function fixWorkers() {
		// check for crazy worker values
		$query = $this->em->createQuery('SELECT f FROM BM2SiteBundle:GeoFeature f JOIN f.geo_data g JOIN g.settlement s WHERE f.workers > 0 AND f.workers * s.population < 1');
		foreach ($query->getResult() as $feature) {
			$this->output->writeln("feature ".$feature->getName()." has low workers");
			$feature->setWorkers(0);
		}
		$query = $this->em->createQuery('SELECT r FROM BM2SiteBundle:Road r JOIN r.geo_data g JOIN g.settlement s WHERE r.workers > 0 AND r.workers * s.population < 1');
		foreach ($query->getResult() as $road) {
			$this->output->writeln("road ".$road->getId()." has low workers");
			$road->setWorkers(0);
		}
		$query = $this->em->createQuery('SELECT b FROM BM2SiteBundle:Building b JOIN b.settlement s WHERE b.workers > 0 AND b.workers * s.population < 1');
		foreach ($query->getResult() as $building) {
			$this->output->writeln("building ".$building->getType()->getName()." has low workers");
			$building->setWorkers(0);
		}
	}
}
