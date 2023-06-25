<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\Ship;
use BM2\SiteBundle\Service\History;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;


class WorkerTravelCommand extends ContainerAwareCommand {

	protected $em;
	private $speedmod=0.15;

	protected function configure() {
		$this
			->setName('maf:worker:travel')
			->setDescription('Update travel - worker component - do not call directly')
			->addArgument('start', InputArgument::OPTIONAL, 'start character id')
			->addArgument('end', InputArgument::OPTIONAL, 'end character id')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->output = $output;
		$container = $this->getContainer();
		$this->em = $container->get('doctrine')->getManager();
		$interactions = $container->get('interactions');
		$geography = $container->get('geography');
		$history = $container->get('history');
		$cycle = $container->get('appstate')->getCycle();
		$start = $input->getArgument('start');
		$end = $input->getArgument('end');
		$this->speedmod = (float)$container->get('appstate')->getGlobal('travel.speedmod', 0.15);

		// primary travel action - update our speed, check if we've arrived and update progress
		$query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Character c WHERE c.id >= :start AND c.id <= :end AND c.travel IS NOT NULL AND c.travel_locked = false');
		$query->setParameters(array('start'=>$start, 'end'=>$end));
		foreach ($query->getResult() as $char) {
			if ($char->getInsidePlace()) {
				if (!$interactions->characterLeavePlace($char)) {
					continue; #If you can't leave, you can't travel.
				}
			}
			if ($char->getInsideSettlement()) {
				if (!$interactions->characterLeaveSettlement($char)) {
					continue; #If you can't leave, you can't travel.
				}
			}
			if ($char->findActions('train.skill')->count() > 0) {
				# Auto cancel any training actions.
				foreach ($char->findActions('train.skill') as $each) {
					$this->em->remove($each);
				}
			}
			$geography->updateTravelSpeed($char);
			// TODO: check the return status, it should alert us to invalid travel settings!
			$progress = $char->getProgress() + ($char->getSpeed() * $this->speedmod);
			if ($progress >= 1.0) {
				// we have arrived!
				$char->setLocation($char->getTravel()->getPoint(-1));
				$char->setTravel(null)->setProgress(null)->setSpeed(null);

				if ($char->getTravelDisembark()) {
					list($land_location, $ship_location) = $geography->findLandPoint($char->getLocation());
					if ($land_location && $ship_location) {
						$char->setLocation($land_location);
						$char->setTravelAtSea(false)->setTravelDisembark(false);
						$history->logEvent(
							$char,
							'event.travel.disembark',
							array(),
							History::HIGH, false, 10
						);

						// spawn a ship here
						$ship = new Ship;
						$ship->setOwner($char);
						$ship->setLocation($ship_location);
						$ship->setCycle($cycle);
						$this->em->persist($ship);
					} else {
						$history->logEvent(
							$char,
							'event.travel.cantland',
							array(),
							History::HIGH, false, 10
						);
						$char->setTravelDisembark(false);
					}
				}

				if ($char->getTravelEnter()) {
					$nearest = $geography->findNearestSettlementToPoint($char->getLocation());
					$settlement=array_shift($nearest);
					$actiondistance = $geography->calculateActionDistance($settlement);
					if ($nearest['distance'] <= $actiondistance) {
						$interactions->characterEnterSettlement($char, $settlement);
					}
					$char->setTravelEnter(false);
				}

			} else {
				$char->setProgress($progress);
			}
		}

		$this->em->flush();
	}


}
