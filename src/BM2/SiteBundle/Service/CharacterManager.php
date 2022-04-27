<?php

namespace BM2\SiteBundle\Service;

use BM2\DungeonBundle\Service\DungeonMaster;
use BM2\SiteBundle\Entity\Achievement;
use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\AssociationMember;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\CharacterBackground;
use BM2\SiteBundle\Entity\House;
use BM2\SiteBundle\Entity\Partnership;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\RealmPosition;
use BM2\SiteBundle\Entity\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CharacterManager {

	protected $em;
	protected $appstate;
	protected $milman;
	protected $history;
	protected $politics;
	protected $realmmanager;
	protected $convman;
	protected $dm;
	protected $warman;
	protected $assocman;


	public function __construct(EntityManager $em, AppState $appstate, History $history, MilitaryManager $milman, Politics $politics, RealmManager $realmmanager, ConversationManager $convman, DungeonMaster $dm, WarManager $warman, AssociationManager $assocman) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->history = $history;
		$this->milman = $milman;
		$this->politics = $politics;
		$this->realmmanager = $realmmanager;
		$this->convman = $convman;
		$this->dm = $dm;
		$this->warman = $warman;
		$this->assocman = $assocman;
	}


	public function create(User $user, $name, $gender='m', $alive=true, Character $father=null, Character $mother=null, Character $partner=null) {
		$character = new Character();
		$character->setGeneration(1);
		$character->setAlive($alive)->setSlumbering(!$alive)->setNpc(false);
		// ugly hardcoded values, but they're hardcoded in the translation strings, so...
		if ($alive) {
			$character->setList(1);
		} else {
			// created dead = ancestor
			$character->setList(150);
		}
		$character->setName($name);
		$character->setUser($user);
		$character->setCreated(new \DateTime("now"));
		$character->setLastAccess(new \DateTime("now"));
		if ($gender=='f') {
			$character->setMale(false);
		} else {
			$character->setMale(true);
		}
		$character->setTravelLocked(false)->setTravelEnter(false)->setTravelAtSea(false)->setTravelDisembark(false);
		$character->setSpecial(false);
		$character->setWounded(0);
		$character->setGold(0);
		$character->setVisibility(5)->setSpottingDistance(1000);
		$character->setGenome($this->createGenome($user, $father, $mother));

		if ($father) {
			$father->addChild($character);
			$character->setGeneration($father->getGeneration()+1);
		}
		if ($mother) {
			$mother->addChild($character);
			if ($mother->getGeneration() >= $character->getGeneration()) {
				$character->setGeneration($mother->getGeneration() + 1);
			}
		}
		if ($father && $mother && $father->getHouse() && $mother->getHouse() && $father->getHouse() == $mother->getHouse()) {
			$character->setHouse($father->getHouse);
			$this->history->logEvent(
				$house,
				'event.house.newbirth2',
				array('%link-character-1%'=>$father->getId(), '%link-character-2%'=>$mother->getId()),
				History::ULTRA, true
			);
		} else if ($father && !$mother && $father->getHouse()) {
			$character->setHouse($father->getHouse);
			$this->history->logEvent(
				$house,
				'event.house.newbirth1',
				array('%link-character%'=>$father->getId()),
				History::ULTRA, true
			);
		} else if ($mother && !$father && $mother->getHouse()) {
			$character->setHouse($mother->getHouse);
			$this->history->logEvent(
				$house,
				'event.house.newbirth1',
				array('%link-character%'=>$mother->getId()),
				History::MEDIUM, true
			);
		}
		if ($partner) {
			$relation = new Partnership();
			$relation->setType('marriage');
			$relation->setPublic(true);
			$relation->setWithSex(true);
			$relation->setActive(true);
			$relation->setInitiator($character);
			$relation->getPartners()->add($character);
			$relation->getPartners()->add($partner);
			$relation->setPartnerMayUseCrest(false);
			$this->em->persist($relation);
		}

		$this->em->persist($character);
		$this->em->flush($character); // because the below needs this flushed

		$this->history->logEvent($character, 'event.character.created');
		$this->history->openLog($character, $character);
		if ($father) {
			$this->history->logEvent($father, 'event.character.child', array('%link-character%'=>$character->getId()), History::HIGH);
		}
		if ($mother) {
			$this->history->logEvent($mother, 'event.character.child', array('%link-character%'=>$character->getId()), History::HIGH);
		}
		return $character;
	}

	// FIXME: should be private after initial update
	public function createGenome(User $user, Character $father=null, Character $mother=null) {
		$genome = '__';
		$genome_set = $user->getGenomeSet();

		if ($father) {
			$genome[0] = $this->randomGenome($father);
		} else {
			// no father - take one random trait from user genome set
			$genome[0] = $genome_set[array_rand(str_split($genome_set))];
		}

		// TODO: must be a different genome from the father, so that we have two unique ones - but our first try to make that happen failed
		if ($mother) {
			$genome[1] = $this->randomGenome($mother);
		} else {
			// no mother - take one random trait from user genome set
			$genome[1] = $genome_set[array_rand(str_split($genome_set))];
		}

		// FIXME: apparently, sometimes this return an empty string? HOW ?
		if ($genome == '' || $genome == '  ') {
			throw new \Exception("Please report this error to mafteam@lemuriacommunity.org: u:".$user->getId()."/f:".($father?$father->getId():'0')."/m:".($mother?$mother->getId():'0')."/g:".$genome."/");
		}

		return $genome;
	}

	private function randomGenome(Character $ancestor) {
		$selection = $ancestor->getGenome();
		if ($ancestor->getFather()) {
			$selection.=$ancestor->getFather()->getGenome();
		}
		if ($ancestor->getMother()) {
			$selection.=$ancestor->getMother()->getGenome();
		}
		return $selection[array_rand(str_split($selection))];
	}

	public function kill(Character $character, $killer=null, $forcekiller=false, $deathmsg='death') {
		$character->setAlive(false)->setList(99)->setSlumbering(true);
		// we used to remove characters from the map as part of this, but that's now handled by the GameRunner.
		$character->setSystem(null);
		// remove from hierarchy
		$character->setLiege(null);

		$this->history->logEvent($character, 'event.character.'.$deathmsg, $killer?array('%link-character%'=>$killer->getId()):null, History::ULTRA, true);

		// reset account restriction, so it is recalculated - we do this very early so later failures don't impact it
		if ($character->getUser()) {
			$character->getUser()->setRestricted(false);
		}

		// TODO: info/event to parents, children and husband

		// terminate all actions and battles
		foreach ($character->getActions() as $act) {
			$this->em->remove($act);
		}
		foreach ($character->getBattlegroups() as $bg) {
			$this->warman->removeCharacterFromBattlegroup($character, $bg);
		}

		// remove all votes
		$query = $this->em->createQuery('DELETE FROM BM2SiteBundle:Vote v WHERE v.character = :me OR v.target_character = :me');
		$query->setParameter('me', $character);
		$query->execute();

		// disband my troops
		foreach ($character->getUnits() as $unit) {
			$this->milman->returnUnitHome($unit, 'death', $character);
		}
		foreach($character->getMarshallingUnits() as $unit) {
			$unit->setMarshal(null);
		}
		foreach ($character->getEntourage() as $entourage) {
			$this->milman->disbandEntourage($entourage, $character);
		}

		// remove all claims
		foreach ($character->getSettlementClaims() as $claim) {
			if ($claim->getEnforceable()) {
				// TODO: enforceable claims are inherited
			}
			$claim->getSettlement()->removeClaim($claim);
			$character->removeSettlementClaim($claim);
			$this->em->remove($claim);
		}

		// assigned troops now can't be reclaimed anymore, because I'm DEAD, you know?
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.liege = NULL WHERE s.liege = :me');
		$query->setParameter('me', $character);
		$query->execute();

		foreach ($character->getNewspapersEditor() as $paper) {
			// TODO: check if we are the last owner, if so, do something (make editors owners or so)
			$character->removeNewspapersEditor($paper);
			$this->em->remove($paper);
		}
		foreach ($character->getNewspapersReader() as $paper) {
			$character->removeNewspapersReader($paper);
			$this->em->remove($paper);
		}

		foreach ($character->getPrisoners() as $prisoner) {
			if ($killer) {
				$prisoner->setPrisonerOf($killer);
				$character->removePrisoner($prisoner);
				$killer->addPrisoner($prisoner);
				$this->history->logEvent(
					$prisoner,
					'event.character.prison.assign2',
					array("%link-character-1%"=>$killer->getId(), "%link-character-2%"=>$character->getId()),
					History::MEDIUM, true, 30
				);
				$this->history->logEvent(
					$killer,
					'event.character.prison.received2',
					array("%link-character-1%"=>$character->getId(), "%link-character-2%"=>$prisoner->getId()),
					History::MEDIUM, true, 30
				);
			} else {
				$prisoner->setPrisonerOf(null);
				$character->removePrisoner($prisoner);
				$this->history->logEvent(
					$prisoner,
					'event.character.prison.free2',
					array("%link-character%"=>$character->getId()),
					History::MEDIUM, true, 30
				);
			}
		}

		foreach ($character->getArtifacts() as $artifact) {
			if ($killer) {
				$artifact->setOwner($killer);
				$this->history->logEvent(
					$killer,
					'event.character.artifact',
					array("%link-artifact%"=>$artifact->getId(), "%link-character%"=>$character->getId()),
					History::MEDIUM, true
				);
				$this->history->logEvent(
					$artifact,
					'event.artifact.killer',
					array("%link-character-1%"=>$killer->getId(), "%link-character-2%"=>$character->getId()),
					History::MEDIUM, true
				);
			} else {
				$this->history->logEvent(
					$artifact,
					'event.artifact.lost',
					array("%link-character%"=>$character->getId()),
					History::MEDIUM, true
				);
				// FIXME: it should drop into the gameworld instead
				$artifact->setOwner(null);
			}
		}

		// dead men are free - TODO: but a notice to the captor would be in order - unless he is the killer (no need for redundancy)

		# Vassalages get changed after we check for the heir, either by the inheritance functions or the legacy check afterwards.
		foreach ($character->getVassals() as $vassal) {
			if ($vassal->getOwnedSettlements() || $vassal->getPositions()) {
				$this->history->logEvent(
					$vassal,
					'event.character.liegedied',
					array("%link-character%"=>$character->getId()),
					History::MEDIUM, true
				);
			} else {
				$this->history->logEvent(
					$vassal,
					'event.character.liegedied2',
					array("%link-character%"=>$character->getId()),
					History::HIGH, true
				);
			}
			$vassal->setLiege(null);
			$character->removeVassal($vassal);
		}

		// FIXME: what about relationships ?


		// wealth
		if ($killer && $character->getGold() > 0) {
			// take most of the gold from him (his entourage may take some, you may miss some, whatever...)
			$gain = round(rand($character->getGold() * 0.5, $character->getGold() * 0.75));
			$killer->setGold($killer->getGold() + $gain);
			$this->history->logEvent(
				$killer,
				'event.character.killgold',
				array("%link-character%"=>$character->getId(), "%gold%"=>$character->getGold()),
				History::MEDIUM, false, 20
			);
		}
		$character->setGold(0);

		// inheritance
		if ($forcekiller) {
			$heir = null;
          		$via = null;
		} else {
			$this->seen = new ArrayCollection;
			list($heir, $via) = $this->findHeir($character);
		}

		// TODO: if no heir set, check if I have a family (need to determine who inherits - partner - children - parents - or a different order ?)

		// TODO: check for realm laws and decide if inheritance allowed


		if (!$heir && $killer) {
			// TOOD: if no heir, killer inherits by Right of Conquest
		}

		foreach ($character->getOwnedSettlements() as $settlement) {
			$this->locationInheritance($settlement, $character, $heir, $via);
		}

		foreach ($character->getOwnedPlaces() as $place) {
			$this->locationInheritance($place, $character, $heir, $via);
		}

		if ($heir) {
			# Legacy vassal check.
			foreach ($character->getVassals() as $vassal) {
				$vassal->setLiege(null);
				$this->history->logEvent(
					$vassal,
					'politics.oath.notcurrent2',
					array('%link-place%'=>$place->getId()),
					History::HIGH, true
				);
			}
		} else {
			foreach ($character->findRulerships() as $realm) {
				$this->failInheritRealm($character, $realm);
			}
		}
		#TODO: This should really also handle positions, rather than leaving them to the game runner.

		foreach ($character->getStewardingSettlements() as $settlement) {
			$settlement->setSteward(null);
			$this->history->logEvent(
				$settlement,
				'event.settlement.stewarddeath',
				array('%link-character%'=>$character->getId()),
				History::MEDIUM, true
			);
		}
		if ($character->getHeadOfHouse()) {
			$this->houseInheritance($character);
		}

		foreach ($character->getAssociationMemberships() as $mbr) {
			$this->assocInheritance($mbr, $character, $heir, $via);
		}

		// close all logs except my personal one
		foreach ($character->getReadableLogs() as $log) {
			if ($log != $character->getLog()) {
				$this->history->closeLog($log, $character);
			}
		}

		foreach ($character->getRequests() as $req) {
			$this->em->remove($req);
		}

		// TODO: permission lists - plus clear out those of old dead characters!


		// clean out dungeon stuff
		$this->dm->cleanupDungeoneer($character);

		$this->convman->removeAllConversations($character);
		$this->em->flush();

		return true;
	}

	public function retire(Character $character) {
		// This is very similar to the kill function above, but retirement is more restricted so we don't worry about certain things.
		// List is set to 90 as this sorts them to the retired listing on the account character list.
		$character->setRetired(true)->setList(90)->setSlumbering(true);
		// remove from map and hiearchy
		$character->setLocation(null)->setInsideSettlement(null)->setTravel(null)->setProgress(null)->setSpeed(null);
		$character->setLiege(null);

		$this->history->logEvent($character, 'event.character.retired', array(), History::HIGH, true);

		// reset account restriction, so it is recalculated - we do this very early so later failures don't impact it
		if ($character->getUser()) {
			$character->getUser()->setRestricted(false);
		}

		// TODO: info/event to parents, children and husband

		// terminate all actions -- don't worry about battles because you can't retire when engaged in one.
		foreach ($character->getActions() as $act) {
			$this->em->remove($act);
		}

		// remove all votes
		$query = $this->em->createQuery('DELETE FROM BM2SiteBundle:Vote v WHERE v.character = :me OR v.target_character = :me');
		$query->setParameter('me', $character);
		$query->execute();

		// disband my troops
		foreach ($character->getUnits() as $unit) {
			$this->milman->returnUnitHome($unit, 'retire', $character);
		}
		foreach($character->getMarshallingUnits() as $unit) {
			$unit->setMarshal(null);
		}
		foreach ($character->getEntourage() as $entourage) {
			$this->milman->disbandEntourage($entourage, $character);
		}

		// FIXME: Since they're not dead, do they lose their claims still? Hm.
		/*
		foreach ($character->getSettlementClaims() as $claim) {
			if ($claim->getEnforceable()) {
				// TODO: enforceable claims are inherited
			}
			$claim->getSettlement()->removeClaim($claim);
			$character->removeSettlementClaim($claim);
			$this->em->remove($claim);
		}
		*/

		// assigned troops now can't be reclaimed anymore, as character is retired
		$query = $this->em->createQuery('UPDATE BM2SiteBundle:Soldier s SET s.liege = NULL WHERE s.liege = :me');
		$query->setParameter('me', $character);
		$query->execute();

		foreach ($character->getNewspapersEditor() as $paper) {
			// TODO: check if we are the last owner, if so, do something (make editors owners or so)
			$character->removeNewspapersEditor($paper);
			$this->em->remove($paper);
		}
		foreach ($character->getNewspapersReader() as $paper) {
			$character->removeNewspapersReader($paper);
			$this->em->remove($paper);
		}

		foreach ($character->getPrisoners() as $prisoner) {
			$prisoner->setPrisonerOf(null);
			$character->removePrisoner($prisoner);
			$this->history->logEvent(
				$prisoner,
				'event.character.prison.free2',
				array("%link-character%"=>$character->getId()),
				History::MEDIUM, true, 30
			);
		}

		foreach ($character->getArtifacts() as $artifact) {
			$this->history->logEvent(
				$artifact,
				'event.artifact.lost',
				array("%link-character%"=>$character->getId()),
				History::MEDIUM, true
			);
			$artifact->setOwner(null);
		}

		foreach ($character->getVassals() as $vassal) {
			if ($vassal->getOwnedSettlements() || $vassal->getPositions()) {
				$this->history->logEvent(
					$vassal,
					'event.character.liegeretired',
					array("%link-character%"=>$character->getId()),
					History::MEDIUM, true
				);
			} else {
				$this->history->logEvent(
					$vassal,
					'event.character.liegeretired2',
					array("%link-character%"=>$character->getId()),
					History::HIGH, true
				);
			}
			$vassal->setLiege(null);
			$character->removeVassal($vassal);
		}

		// TODO: Maybe send this gold to the family, if there is one?
		$character->setGold(0);

		// inheritance
		$this->seen = new ArrayCollection;
		list($heir, $via) = $this->findHeir($character);

		// TODO: if no heir set, check if I have a family (need to determine who inherits - partner - children - parents - or a different order ?)
		// TODO: check for realm laws and decide if inheritance allowed
		foreach ($character->getOwnedSettlements() as $settlement) {
			$this->locationInheritance($settlement, $character, $heir, $via);
		}

		foreach ($character->getOwnedPlaces() as $place) {
			$this->locationInheritance($place, $character, $heir, $via);
		}
		if ($heir) {
			# Legacy vassal check.
			foreach ($character->getVassals() as $vassal) {
				$vassal->setLiege(null);
				$this->history->logEvent(
					$vassal,
					'politics.oath.notcurrent2',
					array('%link-place%'=>$place->getId()),
					History::HIGH, true
				);
			}
		} else {
			foreach ($character->findRulerships() as $realm) {
				$this->failInheritRealm($character, $realm);
			}
		}
		foreach ($character->getStewardingSettlements() as $settlement) {
			$settlement->setSteward(null);
			$this->history->logEvent(
				$settlement,
				'event.settlement.stewardretire',
				array('%link-character%'=>$character->getId()),
				History::MEDIUM, true
			);
		}
		if ($character->getHeadOfHouse()) {
			$this->houseInheritance($character, 'retire');
		}

		foreach ($character->getAssociationMemberships() as $mbr) {
			$this->assocInheritance($mbr, $character, $heir, $via, 'retire');
		}

		foreach ($character->getReadableLogs() as $log) {
			if ($log != $character->getLog()) {
				$this->history->closeLog($log, $character);
			}
		}

		foreach ($character->getRequests() as $req) {
			$this->em->remove($req);
		}

		// TODO: permission lists - plus clear out those of old dead characters!


		// clean out dungeon stuff
		$this->dm->retireDungeoneer($character);
		$character->setRetiredOn(new \DateTime("now"));

		$this->convman->leaveAllConversations($character);
		$this->em->flush();

		return true;
	}

	public function imprison(Character $character, Character $captor) {
		$this->imprison_prepare($character, $captor);
		$this->imprison_complete($character);
	}

	public function imprison_prepare(Character $character, Character $captor) {
		$character->setPrisonerOf($captor);
		$captor->addPrisoner($character);

		// transfer prisoners of my prisoner to me
		foreach ($character->getPrisoners() as $prisoner) {
			$prisoner->setPrisonerOf($captor);
			$captor->addPrisoner($prisoner);
			$character->removePrisoner($prisoner);
			// TODO: history log / event notification
		}

		// clear travel
		$character->setTravel(null)->setProgress(null)->setSpeed(null);
	}

	public function imprison_complete(Character $character) {
		// terminate all actions and battles
		foreach ($character->getActions() as $act) {
			$this->em->remove($act);
		}
		foreach ($character->getBattlegroups() as $bg) {
			$this->war_manager->removeCharacterFromBattlegroup($character, $bg);
		}
		$captor = $character->getPrisonerOf();
		$character->setLocation($captor->getLocation());
		$character->setInsideSettlement($captor->getInsideSettlement());
	}

	public function locationInheritance($thing, Character $char, $heir, $via) {
		# $heir and $via can be false or Character objects.
		if ($thing instanceof Settlement) {
			$type = 'settlement';
			$bequeath = 'bequeathEstate';
			$fail = 'failInheritEstate';
		} else {
			if ($thing->getType()->getName() == 'home' && $thing->getHouse() && $thing->getOwner() === $thing->getHouse()->getHeadOfHouse()) {
				return true; # Lineage is respected over law.
			}
			$type = 'place';
			$bequeath = 'bequeathPlace';
			$fail = 'failInheritPlace';
		}
		if ($realm = $thing->getRealm()) {
			$law = $realm->findLaw('settlementInheritance');
			if ($law) {
				$value = $law->getValue();
			} else {
				$value = 'characterAny';
			}
			# Break locations are intentional and provide law condition cascading, as intended.
			switch ($value) {
				case 'steward':
					if ($steward = $thing->getSteward()) {
						$this->$bequeath($thing, $steward, $char, null);
						$thing->setSteward(null);
						$this->history->logEvent(
							$settlement,
							'event.settlement.stewardpromote',
							array('%link-character%'=>$char->getId()),
							History::MEDIUM, true
						);
						break;
					}
				case 'liege':
					if ($liege = $char->findLiege()) {
						if ($liege instanceof Collection) {
							$liege = $liege->first();
						}
						$this->$bequeath($thing, $liege, $char, null);
						break;
					}
				case 'ruler':
					if ($rulers = $realm->findRulers()) {
						if ($rulers->count() > 0) {
							$this->$bequeath($thing, $rulers->first(), $char, null);
							break;
						}
					}
				case 'characterInternal':
					if ($heir && $heir->findRealms()->contains($realm)) {
						$this->$bequeath($thing, $heir, $char, $via);
					} else {
						$this->$fail($char, $thing, 'lawnoinherit');
					}
					break;
				case 'characterAny':
					if ($heir) {
						$this->$bequeath($thing, $heir, $char, $via);
					} else {
						$this->$fail($char, $thing, 'lawnoinherit');
					}
					break;
				case 'none':
					$this->$fail($char, $thing, 'lawnoinherit');
					break;
			}
		} elseif ($heir) {
			$this->$bequeath($thing, $heir, $char, $via);
		} else {
			$this->$fail($char, $thing);
		}
	}

	public function houseInheritance(Character $character, $why = 'death') {
		$house = $character->getHeadOfHouse();
		$inheritor = false;
		$difhouse = false;
		if ($house->getSuccessor() && $house->getSuccessor()->getHouse() == $character->getHouse() && !$house->getSuccessor()->isActive(true)) {
			# House has a successor, this takes priority, so long as they're also in the house and active (alive, not slumbering or retired)
			$inheritor = true;
			$successor = $character->getHeadOfHouse->getSuccessor();
		} else if ($character->getSuccessor() && $character->getSuccessor()->isActive(true) && (
			$character->getSuccessor()->getHouse() == $character->getHouse() OR (
				$character->findImmediateRelatives()->contains($character->getSuccessor()) AND $character->getSuccessor()->getHouse()
			)
		)) {
			$inheritor = true;
			$successor = $character->getSuccessor();
			if ($successor->getHouse() != $character->getHouse()) {
				$difhouse = true;
			}
		}
		if ($inheritor) {
			$house->setHead($successor);
			$house->setSuccessor(null);
			if (!$difhouse) {
				$this->history->logEvent(
					$house,
					'event.house.inherited.'.$why,
					array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$successor->getId()),
					History::ULTRA, true
				);
			} else {
				$this->history->logEvent(
					$house,
					'event.house.merged.'.$why,
					array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$successor->getId(), '%link-house-1%'=>$house->getId(), '%link-house-2%'=>$successor->getHouse()->getId()),
					History::ULTRA, true
				);
				$this->history->logEvent(
					$successor->getHouse(),
					'event.house.merged.'.$why,
					array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$successor->getId(), '%link-house-1%'=>$house->getId(), '%link-house-2%'=>$successor->getHouse()->getId()),
					History::ULTRA, true
				);
				$house->setSuperior($successor->getHouse());
				$successor->setHouse($house);
			}
			if ($home = $house->getHome()) {
				$home->setOwner($successor);
				$this->history->logEvent(
					$house,
					'event.place.inherited.'.$why,
					array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$successor->getId()),
					History::ULTRA, true
				);
			}
		} else {
			$best = null;
			foreach ($house->findAllActive() as $member) {
				if ($best === null && $member != $character) {
					$best = $member;
				}
				if ($member->getHouseJoinDate() < $best->getHouseJoinDate() && $member != $character) {
					$best = $member;
				}
			}
			$house->setHead($best);
			$house->setSuccessor(null);
			if ($best !== null) {
				$this->history->logEvent(
					$house,
					'event.house.newhead.'.$why,
					array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$best->getId()),
					History::ULTRA, true
				);
			} else {
				$house->setActive(false);
				$this->history->logEvent(
					$house,
					'event.house.collapsed.'.$why,
					array(),
					History::ULTRA, true
				);
			}
			if ($home = $house->getHome()) {
				$home->setOwner(null);
				$this->history->logEvent(
					$house,
					'event.place.abandoned.'.$why,
					array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$successor->getId()),
					History::ULTRA, true
				);
			}
		}
	}

	public function assocInheritance(AssociationMember $mbr, Character $char, Character $heir=null, Character $via=null, $why = 'death') {
		if ($rank = $mbr->getRank()) {
			if ($rank->isOwner() && $rank->getMembers()->count() == 1) {
				$assoc = $rank->getAssociation();
				$law = $assoc->findLaw('assocInheritance');
				if ($law) {
					$value = $law->getValue();
				} else {
					$value = 'senior';
				}
				if ($value == 'character' && $heir == null) {
					$value = 'senior';
				}
				switch ($value) {
					case 'character':
						$this->assocman->updateMember($assoc, $rank, $heir, false);
						$this->bequeathAssoc($assoc, $rank, $heir, false);
						break;
					case 'senior':
						$seniors = new ArrayCollection;
						foreach($assoc->getRanks() as $aRank) {
							if ($aRank->getSuperior() == null) {
								foreach ($aRank->getMembers() as $each) {
									$seniors->add($each);
								}
							}
						}
						$mostSenior = null;
						foreach ($seniors as $each) {
							if (!$mostSenior) {
								$mostSenior = $each->getCharacter();
							}
							if ($each->getRankDate() > $mostSenior->getRankDate()) {
								$mostSenior = $each->getCharacter();
							}
						}
						$this->assocman->updateMember($assoc, $rank, $mostSenior, false);
						$this->bequeathAssoc($assoc, $rank, $mostSenior, false);
						break;
					case 'oldest':
						$query = $this->em->createQuery('SELECT m, c FROM BM2SiteBundle:AssociationMember m JOIN m.character c WHERE m.association = :assoc ORDER BY m.join_date ASC LIMIT 1');
						$query->setParameters(['assoc'=>$assoc]);
						$oldest = $query->getResult();
						$this->assocman->updateMember($assoc, $rank, $oldest->getCharacter(), false);
						$this->bequeathAssoc($assoc, $rank, $oldest->getCharacter(), false);
						break;
				}
				$this->em->flush();
			}
		}
		$this->assocman->removeMember($mbr->getAssociation(), $mbr->getCharacter());
	}

	public function bequeathAssoc(Association $assoc, Character $heir, Character $from, Character $via=null) {
		if ($from == $via || $via == null) {
			$this->history->logEvent(
				$heir,
				'event.character.inherit.assoc',
				array('%link-association%'=>$assoc->getId(), '%link-character%'=>$char->getId()),
				HISTORY::HIGH, true
			);
		} else {
			$this->history->logEvent(
				$heir,
				'event.character.inheritvia.assoc',
				array('%link-association%'=>$assoc->getId(), '%link-character-1%'=>$char->getId(), '%link-character-2%'=>$via->getId()),
				History::HIGH, true
			);
		}
		$this->history->logEvent(
			$assoc,
			'event.assoc.inherited',
			array('%link-character%'=>$char->getId()),
			History::HIGH, true
		);
	}

	public function bequeathEstate(Settlement $settlement, Character $heir, Character $from, Character $via=null) {
		$this->politics->changeSettlementOwner($settlement, $heir);

		$this->history->closeLog($settlement, $from);
		$this->history->openLog($settlement, $heir);

		// Note that this CAN leave a character the lord of estates in seperate realms.
		if ($from == $via || $via == null) {
			$this->history->logEvent(
				$heir,
				'event.character.inherit.estate',
				array('%link-settlement%'=>$settlement->getId(), '%link-character%'=>$from->getId()),
				HISTORY::HIGH, true
			);
		} else {
			$this->history->logEvent(
				$heir,
				'event.character.inheritvia.estate',
				array('%link-settlement%'=>$settlement->getId(), '%link-character-1%'=>$from->getId(), '%link-character-2%'=>$via->getId()),
				History::HIGH, true
			);
		}
		$this->history->logEvent(
			$settlement, 'event.settlement.inherited',
			array('%link-character%'=>$from->getId()),
			History::HIGH, true
		);
	}

	private function failInheritEstate(Character $character, Settlement $settlement, $string = 'inherifail') {
		$this->politics->changeSettlementOwner($settlement, null);
		$this->history->logEvent(
			$settlement, 'event.settlement.'.$string,
			array('%link-character%'=>$character->getId()),
			HISTORY::HIGH, true
		);

	}

	public function bequeathPlace(Place $place, Character $heir, Character $from, Character $via=null) {
		$oldowner = $place->getOwner();
		if ($oldowner) {
			$oldowner->removeOwnedPlace($place);
		}
		if ($heir) {
			$heir->addOwnedPlace($place);
		}
		$place->setOwner($heir);
		foreach ($place->getPermissions() as $perm) {
			$place->removePermission($perm);
			$this->em->remove($perm);
		}

		foreach ($place->getVassals() as $vassal) {
			$vassal->setOathCurrent(false);
			$this->history->logEvent(
				$vassal,
				'politics.oath.notcurrent2',
				array('%link-place%'=>$place->getId()),
				History::HIGH, true
			);
		}

		$this->history->closeLog($place, $from);
		$this->history->openLog($place, $heir);

		// Note that this CAN leave a character the lord of estates in seperate realms.
		if ($from == $via || $via == null) {
			$this->history->logEvent(
				$heir,
				'event.character.inherit.place',
				array('%link-place%'=>$place->getId(), '%link-character%'=>$from->getId()),
				HISTORY::HIGH, true
			);
		} else {
			$this->history->logEvent(
				$heir,
				'event.character.inheritvia.place',
				array('%link-place%'=>$place->getId(), '%link-character-1%'=>$from->getId(), '%link-character-2%'=>$via->getId()),
				History::HIGH, true
			);
		}
		$this->history->logEvent(
			$place,
			'event.place.inherited',
			array('%link-character%'=>$from->getId()),
			History::HIGH, true
		);
	}

	private function failInheritPlace(Character $character, Place $place, $string = 'inherifail') {
		$oldowner = $place->getOwner();
		if ($oldowner) {
			$oldowner->removeOwnedPlace($place);
		}
		if ($character) {
			$character->addOwnedPlace($place);
		}
		$place->setOwner(null);
		foreach ($place->getPermissions() as $perm) {
			$place->removePermission($perm);
			$this->em->remove($perm);
		}
		$place->setOwner(null);

		foreach ($place->getVassals() as $vassal) {
			$vassal->setOathCurrent(false);
			$this->history->logEvent(
				$vassal,
				'politics.oath.notcurrent2',
				array('%link-place%'=>$place->getId()),
				History::HIGH, true
			);
		}
		$this->history->logEvent(
			$place, 'event.place.'.$string,
			array('%link-character%'=>$character->getId()),
			HISTORY::HIGH, true
		);

	}


	public function updateVassal(Character $vassal, Character $heir, Character $from, Character $via=null) {
		// TODO - quite a bit here, the new lord could be a different realm and all
	}

	public function findHeir(Character $character, Character $from=null) {
		// NOTE: This should match the implemenation on GameRunner.php
		if (!$from) {
			$from = $character;
		}

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
		} elseif ($why == 'retire') {
			$this->history->logEvent(
				$realm, 'event.realm.inherifailretire',
				array('%link-character%'=>$character->getId()),
				HISTORY::HIGH, true
			);
		} elseif ($why == 'slumber') {
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

	public function failInheritPosition(Character $character, RealmPosition $position, $why='death') {
		if ($why == 'death') {
			$this->history->logEvent(
				$position->getRealm(),
				'event.position.death',
				array('%link-character%'=>$character->getId(), '%link-realmposition%'=>$position->getId()),
				History::LOW, true
			);
		} else if ($why == 'slumber') {
			$this->history->logEvent(
				$position->getRealm(),
				'event.position.inactive',
				array('%link-character%'=>$character->getId(), '%link-realmposition%'=>$position->getId()),
				History::LOW, true
			);
		}
	}

	public function findEvents(Character $character) {
		$query = $this->em->createQuery('SELECT e, l, m FROM BM2SiteBundle:Event e JOIN e.log l JOIN l.metadatas m WHERE m.reader = :me AND e.ts > m.last_access AND (m.access_until IS NULL OR e.cycle <= m.access_until) AND (m.access_from IS NULL OR e.cycle >= m.access_from) ORDER BY e.ts DESC');
		$query->setParameter('me', $character);
		return $query->getResult();
	}


	/* achievements */
	public function getAchievement(Character $character, $key) {
		return $character->getAchievements()->filter(
			function($entry) use ($key) {
				return ($entry->getType()==$key);
			}
		)->first();
	}

	public function getAchievementValue(Character $character, $key) {
		if ($a = $this->getAchievement($character, $key)) {
			return $a->getValue();
		} else {
			return null;
		}
	}

	public function setAchievement(Character $character, $key, $value) {
		$this->setMaxAchievement($character, $key, $value, false);
	}

	public function setMaxAchievement(Character $character, $key, $value, $only_raise=true) {
		if ($a = $this->getAchievement($character, $key)) {
			if (!$only_raise || $a->getValue() < $value) {
				$a->setValue($value);
			}
		} else {
			$a = new Achievement;
			$a->setType($key);
			$a->setValue($value);
			$a->setCharacter($character);
			$this->em->persist($a);
			$character->addAchievement($a);
		}
	}

	public function addAchievement(Character $character, $key, $value=1) {
		if ($value==0) return; // this way we can call this method without checking and it'll not update if not necessary
		$value = round($value);
		if ($a = $this->getAchievement($character, $key)) {
			$a->setValue($a->getValue() + $value);
		} else {
			$a = new Achievement;
			$a->setType($key);
			$a->setValue($value);
			$a->setCharacter($character);
			$this->em->persist($a);
			$character->addAchievement($a);
		}
	}

	public function Reputation(Character $char, User $me=null) {
		// There are probably nice ways to do all this in SQL
		$ratings = $this->em->getRepository('BM2SiteBundle:CharacterRating')->findByCharacter($char);
		$data = array();
		$respect=array('yes'=>0,'no'=>0);
		$honor=array('yes'=>0,'no'=>0);
		$trust=array('yes'=>0,'no'=>0);
		foreach ($ratings as $rating) {
			$pro = 1;
			$contra = 1;
			$myvote = 0;

			foreach ($rating->getVotes() as $vote) {
				$voter = $vote->getUser();
				$hisvotes = 0;
				foreach ($ratings as $r) {
					foreach ($r->getVotes() as $v) {
						if ($v->getUser() == $voter) {
							$hisvotes++;
						}
						if ($me && $v->getUser() == $me) {
							$myvote = $v->getValue();
						}
					}
				}
				if ($vote->getValue()<0) {
					$contra += 1/$hisvotes;
				} else {
					$pro += 1/$hisvotes;
				}
			}
			$value = $pro/$contra;
			$data[] = array(
				'rating' => $rating,
				'pro' => $pro,
				'contra' => $contra,
				'value' => $value,
				'myvote' => $myvote,
			);

			if ($rating->getRespect()>0) { $respect['yes']+=$value; } elseif ($rating->getRespect()<0) { $respect['no']+=$value; }
			if ($rating->getHonor()>0) { $honor['yes']+=$value; } elseif ($rating->getHonor()<0) { $honor['no']+=$value; }
			if ($rating->getTrust()>0) { $trust['yes']+=$value; } elseif ($rating->getTrust()<0) { $trust['no']+=$value; }
		}

		return array($respect, $honor, $trust, $data);
	}

	public function SimpleReputation(Character $char, User $me=null) {
		list($respect, $honor, $trust, $data) = $this->Reputation($char, $me);

		$max=0;
		if ($respect['yes'] > $max) { $max = $respect['yes']; }
		if ($respect['no'] > $max) { $max = $respect['no']; }
		if ($honor['yes'] > $max) { $max = $honor['yes']; }
		if ($honor['no'] > $max) { $max = $honor['no']; }
		if ($trust['yes'] > $max) { $max = $trust['yes']; }
		if ($trust['no'] > $max) { $max = $trust['no']; }

		$threshold = $max * 0.65;

		$rep = array();
		if ($respect['yes'] > $threshold) { $rep[] = 'respect'; }
		if ($respect['no'] > $threshold) { $rep[] = 'disrespect'; }
		if ($honor['yes'] > $threshold) { $rep[] = 'honor'; }
		if ($honor['no'] > $threshold) { $rep[] = 'dishonor'; }
		if ($trust['yes'] > $threshold) { $rep[] = 'trust'; }
		if ($trust['no'] > $threshold) { $rep[] = 'distrust'; }

		return $rep;
	}

	public function newBackground(Character $character) {
		if (!$character->getBackground()) {
			$background = new CharacterBackground;
			$character->setBackground($background);
			$background->setCharacter($character);
			$this->em->persist($background);
			$this->em->flush();
		}
	}

	public function checkReturnability(Character $character) {
		if (!is_null($character->getRetiredOn()) && $character->getRetiredOn()->diff(new \DateTime("now"))->days < 7) {
			throw new AccessDeniedHttpException('error.noaccess.notreturnable');
		}
	}

	public function updateAllegiance(Character $character, Realm $realm = null, Place $place, Settlement $settlement = null, RealmPosition $position = null) {
		if ($realm) {
			$character->setRealm($realm);
			$character->setLiegeLand(null);
			$character->setLiegePlace(null);
			$character->setLiegePosition(null);
		} elseif ($place) {
			$character->setRealm(null);
			$character->setLiegeLand(null);
			$character->setLiegePlace($place);
			$character->setLiegePosition(null);
		} elseif ($settlement) {
			$character->setRealm(null);
			$character->setLiegeLand($settlement);
			$character->setLiegePlace(null);
			$character->setLiegePosition(null);
		} elseif ($position) {
			$character->setRealm(null);
			$character->setLiegeLand(null);
			$character->setLiegePlace(null);
			$character->setLiegePosition($position);
		}
	}

}
