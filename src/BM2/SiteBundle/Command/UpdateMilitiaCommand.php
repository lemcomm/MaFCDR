<?php

namespace BM2\SiteBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

use BM2\SiteBundle\Entity\Settlement;

class UpdateMilitiaCommand extends ContainerAwareCommand {

	private $inactivityDays = 21;

	protected function configure() {
		$this
			->setName('maf:update:militia')
			->setDescription('Convert one or all settlement militias to 2.0 Unit System')
			->addArgument('target', InputArgument::REQUIRED, 'Either "all" or specific settlement id')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$mm = $this->getContainer()->get('military_manager');
                $source = $input->getArgument('target');
                $stopwatch = new Stopwatch();
		$execLimit = 50;

		$distinctQuery = $em->createQuery('SELECT DISTINCT(s.base) FROM BM2SiteBundle:Soldier s WHERE s.base IS NOT NULL');
		$garrisons = $distinctQuery->getResult();
                if ($source != 'all') {
                        $output->writeln("Looking for Settlement #".$source);
			$target = $em->getRepository('BM2SiteBundle:Settlement')->findOneById($source);
                        if ($target instanceof Settlement) {
                                $output->writeln('Settlement found...');
                        } else {
                                throw new \Exception("Cannot find settlement. Please check the id and try again.");
                        }
			$output->writeln("Converting selection to script expectations...");
			# The way this script is written, we have to do a DQL query so we can iterate.
			$query = $em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s WHERE s.id = :id AND s.id IN (:garrisons) ORDER BY s.id ASC')->setParameters(['garrisons' => $garrisons]);
			$query->setParameters(["id"=>$source]);
			$countQuery = $em->createQuery('SELECT COUNT(s.id) FROM BM2SiteBundle:Settlement s WHERE s.id = :id AND s.id IN (:garrisons)')->setParameters(['garrisons' => $garrisons]);
			$countQuery->setParameters(["id"=>$source]);
                } else {
                        $output->writeln('All detected, fetching repository...');

			#$query = $em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s ORDER BY s.id ASC');
			#$countQuery = $em->createQuery('SELECT COUNT(s.id) FROM BM2SiteBundle:Settlement s');

			$query = $em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s WHERE s.id IN (:garrisons) ORDER BY s.id ASC')->setParameters(['garrisons' => $garrisons]);
			$countQuery = $em->createQuery('SELECT COUNT(s.id) FROM BM2SiteBundle:Settlement s WHERE s.id IN (:garrisons)')->setParameters(['garrisons' => $garrisons]);
                }
		$stopwatch->start('updateSoldiers');

                $allCount = $countQuery->getSingleScalarResult();
                $output->writeln('Generating units for '.$allCount.' settlements.');
		$total = 0;
                $progress = 0;
                $unitCount = 0;
		$executions = 0;
		$result = $query->iterate();

		while ($progress < $allCount && $executions < $execLimit) {
			$output->writeln("Beginning execution loop...");
			$em->clear();

			while (($row = $result->next()) !== false) {
			#while (($row = $result->next()) !== false AND $executions < $execLimit AND $progress < $allCount) {
				$s = $row[0];
	                        if ($s->getSoldiersOld()->isEmpty()) {
	                                $progress++;
	                                $output->writeln('No units needed for '.$s->getName().', ID# '.$s->getId().'. ('.$progress.'/'.$allCount.')');
					$executions++;
	                        } else {
	                                $progress++;
	                                $output->writeln('Creating unit(s) for '.$s->getName().', ID# '.$s->getId().'... ('.$progress.'/'.$allCount.')');
	                                $total = $mm->convertToUnit(null, $s, null, true);
	                                $unitCount += $total;
	                                $output->writeln('Created '.$total.' units. '.$unitCount.' so far this execution.');
					$executions++;
	                        }
	                }
			$em->flush();
			if ($progress >= $allCount) {
				break;
			}
		}

		$output->writeln('Removing settlement associations for soldiers now assigned to units.');
		$query = $em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.liege = NULL, s.character = NULL, s.base = NULL, s.group = NULL, s.assigned_since = NULL WHERE s.unit IS NOT NULL and s.base IS NOT NULL');
		$query->execute();
		$event = $stopwatch->stop('updateSoldiers');
		$output->writeln('End of update reached. '.$unitCount.' Units for '.$progress.' Settlements in '.($event->getDuration()/1000).' seconds.');
	}
}
