<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Election;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\RealmPosition;
use Doctrine\ORM\EntityManager;


class RealmManager {

	protected $em;
	protected $history;
	protected $politics;
	protected $convman;
	protected $lawman;

	public function __construct(EntityManager $em, History $history, Politics $politics, ConversationManager $convman, LawManager $lawman) {
		$this->em = $em;
		$this->history = $history;
		$this->politics = $politics;
		$this->convman = $convman;
		$this->lawman = $lawman;
	}

	public function create($name, $formalname, $type, Character $founder) {
		$realm = $this->_create($name, $formalname, $type, $founder);

		$this->history->logEvent(
			$realm,
			'event.realm.founded',
			array('%link-character%'=>$founder->getId()),
			History::ULTRA, true
		);
		$this->history->logEvent(
			$founder,
			'event.character.realmfounded',
			array('%link-realm%'=>$realm->getId()),
			History::HIGH, true
		);
		$this->updateHierarchy($founder, $realm);
		return $realm;
	}

	public function subcreate($name, $formalname, $type, Character $ruler, Character $founder, Realm $parentrealm) {
		$realm = $this->_create($name, $formalname, $type, $ruler);
		$realm->setSuperior($parentrealm);
		$parentrealm->addInferior($realm);

		$this->history->logEvent(
			$realm,
			'event.subrealm.founded',
			array('%link-character-1%'=>$founder->getId(), '%link-character-2%'=>$ruler->getId(), '%link-realm%'=>$parentrealm->getId()),
			History::ULTRA, true
		);
		$this->history->logEvent(
			$founder,
			'event.character.realmfounded',
			array('%link-realm%'=>$realm->getId()),
			History::HIGH, true
		);
		$this->history->logEvent(
			$ruler,
			'event.character.realmgranted',
			array('%link-realm%'=>$realm->getId(), '%link-character%'=>$founder->getId()),
			History::HIGH, true
		);
		$this->updateHierarchy($ruler, $realm, false);
		return $realm;
	}

	private function _create($name, $formalname, $type, $ruler) {
		$realm = new Realm;
		$realm->setName($name)->setFormalName($formalname);
		$realm->setActive(true);
		$realm->setType($type);
		$realm->setColourHex('#cccccc');
		$realm->setColourRgb('204,204,204');
		$this->em->persist($realm);
		$this->em->flush($realm); // or we don't have a realm ID that we need below

		// create ruler position
		$position = new RealmPosition;
		$position->setRealm($realm);
		$position->setRuler(true);
		$position->setName('ruler');
		$position->setDescription('This is the rulership position for the realm.');
		$position->setElected(true);
		$position->setInherit(true);
		$position->setTerm(0);
		$this->em->persist($position);
		$realm->addPosition($position);

		$this->makeRuler($realm, $ruler);

		return $realm;
	}

	public function abandon(Realm $realm) {
		$realm->setActive(false);
		$this->history->logEvent(
			$realm,
			'event.realm.deserted',
			array(),
			History::ULTRA, true
		);
		foreach ($realm->getSettlements() as $e) {
			if ($realm->getSuperior() && $realm->getSuperior()->getActive()) {
				$this->politics->changeSettlementRealm($e, $realm->getSuperior(), 'fail');
			} else {
				$this->politics->changeSettlementRealm($e, null, 'fail');
			}
		}
		foreach ($realm->getSpawns() as $spawn) {
			$spawn->setActive(false);
		}
		foreach ($realm->getPlaces() as $place) {
			if ($realm->getSuperior() && $realm->getSuperior()->getActive()) {
				$this->politics->changePlaceRealm($place, $realm->getSuperior(), 'fail');
			} else {
				$this->politics->changePlaceRealm($place, null, 'fail');
			}
		}
	}

	private function updateHierarchy(Character $char, Realm $realm, $setrealm=true) {
		// update the downwards hierarchy on a new realm creation
		// everyone on here gets unlimited access, because the realm just got founded
		$this->history->openLog($realm, $char);

		$query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Conversation c WHERE c.realm = :realm');
		$query->setParameter('realm', $realm);
		foreach ($query->getResult() as $conversation) {
			$this->convman->updateMembers($conversation);
		}

		if ($setrealm) {
			foreach ($char->getOwnedSettlements() as $settlement) {
				if (!$settlement->getRealm()) {
					$this->politics->changeSettlementRealm($settlement, $realm, 'update');
				}
			}
		}

		foreach ($char->getVassals() as $vassal) {
			$this->updateHierarchy($vassal, $realm, $setrealm);
		}
	}


	public function abdicate(Realm $realm, Character $oldruler, Character $successor=null) {
		// ruler abdication and announcement of successor (or not)

		foreach ($realm->getPositions() as $pos) {
			if ($pos->getRuler()) {
				$pos->removeHolder($oldruler);
				$oldruler->removePosition($pos);
			}
		}

		if ($successor) {
			$this->history->logEvent(
				$realm,
				'event.realm.abdicated',
				array('%link-character-1%'=>$oldruler->getId(), '%link-character-2%'=>$successor->getId()),
				History::HIGH, true
			);
			$this->history->logEvent(
				$oldruler,
				'event.character.abdicated',
				array('%link-realm%'=>$realm->getId(), '%link-character%'=>$successor->getId()),
				History::HIGH, true
			);
			$this->history->logEvent(
				$successor,
				'event.character.succeeds',
				array('%link-realm%'=>$realm->getId(), '%link-character%'=>$oldruler->getId()),
				History::HIGH, true
			);
			$this->makeRuler($realm, $successor);
		} else {
			$this->history->logEvent(
				$realm,
				'event.realm.abdicated2',
				array('%link-character%'=>$oldruler->getId()),
				History::HIGH, true
			);
			$this->history->logEvent(
				$oldruler,
				'event.character.abdicated2',
				array('%link-realm%'=>$realm->getId()),
				History::HIGH, true
			);
		}
	}


	public function makeRuler(Realm $realm, Character $newruler, $ignore_position=false) {
		// find rulership position - we assume here that there's only one
		if (!$ignore_position) foreach ($realm->getPositions() as $position) {
			if ($position->getRuler() && !$position->getHolders()->contains($newruler)) {
				$position->addHolder($newruler);
				$newruler->addPosition($position);
			}
		}

		$this->removeRulerLiege($realm, $newruler);
	}


	public function removeRulerLiege(Realm $realm, Character $newruler) {
		if ($liege = $newruler->getLiege()) {
			if ($liege instanceof ArrayCollection) {
				foreach ($liege as $one) {
					$this->history->logEvent(
						$one,
						'politics.oath.nowruler',
						array('%link-realm%'=>$realm->getId(), '%link-character%'=>$newruler->getId()),
						History::MEDIUM, true
					);
				}
			} else {
				$this->history->logEvent(
					$liege,
					'politics.oath.nowruler',
					array('%link-realm%'=>$realm->getId(), '%link-character%'=>$newruler->getId()),
					History::MEDIUM, true
				);
			}
			$liege->removeVassal($newruler);
		}
	}

	public function getVoteWeight(Election $election, Character $character) {
		$law = $election->getRealm()->findActiveLaw('realmVotingAge');
		if (!$law || !$law->getValue()) {
			$joinBy = new DateTime('2013-01-01');
		} else {
			$joinBy = new DateTime('-'.$law->getValue().' days');
		}
		$weight = 0;
		switch ($election->getMethod()) {
			case 'spears':
				foreach ($character->getUnits() as $unit) {
					$weight += $unit->getActiveSoldiers()->count();
				}
				break;
			case 'swords':
				$weight = $character->getVisualSize();
				break;
			case 'land':
				$weight = $character->getOwnedSettlements()->count();
				break;
			case 'horses':
				foreach ($character->getUnits() as $unit) {
					foreach ($unit->getActiveSoldiers() as $soldier) {
						# While yes, I realize only mounts are horses, I'm leaving open the possibility of non-horse mounts.
						if ($soldier->getMount()->getName() == 'horse' || $soldier->getMount()->getName() == 'war horse') {
							$weight++;
						}
					}
				}
				break;
			case 'realmland':
				$realms = $election->getRealm()->findAllInferiors(true);
				$realmids = [];
				foreach ($realms as $realm) {
					$realmids[] = $realm->getId();
					}
				foreach ($character->getOwnedSettlements() as $e) {
					if (in_array($e->getRealm()->getId(), $realmids)) {
						$weight++;
					}
				}
				break;
			case 'heads':
				foreach ($character->getOwnedSettlements() as $e) {
					$weight += $e->getPopulation();
				}
				break;
			case 'realmcastles':
				$realms = [];
				foreach ($election->getRealm()->findAllInferiors(true) as $realm) {
					$realms[] = $realm->getId();
				}
				foreach ($character->getOwnedSettlements() as $settlement) {
					if (in_array($settlement->getRealm()->getId(), $realms)) {
						foreach ($settlement->getBuildings() as $b) {
							if ($b->getType()->getDefenses() > 0) {
								$weight += $b->getType()->getDefenses()/10;
							}
						}
					}
				}
				break;
			case 'castles':
				foreach ($character->getOwnedSettlements() as $settlement) {
					foreach ($settlement->getBuildings() as $b) {
						if ($b->getType()->getDefenses() > 0) {
							$weight += $b->getType()->getDefenses()/10;
						}
					}
				}
			case 'banner':
			default:
				$weight = 1;
		}
		if ($character->getCreated() >= $joinBy) {
			return 0;
		} else {
			return $weight;
		}
	}

	public function countElection(Election $election) {
		$election->setClosed(true);
		$position = $election->getPosition();

		$candidates = array();
		foreach ($election->getVotes() as $vote) {
			$target = $vote->getTargetCharacter();
			$c = $target->getId();
			# Only allow slumbering electees on positions that allow you to hold it while slumbering. Never allow the dead to be voted in.
			if (!$position || ($position->getKeepOnSlumber() == false && $target->isActive(true)) || ($position->getKeepOnSlumber() == true && $target->isAlive())) {
				if (!isset($candidates[$c])) {
					$candidates[$c] = array('char'=>$vote->getTargetCharacter(), 'votes'=>0, 'weight'=>0);
				}
				$candidates[$c]['votes'] += $vote->getVote();
				$candidates[$c]['weight'] += $vote->getVote() * $this->getVoteWeight($election, $vote->getCharacter());
			}
		}

		$winner = null;
		$max = 0;

		foreach ($candidates as $c) {
			if ($c['weight'] > $max) {
				$winner = $c['char'];
				$max = $c['weight'];
			}
		}

		if ($winner) {
			$election->setWinner($winner);
			if ($election->getPosition()) {
				if (!$election->getPosition()->getHolders()->contains($winner)) {
					/* Yes, this ranks up there as one of the more stupid checks.
					The game is supposed to remove existing holders, but sometimes, it doesn't.
					No idea why not. If we do this though, the code can carry on carrying on.
					*/
					$election->getPosition()->addHolder($winner);
					$winner->addPosition($election->getPosition());
				}
				$this->history->logEvent(
					$winner,
					'event.character.position.elected',
					array('%link-realm%'=>$election->getRealm()->getId(), '%link-realmposition%'=>$election->getPosition()->getId()),
					History::MEDIUM, true
				);
				$this->history->logEvent(
					$election->getRealm(),
					'event.realm.elected2',
					array('%link-character%'=>$winner->getId(), '%link-realmposition%'=>$election->getPosition()->getId()),
					History::MEDIUM, true
				);

				if ($election->getPosition()->getRuler()) {
					$this->removeRulerLiege($election->getRealm(), $winner);
				}
			}
		}
	}

	public function dropIncumbents(Election $election) {
		if ($election->getRoutine()) {
			$position = $election->getPosition();
			$holders = $position->getHolders();
			foreach ($holders as $character) {
				$position->removeHolder($character);
				$character->removePosition($position);
			}
		}
	}

	public function dismantleRealm(Character $character, Realm $realm, $sovereign=false) {
		$this->history->logEvent(
			$realm,
			'event.realm.abolished.realm',
			array('%link-character%'=>$character->getId()),
			History::HIGH
		); # 'By order of %link-character%, the realm has been dismantled.'
		if (!$sovereign) {
			$superior = $realm->getSuperior();
			$this->history->logEvent(
				$superior,
				'event.realm.abolished.superior',
				array('%link-character%'=>$character->getId(), '%link-realm%'=>$realm->getId()),
				History::HIGH
			); # 'By order of %link-character%, the realm's subrealm of %link-realm% has been dismantled.'
		}
		$this->history->logEvent(
			$character,
			'event.realm.abolished.character',
			array('%link-realm%'=>$realm->getId()),
			History::HIGH
		); # 'Ordered the dismantling of the realm of %link-realm%.'
		foreach ($realm->getSettlements() as $settlement) {
			if ($sovereign) {
				$this->history->logEvent(
					$settlement,
					'event.realm.abolished.sovereign.estate',
					array('%link-realm%'=>$realm->getId()),
					History::HIGH
				); # 'With the dismantling of %link-realm%, the estate is effectively rogue.'
				foreach ($settlement->getVassals() as $vassal) {
					$this->history->logEvent(
						$vassal,
						'event.realm.abolished.vassals.estate.sov',
						array('%link-realm%'=>$realm->getId()),
						History::MEDIUM
					);
					$vassal->setLiegeLand(null);
				}
				$settlement->setRealm(null);
				$realm->removeSettlement($settlement);
				$this->em->flush();
			} else {
				$this->history->logEvent(
					$settlement,
					'event.realm.abolished.notsovereign.estate',
					array('%link-realm-1%'=>$realm->getId(), '%link-realm-2%'=>$superior->getId()),
					History::HIGH
				); # 'With the dismantling of %link-realm%, the estate now falls under %link-realm-2%.'
				foreach ($settlement->getVassals() as $vassal) {
					$this->history->logEvent(
						$vassal,
						'event.realm.abolished.vassals.estate.notsov',
						array('%link-realm%'=>$realm->getId(), '%link-realm-2%'=>$superior->getId()),
						History::MEDIUM
					);
					$vassal->setLiegeLand(null);
				}
				$realm->removeSettlement($settlement);
				$settlement->setRealm($superior);
				$superior->addSettlement($settlement);
				$this->em->flush();
			}
		}
		foreach ($realm->getPlaces() as $place) {
			if ($sovereign) {
				$this->history->logEvent(
					$place,
					'event.realm.abolished.sovereign.place',
					array('%link-realm%'=>$realm->getId()),
					History::HIGH
				); # 'With the dismantling of %link-realm%, the place is effectively rogue.'
				foreach ($place->getVassals() as $vassal) {
					$this->history->logEvent(
						$vassal,
						'event.realm.abolished.vassals.estate.sov',
						array('%link-realm%'=>$realm->getId()),
						History::MEDIUM
					);
					$vassal->setLiegePlace(null);
				}
				$place->setRealm(null);
				$realm->removePlace($place);
			} else {
				$this->history->logEvent(
					$place,
					'event.realm.abolished.notsovereign.estate',
					array('%link-realm-1%'=>$realm->getId(), '%link-realm-2%'=>$superior->getId()),
					History::HIGH
				); # 'With the dismantling of %link-realm%, the estate now falls under %link-realm-2%.'
				foreach ($place->getVassals() as $vassal) {
					$this->history->logEvent(
						$vassal,
						'event.realm.abolished.vassals.estate.notsov',
						array('%link-realm%'=>$realm->getId(), '%link-realm-2%'=>$superior->getId()),
						History::MEDIUM
					);
					$vassal->setLiegePlace(null);
				}
				$realm->removePlace($place);
				$place->setRealm($superior);
				$superior->addPlace($place);
			}
		}
		$this->em->flush();
		foreach ($realm->getVassals() as $vassal) {
			if ($sovereign) {
				$this->history->logEvent(
					$vassal,
					'event.realm.abolished.vassals.estate.sov',
					array('%link-realm%'=>$realm->getId()),
					History::MEDIUM
				);
				$vassal->setRealm(null);
			} else {
				$this->history->logEvent(
					$vassal,
					'event.realm.abolished.vassals.estate.notsov',
					array('%link-realm%'=>$realm->getId(), '%link-realm-2%'=>$superior->getId()),
					History::MEDIUM
				);
				$vassal->setRealm($superior);
			}
		}
		$this->em->flush();
		foreach ($realm->getPositions() as $position) {
			if ($position->getHolders()) {
				foreach ($position->getHolders() as $holder) {
					if ($position->getRuler()) {
						$this->abdicate($realm, $holder);
					} else if (!$position->getRuler()) {
						$position->removeHolder($holder);
						$holder->removePosition($position);
						$this->history->logEvent(
							$holder,
							'event.character.position.abolished',
							array('%link-realm%'=>$realm->getId(), '%link-realmposition%'=>$position->getId()),
							History::MEDIUM
						); # 'Lost the position of %link-realmposition% due to the dismantling of %link-realm%.'
					}
				}
				foreach ($position->getVassals() as $vassal) {
					$this->history->logEvent(
						$vassal,
						'event.realm.abolished.vassals.place',
						array('%link-realm%'=>$realm->getId(), '%link-realmposition%'=>$position->getId()),
						History::MEDIUM
					);
					$vassal->setLiegePosition(null);
				}
			}
		}
		$this->em->flush();
	}
}
