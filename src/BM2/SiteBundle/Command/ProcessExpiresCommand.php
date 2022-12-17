<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ProcessExpiresCommand extends ContainerAwareCommand {

	protected $em;
	protected $output;
	protected $cycle;
	private $marker_lifetime = 36;

	protected function configure() {
		$this
			->setName('maf:process:expires')
			->setDescription('Run various expiration routines')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->em = $this->getContainer()->get('doctrine')->getManager();
		$this->output = $output;
		$this->cycle = $this->getContainer()->get('appstate')->getCycle();

		$this->expireEvents();
		$this->expireMarkers();
		$this->expireShips();

		$this->em->flush();
		$this->output->writeln("...expires complete");
	}

	public function expireEvents() {
		$this->output->writeln("expiring events...");
		$query = $this->em->createQuery('SELECT e FROM BM2SiteBundle:Event e WHERE e.lifetime IS NOT NULL AND e.cycle + e.lifetime < :cycle');
		$query->setParameter('cycle', $this->cycle);
		$all = $query->getResult();
		foreach ($all as $each) {
			if ($each->getMailEntries()->count() < 1) {
				$this->em->remove($each);
			}
		}
		$this->em->flush();

		$query = $this->em->createQuery('DELETE FROM BM2SiteBundle:SoldierLog l WHERE l.soldier IS NULL');
		$query->execute();
	}

	public function expireMarkers() {
		$this->output->writeln("expiring markers...");
		$query = $this->em->createQuery('DELETE FROM BM2SiteBundle:MapMarker m WHERE m.placed < :cycle');
		$query->setParameter('cycle', $this->cycle - $this->marker_lifetime);
		$query->execute();
	}

	public function expireShips() {
		$this->output->writeln("ships cleanup...");

		$query = $this->em->createQuery('DELETE FROM BM2SiteBundle:Ship s WHERE s.cycle < :before');
		$query->setParameters(array('before'=>$this->cycle-60));
		$query->execute();
	}


}
