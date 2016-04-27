<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Common\Collections\ArrayCollection;

use BM2\SiteBundle\Entity\Settlement;

class TestCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
		->setName('maf:test')
		->setDescription('generic testing command, contents change all the time')
		;
	}

	private function testRealm($msg, $id) {
		$time_start = microtime(true);
		$me = $this->em->getRepository('BM2SiteBundle:Realm')->find($id);
		echo "realm ".$me->getName().":\n";
		$recipients = $this->getContainer()->get('communication')->MessageToRealm($msg, $me);
		$this->em->flush();
		$time_spent = microtime(true)-$time_start;
		echo "time spent: $time_spent\n";
		echo "characters reached: $recipients\n";
	}

	private function testSettlement($msg, $id) {
		$time_start = microtime(true);
		$me = $this->em->getRepository('BM2SiteBundle:Settlement')->find($id);
		echo "settlement ".$me->getName().":\n";
		$recipients = $this->getContainer()->get('communication')->MessageToSettlement($msg, $me);
		$this->em->flush();
		$time_spent = microtime(true)-$time_start;
		echo "time spent: $time_spent\n";
		echo "characters reached: $recipients\n";
	}

	private function testBroadcast($msg, $id, $realm=false) {
		$time_start = microtime(true);
		$me = $this->em->getRepository('BM2SiteBundle:Settlement')->find($id);
		echo "settlement ".$me->getName().":\n";

		$he = $this->em->getRepository('BM2SiteBundle:Character')->find(959);

		$this->getContainer()->get('communication')->createTowerLink($he, $me);
		$this->em->flush();


		if ($realm) {
			$realm = $this->em->getRepository('BM2SiteBundle:Realm')->find($realm);;
		} else {
			$realm = $me->getRealm();
		}
//		$recipients = $this->getContainer()->get('communication')->BroadcastMessage($me, $realm);
		$recipients = $this->getContainer()->get('communication')->broadcast_recipients(new ArrayCollection(array($me)));
		\Doctrine\Common\Util\Debug::dump($recipients);
//		$this->em->flush();
		$time_spent = microtime(true)-$time_start;
		echo "time spent: $time_spent\n";
		echo "characters reached: $recipients\n";
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->em = $this->getContainer()->get('doctrine')->getManager();

		$this->getContainer()->get('game_runner')->runTowerLinksCycle();
		exit;

		$me = $this->em->getRepository('BM2SiteBundle:Character')->find(959);

/*
		$me = $this->em->getRepository('BM2SiteBundle:Character')->find(959);
		$towers = $this->getContainer()->get('communication')->nearbyTowers($me);
		$output->writeln("Settlements in Range:");
		foreach ($towers['settlements'] as $s) {
			$output->writeln($s['settlement']->getName().' ('.($s['send']?'send':'receive only').')');
		}
		$output->writeln("Realms reachable:");
		foreach ($towers['realms'] as $r) {
			$output->writeln($r['realm']->getName().' ('.($r['send']?'send':'receive only').')');
		}
*/

		$me = $this->em->getRepository('BM2SiteBundle:Character')->find(959);
		$msg = $this->getContainer()->get('communication')->NewMessage($me, "This is a test.", array("aaa"));
/*
		$this->testRealm($msg, 2);
		$this->testRealm($msg, 189);
		$this->testRealm($msg, 415);

		$this->testSettlement($msg, 2410);
		$this->testSettlement($msg, 542);
		$this->testSettlement($msg, 3545);
*/

		$this->testBroadcast($msg, 3745);
//		$this->testBroadcast($msg, 3745, 1);
/*
		$this->testBroadcast($msg, 2966);
		$this->testBroadcast($msg, 2792);
*/
		exit;


		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Settlement s SET s.gold=0');
		$query->execute();
		$this->em->flush();

		$inserted = 1;
		$iteration = 0;
		while ($inserted > 0) {
			$inserted = 0;
			$iteration++;
			echo "\nITERATION $iteration\n";
			$query = $this->em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s WHERE s.gold=0 ORDER BY s.population+s.thralls DESC');
			$query->setMaxResults(10);
			$top_settlements = $query->getResult();

			foreach ($top_settlements as $s) {
				$this->insert($s->getId(), $s);
				$inserted++;
				$inserted += $this->addNeighbours($s->getId(), $s, 1, array($s->getId()));
			}
			$this->em->flush();
		}
		echo "completed in $iteration iterations, generates ".count($this->sets)." sets.\n";

		$query = $this->em->createQuery('SELECT MAX(s.gold) FROM BM2SiteBundle:Settlement s');
		$max = $query->getSingleScalarResult();
		$query = $this->em->createQuery('SELECT AVG(s.gold) FROM BM2SiteBundle:Settlement s');
		$avg = $query->getSingleScalarResult();
		for ($i=1; $i<=$max; $i++) {
			$query = $this->em->createQuery('SELECT COUNT(s) FROM BM2SiteBundle:Settlement s WHERE s.gold=:val');
			$query->setParameter('val', $i);
			$count = $query->getSingleScalarResult();
			echo "$i - ";
			for ($j=0;$j<$count;$j+=5) { echo "#"; }
			echo "\n";
		}
		echo "max sets for one settlement: $max\navg sets per settlement: $avg\n";

		$smallest = 999; $largest = -1;
		foreach ($this->sets as $set) {
			$count = count($set);
			if ($count < $smallest) { $smallest = $count; }
			if ($count > $largest) { $largest = $count; }
		}
		echo "smallest set: $smallest settlements\nlargest set: $largest settlements\n";
	}

	private function addNeighbours($set, Settlement $s, $depth, $except) {
		echo ".";
		$added = 0;
		$query = $this->em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s JOIN s.geo_data g, BM2SiteBundle:GeoData x WHERE ST_TOUCHES(g.poly, x.poly)=true AND x=:me AND s NOT IN (:except)');
		$query->setParameters(array('me'=>$s->getGeoData(), 'except'=>$except));
		$neighbours = $query->getResult();
		$except = array($s->getId());
		foreach ($neighbours as $n) {
			$except[] = $n->getId();
		}

		foreach ($neighbours as $n) {
			$this->insert($set, $n);
			$added++;
			if ($depth>0) {
				$this->addNeighbours($set, $n, $depth-1, $except);
			}
		}
		return $added;
	}

	private function insert ($set, Settlement $s) {
		if (!isset($this->sets[$set])) {
			$this->sets[$set] = array();
		}
		$this->sets[$set][$s->getId()] = $s->getName();
		$s->setGold($s->getGold()+1);
	}
}
