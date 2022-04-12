<?php

namespace BM2\DungeonBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class HourlyCommand extends ContainerAwareCommand {

	private $debug_mode=false;
	private $output=null;
	private $logger=null;

	protected function configure() {
		$this
			->setName('dungeons:hourly')
			->setDescription('hourly dungeons resolution')
			->addOption('debug', 'd', InputOption::VALUE_NONE, 'output debug information')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->debug_mode = $input->getOption('debug');
		$this->output = $output;
		$this->logger = $this->getContainer()->get('logger');

		$this->info("running dungeons...");
		$em = $this->getContainer()->get('doctrine')->getManager();
		$creator = $this->getContainer()->get('dungeon_creator');
		$master = $this->getContainer()->get('dungeon_master');

		$query = $em->createQuery('SELECT count(d.id) FROM DungeonBundle:Dungeon d');
		$dungeons = $query->getSingleScalarResult();

		$query = $em->createQuery('SELECT s FROM BM2SiteBundle:StatisticGlobal s ORDER BY s.id DESC')->setMaxResults(1);
		$result = $query->getSingleResult();
		$players = $result->getReallyActiveUsers(); # This isn't exact, but it's better than counting the spam bots.
		#$query = $em->createQuery('SELECT count(u.id) FROM BM2SiteBundle:User u WHERE u.account_level > 0');
		#$players = $query->getSingleScalarResult();

		$want = ceil($players/10);

		$this->info("$dungeons dungeons for $players players, we want to have $want");

		if ($dungeons < $want) {
			$create = ceil(($want - $dungeons)/10);
			$this->info("creating $create new dungeons:");
			for ($i=0;$i<$create;$i++) {
				$creator->createRandomDungeon();
			}
			$em->flush();
		}

		$this->debug("updating parties...");
		$query = $em->createQuery('UPDATE DungeonBundle:DungeonParty p SET p.counter=p.counter + 1 WHERE p.counter IS NOT NULL');
		$query->execute();

		$query = $em->createQuery('SELECT p FROM DungeonBundle:DungeonParty p WHERE p.counter > 50');
		foreach ($query->getResult() as $party) {
			$this->debug("party #".$party->getId()." timed out");
			$master->dissolveParty($party);
		}
		$em->flush();

		$dungeons = $em->getRepository('DungeonBundle:Dungeon')->findAll();
		foreach ($dungeons as $dungeon) {
			$this->debug("checking dungeon #".$dungeon->getId());
			if (!$dungeon->getCurrentLevel()) {
				$master->startDungeon($dungeon);
			}
			$master->runDungeon($dungeon);
		}
		$em->flush();
		$this->info("completed");
	}


	private function debug($text) {
//		if ($this->debug_mode) { $this->output->writeln("<comment>$text</comment>"); }
		$this->logger->debug($text);
	}
	private function info($text) {
//		if ($this->debug_mode) { $this->output->writeln("<info>$text</info>"); }
		$this->logger->info($text);
	}
	private function error($text) {
//		if ($this->debug_mode) { $this->output->writeln("<error>$text</error>"); }
		$this->logger->error($text);
	}

}
