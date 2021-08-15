<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\StatisticGlobal;
use BM2\SiteBundle\Entity\StatisticRealm;
use BM2\SiteBundle\Entity\StatisticResources;
use BM2\SiteBundle\Entity\StatisticSettlement;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class StatisticsTurnCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
		->setName('maf:stats:turn')
		->setDescription('statistics: gather turn data')
		->addOption('debug', 'd', InputOption::VALUE_NONE, 'output debug information')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$cycle = $this->getContainer()->get('appstate')->getCycle();
		$economy = $this->getContainer()->get('economy');
		$debug = $input->getOption('debug');
		$oneWeek = new \DateTime("-1 week");
		$twoDays = new \DateTime("-2 days");

		if ($debug) { $output->writeln("gathering global statistics..."); }
		$global = new StatisticGlobal;
		$global->setCycle($cycle);

		$query = $em->createQuery('SELECT count(u.id) FROM BM2SiteBundle:User u');
		$global->setUsers($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(u.id) FROM BM2SiteBundle:User u WHERE u.account_level > 0 AND u.last_login >= :time');
		$query->setParameters(['time'=>$oneWeek]);
		$global->setActiveUsers($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(u.id) FROM BM2SiteBundle:User u WHERE u.account_level > 0 AND u.last_login >= :time');
		$query->setParameters(['time'=>$twoDays]);
		$global->setReallyActiveUsers($query->getSingleScalarResult());
		// FIXME: this is hardcoded, but it could be made better by calling payment_manager and checking which levels have fees
		$query = $em->createQuery('SELECT count(u.id) FROM BM2SiteBundle:User u WHERE u.account_level > 10');
		$global->setPayingUsers($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(distinct u.id) FROM BM2SiteBundle:User u JOIN u.payments p');
		$global->setEverPaidUsers($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(distinct u.id) FROM BM2SiteBundle:User u JOIN u.patronizing p WHERE p.status = :active');
		$query->setParameters(['active'=>'active_patron']);
		$global->setPatrons($query->getSingleScalarResult());

		$query = $em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Character c');
		$global->setCharacters($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Character c WHERE c.alive = true');
		$global->setLivingCharacters($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Character c WHERE c.slumbering = false');
		$global->setActiveCharacters($query->getSingleScalarResult());
		// FIXME: the below used to have an "AND c.log IS NOT NULL" statement, but for some reason I don't understand, every character now seems to have a log, which leads to an error. WTF? -- anyways, it didn't work for what I wanted it to do, still looking for a way to figure out which characters didn't die, but were created dead...
		$query = $em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Character c WHERE c.alive = false');
		$global->setDeceasedCharacters($query->getSingleScalarResult());

		$query = $em->createQuery('SELECT count(b.id) FROM BM2SiteBundle:Building b WHERE b.condition >= 0');
		$global->setBuildings($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(b.id) FROM BM2SiteBundle:Building b WHERE b.condition < 0 AND b.workers > 0');
		$global->setConstructions($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(b.id) FROM BM2SiteBundle:Building b WHERE b.condition < 0 AND b.workers <= 0');
		$global->setAbandoned($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(f.id) FROM BM2SiteBundle:GeoFeature f JOIN f.type t WHERE f.condition >= 0 AND t.hidden = false');
		$global->setFeatures($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(r.id) FROM BM2SiteBundle:Road r WHERE r.condition >= 0');
		$global->setRoads($query->getSingleScalarResult());

		$query = $em->createQuery('SELECT count(t.id) FROM BM2SiteBundle:Trade t');
		$global->setTrades($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(b.id) FROM BM2SiteBundle:Battle b');
		$global->setBattles($query->getSingleScalarResult());

		$query = $em->createQuery('SELECT count(r.id) FROM BM2SiteBundle:Realm r WHERE r.active = true');
		$global->setRealms($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(r.id) FROM BM2SiteBundle:Realm r WHERE r.active = true AND r.superior IS NULL');
		$global->setMajorRealms($query->getSingleScalarResult());

		$query = $em->createQuery('SELECT count(s.id) FROM BM2SiteBundle:Soldier s JOIN s.unit u WHERE s.training_required = 0 AND u.character IS NOT NULL');
		$global->setSoldiers($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(s.id) FROM BM2SiteBundle:Soldier s JOIN s.unit u WHERE s.training_required = 0 AND u.character IS NULL');
		$global->setMilitia($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT count(s.id) FROM BM2SiteBundle:Soldier s WHERE s.training_required > 0');
		$global->setRecruits($query->getSingleScalarResult());
		#$query = $em->createQuery('SELECT count(s.id) FROM BM2SiteBundle:Soldier s WHERE s.offered_as IS NOT NULL');
		$global->setOffers(0);

		$query = $em->createQuery('SELECT count(e.id) FROM BM2SiteBundle:Entourage e');
		$global->setEntourage($query->getSingleScalarResult());

		$query = $em->createQuery('SELECT sum(s.population) FROM BM2SiteBundle:Settlement s');
		$global->setPeasants($query->getSingleScalarResult());
		$query = $em->createQuery('SELECT sum(s.thralls) FROM BM2SiteBundle:Settlement s');
		$global->setThralls($query->getSingleScalarResult());

		$em->persist($global);
		$em->flush();

		if ($debug) { $output->write("gathering realm statistics"); }
		$realms = $em->getRepository('BM2SiteBundle:Realm')->findAll();
		foreach ($realms as $realm) {
			if ($debug) { $output->write("."); }
			$territory = $realm->findTerritory();
			if ($territory->count() > 0) {
				$population = 0;
				$soldiers = 0;
				$militia = 0;
				$nobles = $realm->findMembers();

				foreach ($territory as $settlement) {
					$population += $settlement->getFullPopulation();
					foreach($settlement->getUnits() as $unit) {
						if ($unit->isLocal()) {
							$militia += $unit->getActiveSoldiers()->count();
						}
					}
					foreach ($settlement->getDefendingUnits() as $unit) {
						$militia += $unit->getActiveSoldiers()->count();
					}
				}

				$players = array();
				foreach ($nobles as $noble) {
					foreach ($noble->getUnits() as $unit) {
						$soldiers += $unit->getActiveSoldiers()->count();
					}
					$players[$noble->getUser()->getId()] = true;
				}

				$stat = new StatisticRealm;
				$stat->setCycle($cycle);
				$stat->setRealm($realm);
				$stat->setSuperior($realm->getSuperior());
				$stat->setEstates($territory->count());
				$stat->setPopulation($population);
				$stat->setSoldiers($soldiers);
				$stat->setMilitia($militia);
				$stat->setArea(round($this->getContainer()->get('geography')->calculateRealmArea($realm)/(1000*1000)));
				$stat->setCharacters($nobles->count());
				$stat->setPlayers(count($players));

				$em->persist($stat);
			}
		}
		if ($debug) { $output->write("flush"); }
		$em->flush();
		if ($debug) { $output->writeln(" - done"); }

		$resources = $em->getRepository('BM2SiteBundle:ResourceType')->findAll();
		$resource_stats = array();
		foreach ($resources as $resource) {
			$resource_stats[$resource->getName()] = array('supply'=>0, 'demand'=>0, 'trade'=>0);
		}
		$em->clear();

		// FIXME: iterate or not, this runs me out of memory, probably due to all the cyclic references in Doctrine
		if ($debug) { $output->write("gathering settlement statistics"); }
		$query = $em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s');
		$iterableResult = $query->iterate();
		$i=1; $batchsize=150;
		while ($row = $iterableResult->next()) {
			if ($debug) { $output->write("."); }
			$settlement = $row[0];

			// this is really all settlements, so think twice about what to gather
			// definitely realm, though - but that's easy, just set both entity links...
			$stat = new StatisticSettlement;
			$stat->setCycle($cycle);
			$stat->setSettlement($settlement);
			$stat->setRealm($settlement->getRealm());
			$stat->setPopulation($settlement->getPopulation());
			$stat->setThralls($settlement->getThralls());
			$militia = 0;
			foreach($settlement->getUnits() as $unit) {
				if ($unit->isLocal()) {
					$militia += $unit->getActiveSoldiers()->count();
				}
			}
			foreach ($settlement->getDefendingUnits() as $unit) {
				$militia += $unit->getActiveSoldiers()->count();
			}
			$stat->setMilitia($militia);
			$stat->setStarvation($settlement->getStarvation());
			$stat->setWarFatigue($settlement->getWarFatigue());

			foreach ($resources as $resource) {
				$g = $settlement->findResource($resource);
				$amount = $g->getAmount();
				$supply = $economy->ResourceProduction($settlement, $resource);
				$demand = $economy->ResourceDemand($settlement, $resource);

				$resource_stats[$resource->getName()]['supply'] += $supply;
				$resource_stats[$resource->getName()]['demand'] += $demand;
			}

			$em->persist($stat);

			if (($i++ % $batchsize) == 0) {
				$em->flush();
				$em->clear();
			}
		}
		if ($debug) { $output->writeln(""); }
		$em->flush();
		$em->clear();

		if ($debug) { $output->write("gathering trade statistics"); }
		$trades = $em->getRepository('BM2SiteBundle:Trade')->findAll();
		foreach ($trades as $trade) {
			if ($debug) { $output->write("."); }
			$resource_stats[$trade->getResourceType()->getName()]['trade'] += $trade->getAmount();
		}
		if ($debug) { $output->writeln("done"); }

		if ($debug) { $output->write("writing resource statistics"); }
		$resources = $em->getRepository('BM2SiteBundle:ResourceType')->findAll();
		foreach ($resources as $resource) {
			$stat = new StatisticResources;
			$stat->setCycle($cycle);
			$stat->setResource($resource);
			$stat->setSupply($resource_stats[$resource->getName()]['supply']);
			$stat->setDemand($resource_stats[$resource->getName()]['demand']);
			$stat->setTrade($resource_stats[$resource->getName()]['trade']);

			$em->persist($stat);
		}

		$em->flush();
	}


}
