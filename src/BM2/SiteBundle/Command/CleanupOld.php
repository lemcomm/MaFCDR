<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GeoData;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\RealmPosition;
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

	# TODO: Put this someplace that makes more sense, rather than a command called Cleanup. Maybe the Realm Cycle of GameRunner? --Andrew
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

	public function inheritRealm(Realm $realm, Character $heir, Character $from, Character $via=null, $why='death') {
		$this->realmmanager->makeRuler($realm, $heir);
		// NOTE: This can leave someone ruling a realm they weren't originally part of!
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

	public function failInheritRealm(Character $character, Realm $realm, $why = 'death') {
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
		// NOTE: This can add characters to realms they weren't already in!
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
	
	private function failInheritPosition(Character $character, RealmPosition $position, $why='death') {
		if ($why == 'death') {
			$this->history->logEvent(
				$position->getRealm(), 
				'event.position.inactive',
				array('%link-character%'=>$character->getId(), '%link-realmposition%'=>$position->getId()),
				History::LOW, true
			);
		} else if ($why == 'slumber') {
			$this->history->logEvent(
				$position->getRealm(), 
				'event.position.death',
				array('%link-character%'=>$character->getId(), '%link-realmposition%'=>$position->getId()),
				History::LOW, true
			);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$this->history = $this->getContainer()->get('history');
		$this->realmmanager = $this->getContainer()->get('realm_manager');

		$query = $em->createQuery('SELECT c FROM BM2SiteBundle:Character c WHERE c.alive=false');
		$deadcount = count($query->getResult());
		$output->writeln("Removing $deadcount dead from the map...");
		$query = $em->createQuery('UPDATE BM2SiteBundle:Character c SET c.location=null WHERE c.alive=false');
		$query->execute();

		$output->writeln("checking for dead and slumbering characters with positions...");
		$query = $em->createQuery('SELECT c FROM BM2SiteBundle:Character c JOIN c.positions p WHERE c.alive = false OR c.slumbering = true');
		$result = $query->getResult();
		if (count($result) > 0) {
			$output->writeln("Sorting the dead from the slumbering...");
		} else {
			$output->writeln("No dead or slumbering found!");
		}
		$dead = [];
		$slumbered = [];
		$deadcount = 0;
		$slumbercount = 0;
		$keeponslumbercount = 0;
		foreach ($result as $character) {
			$this->seen = new ArrayCollection;
			list($heir, $via) = $this->findHeir($character);
			if ($character->isAlive() == FALSE) {
				$deadcount++;
				$dead[] = $character;
			} else if ($character->getSlumbering() == TRUE) {
				$slumbercount++;
				$slumbered[] = $character;
			}
		}
		if (count($deadcount)+count($slumbercount) != 0) {
			$output->writeln("Sorting $deadcount dead and $slumbercount slumbering");
		}
		foreach ($dead as $character) {
			$output->writeln($character->getName()." is dead, heir: ".($heir?$heir->getName():"(nobody)"));
			foreach ($character->getPositions() as $position) {
				if ($position->getRuler()) {
                                        if ($position->getInherit()) {
                                                if ($heir) {
                                                        $this->inheritRealm($position->getRealm(), $heir, $character, $via, 'death');
                                                } else {
                                                        $this->failInheritRealm($character, $position->getRealm(), 'death');
                                                }
                                        }
					$position->removeHolder($character);
					$character->removePosition($position);
				} else if ($position->getInherit()) {
					if ($heir) {
						$this->inhertPosition($position->getRealm(), $heir, $character, $via, 'death');
					} else {
						$this->failInheritPosition($character, $position, 'death');
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
                                        if ($position->getInherit()) {
                                                if ($heir) {
                                                        $this->inheritRealm($position->getRealm(), $heir, $character, $via, 'death');
                                                } else {
                                                        $this->failInheritRealm($character, $position->getRealm(), 'death');
                                                }
                                        }
					$position->removeHolder($character);
					$character->removePosition($position);
				} else if (!$position->getKeepOnSlumber() && $position->getInherit()) {
					if ($heir) {
						$this->inheritPosition($position->getRealm(), $heir, $character, $via, 'slumber');
					} else {
						$this->failInheritPosition($character, $position, 'slumber');
					}
					$position->removeHolder($character);
					$character->removePosition($position);
				} else if (!$position->getKeepOnSlumber()) {
					$this->failInheritPosition($character, $position, 'slumber');
					$position->removeHolder($character);
					$character->removePosition($position);
				} else {
					$this->history->logEvent(
						$position->getRealm(),
						'event.position.inactivekept',
						array('%link-character%'=>$character->getId(), '%link-position%'=>$position->getId()),
						History::LOW, true
					);
					$keeponslumbercount++;
				}
			}
		}
		$output->writeln("$keeponslumbercount positions kept on slumber!");
		$em->flush();
	
	}


}
