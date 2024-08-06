<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Battle;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\BattleGroup;
use BM2\SiteBundle\Twig\GameTimeExtension;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;

use CrEOF\Spatial\PHP\Types\Geometry\Point;


class ActionResolution {

	private $em;
	private $appstate;
	private $history;
	private $dispatcher;
	private $generator;
	private $geography;
	private $interactions;
	private $politics;
	private $characters;
	private $permissions;
	private $gametime;
	private $warman;
	private $actman;
	private $helper;

	private $max_progress = 100; // maximum number of actions to resolve in each background progression call
	private $debug=100;
	private $speedmod = 1.0;


	public function __construct(EntityManager $em, AppState $appstate, CharacterManager $charman, History $history, Dispatcher $dispatcher, Generator $generator, Geography $geography, Interactions $interactions, Politics $politics, PermissionManager $permissions, GameTimeExtension $gametime, WarManager $warman, ActionManager $actman, HelperService $helper) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->charman = $charman;
		$this->history = $history;
		$this->dispatcher = $dispatcher;
		$this->generator = $generator;
		$this->geography = $geography;
		$this->interactions = $interactions;
		$this->politics = $politics;
		$this->permissions = $permissions;
		$this->gametime = $gametime;
		$this->warman = $warman;
		$this->actman = $actman;
		$this->helper = $helper;
		$this->characters = new ArrayCollection();

		$this->speedmod = (float)$this->appstate->getGlobal('travel.speedmod', 1.0);
	}

	public function progress() {
		$query = $this->em->createQuery("SELECT a FROM BM2SiteBundle:Action a WHERE a.complete IS NOT NULL AND a.complete < :now");
		$query->setParameter('now', new \DateTime("now"));
		$query->setMaxResults($this->max_progress);
		foreach ($query->getResult() as $action) {
			$this->resolve($action);
		}
	}

	public function resolve(Action $action) {
		$type = strtr($action->getType(), '.', '_');

		if (method_exists(__CLASS__, $type)) {
			if ($char = $action->getCharacter()) {
				$this->characters->add($char);
				$this->dispatcher->setCharacter($char);
			}
			$this->$type($action);
			return true;
		} else {
			$this->remove($action);
			return false;
		}
	}

	public function update(Action $action) {
		$type = strtr($action->getType(), '.', '_');

		$up = 'update_'.$type;
		if (method_exists(__CLASS__, $up)) {
			return $this->$up($action);
		}
		return false;
	}


	/* ========== Resolution Methods ========== */

	// TODO: time counter, etc.

	// TODO: messages are mixed, sometimes 2nd person (you have...) and sometimes 3rd (he has...)
	//      --> see note in MessageTranslateExtension

	private function remove(Action $action) {
		// this is just a placeholder action marked for removal, so let's do exactly that (it's our workaround to Doctrine's broken cascades)
		$this->em->remove($action);
	}

	private function check_settlement_take(Action $action) {
		$settlement = $action->getTargetSettlement();
		$this->dispatcher->setCharacter($action->getCharacter());
		$test = $this->dispatcher->controlTakeTest(false, false);
		if (!isset($test['url'])) {
			$this->history->logEvent(
				$action->getCharacter(),
				'multi',
				array('events'=>array('resolution.take.failed', 'resolution.'.$test['description']),
				 '%link-settlement%'=>$settlement->getId()),
				History::LOW, false, 30
			);
			$this->history->logEvent(
				$settlement,
				'event.settlement.take.stopped',
				array('%link-character%'=>$action->getCharacter()->getId()),
				History::HIGH, true, 20
			);
			if ($owner = $settlement->getOwner()) {
				$this->history->logEvent(
					$owner,
					'event.character.take.stopped',
					array('%link-character%'=>$action->getCharacter()->getId(), '%link-settlement'=>$settlement->getId()),
					History::MEDIUM, false, 20
				);
			}
			if ($steward = $settlement->getSteward()) {
				$this->history->logEvent(
					$steward,
					'event.character.take.stopped',
					array('%link-character%'=>$action->getCharacter()->getId(), '%link-settlement'=>$settlement->getId()),
					History::MEDIUM, false, 20
				);
			}
			foreach ($settlement->getVassals() as $vassal) {
				$this->history->logEvent(
					$vassal,
					'event.character.take.stopped',
					array('%link-character%'=>$action->getCharacter()->getId(), '%link-settlement'=>$settlement->getId()),
					History::MEDIUM, false, 20
				);
			}
			$this->em->flush();
			return false;
		} else {
			return true;
		}
	}

	private function update_settlement_take(Action $action) {
		// recalculate time
		if ($this->check_settlement_take($action)) {
			$now = new \DateTime("now");
			$old_time = $action->getComplete()->getTimestamp() - $action->getStarted()->getTimestamp();
			$elapsed = $now->getTimestamp() - $action->getStarted()->getTimestamp();
			$done = min(1.0, $elapsed / $old_time);

			if ($action->getSupportingActions()->count() > 0) {
				$supporters = new ArrayCollection();
				foreach ($action->getSupportingActions() as $support) {
					foreach ($support->getCharacter() as $char) {
						$supporters->add($char);
					}
				}
			} else {
				$supporters = null;
			}
			if ($action->getOpposingActions()->count() > 0) {
				$opposers = new ArrayCollection();
				foreach ($action->getOpposingActions() as $oppose) {
					foreach ($oppose->getCharacter() as $char) {
						$opposers->add($char);
					}
				}
			} else {
				$opposers = null;
			}

			$time = $action->getTargetSettlement()->getTimeToTake($action->getCharacter(), $supporters, $opposers);

			if ($time/$old_time < 0.99 || $time/$old_time > 1.01) {
				$time_left = round($time * (1-$done));
				$action->setComplete($now->add(new \DateInterval("PT".$time_left."S")));
			}
		} else {
			$this->em->remove($action);
		}
		$this->em->flush();
	}

	private function settlement_take(Action $action) {
		if ($this->check_settlement_take($action)) {
			// success
			$settlement = $action->getTargetSettlement();

			// update log access
			if ($settlement->getOwner()) {
				$this->history->closeLog($settlement, $settlement->getOwner());
			}
			if ($settlement->getSteward()) {
				$this->history->closeLog($settlement, $settlement->getSteward());
			}
			$this->history->openLog($settlement, $action->getCharacter());

			// the actual change
			$this->politics->changeSettlementOwner($settlement, $action->getCharacter(), 'take');
			$this->politics->changeSettlementRealm($settlement, $action->getTargetRealm(), 'take');

			// if we are not already inside, enter
			if ($action->getCharacter()->getInsideSettlement() != $settlement) {
				$this->interactions->characterEnterSettlement($action->getCharacter(), $settlement);
			}
		}
		$this->em->remove($action);
	}

	private function update_settlement_loot(Action $action) {
		$now = new \DateTime("now");
		if ($action->getComplete() <= $now) {
			$this->em->remove($action);
		}
	}

	private function settlement_loot(Action $action) {
		// just remove this, damage and all has already been applied, we just needed the action to stop travel
		$this->em->remove($action);
	}

	private function update_military_block(Action $action) {
		if ($action->getCharacter()->isInBattle()) {
			return; // to avoid double battls
		}
		// check if there are targets nearby we want to engage
		$maxdistance = 2 * $this->geography->calculateInteractionDistance($action->getCharacter());
		$possible_targets = $this->geography->findCharactersNearMe($action->getCharacter(), $maxdistance, $action->getCharacter()->getInsideSettlement()?false:true, true, false, true);

		$victims = array();
		foreach ($possible_targets as $target) {
			list($check, $list, $level) = $this->permissions->checkListing($action->getTargetListing(), $target['character']);
			if ( ( ($check && $action->getStringValue()=='attack') || (!$check && $action->getStringValue()=='allow') ) && $target['character']->getSystem() != 'GM' ){
				$victims[] = $target['character'];
			}
		}
		if ($victims) {
			$this->warman->createBattle($action->getCharacter(), null, null, $victims);
			$this->em->remove($action);
		}
	}


	private function military_damage(Action $action) {
		// just remove this, damage and all has already been applied, we just needed the action to stop travel
		$this->em->remove($action);
	}

	private function military_hire(Action $action) {
		// just remove this, it is just a timed action to stop rich people from hiring all mercenaries in one go
		$this->em->remove($action);
	}

	private function military_regroup(Action $action) {
		// just remove this, it is just a timed action to stop immediate re-engagements
		$this->history->logEvent(
			$action->getCharacter(),
			'resolution.regroup.success',
			array(),
			History::LOW, false, 15
		);
		$this->em->remove($action);
		$this->em->flush();
	}

	// TODO: this is not actually being used anymore - do we still want to keep it?
	private function settlement_enter(Action $action) {
		$settlement = $action->getTargetSettlement();

		if (!$settlement) {
			$this->log(0, 'invalid action '.$action->getId());
			// TODO: clean it up, but during alpha we want it to hang around for debug purposes
			return;
		}

		if ($this->interactions->characterEnterSettlement($action->getCharacter(), $settlement)) {
			// entered the place
			$this->history->logEvent(
				$action->getCharacter(),
				'resolution.enter.success',
				array('%settlement%'=>$settlement),
				History::LOW, false, 20
			);
		} else {
			// we are not allowed to enter
			$this->history->logEvent(
				$action->getCharacter(),
				'resolution.enter.success',
				array('%settlement%'=>$settlement),
				History::LOW, false, 20
			);
		}
		$this->em->remove($action);
		$this->em->flush();
	}

	private function settlement_rename(Action $action) {
		$settlement = $action->getTargetSettlement();
		$newname = $action->getStringValue();
		$oldname = $settlement->getName();
		if (!$settlement || !$newname || $newname=="") {
			$this->log(0, 'invalid action '.$action->getId());
			// TODO: clean it up, but during alpha we want it to hang around for debug purposes
			return;
		}

		$test = $this->dispatcher->controlRenameTest();
		if (!isset($test['url'])) {
			$this->history->logEvent(
				$action->getCharacter(),
				'multi',
				array('events'=>array('resolution.rename.failed', 'resolution.'.$test['description']),
				 '%link-settlement%'=>$settlement->getId(), '%new%'=>$newname),
				History::LOW, false, 30
			);
		} else {
			$settlement->setName($newname);
			if ($marker = $settlement->getGeoMarker()) { $marker->setName($newname); } // update hidden geofeature
			$this->history->logEvent(
				$settlement,
				'event.settlement.renamed',
				array('%oldname%'=>$oldname, '%newname%'=>$newname),
				History::MEDIUM, true
			);
			$this->history->logEvent(
				$action->getCharacter(),
				'resolution.rename.success',
				array('%new%'=>$newname),
				History::LOW, false, 20
			);

		}
		$this->em->remove($action);
		$this->em->flush();
	}

	private function settlement_grant(Action $action) {
		$settlement = $action->getTargetSettlement();
		$to = $action->getTargetCharacter();

		if (!$settlement || !$to || !$action->getCharacter()) {
			$this->log(0, 'invalid action '.$action->getId());
			// TODO: clean it up, but during alpha we want it to hang around for debug purposes
			return;
		}


		$test = $this->dispatcher->controlGrantTest();
		if (!isset($test['url'])) {
			$this->history->logEvent(
				$action->getCharacter(),
				'resolution.grant.failed',
				array('%link-settlement%'=>$settlement->getId(), '%link-character%'=>$to->getId(), '%reason%'=>array('key'=>'resolution.'.$test['description'])),
				History::MEDIUM, false, 30
			);
		} else {
			if ($settlement->getOwner()) {
				$this->history->closeLog($settlement, $settlement->getOwner());
			}
			$this->history->openLog($settlement, $to);
			if (strpos($action->getStringValue(), 'keep_claim') === false) {
				$reason = 'grant';
			} else {
				$reason = 'grant_fief';
			}
			$this->politics->changeSettlementOwner($settlement, $to, $reason);

			if (strpos($action->getStringValue(), 'clear_realm') !== false && $settlement->getRealm()) {
				$this->politics->changeSettlementRealm($settlement, null, 'grant');
			}
		}
		$this->em->remove($action);
		$this->em->flush();
	}


	private function settlement_occupant(Action $action) {
		$settlement = $action->getTargetSettlement();
		$to = $action->getTargetCharacter();

		if (!$settlement || !$to || !$action->getCharacter()) {
			$this->log(0, 'invalid action '.$action->getId());
			// TODO: clean it up, but during alpha we want it to hang around for debug purposes
			return;
		}


		$test = $this->dispatcher->controlChangeOccupantTest();
		if (!isset($test['url'])) {
			$this->history->logEvent(
				$action->getCharacter(),
				'resolution.occupant.failed',
				array('%link-settlement%'=>$settlement->getId(), '%link-character%'=>$to->getId(), '%reason%'=>array('key'=>'resolution.'.$test['description'])),
				History::MEDIUM, false, 30
			);
		} else {
			$this->politics->changeSettlementOccupier($to, $settlement, $settlement->getOccupier());
		}
		$this->em->remove($action);
		$this->em->flush();
	}

	private function settlement_attack(Action $action) {
		// this is just a convenience alias
		$this->military_battle($action);
	}

	private function settlement_assault(Action $action) {
		/* Just an alias for now, so we can differentiate these on creation. Later we can add more dynamic logic. */
		$this->military_battle($action);
	}

	private function settlement_sortie(Action $action) {
		/* Just an alias for now, so we can differentiate these on creation. Later we can add more dynamic logic. */
		$this->military_battle($action);
	}

	private function update_settlement_defend(Action $action) {
		if (!$action->getCharacter() || !$action->getTargetSettlement()) {
			$this->log(0, 'invalid action '.$action->getId());
			// TODO: clean it up, but during alpha we want it to hang around for debug purposes
			return;
		}

		// check if we are in action range
		$distance = $this->geography->calculateDistanceToSettlement($action->getCharacter(), $action->getTargetSettlement());
		$actiondistance = $this->geography->calculateActionDistance($action->getTargetSettlement());
		if ($distance > $actiondistance) {
			$this->history->logEvent(
				$action->getCharacter(),
				'resolution.defend.removed',
				array('%link-settlement%'=>$action->getTargetSettlement()->getId()),
				History::LOW, false, 10
			);
			$this->em->remove($action);
			$this->em->flush();
		}
	}

	private function update_military_aid(Action $action) {
		$character = $action->getCharacter();
		if ($character->isInBattle() || $character->isDoingAction('military.regroup')) {
			return;
		}

		// check if target is in battle and within interaction range
		if ($action->getTargetCharacter()->isInBattle()) {
			$distance = $this->geography->calculateDistanceToCharacter($character, $action->getTargetCharacter());
			$actiondistance = $this->geography->calculateInteractionDistance($character);
			if ($distance < $actiondistance && $character->getInsideSettlement() == $action->getTargetCharacter()->getInsideSettlement()) {
				// join all battles on his side
				foreach ($action->getTargetCharacter()->getBattlegroups() as $group) {
					$this->warman->joinBattle($character, $group);
					$this->history->logEvent(
						$character,
						'resolution.aid.success',
						array('%link-character%'=>$action->getTargetCharacter()->getId()),
						History::HIGH, false, 15
					);
				}

				$this->em->remove($action);
				$this->em->flush();
			}
		}
	}

	private function military_aid(Action $action) {
		// support ends
		$this->history->logEvent(
			$action->getCharacter(),
			'resolution.aid.removed',
			array('%link-character%'=>$action->getTargetCharacter()->getId()),
			History::LOW, false, 10
		);
		$this->em->remove($action);
		$this->em->flush();
	}

	private function military_battle(Action $action) {
		// battlerunner actually resolves this all
	}

	private function military_disengage(Action $action) {
		$char = $action->getCharacter();

		// ideas:
		// * chance should depend on relative force sizes and maybe scouts and other entourage
		// * larger enemy forces can encircle better
		// * small parts of large forces can evade better (betrayl works....)

		// TODO: how to notify parties remaining in the battle? Do we need a battle event log? hope not!

		// higher chance to evade if we are in multiple battles?

		$chance = 40;
		// the larger my army, the less chance I have to evade (with 500 people, -50 %)
		$soldiercount = 0;
		foreach ($char->getUnits() as $unit) {
			$soldiercount += $unit->getSoldiers()->count();
		}
		$chance -= sqrt( ($soldiercount + $char->getEntourage()->count()) * 5);

		// biome - we re-use spotting here
		$biome = $this->geography->getLocalBiome($char);
		$chance *= 1/$biome->getSpot();

		// avoid the abusive "catch with small army to engage, while large army moves in for the kill" abuse for extreme scenarios
		$eGrps = $action->getTargetBattlegroup()->getEnemies();
		$enemies = 0;
		$eChars = new ArrayCollection();
		foreach ($eGrps as $eGrp) {
			$enemies = $eGrp->getActiveSoldiers()->count();
			foreach ($eGrp->getCharacters as $gChar) {
				if (!$eChars->contains($gChar)) {
					$eChars->add($gChar);
				}
			}
		}
		if ($enemies < 5) {
			$chance += 30;
		} elseif ($enemies < 10) {
			$chance += 20;
		} elseif ($enemies < 25) {
			$chance += 10;
		}

		// cap between 5% and 80%
		$chance = min(80, max(5,$chance));

		if ($char->isDoingAction('military.block')
			|| $char->isDoingAction('military.damage')
			|| $char->isDoingAction('military.loot')
			|| $char->isDoingAction('settlement.attack')
			|| $char->isDoingAction('settlement.defend') ) {
			// these actions are incompatible with evasion - fail
			$chance = 0;
		}

		if (rand(0,100) < $chance) {
			if ($action->getTargetBattlegroup()->getCharacters()->count() === 1 && $action->getTargetBattlegroup()->getBattle()->getGroups()->count() == 2) {
				# Just us, we can short-circuit this battle.
				foreach ($action->getTargetBattlegroup()->getBattle()->getGroups() as $group) {
					$this->warman->disbandGroup($group);
				}
			} else {
				// add a short regroup timer to those who engaged me, to prevent immediate re-engages
				foreach ($eChars as $enemy) {
					$act = new Action;
					$act->setType('military.regroup')->setCharacter($enemy);
					$act->setBlockTravel(false);
					$act->setCanCancel(false);
					$complete = new \DateTime('now');
					$complete->add(new \DateInterval('PT60M'));
					$act->setComplete($complete);
					$this->actman->queue($act, true);
				}
				$this->warman->removeCharacterFromBattlegroup($char, $action->getTargetBattlegroup());
				$this->em->remove($action);
			}
			$this->history->logEvent(
				$char,
				'resolution.disengage.success',
				array(),
				History::MEDIUM, false, 10
			);
			$get_away = 0.1;
		} else {
			$this->history->logEvent(
				$char,
				'resolution.disengage.failed',
				array(),
				History::MEDIUM, false, 10
			);
			$action->setType('military.intercepted');
			$action->setCanCancel(false);
			$action->setHidden(true);

			$get_away = 0.05;
		}
		$this->em->flush();

		// to avoid people being trapped by overlapping engages - allow them to move a tiny bit along travel route
		// 0.1 is 10% of a day's journey, or about 50% of an hourly journey - or about 1km base speed, modified for character speed
		// if the disengage failed, we move half that.
		if ($char->getTravel()) {
			$char->setProgress(min(1.0, $char->getProgress() + $char->getSpeed()*$get_away));
		} else {
			// TODO: we should move a tiny bit, but must take rivers, oceans, etc. into account - can we re-use the travel check somehow?
		}
	}

	private function military_intercepted(Action $action) {
		// Get our character.
		$character = $action->getCharacter();
		// Set battle to false.
		$battle = false;
		// Get character actions.
		if ($character->getActions()) {
			// Check each of them.
			foreach ($character->getActions() as $otheract) {
				// If one of them is a battle, set $battle to true and stop checking.
				if ($otheract->getType() == 'military.battle' && !$battle) {
					$battle = true;
				}
			}
		}
		// If we didn't find a battle, remove the military.intercepted action. Otherwise, we keep it, to ensure you can only evade once.
		if (!$battle) {
			$this->em->remove($action);
		}
	}

	private function personal_prisonassign(Action $action) {
		// just remove, this is just a blocking action
		$this->em->remove($action);
	}

	private function character_escape(Action $action) {
		// just remove, this is just a blocking action
		$char = $action->getCharacter();

		if ($captor = $char->getPrisonerOf()) {
			// low chance if captor is active, otherwise automatic
			if ($captor->isActive()) {
				$chance = 10;
			} else {
				$chance = 100;
			}
			if (rand(0,99) < $chance) {
				// escaped!
				$this->charman->addAchievement($captor, 'escapees', 1);
				$captor->removePrisoner($char);
				$char->setPrisonerOf(null);
				$this->charman->addAchievement($char, 'escaped', 1);
				$this->history->logEvent(
					$char,
					'resolution.escape.success',
					array(),
					History::HIGH, true, 20
				);
				$this->history->logEvent(
					$captor,
					'resolution.escape.by',
					array('%link-character%'=>$char->getId()),
					History::MEDIUM, false, 30
				);
			} else {
				// failed
				$this->charman->addAchievement($char, 'failedescapes', 1);
				$this->history->logEvent(
					$char,
					'resolution.escape.failed',
					array(),
					History::HIGH, true, 20
				);
				$this->history->logEvent(
					$captor,
					'resolution.escape.try',
					array('%link-character%'=>$char->getId()),
					History::MEDIUM, false, 30
				);
			}
		}

		$this->em->remove($action);
		$this->em->flush();
	}

	private function update_task_research(Action $action) {
		// TODO: shift event journal start max(one day, one task) into the past
		// easily done: get cycle of next-oldest date and shift to there

		if ($action->getTargetRealm()) {
			$log = $action->getTargetRealm()->getLog();
		} elseif ($action->getTargetSettlement()) {
			$log = $action->getTargetSettlement()->getLog();
		} elseif ($action->getTargetCharacter()) {
			$log = $action->getTargetCharacter()->getLog();
		} else {
			// FIXME: should never happen
			$this->log(0, 'invalid research action '.$action->getId());
			// TODO: clean it up, but during alpha we want it to hang around for debug purposes
			return;
		}
		$meta = $this->em->getRepository('BM2SiteBundle:EventMetadata')->findOneBy(array('log'=>$log, 'reader'=>$action->getCharacter()));

		if (!$meta) {
			# Somehow we're looking at a log we don't have our own version of?
			$meta = $this->history->openLog($log->getSubject(), $action->getCharacter());
			$this->em->flush(); #Probably not needed, but just in case.
		}

		$query = $this->em->createQuery('SELECT MAX(e.cycle) FROM BM2SiteBundle:Event e WHERE e.log=:log AND e.cycle < :earliest');
		$query->setParameters(array('log'=>$log, 'earliest'=>$meta->getAccessFrom()));
		$next = $query->getSingleScalarResult();
		$meta->setAccessFrom($next);

		$allMeta = $this->em->getRepository('BM2SiteBundle:EventMetadata')->findBy(array('log'=>$log, 'reader'=>$action->getCharacter()));
		if (count($allMeta) > 1) {
			# We have multiple, check for possible merges.
			foreach ($allMeta as $each) {
				foreach ($allMeta as $other) {
					if ($other->getAccessFrom() <= $each->getAccessUntil()) {
						$other->setAccessUntil($each->getAccessUntil());
						$this->em->remove($each);
					}
				}
			}
		}

		// see history::investigateLog() - actually, we might move this code here to there

		if (!$next) {
			foreach ($action->getAssignedEntourage() as $npc) {
				$npc->setAction(null);
			}
			$this->history->logEvent(
				$action->getCharacter(),
				'resolution.research.complete', array("%link-log%"=>$log->getId()),
				History::LOW, false, 30
			);
			$this->em->remove($action);
			$this->em->flush();
		}
	}

	private function update_train_skill(Action $action) {
		$this->helper->trainSkill($action->getcharacter(), $action->getTargetSkill(), 0, 1);
	}

	public function log($level, $text) {
		if ($level <= $this->debug) {
			echo $text."\n";
			flush();
		}
	}

}
