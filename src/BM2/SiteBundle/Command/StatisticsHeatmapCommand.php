<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class StatisticsHeatmapCommand extends ContainerAwareCommand {

	private $em;
	private $first = true;

	protected function configure() {
		$this
		->setName('maf:stats:heatmap')
		->setDescription('statistics: generate the source data for various heatmaps')
		->addArgument('which', InputArgument::REQUIRED, 'which heatmap to generate, one of characters, battles, deaths')
		->addArgument('since', InputArgument::OPTIONAL, 'since which game cycle (only useful for deaths and battles)')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->em = $this->getContainer()->get('doctrine')->getManager();
		$since = $input->getArgument('since')?:0;

		switch ($input->getArgument('which')) {
			case 'characters':
				$this->header();
				$this->map_characters();
				$this->footer();
				break;
			case 'familiarity':
				$this->header();
				$this->map_familiarity();
				$this->footer();
				break;
			case 'battles':
				$this->header();
				$this->map_battles($since);
				$this->footer();
				break;
			case 'deaths':
				$this->header();
				$this->map_deaths($since);
				$this->footer();
				break;
			default:
				echo "unknown heatmap - choose one of characters, battles, deaths";
				exit;
		}
	}

	private function header() {
		echo '{"type":"FeatureCollection","features":['."\n";
	}

	private function footer() {
		echo "\n]}\n";
	}

	private function feature($id, $coordinates, $properties=null) {
		if ($this->first) {
			$this->first=false;
		} else {
			echo ",\n";
		}
		$data = array(
			"type"=>"Feature",
			"id"=>$id,
			"geometry"=>array(
				"type"=>"Point",
				"coordinates"=>array($coordinates->getX(), $coordinates->getY())
				)
			);
		if ($properties) {
			$data["properties"] = $properties;
		}
		echo json_encode($data);
	}

	private function map_characters() {

	}

	private function map_familiarity() {
		$query = $this->em->createQuery('SELECT g.id, g.center, sum(f.amount) as total FROM BM2SiteBundle:RegionFamiliarity f JOIN f.geo_data g GROUP BY g');

		foreach ($query->getResult() as $row) {
			$this->feature($row['id'], $row['center'], array('total'=>$row['total']));
		}

	}

	private function map_battles($since) {
		$battles = $this->em->getRepository('BM2SiteBundle:BattleReport')->findAll();

		foreach ($battles as $battle) {
			$total = 0;
			if ($battle->getStart()) foreach ($battle->getStart() as $side) {
				foreach ($side as $type=>$amount) {
					$total += $amount;
				}
			}
			$this->feature($battle->getId(), $battle->getLocation(), array('total'=>$total));
		}
	}

	private function map_deaths($since) {
		$query = $this->em->createQuery('SELECT r FROM BM2SiteBundle:BattleReport r WHERE r.cycle >= :since');
		$query->setParameter('since', $since);

		foreach ($query->getResult() as $battle) {
			$kills = 0; $wounds = 0;
			if ($combat = $battle->getCombat()) {
				if (isset($combat['ranged'])) {
					foreach ($combat['ranged'] as $ranged) {
						if (isset($ranged['kill'])) {
							$kills += $ranged['kill'];
						} 
						if (isset($ranged['wound'])) {
							$wounds += $ranged['wound'];
						} 
					}
				}
				if (isset($combat['melee'])) {
					foreach ($combat['melee'] as $melee) {
						foreach ($melee as $group) {
							if (isset($group['kill'])) {
								$kills += $group['kill'];
							}
							if (isset($group['wound'])) {
								$wounds += $group['wound'];
							}
						}
					}
				}
			}
			if ($wounds>0 || $kills>0) {
				$this->feature($battle->getId(), $battle->getLocation(), array('wounds'=>$wounds, 'kills'=>$kills, 'casualties'=>$wounds+$kills));
			}
		}
		
	}

}


