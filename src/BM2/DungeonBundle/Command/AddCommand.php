<?php

namespace BM2\DungeonBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use BM2\DungeonBundle\Entity\DungeonCardType;
use BM2\DungeonBundle\Entity\DungeonMonsterType;
use BM2\DungeonBundle\Entity\DungeonCard;


class AddCommand extends ContainerAwareCommand {

	private $cards = array(
	);

	private $monsters = array(
	);

	protected function configure() {
		$this
			->setName('dungeons:add')
			->setDescription('adds cards and monsters')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->output = $output;
		$this->info("adding dungeons content");
		$em = $this->getContainer()->get('doctrine')->getManager();

		foreach ($this->cards as $name=>$data) {
			$this->info("adding card $name");
			$type = new DungeonCardType;
			$type->setName($name);
			$type->setRarity($data['rarity']);
			$type->setMonsterClass($data['monsterclass']);
			$type->setTargetMonster($data['target']['monster']);
			$type->setTargetTreasure($data['target']['treasure']);
			$type->setTargetDungeoneer($data['target']['dungeoneer']);
			$em->persist($type);

			if (isset($data['addall']) && $data['addall']>0) {
				if (!isset($all_dungeoneers)) {
					$all_dungeoneers = $em->getRepository('DungeonBundle:Dungeoneer')->findAll();
				}
				foreach ($all_dungeoneers as $hero) {
					$cardset = new DungeonCard;
					$cardset->setAmount($data['addall']);
					$cardset->setPlayed(0);
					$cardset->setType($type);
					$cardset->setOwner($hero);
					$hero->addCard($cardset);
					$em->persist($cardset);
				}
			}
		}

		foreach ($this->monsters as $name=>$data) {
			$this->info("adding monster $name");
			$type = new DungeonMonsterType;
			$type->setName($name);
			$type->setClass($data['class']);
			$type->setAreas($data['areas']);
			$type->setMinDepth($data['mindepth']);
			$type->setPower($data['power']);
			$type->setAttacks($data['attacks']);
			$type->setDefense($data['defense']);
			$type->setWounds($data['wounds']);
			$em->persist($type);
		}

		$em->flush();
		$this->info("completed");
	}


	private function info($text) {
		$this->output->writeln("<info>$text</info>");
	}
	private function error($text) {
		$this->output->writeln("<error>$text</error>");
	}

}
