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

	public function inheritRealmDeath(Realm $realm, Character $heir, Character $from, Character $via=null, $why='death') {
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
		if ($why == 'death') {
			$this->history->logEvent(
				$realm, 'event.realm.inheriteddeath',
				array('%link-character-1%'=>$from->getId(), '%link-character-2%'=>$heir->getId()),
				History::HIGH, true
				);
		} else if ($why == 'slumber') {
			$this->history->logEvent(
				$realm, 'event.realm.inheritedslumber',
				array('%link-character-1%'=>$from->getId(), '%link-character-2%'=>$heir->getId()),
				History::HIGH, true
			);
		}
	}

	private function failInheritRealmDeath(Character $character, Realm $realm, $why = 'death') {
		if ($why == 'death') {
			$this->history->logEvent(
				$realm, 'event.realm.inherifaildeath',
				array('%link-character%'=>$character->getId()),
				HISTORY::HIGH, true
			);
		} else if ($why == 'slumber') {
			$this->history->logEvent(
				$realm, 'event.realm.inherifailslumber',
				array('%link-character%'=>$character->getId()),
				HISTORY::HIGH, true
			);
		}
	}
	
	public function inheritPosition(RealmPosition $position, Realm $realm, Character $heir, Character $from, Character $via=null, $why='death') {
		$this->realmmanager->makePositionHolder($position, $heir);
		// Note that this CAN leave a character in charge of a realm he was not a member of
		if ($from == $via || $via == null) {
			$this->history->logEvent(
				$heir,
				'event.character.inherit.position',
				array('%link-realm%'=>$realm->getId(), '%link-character%'=>$from->getId()),
				History::HIGH, true
			);
		} else {
			$this->history->logEvent(
				$heir,
				'event.character.inheritvia.position',
				array('%link-realm%'=>$realm->getId(), '%link-character-1%'=>$from->getId(), '%link-character-2%'=>$via->getId()),
				History::HIGH, true
			);
		}
		if ($why == 'death') {
			$this->history->logEvent(
			$realm, 'event.position.inherited.death',
			array('%link-position%'=>$position->getId(), '%link-character-1%'=>$from->getId(), '%link-character-2%'=>$heir->getId()),
			History::HIGH, true
			);
		} else if ($why == 'slumber') {
			$this->history->logEvent(
			$realm, 'event.position.inherited.slumber',
			array('%link-position%'=>$position->getId(), '%link-character-1%'=>$from->getId(), '%link-character-2%'=>$heir->getId()),
			History::HIGH, true
			);
		}
	}
	
	private function failInheritPosition(Character $character, RealmPosition $position) {
		$this->history->logEvent(
			$position->getRealm(), 
			'event.position.inactive',
			array('%link-character%'=>$character->getId(), '%link-realmposition%'=>$position->getId()),
			History::LOW, true
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
		$results = $query->getResult();
		$dead = [];
		$slumbered = [];
		foreach ($results as $character) {			
			$this->seen = new ArrayCollection;
			list($heir, $via) = $this->findHeir($character);
			if ($character->isAlive == FALSE) {
				$dead[] = $character;
			} else if ($character->isSlumbering == TRUE) {
				$slumbered[] = $character;
			}
		}
		foreach ($dead as $character) {
			$output->writeln($character->getName()." is dead, heir: ".($heir?$heir->getName():"(nobody)"));
			foreach ($character->getPositions() as $position) {
				if ($position->getRuler()) {
					if ($heir) {
						$this->inheritRealmDeath($position->getRealm(), $heir, $character, $via, 'death');
					} else {
						$this->failInheritRealmDeath($character, $position->getRealm());
					}
					$position->removeHolder($character);
					$character->removePosition($position);
				} else if ($position->getInherit()) {
					if ($heir) {
						$this->inhertPosition($position->getRealm(), $heir, $character, $via, 'death');
					} else {
						$this->failInheritPosition($character, $position);
					}
					$position->removeHolder($character);
					$character->removePosition($position);
				} else {
					$this->history->logEvent(
						$position->getRealm(), 
						'event.position.death',
						array('%link-character%'=>$character->getId(), '%link-realmposition%'=>$position->getId()),
						History::LOW, true
					);
					$position->removeHolder($character);
					$character->removePosition($position);
				}
			}
		}
		foreach ($slumbered as $character) {			
			$output->writeln($character->getName()." is inactive, heir: ".($heir?$heir->getName():"(nobody)"));
			foreach ($character->getPositions() as $position) {
				if ($position->getRuler()) {
					if ($heir) {
						$this->inheritRealm($position->getRealm(), $heir, $character, $via, 'slumber');
					} else {
						$this->failInheritRealm($character, $position->getRealm());
					}
				} else if (!$position->getKeepOnSlumber && $position->getInherit) {
					if ($heir) {
						$this->inheritPosition($position->getRealm(), $heir, $character, $via, 'slumber');
					} else {
						$this->failInheritPosition($character, $position);
					}
				} else if (!$position->getKeepOnSlumber) {
					$this->failInheritPosition($character, $position->getRealm());
					$position->removeHolder($character);
					$character->removePosition($position);
				} else {
					$this->history->logEvent(
						$position->getRealm(),
						'event.position.inactivekept',
						array('%link-character%'=>$character->getId(), '%link-position%'=>$position->getId()),
						History::LOW, true
					);
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

