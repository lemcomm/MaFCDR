<?php

namespace BM2\SiteBundle\Service;

use BM2\DungeonBundle\Service\DungeonMaster;
use BM2\SiteBundle\Entity\Achievement;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Partnership;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\RealmPosition;
use BM2\SiteBundle\Entity\User;
use Calitarus\MessagingBundle\Service\MessageManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;

class CharacterManager {

	protected $em;
	protected $appstate;
	protected $military;
	protected $history;
	protected $politics;
	protected $realmmanager;
	protected $messagemanager;
	protected $dm;


	public function __construct(EntityManager $em, AppState $appstate, History $history, Military $military, Politics $politics, RealmManager $realmmanager, MessageManager $messagemanager, DungeonMaster $dm) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->history = $history;
		$this->military = $military;
		$this->politics = $politics;
		$this->realmmanager = $realmmanager;
		$this->messagemanager = $messagemanager;
		$this->dm = $dm;
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
		// remove from map
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
			$this->military->removeCharacterFromBattlegroup($character, $bg);
		}

		// remove all votes
		$query = $this->em->createQuery('DELETE FROM BM2SiteBundle:Vote v WHERE v.character = :me OR v.target_character = :me');
		$query->setParameter('me', $character);
		$query->execute();

		// disband my troops
		foreach ($character->getSoldiers() as $soldier) {
			$this->military->disband($soldier, $character);
		}
		foreach ($character->getEntourage() as $entourage) {
			$this->military->disbandEntourage($entourage, $character);
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

		foreach ($character->getVassals() as $vassal) {
			if ($vassal->getEstates() || $vassal->getPositions()) {
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

		if ($heir) {
			foreach ($character->getEstates() as $estate) {
				$this->bequeathEstate($estate, $heir, $character, $via);
			}

			foreach ($character->getVassals() as $vassal) {
				$this->updateVassal($vassal, $heir, $character, $via);
			}
		} else {
			foreach ($character->getEstates() as $estate) {
				$this->failInheritEstate($character, $estate);
			}
			foreach ($character->findRulerships() as $realm) {
				$this->failInheritRealm($character, $realm);
			}
		}

		// close all logs except my personal one
		foreach ($character->getReadableLogs() as $log) {
			if ($log != $character->getLog()) {
				$this->history->closeLog($log, $character);
			}
		}

		// TODO: permission lists - plus clear out those of old dead characters!
		

		// clean out dungeon stuff
		$this->dm->cleanupDungeoneer($character);

		$this->messagemanager->leaveAllConversations($this->messagemanager->getMsgUser($character));

		return true;
	}

	public function retire(Character $character) {
		// This is very similar to the kill function above, but retirement is more restricted so we don't worry about certain things.
		// List is set to 90 as this sorts them to the retired listing on the account character list.
		$character->setRetired(true)->setList(90)->setSlumbering(true);
		// remove from map and hiearchy
		$character->setLiege(null);

		$this->history->logEvent($character, 'event.character.retired', array(), History::ULTRA, true);

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
		foreach ($character->getSoldiers() as $soldier) {
			$this->military->disband($soldier, $character);
		}
		foreach ($character->getEntourage() as $entourage) {
			$this->military->disbandEntourage($entourage, $character);
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
			if ($vassal->getEstates() || $vassal->getPositions()) {
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

		// TODO: When we add dynasites, maybe send gold to family?
		$character->setGold(0);

		// inheritance
		$this->seen = new ArrayCollection;
		list($heir, $via) = $this->findHeir($character);

		// TODO: if no heir set, check if I have a family (need to determine who inherits - partner - children - parents - or a different order ?)
		// TODO: check for realm laws and decide if inheritance allowed

		if ($heir) {
			foreach ($character->getEstates() as $estate) {
				$this->bequeathEstate($estate, $heir, $character, $via);
			}

			foreach ($character->getVassals() as $vassal) {
				$this->updateVassal($vassal, $heir, $character, $via);
			}
		} else {
			foreach ($character->getEstates() as $estate) {
				$this->failInheritEstate($character, $estate);
			}
			foreach ($character->findRulerships() as $realm) {
				$this->failInheritRealm($character, $realm);
			}
		}

		// FIXME: Do we want to close logs for a character that could come back?
		/* 
		foreach ($character->getReadableLogs() as $log) {
			if ($log != $character->getLog()) {
				$this->history->closeLog($log, $character);
			}
		} 
		*/

		// TODO: permission lists - plus clear out those of old dead characters!
		

		// clean out dungeon stuff
		$this->dm->retireDungeoneer($character);

		$this->messagemanager->leaveAllConversations($this->messagemanager->getMsgUser($character));

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
			$this->military->removeCharacterFromBattlegroup($character, $bg);
		}
		$captor = $character->getPrisonerOf();
		$character->setLocation($captor->getLocation());
		$character->setInsideSettlement($captor->getInsideSettlement());
	}

	public function bequeathEstate(Settlement $estate, Character $heir, Character $from, Character $via=null) {
		$this->politics->changeSettlementOwner($estate, $heir);

		$this->history->closeLog($estate, $from);
		$this->history->openLog($estate, $heir);

		// Note that this CAN leave a character the lord of estates in seperate realms.
		if ($from == $via || $via == null) {
			$this->history->logEvent(
				$heir,
				'event.character.inherit.estate',
				array('%link-settlement%'=>$estate->getId(), '%link-character%'=>$from->getId()),
				HISTORY::HIGH, true
			);
		} else {
			$this->history->logEvent(
				$heir,
				'event.character.inheritvia.estate',
				array('%link-settlement%'=>$estate->getId(), '%link-character-1%'=>$from->getId(), '%link-character-2%'=>$via->getId()),
				History::HIGH, true
			);
		}
		$this->history->logEvent(
			$estate, 'event.settlement.inherited',
			array('%link-character%'=>$from->getId()),
			History::HIGH, true
		);
	}

	private function failInheritEstate(Character $character, Settlement $estate) {
		$this->politics->changeSettlementOwner($estate, null);
		$this->history->logEvent(
			$estate, 'event.settlement.inherifail',
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

}
