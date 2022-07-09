<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\CreditHistory;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RefundHeraldryCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:refund:heraldry')
			->setDescription('Refund the heraldry bought at 500 credits with 250 credits per heraldry.')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$query = $em->createQuery('SELECT u from BM2SiteBundle:User u join u.crests h');
		$all = $query->getResult();
		$total = 0;
		foreach ($all as $each) {
			$found = false;
			$type ="Heraldry Change Refund";
			foreach ($each->getCreditHistory() as $old) {
				if ($old->getType() === $type) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				$output->writeln("Processing ".$each->getUsername());
				$count = $each->getCrests()->count();
				$refund = 250 * $count;
				$output->writeln("Refund total of ".$refund." credits");
				$hist = new CreditHistory();
				$hist->setTs(new \DateTime('now'));
				$hist->setCredits($refund);
				$hist->setType($type);
				$hist->setPayment(null);
				$hist->setUser($each);
				$em->persist($hist);
				$each->addCreditHistory($hist);
				$output->writeln("Refund entered into history.");
				$total++;
			}
		}
		$em->flush();
		$output->writeln($total." refunds logged.");
	}


}
