<?php

namespace BM2\SiteBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class StatisticsTradeCommand extends ContainerAwareCommand {

	private $em;

	protected function configure() {
		$this
		->setName('maf:stats:trade')
		->setDescription('statistics: trade network')
      ->addArgument('resource', InputArgument::OPTIONAL, 'only one resource')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$resource = $input->getArgument('resource');
		$this->em = $this->getContainer()->get('doctrine')->getManager();

		$trades = $this->em->getRepository('BM2SiteBundle:Trade')->findAll();
		if ($resource) {
			$output->writeln("generating trade network data for $resource.");
			$type = $this->em->getRepository('BM2SiteBundle:ResourceType')->findOneByName($resource);
			if ($type) {
				$input = new ArrayCollection($trades);
				$trades = $input->filter(
					function($entry) use ($type) {
						return ($entry->getResourceType()==$type);
					}
				);
			} else {
				$output->writeln("<error>cannot find resource $resource</error>");
			}
		} else {
			$output->writeln("generating generic trade network data");
		}
		$places = array();
		$matrix = array();
		foreach ($trades as $trade) {
			$places[$trade->getSource()->getId()] = array($trade->getSource()->getName(), $trade->getSource()->getGeoData()->getCenter());
			$places[$trade->getDestination()->getId()] = array($trade->getDestination()->getName(), $trade->getDestination()->getGeoData()->getCenter());

			$matrix[$trade->getSource()->getId()][$trade->getDestination()->getId()] = $trade->getAmount();
		}

		$coordinates = "";
		$names = "";
		$data = "";
		foreach ($places as $id => $place) {
			$names .= $place[0]."\n";
			$coordinates .= $place[1]->getX()." ".$place[1]->getY()."\n";
			$first = true;
			foreach ($places as $sub_id => $sub_data) {
				if ($first) {
					$first = false;
				} else {
					$data .= " ";
				}
				if (isset($matrix[$id][$sub_id])) {
					$data .= $matrix[$id][$sub_id];
				} else {
					$data .= 0;
				}
			}
			$data.="\n";
		}

		$output->writeln("saving results...");

 		file_put_contents("coordinates.txt", $coordinates);
 		file_put_contents("names.txt", $names);
 		file_put_contents("flowdata.txt", $data);
	}


}


