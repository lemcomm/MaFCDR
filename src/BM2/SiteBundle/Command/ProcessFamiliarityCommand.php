<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\RegionFamiliarity;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ProcessFamiliarityCommand extends AbstractProcessCommand {

	protected $em;
	protected $opt_time;

	protected function configure() {
		$this
			->setName('maf:process:familiarity')
			->setDescription('Update character/region familiarity')
			->addOption('time', 't', InputOption::VALUE_NONE, 'output timing information')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->output = $output;
		$this->opt_time = $input->getOption('time');

		$this->start('distance from home');
		// this is PostgreSQL specific, since DQL doesn't handle FROM in updates
		$conn = $this->em->getConnection();
		$conn->executeUpdate('UPDATE soldier set distance_home = ROUND(ST_Distance(c.location, gh.center)) FROM playercharacter c, settlement h, geodata gh WHERE soldier.character_id=c.id and soldier.home_id=h.id and h.geo_data_id = gh.id and c.travel is not null');
		$conn->executeUpdate('UPDATE entourage set distance_home = ROUND(ST_Distance(c.location, gh.center)) FROM playercharacter c, settlement h, geodata gh WHERE entourage.character_id=c.id and entourage.home_id=h.id and h.geo_data_id = gh.id and c.travel is not null');
		// the above is actually pretty sufficient, because to move soldiers/NPC from one settlement to the other, a character has to bring them
		// we could add one more update on "set as militia", but why? Has to be in settlement already and so on.
		// the only place where this leads to wrong results right now is on recall, because there we teleport.
		$this->finish('distance from home');

		$this->start('familiarity');
		$this->decayFamiliarity();
		$this->process('familiarity', 'Character');
		$this->finish('familiarity');
	}

	private function decayFamiliarity() {
		$this->em->createQuery('UPDATE BM2SiteBundle:RegionFamiliarity f SET f.amount=f.amount-1')->execute();
		$this->em->createQuery('DELETE FROM BM2SiteBundle:RegionFamiliarity f WHERE f.amount <= 0')->execute();
	}

}
