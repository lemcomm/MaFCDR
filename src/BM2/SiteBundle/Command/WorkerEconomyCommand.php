<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Service\History;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Common\Collections\ArrayCollection;


class WorkerEconomyCommand extends ContainerAwareCommand {

	protected $em;
	protected $generator;
	protected $economy;

	protected function configure() {
		$this
			->setName('maf:worker:economy')
			->setDescription('Economy - worker component - do not call directly')
			->addArgument('start', InputArgument::OPTIONAL, 'start settlement id')
			->addArgument('end', InputArgument::OPTIONAL, 'end settlement id')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$container = $this->getContainer();
		$this->em = $container->get('doctrine')->getManager();
		$this->generator = $container->get('generator');
		$this->economy = $container->get('economy');
	        $logger = $this->getContainer()->get('logger');
		$start = $input->getArgument('start');
		$end = $input->getArgument('end');

		$memory_limit = $this->return_bytes(ini_get('memory_limit'));

		$query = $this->em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s WHERE s.id >= :start AND s.id <= :end');
		$query->setParameters(array('start'=>$start, 'end'=>$end));
		$iterableResult = $query->iterate();
		while ($row = $iterableResult->next()) {
			$settlement = $row[0];
			// workaround for our calculations below causing errors on 0 values
			if ($settlement->getPopulation()<5) {
				$settlement->setPopulation(5);
				continue;
			}

			// check and update trades, food and wealth production
			$WealthProduction = 0;
			foreach ($this->economy->getResources() as $resource) {
				if (!$settlement->getSiege() || ($settlement->getSiege() && !$settlement->getSiege()->getEncircled())) {
					$production = $this->economy->ResourceProduction($settlement, $resource, false, true); // with forced recalculation to update building effects
					$WealthProduction += $production * $resource->getGoldValue();
					$tradebalance = $this->economy->TradeBalance($settlement, $resource);
					// wealth counts trade for 10%, but even outgoing trade adds (networking effects)
					if ($tradebalance < 0) {
						$tradebalance += $this->economy->fixTrades($settlement, $resource, $production, $tradebalance);
					}
					$WealthProduction += ($production + abs($tradebalance)*0.1) * $resource->getGoldValue();

					// calculate supply and update storage
					$demand = $this->economy->ResourceDemand($settlement, $resource);
					$available = $production + $tradebalance;
					$available = $this->economy->updateSupplyAndStorage($settlement, $resource, $demand, $available);

					// growth or starvation
					if ($resource->getName()=='food') {
						if ($available <= 0) {
							$shortage = 1.0;
						} else {
							$shortage = ($demand - $available) / $available;
						}
						$logger->info("food in ".$settlement->getName()." (".$settlement->getId()."): $production + $tradebalance (+storage) = $available of $demand = ".(round($shortage*100)/100));
						$this->economy->FoodSupply($settlement, $shortage);
					}
				} else {
					$logger->info("skipping ".$settlement->getName()." (".$settlement->getId().") as it is encircled.");
				}
			}

			// taxation
			if ($settlement->getOwner()) {
				// no tax collection in free settlements
				if (is_nan($WealthProduction)) {
	//				$logger->warning("NAN for WealthProduction for ".$settlement->getId()."/".$resource->getType()->getName());
				} else {
					$settlement->setGold(round($settlement->getGold() * 0.9 + $WealthProduction));
				}
			}

			if (!$settlement->getSiege() || !$settlement->getSiege()->getEncircled()) {
				// check workforce
				$this->economy->checkWorkforce($settlement);
			}

			if (memory_get_usage() > $memory_limit * 0.75) {
				echo "running out of memory... refreshing...\n";
				$this->em->flush();
				$this->em->clear();
			}
		}

		echo "...flushing...\n";
		$this->em->flush();
	}

	private function return_bytes($val) {
		$val = trim($val);
		$last = strtolower($val[strlen($val)-1]);
		$val = substr($val, 0, -1);
		switch($last) {
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
		    $val *= 1024;
		case 'm':
		    $val *= 1024;
		case 'k':
		    $val *= 1024;
		}

		return $val;
	}


}
