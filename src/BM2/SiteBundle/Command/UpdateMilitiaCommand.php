<?php

namespace BM2\SiteBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

                if ($source != 'all') {
                        $output->writeln("Looking for Settlement #".$source);
                        $target = $em->getRepository('BM2SiteBundle:Settlement')->findOneById($source);
                        if ($target instanceof Settlement) {
                                $output->writeln('Settlement found...');
                        } else {
                                throw new \Exception("Cannot find settlement. Please check the id and try again.");
                        }
                        $all = new ArrayCollection();
                        $all->add($target);
                } else {
                        $output->writeln('All detected, fetching repository...');
                        $all = new ArrayCollection($em->getRepository('BM2SiteBundle:Settlement')->findAll());
                }

                $allcount = $all->count();
                $output->writeln('Generating units for '.$allcount.' settlements.');
                $progress = 0;
                $unitcount = 0;
                $counter = 1;

                foreach ($all as $s) {
                        if ($s->getSoldiers()->isEmpty()) {
                                $progress++;
                                $output->writeln('No units needed for '.$s->getName().'. ('.$progress.'/'.$allcount.')');
                        } else {
                                $progress++;
                                $output->writeln('Creating unit(s) for '.$c->getName().'... ('.$progress.'/'.$allcount.')');
                                $total = $mm->newUnit(null, $s, null, true);
                                $unitcount += $total;
                                $output->writeln('Created '.$total.' units. '.$unitcount.' so far this execution.');
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

                $query = $em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.liege = NULL, s.character = NULL, s.base = NULL, s.group = NULL, s.assigned_since = NULL WHERE s.unit IS NOT NULL and s.base_id IS NOT NULL');
                $query->execute();
	}
}
