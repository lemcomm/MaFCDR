<?php

namespace BM2\SiteBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class RealmMaxExtentCommand extends ContainerAwareCommand {

	private $em;

	protected function configure() {
		$this
			->setName('maf:realm:extent')
			->setDescription('Calculate all the land ever owned by a realm (and its subrealms)')
			->addArgument('realm', InputArgument::REQUIRED, 'realm name or id')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
 		$this->em = $this->getContainer()->get('doctrine')->getManager();
		$r = $input->getArgument('realm');

		if (intval($r)) {
			$this->realm = $this->em->getRepository('BM2SiteBundle:Realm')->find(intval($r));
		} else {
			$this->realm = $this->em->getRepository('BM2SiteBundle:Realm')->findOneByName($r);
		}

		$regions = array();

		// FIXME: we sometimes have duplicates - that should be fixed on the DB level, but how?
		$query = $this->em->createQuery('SELECT DISTINCT r.cycle FROM BM2SiteBundle:StatisticRealm r WHERE r.realm = :me ORDER BY r.cycle ASC');
		$query->setParameter('me', $this->realm);

		$output->writeln("Gathering data for ".$this->realm->getName()." by cycle...");
		$subrealms = array();
		foreach ($query->getResult() as $row) {
			$subs = $this->gatherSubrealms($this->realm->getId(), $row['cycle'], array());
			foreach ($subs as $id=>$fromto) {
				if (isset($subrealms[$id])) {
					$subrealms[$id]['min'] = min($fromto['min'], $subrealms[$id]['min']);
					$subrealms[$id]['max'] = max($fromto['max'], $subrealms[$id]['max']);
				} else {
					$subrealms[$id] = $fromto;
				}
				$output->write('.');
			}
			$output->write($row['cycle']);
		}
		\Doctrine\Common\Util\Debug::dump($subrealms);
		$this->em->clear();

		$r = array();
		foreach ($subrealms as $id=>$fromto) {
			$r[] = $id;
		}

		
	}

	private function gatherSubrealms($id, $cycle, $realms) {
		$seen = false;
		if (isset($realms[$id])) {
			if ($realms[$id]['max'] == $cycle) {
				$seen = true;
			} else {
				$realms[$id]['max']=$cycle;
			}
		} else {
			$realms[$id] = array('min'=>$cycle, 'max'=>$cycle);
		}

		if (!$seen) {
			$query = $this->em->createQuery('SELECT x.id FROM BM2SiteBundle:StatisticRealm r JOIN r.realm x WHERE r.cycle = :cycle AND r.superior = :me');
			$query->setParameters(array('cycle'=>$cycle, 'me'=>$id));
			foreach ($query->getResult() as $row) {
				$realms = $this->gatherSubrealms($row['id'], $cycle, $realms);
			}
		}
		return $realms;
	}
}
