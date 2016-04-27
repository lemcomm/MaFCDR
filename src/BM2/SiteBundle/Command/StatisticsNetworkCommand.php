<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\Character;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class StatisticsNetworkCommand extends ContainerAwareCommand {

	private $em;

	protected function configure() {
		$this
		->setName('maf:stats:network')
		->setDescription('statistics: character relations network')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->em = $this->getContainer()->get('doctrine')->getManager();

		echo "graph characters {\n";
		$characters = $this->em->getRepository('BM2SiteBundle:Character')->findAll();
		foreach ($characters as $character) {
			echo "\"".$character->getId()."\" [label=\"".addslashes($character->getName())."\"];\n";
			foreach ($character->getParents() as $parent) {
				$this->link($character, $parent, "orange");
			}
			foreach ($character->getPartnerships() as $partnership) {
				if ($partnership->getActive() && $partnership->getPublic() && $partnership->getType()=="marriage") {
					$other = $partnership->getOtherPartner($character);
					if ($character->getId() > $other->getId()) { // so we don't get the line twice
						$this->link($character, $other, "red");
					}
				}
			}
			if ($character->getLiege()) {
				$this->link($character, $character->getLiege(), "blue");
			}
			if ($character->getSuccessor()) {
				$this->link($character, $character->getSuccessor(), "green");				
			}
		}

		echo "}\n";
	}


	private function link(Character $from, Character $to, $color) {
		echo "\"".$from->getId()."\" -- \"".$to->getId()."\" [color=\"$color\"];\n";
	}
}


