<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Common\Collections\ArrayCollection;



class RoadNetworkCommand extends ContainerAwareCommand {

	private $em;
	private $travel_points = 5000;
	private $max_distance = 50000;

	protected function configure() {
		$this
		->setName('maf:roads')
		->setDescription('calculate road network')
		->addArgument('settlement', InputArgument::REQUIRED, 'id of the settlement to calculate road network for')
		;
	}


	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->em = $this->getContainer()->get('doctrine')->getManager();
		$settlement_id = $input->getArgument('settlement');

		$settlement = $this->em->getRepository('BM2SiteBundle:Settlement')->find($settlement_id);
		$marker = $settlement->getGeoMarker();

		$this->destinations = array();

		$this->getDestinations($marker, $this->travel_points);
		echo "\n\nFinal Destinations:\n";
		foreach ($this->destinations as $feature) {
			if ($feature['type'] == "settlement") {
				$output->writeln($feature['name']." - ".round($feature['distance'])." miles - ".$feature['cost']." cost");
			}
		}
	}

	protected function getDestinations($feature, $travel_points, $distance=0) {
		$query = $this->em->createQuery('SELECT r,ST_Length(r.path) as length,ST_Length(r.path)/r.quality as cost FROM BM2SiteBundle:Road r JOIN r.waypoints w WHERE w = :me');
		$query->setParameter('me', $feature);

		foreach ($query->getResult() as $row) {
			$road = $row[0];
			foreach ($road->getWaypoints() as $wp) {
				if ($wp != $feature) {
					// check if we exist
					if (isset($this->destinations[$wp->getId()])) {
						if ($this->destinations[$wp->getId()]['distance'] > $distance+$row['length']) {
							$add = true;
						} else {
							$add = false;
						}
					} else {
						$add = true;
					}
					if ($add) {
						$this->destinations[$wp->getId()] = array('distance' => $distance+$row['length'], 'type' => $wp->getType()->getName(), 'name' => $wp->getName());
						if ($distance < $this->max_distance || $wp->getType()->getName() != "settlement") {
							$this->getDestinations($wp, $travel_points-$row['cost'], $distance+$row['length']);
						}
					}
				}
			}
		}
	}

}


