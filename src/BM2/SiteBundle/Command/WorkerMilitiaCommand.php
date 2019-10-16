<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Service\History;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Common\Collections\ArrayCollection;


class WorkerMilitiaCommand extends ContainerAwareCommand {

	protected $em;
	protected $generator;
	protected $economy;

	protected function configure() {
		$this
			->setName('maf:worker:militia')
			->setDescription('Militia - worker component - do not call directly')
			->addArgument('start', InputArgument::OPTIONAL, 'start character id')
			->addArgument('end', InputArgument::OPTIONAL, 'end character id')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$container = $this->getContainer();
		$this->em = $container->get('doctrine')->getManager();
		$this->generator = $container->get('generator');
		$this->economy = $container->get('economy');
		$history = $container->get('history');
		$military = $container->get('military_manager');
		$start = $input->getArgument('start');
		$end = $input->getArgument('end');

		$query = $this->em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s WHERE s.id >= :start AND s.id <= :end');
		$query->setParameters(array('start'=>$start, 'end'=>$end));
		foreach ($query->getResult() as $settlement) {
			// check if abandoned
			if ($settlement->getOwner() && $settlement->getOwner()->getSlumbering() == true) {
				$this->abandonMilitia($settlement);
			}

			$military->TrainingCycle($settlement);
		}

		$this->em->flush();
	}

	public function abandonMilitia(Settlement $settlement) {
		$days = $settlement->getOwner()->getLastAccess()->diff(new \DateTime("now"), true)->days;
		foreach ($settlement->getSoldiers() as $soldier) {
			if (!$soldier->getAlive()) {
				$chance = 20;
			} else {
				$chance = sqrt($days);
			}
			if (rand(0,100) < $chance) {
				if ($soldier->getAlive()) {
					$settlement->setPopulation($settlement->getPopulation()+1);
				}
				$this->em->remove($soldier);
			}
		}
	}

	private function recruitCitizenMilitia(Settlement $settlement) {
		if ($settlement->getPopulation() > 1000) {
			$want_soldiers = sqrt($settlement->getPopulation()/5);
		} else {
			$want_soldiers = 3;
			if ($settlement->getPopulation() > 100) { $want_soldiers += 2; }
			if ($settlement->getPopulation() > 200) { $want_soldiers += 2; }
			if ($settlement->getPopulation() > 400) { $want_soldiers += 2; }
			if ($settlement->getPopulation() > 600) { $want_soldiers += 2; }
			if ($settlement->getPopulation() > 800) { $want_soldiers += 2; }
		}
		if ($settlement->getSoldiers()->count() < $want_soldiers) {
			// we want more soldiers, so add one every day/turn
			// we don't bother with equipment - we won't have a training ground in all but the rarest of circumstances anyways
			// so we just give them a 50% chance to have an axe and a 25% chance to have cloth armour and we're done with it.
			if (rand(0,100)<50) { $weapon=$this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('axe'); } else { $weapon=null; }
			if (rand(0,100)<25) { $armour=$this->em->getRepository('BM2SiteBundle:EquipmentType')->findOneByName('cloth armour'); } else { $armour=null; }
			// we don't set home to avoid the acquire_item checks...
			$soldier = $this->generator->randomSoldier($weapon, $armour, null);
			// ...but that means we must set it manually:
			$soldier->setHome($settlement)->setBase($settlement);
		}
	}

}
