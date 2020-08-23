<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;


class WorkerUpdateSpottingCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:worker:spot:update')
			->setDescription('Update spotting distance and visibility - worker component - do not call directly')
			->addArgument('start', InputArgument::OPTIONAL, 'start character id')
			->addArgument('end', InputArgument::OPTIONAL, 'end character id')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$container = $this->getContainer();
		$em = $container->get('doctrine')->getManager();
		$appstate = $container->get('appstate');
		$start = $input->getArgument('start');
		$end = $input->getArgument('end');

		$spotBase = $appstate->getGlobal('spot.basedistance');
		$spotScout = $appstate->getGlobal('spot.scoutmod');
		$scout = $em->getRepository('BM2SiteBundle:EntourageType')->findOneByName('scout');

		$qb = $em->createQueryBuilder();
		// TODO: currently using a 50% spot biome mod, should probably have a seperate "looking out from" value
		$qb->select(array('c as character', '(:base + SQRT(count(DISTINCT e))*:mod + POW(count(DISTINCT s), 0.3333333))*((1.0+b.spot)/2.0) as spotdistance', 'b.spot as spotmod', 'f.amount as familiarity'))
			->from('BM2SiteBundle:GeoData', 'g')
			->join('g.biome', 'b')
			->from('BM2SiteBundle:Character', 'c')
			->leftJoin('c.soldiers_old', 's', 'WITH', 's.alive=true')
			->leftJoin('c.entourage', 'e', 'WITH', '(e.type = :scout AND e.alive=true)')
			->where($qb->expr()->eq('ST_Contains(g.poly, c.location)', 'true'))
			->from('BM2SiteBundle:RegionFamiliarity', 'f')
			->andWhere($qb->expr()->eq('f.character', 'c'))
			->andWhere($qb->expr()->eq('f.geo_data', 'g'))
			->andWhere($qb->expr()->eq('c.alive', $qb->expr()->literal(true))) // we see nothing if we are dead,
			->andWhere($qb->expr()->eq('c.slumbering', $qb->expr()->literal(false))) // ...slumbering
			->andWhere($qb->expr()->isNull('c.prisoner_of')) // ...or a prisoner
			->andWhere($qb->expr()->gte('c.id', ':start'))
			->andWhere($qb->expr()->lte('c.id', ':end'))
			->groupBy('c')
			->addGroupBy('b.spot')
			->addGroupBy('f.amount')
			->setParameter('base', $spotBase)
			->setParameter('mod', $spotScout)
			->setParameter('scout', $scout)
			->setParameter('start', $start)
			->setParameter('end', $end)
		;
		$query = $qb->getQuery();
		foreach ($query->getResult() as $row) {
			$char = $row['character'];
			$spot = $row['spotdistance'];
			if ($row['familiarity']>0) {
				$spot *= 1.0 + $row['familiarity']/20000; // familiarity can go up to 10.000, so this is at most a +50% increase
			}
			$char->setSpottingDistance(round($spot));
			$visibility = $char->getVisualSize() * $row['spotmod'];
			if ($char->getInsideSettlement()) {
				// FIXME: this should be smarter, taking at least settlement size into account
				$visibility *= 0.25;
			}
			$char->setVisibility(round($visibility));
		}
		$em->flush();

	}

}
