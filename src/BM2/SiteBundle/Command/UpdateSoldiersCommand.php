<?php

namespace BM2\SiteBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

use BM2\SiteBundle\Entity\Character;

class UpdateSoldiersCommand extends ContainerAwareCommand {

	private $inactivityDays = 21;

	protected function configure() {
		$this
			->setName('maf:update:soldiers')
			->setDescription('Convert one or all character soldiers to 2.0 Unit System')
			->addArgument('target', InputArgument::REQUIRED, 'Either "all" or specific settlement id')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$mm = $this->getContainer()->get('military_manager');
                $source = $input->getArgument('target');
                $stopwatch = new Stopwatch();
		$execLimit = 50;

		$distinctQuery = $em->createQuery('SELECT DISTINCT(s.character) FROM BM2SiteBundle:Soldier s WHERE s.character IS NOT NULL');
		$commanders = $distinctQuery->getResult();
                if ($source != 'all') {
                        $output->writeln("Looking for Character #".$source);
                        /*$target = $em->getRepository('BM2SiteBundle:Character')->findOneById($source);*/
			$query = $em->createQuery('SELECT c FROM BM2SiteBundle:Character c WHERE c.id = :id AND c.id IN (:commanders)');
			$query->setParameters(['id' => $source, 'commanders' => $commanders]);
			$countQuery = $em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Character c WHERE c.id = :id AND c.id IN (:commanders)');
			$countQuery->setParameters(['id' => $source, 'commanders' => $commanders]);
                        /*if ($target instanceof Character) {
                                $output->writeln('Character found...');
                        } else {
                                throw new \Exception("Cannot find settlement. Please check the id and try again.");
                        }
                        $all = new ArrayCollection();
                        $all->add($target);*/
                } else {
                        $output->writeln('All detected, fetching repository...');
			$query = $em->createQuery('SELECT c FROM BM2SiteBundle:Character c WHERE c.alive = true AND c.id IN (:commanders)');
			$query->setParameters(['commanders' => $commanders]);
			$countQuery = $em->createQuery('SELECT count(c.id) FROM BM2SiteBundle:Character c WHERE c.alive = true AND c.id IN (:commanders)');
			$countQuery->setParameters(['commanders' => $commanders]);
                }

		$stopwatch->start('updateSoldiers');
		$allCount = $countQuery->getSingleScalarResult();
		$total = 0;
		$progress = 0;
                $output->writeln('Generating units for '.$allCount.' characters.');
		while ($progress < $allCount) {
			$output->writeln("Beginning execution loop...");
			$em->clear();
	                $unitCount = 0;
	                $executions = 0;

			$result = $query->iterate();
			while (($row = $result->next()) !== false AND $executions < $execLimit AND $progress <= $allCount) {
				$c = $row[0];
	                        if ($c->getSoldiersOld()->isEmpty()) {
	                                $output->writeln('No units needed for '.$c->getName().'. ('.$progress.'/'.$allCount.')');
					$executions++;
	                                $progress++;
	                        } else {
	                                $output->writeln('Creating unit(s) for '.$c->getName().'... ('.$progress.'/'.$allCount.')');
	                                $total = $mm->convertToUnit($c, null, null, true);
	                                $unitCount += $total;
	                                $output->writeln('Created '.$total.' units. '.$unitCount.' so far this execution.');
	                                $executions++;
	                                $progress++;
	                        }
	                }
			$em->flush();
			if ($progress >= $allCount) {
				break;
			}
		}
		$output->writeln('Removing character associations for soldiers now assigned to units.');
                $query = $em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.liege = NULL, s.character = NULL, s.base = NULL, s.group = NULL, s.assigned_since = NULL WHERE s.unit IS NOT NULL AND s.character IS NOT NULL');
                $query->execute();
		$output->writeln('Removing orphaned unit settings (those that lack a unit association)');
		$query= $em->createQuery('DELETE FROM BM2SiteBundle:UnitSettings s WHERE s.unit IS NULL');
		$query->execute();
		$event = $stopwatch->stop('updateSoldiers');
		$output->writeln('End of update reached. '.$unitCount.' Units for '.$progress.' Characters in '.($event->getDuration()/1000).' seconds.');
	}
}
