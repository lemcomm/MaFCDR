<?php

namespace BM2\SiteBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

                if ($source != 'all') {
                        $output->writeln("Looking for Settlement #".$source);
                        $target = $em->getRepository('BM2SiteBundle:Character')->findOneById($source);
                        if ($target instanceof Character) {
                                $output->writeln('Character found...');
                        } else {
                                throw new \Exception("Cannot find settlement. Please check the id and try again.");
                        }
                        $all = new ArrayCollection();
                        $all->add($target);
                } else {
                        $output->writeln('All detected, fetching repository...');
                        $all = new ArrayCollection($em->getRepository('BM2SiteBundle:Character')->findBy(array('alive' => true)));
                }

                $allcount = $all->count();
                $output->writeln('Generating units for '.$allcount.' settlements.');
                $progress = 0;
                $unitcount = 0;
                $counter = 1;

                foreach ($all as $c) {
                        if ($c->getSoldiers()->isEmpty()) {
                                $progress++;
                                $output->writeln('No units needed for '.$c->getName().'. ('.$progress.'/'.$allcount.')');
                                $alter = false;
                        } else {
                                $progress++;
                                $output->writeln('Creating unit(s) for '.$c->getName().'... ('.$progress.'/'.$allcount.')');
                                $total = $mm->newUnit($c, null, null, true);
                                $unitcount += $total;
                                $output->writeln('Created '.$total.' units. '.$unitcount.' so far this execution.');
                                $alter = true;
                        }
                        if ($alter) {
                                $counter++;
                                $alter = false;
                        }
                        if ($counter == 200) {
                                $output->writeln('Execution optimization limit reached. Rerun this command to continue.');
                                break;
                        }

                }

                $query = $em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.liege = NULL, s.character = NULL, s.base = NULL, s.group = NULL, s.assigned_since = NULL WHERE s.unit IS NOT NULL AND s.character IS NOT NULL');
                $query->execute();
	}
}
