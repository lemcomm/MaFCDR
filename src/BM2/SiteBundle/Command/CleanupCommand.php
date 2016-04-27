<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GeoData;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\RegionFamiliarity;
use BM2\SiteBundle\Service\History;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * @codeCoverageIgnore
 */
class CleanupCommand extends ContainerAwareCommand {

	private $seen;
	private $history;
	private $realmmanager;

	protected function configure() {
		$this
			->setName('maf:cleanup')
			->setDescription('Clean up some stuff (until we finally fix it)')
			->addOption('debug', 'd', InputOption::VALUE_NONE, 'output debug information')
		;
	}

	public function findHeir(Character $character, Character $from=null) {
		if (!$from) { $from = $character; }

		if ($this->seen->contains($character)) {
			// loops back to someone we've already checked
			return array(false, false);
		} else {
			$this->seen->add($character);
		}

		if ($heir = $character->getSuccessor()) {
			if ($heir->isAlive() && !$heir->getSlumbering()) {
				return array($heir, $from);
			} else {
				return $this->findHeir($heir, $from);
			}
		}
		return array(false, false);
	}

	public function inheritRealm(Realm $realm, Character $heir, Character $from, Character $via=null) {
		$this->realmmanager->makeRuler($realm, $heir);
		// Note that this CAN leave a character in charge of a realm he was not a member of
		if ($from == $via || $via == null) {
			$this->history->logEvent(
				$heir,
				'event.character.inherit.realm',
				array('%link-realm%'=>$realm->getId(), '%link-character%'=>$from->getId()),
				History::HIGH, true
			);
		} else {
			$this->history->logEvent(
				$heir,
				'event.character.inheritvia.realm',
				array('%link-realm%'=>$realm->getId(), '%link-character-1%'=>$from->getId(), '%link-character-2%'=>$via->getId()),
				History::HIGH, true
			);
		}
		$this->history->logEvent(
			$realm, 'event.realm.inherited',
			array('%link-character-1%'=>$from->getId(), '%link-character-2%'=>$heir->getId()),
			History::HIGH, true
		);
	}

	private function failInheritRealm(Character $character, Realm $realm) {
		$this->history->logEvent(
			$realm, 'event.realm.inherifail',
			array('%link-character%'=>$character->getId()),
			HISTORY::HIGH, true
		);

	}


	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$this->history = $this->getContainer()->get('history');
		$this->realmmanager = $this->getContainer()->get('realm_manager');

		// remove dead characters from map
		$query = $em->createQuery('UPDATE BM2SiteBundle:Character c SET c.location=null WHERE c.alive=false');
		$query->execute();

		$output->writeln("checking for dead characters with positions...");
		$query = $em->createQuery('SELECT c FROM BM2SiteBundle:Character c JOIN c.positions p WHERE c.alive = false OR c.slumbering = true');
		$dead = $query->getResult();
		foreach ($dead as $character) {
			$this->seen = new ArrayCollection;
			list($heir, $via) = $this->findHeir($character);

			$output->writeln($character->getName()." is dead or inactive, heir: ".($heir?$heir->getName():"(nobody)"));
			foreach ($character->getPositions() as $position) {
				if ($position->getRuler()) {
					if ($heir) {
						$this->inheritRealm($position->getRealm(), $heir, $character, $via);
					} else {
						$this->failInheritRealm($character, $position->getRealm());
					}
				} else {
					// FIXME: wrong message for inactive characters!
					if (!$character->isAlive()) {
						$msg = 'event.position.death';
					} else {
						$msg = 'event.position.inactive';
					}
					$this->history->logEvent(
						$position->getRealm(), $msg,
						array('%link-character%'=>$character->getId(), '%link-realmposition%'=>$position->getId()),
						History::LOW, true
					);
				}
				$position->removeHolder($character);
				$character->removePosition($position);
			}
		}
		$em->flush();

		$output->writeln("checking for realms without a ruler...");
		$query = $em->createQuery('SELECT r FROM BM2SiteBundle:Realm r LEFT JOIN r.positions p LEFT JOIN p.holders h WHERE r.active = true AND p.ruler = true AND h IS NULL');
		foreach ($query->getResult() as $realm) {
			if ($realm->findMembers()->count() > 0) {
				// FIXME: do we have an election? shouldn't we trigger one if not?
				//$output->writeln($realm->getName().' => no ruler');
			} else {
				// FIXME: shouldn't we go to dead?
				//$output->writeln('('.$realm->getName().')');
			}
		}
	}


}

