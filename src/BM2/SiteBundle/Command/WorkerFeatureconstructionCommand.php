<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Service\History;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;


class WorkerFeatureconstructionCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:worker:construction:features')
			->setDescription('Featureconstruction - worker component - do not call directly')
			->addArgument('start', InputArgument::OPTIONAL, 'start character id')
			->addArgument('end', InputArgument::OPTIONAL, 'end character id')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$container = $this->getContainer();
		$em = $container->get('doctrine')->getManager();
		$economy = $container->get('economy');
		$history = $container->get('history');
		$start = $input->getArgument('start');
		$end = $input->getArgument('end');

		$query = $em->createQuery('SELECT f FROM BM2SiteBundle:GeoFeature f JOIN f.type t JOIN f.geo_data g WHERE g.id >= :start and g.id <= :end AND f.workers > 0 OR (f.workers = 0 AND f.condition < 0 AND f.condition > -t.build_hours)');
		$query->setParameters(array('start'=>$start, 'end'=>$end));
		foreach ($query->getResult() as $feature) {
			if ($feature->getWorkers() > 0) {
				// construction
				if ($economy->FeatureConstruction($feature)) {
					// construction finished
					$history->logEvent(
						$feature->getGeoData()->getSettlement(),
						'report.feature.complete',
						array('%link-featuretype%'=>$feature->getType()->getId()),
						History::LOW
					);
				}
			} else {
				// deterioration
				$takes = $feature->getType()->getBuildHours();
				$loss = rand(10, $takes/100) + rand(0, $takes/200);
				$result = $feature->ApplyDamage($loss);

				$history->logEvent(
					$feature->getGeoData()->getSettlement(),
					'event.feature2.'.$result,
					array('%link-featuretype%'=>$feature->getType()->getId(), '%name%'=>$feature->getName()),
					$result=='destroyed'?History::MEDIUM:History::LOW, true, $result=='destroyed'?30:15
				);

				if ($result == 'destroyed') {
					$output->writeln($feature->getType()->getName().' '.$feature->getName().' has deteriorated away.');
				}
			}
		}


		$em->flush();
	}


}
