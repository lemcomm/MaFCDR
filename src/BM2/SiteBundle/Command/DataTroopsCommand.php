<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class DataTroopsCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
		->setName('maf:data:troops')
		->setDescription('data: troops location/density')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();

		$id = 0;

		echo '{"type":"FeatureCollection","features":[';

		$query = $em->createQuery('SELECT c, ST_AsGeoJSON(c.location) as json FROM BM2SiteBundle:Character c WHERE c.alive = true AND c.location IS NOT NULL');
		foreach ($query->getResult() as $data) {
			$character = $data[0];
			$size = $character->getVisualSize();
			$data = array(
				'type' => 'Feature',
				'id' => $id++,
				'properties' => array(
					'size' => $size,
					),
				'geometry' => json_decode($data['json'])
			);
			if ($id>1) {echo ",";}
			echo json_encode($data);
		}

		$query = $em->createQuery('SELECT s, ST_AsGeoJSON(g.center) as json FROM BM2SiteBundle:Settlement s JOIN s.geo_data g');
		foreach ($query->getResult() as $data) {
			$settlement = $data[0];
			$militia = $settlement->getActiveMilitia();
			$size = 0;
			foreach ($militia as $m) {
				$size += $m->getVisualSize();
			}
			$data = array(
				'type' => 'Feature',
				'id' => $id++,
				'properties' => array(
					'size' => $size,
					),
				'geometry' => json_decode($data['json'])
			);
			if ($id>1) {echo ",";}
			echo json_encode($data);
		}

		echo ']}';
	}


}
