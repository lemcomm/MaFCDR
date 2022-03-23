<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Battle;
use BM2\SiteBundle\Entity\BattleGroup;
use BM2\SiteBundle\Entity\BattleReport;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Siege;

use Doctrine\ORM\EntityManager;
use BM2\SiteBundle\Service\ActionManager;
use BM2\SiteBundle\Service\History;
use BM2\SiteBundle\Service\Interactions;
use BM2\SiteBundle\Service\MilitaryManager;
use BM2\SiteBundle\Twig\GameTimeExtension;
use Monolog\Logger;

use CrEOF\Spatial\PHP\Types\Geometry\Point;

/*
War Manager exists to handle all service duties involved in battles and sieges. Things relating to specific soldiers, units, equipment, or entourage belong in Military.
*/

class WarManager {

	protected $em;
	protected $history;
	protected $milman;
	protected $actman;
	protected $interactions;
	protected $politics;
	protected $logger;

	private $report;
	private $debug=0;

	public function __construct(EntityManager $em, History $history, MilitaryManager $milman, ActionManager $actman, GameTimeExtension $gametime, Interactions $interactions, Politics $politics, Logger $logger) {
		$this->em = $em;
		$this->history = $history;
		$this->milman = $milman;
		$this->actman = $actman;
		$this->gametime = $gametime;
		$this->interactions = $interactions;
		$this->politics = $politics;
		$this->logger = $logger;
	}

	public function createBattle(Character $character, Settlement $settlement=null, Place $place=null, $targets=array(), Siege $siege=null, BattleGroup $attackers=null, BattleGroup $defenders=null) {
		/* for future reference, $outside is used to determine whether or not attackers need to leave the settlement in order to attack someone.
		It's used by attackOthersAction of WarCon. --Andrew */
		$bothinside = false;
		$type = 'field';

		$battle = new Battle;
		$this->em->persist($battle);
		if ($siege) {
			# Check for sieges first, because they'll always have settlements or places attached, but settlements and places won't always come with sieges.
			if ($settlement) {
				$location = $siege->getSettlement()->getGeoData()->getCenter();
				$battle->setSettlement($settlement);
				$outside = false;
			} elseif ($place) {
				if ($place->getSettlement()) {
					$location = $siege->getPlace()->getSettlement()->getGeoData()->getCenter();
					$battle->setSettlement($place->getSettlement());
					$battle->setPlace($place);
					$outside = false;
				} else {
					$location = $place->getLocation();
					$battle->setPlace($place);
					$outside = true;
				}
			}
			$battle->setSiege($siege);
			if ($siege->getAttacker() == $attackers) {
				# If they are the siege attackers and attacking in this battle, then they're assaulting. If not, they're sallying. It affects defensive bonuses.
				$battle->setType('siegeassault');
				$type = 'assault';
				if ($settlement) {
					$this->history->logEvent(
						$settlement,
						'event.settlement.siege.assault',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
				} elseif ($place && $place->getSettlement()) {
					$this->history->logEvent(
						$place->getSettlement(),
						'event.settlement.place.assault',
						array('%link-character%'=>$character->getId(), '%link-place%'=>$place->getId()),
						History::MEDIUM, false, 60
					);
					$this->history->logEvent(
						$place,
						'event.place.siege.assault',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
				} else {
					$this->history->logEvent(
						$place,
						'event.place.siege.assault',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
				}
			} else {
				$battle->setType('siegesortie');
				$type = 'sortie';
				if ($settlement) {
					$this->history->logEvent(
						$settlement,
						'event.settlement.siege.sortie',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
				} elseif ($place && $place->getSettlement()) {
					$this->history->logEvent(
						$place->getSettlement(),
						'event.settlement.place.sortie',
						array('%link-character%'=>$character->getId(), '%link-place%'=>$place->getId()),
						History::MEDIUM, false, 60
					);
					$this->history->logEvent(
						$place,
						'event.place.siege.sortie',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
				} else {
					$this->history->logEvent(
						$place,
						'event.place.siege.sortie',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
				}
			}
		} else if ($settlement || $place) {
			if ($settlement) {
				$battle->setSettlement($settlement);
			} else {
				$battle->setPlace($place);
			}
			$foundinside = false;
			$foundoutside = false;
			$foundboth = false;
			/* Because you can only attack a settlement/place during a siege, that means that if we're doing this we must be attacking FROM a settlement/place without a siege.
			Outside of a siege this is only set if you start a battle
			So we need to figure out if our targets are inside or outside. If we find a mismatch, we drop the outsiders and only attack those inside. */
			if ($place) {
				foreach ($targets as $target) {
					if ($target->getInsidePlace()) {
						$foundinside = true;
					} else {
						$foundoutside = true;
					}
				}
			} else {
				foreach ($targets as $target) {
					if ($target->getInsideSettlement()) {
						$foundinside = true;
					} else {
						$foundoutside = true;
					}
				}
			}
			if ($foundinside && $foundoutside) {
				# Found people inside and outside, prioritize inside. Battle type is urban.
				$foundboth = true;
				$battle->setType('urban');
				$type = 'skirmish';
				if ($settlement) {
					$location = $settlement->getGeoData()->getCenter();
					foreach ($targets as $target) {
						# Logic to remove people outside from target list.
						if (!$target->getInsideSettlement()) {
							$key = array_search($target, $targets);
							if($key!==false){
							    unset($targets[$key]);
							}
						}
					}
					$this->history->logEvent(
						$settlement,
						'event.settlement.skirmish',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
				} else {
					if ($place->getInsideSettlement()) {
						$location = $place->getInsideSettlement()->getGeoData()->getCenter();
					} else {
						$location = $place->getLocation();
					}
					foreach ($targets as $target) {
						# Logic to remove people outside from target list.
						if (!$target->getInsidePlace()) {
							$key = array_search($target, $targets);
							if($key!==false){
							    unset($targets[$key]);
							}
						}
					}
					$this->history->logEvent(
						$place,
						'event.place.skirmish',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
				}
			} else if ($foundinside && !$foundoutside) {
				# Only people inside. Urban battle.
				$battle->setType('urban');
				$location = $settlement->getGeoData()->getCenter();
				$outside = false;
				$this->history->logEvent(
					$settlement,
					'event.settlement.skirmish',
					array('%link-character%'=>$character->getId()),
					History::MEDIUM, false, 60
				);
				$type = 'skirmish';
			} else if (!$foundinside && $foundoutside) {
				if ($place && $place->getSettlement()) {
					$battle->setType('urban');
					# Outside the place, but inside a settlement.
					$outside = false;
					$location = $place->getSettlement()->getGeoData()->getCenter();
					$this->history->logEvent(
						$settlement,
						'event.settlement.skirmish',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
				} else {
					$battle->setType('field');
					# Only people outside. Battle type is field. Collect location data.
					$outside = true;
					$x=0; $y=0; $count=0;
					foreach ($targets as $target) {
						$x+=$target->getLocation()->getX();
						$y+=$target->getLocation()->getY();
						$count++;
					}
					$location = new Point($x/$count, $y/$count);
					# Yes, we are literally just averaging the X and Y coords of the participants.
					$this->history->logEvent(
						$settlement,
						'event.settlement.sortie',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
					$type = 'skirmish';
				}
			} else {
				# You've somehow broke the laws of space, and appear to exist in neither inside nor outside. Congrats.
			}
		} else {
			$x=0; $y=0; $count=0; $outside = false;
			foreach ($targets as $target) {
				$x+=$target->getLocation()->getX();
				$y+=$target->getLocation()->getY();
				$count++;
			}
			$location = new Point($x/$count, $y/$count);
		}
		$battle->setLocation($location);
		$battle->setStarted(new \DateTime('now'));

		// setup attacker (i.e. me)
		if (!$attackers) {
			$attackers = new BattleGroup;
			$this->em->persist($attackers);
		}
		$attackers->setBattle($battle);
		if (!$siege) {
			# Already setup by siege handlers.
			$attackers->setAttacker(true);
			$attackers->addCharacter($character);
		}
		$battle->addGroup($attackers);

		// setup defenders
		if (!$defenders) {
			$defenders = new BattleGroup;
			$this->em->persist($defenders);
		}
		$defenders->setBattle($battle);
		if (!$siege) {
			# Already setup by siege handlers.
			$defenders->setAttacker(false);
			foreach ($targets as $target) {
				$defenders->addCharacter($target);
			}
		}
		$battle->addGroup($defenders);
		$battle->setPrimaryAttacker($attackers);
		$battle->setPrimaryDefender($defenders);

		// now we have all involved set up we can calculate the preparation timer
		$time = $this->calculatePreparationTime($battle);
		$complete = new \DateTime('now');
		$complete->add(new \DateInterval('PT'.$time.'S'));
		$battle->setInitialComplete($complete)->setComplete($complete);
		$this->em->flush();


		// setup actions and lock travel
		switch ($type) {
			case 'siegeassault':
			case 'assault':
				$acttype = 'siege.assault';
				break;
			case 'siegesortie':
			case 'sortie':
				$acttype = 'siege.sortie';
				break;
			case 'field':
			case 'urban':
			default:
				$acttype = 'military.battle';
				break;
		}

		if ($acttype == 'military.battle') {
			if ($place) {
				$act = new Action;
				$act->setType($acttype);
				$act->setCharacter($character)
					->setTargetPlace($place)
					->setTargetSettlement($settlement)
					->setTargetBattlegroup($attackers)
					->setCanCancel(false)
					->setBlockTravel(true);
				$this->actman->queue($act);
			} else {
				$act = new Action;
				$act->setType($acttype);
				$act->setCharacter($character)
					->setTargetSettlement($settlement)
					->setTargetBattlegroup($attackers)
					->setCanCancel(false)
					->setBlockTravel(true);
				$this->actman->queue($act);
			}
			$character->setTravelLocked(true);
		} elseif (in_array($acttype, ['siege.assault','siege.sortie'])) {
			foreach ($attackers->getCharacters() as $BGChar) {
				if ($place) {
					$act = new Action;
					$act->setType($acttype);
					$act->setCharacter($character)
						->setTargetPlace($place)
						->setTargetSettlement($settlement)
						->setTargetBattlegroup($attackers)
						->setCanCancel(false)
						->setBlockTravel(true);
					$this->actman->queue($act);
				} else {
					$act = new Action;
					$act->setType($acttype);
					$act->setCharacter($character)
						->setTargetSettlement($settlement)
						->setTargetBattlegroup($attackers)
						->setCanCancel(false)
						->setBlockTravel(true);
					$this->actman->queue($act);
				}
				$BGChar->setTravelLocked(true);
			}
		}


		// notifications and counter-actions
		if ($targets) {
			foreach ($targets as $target) {
				$act = new Action;
				$act->setType($acttype)
					->setCharacter($target)
					->setTargetBattlegroup($defenders)
					->setStringValue('forced')
					->setCanCancel(false)
					->setBlockTravel(true);
				$this->actman->queue($act);

				if ($target->hasAction('military.evade')) {
					// we have an evade action set, so automatically queue a disengage
					$this->createDisengage($target, $defenders, $act);
					// and notify
					$this->history->logEvent(
						$target,
						'resolution.attack.evading', array("%time%"=>$this->gametime->realtimeFilter($time)),
						History::HIGH, false, 25
					);
				} else {
					// regular notififaction
					$this->history->logEvent(
						$target,
						'resolution.attack.targeted', array("%time%"=>$this->gametime->realtimeFilter($time)),
						History::HIGH, false, 25
					);
				}

				$target->setTravelLocked(true);
			}
		}
		$this->em->flush();

		return array('time'=>$time, 'outside'=>$outside, 'battle'=>$battle);
	}

	public function joinBattle(Character $character, BattleGroup $group) {
		$battle = $group->getBattle();
		$soldiers = 0;

		foreach ($character->getUnits() as $unit) {
			$soldiers += $unit->getActiveSoldiers()->count();
		}

		// make sure we are only on one side, and send messages to others involved in this battle
		foreach ($battle->getGroups() as $mygroup) {
			$mygroup->removeCharacter($character);

			foreach ($mygroup->getCharacters() as $char) {
				$this->history->logEvent(
					$char,
					'event.military.battlejoin',
					array('%soldiers%'=>$soldiers, '%link-character%'=>$character->getId()),
					History::MEDIUM, false, 12
				);
			}
		}
		$group->addCharacter($character);

		$action = new Action;
		$action->setBlockTravel(true);
		$action->setType('military.battle')
			->setCharacter($character)
			->setTargetBattlegroup($group)
			->setCanCancel(false)
			->setHidden(false);
		$result = $this->actman->queue($action);

		$character->setTravelLocked(true);

		$this->recalculateBattleTimer($battle);
	}

	public function recalculateBattleTimer(Battle $battle) {
		$time = $this->calculatePreparationTime($battle);
		$complete = clone $battle->getStarted();
		$complete->add(new \DateInterval("PT".$time."S"));
		// it can't be less than the initial timer, but otherwise, update the time calculation
		if ($complete > $battle->getInitialComplete()) {
			$battle->setComplete($complete);
		}
	}

	public function calculatePreparationTime(Battle $battle) {
		// prep time is based on the total number of soldiers, but only 20:1 (attackers) or 10:1 (defenders) actually get ready, i.e.
		// if your 1000 men army attacks 10 men, it calculates battle time as if only 200 of your men get ready for battle.
		// if your 1000 men are attacked by 10 men, it calculates battle time as if only 100 of them get ready for battle.
		// this is to prevent blockade battles from being too effective for tiny sacrifical units
		$smaller = max(1,min($battle->getActiveAttackersCount(), $battle->getActiveDefendersCount()));
		$soldiers = min($battle->getActiveAttackersCount(), $smaller*20) + min($battle->getActiveDefendersCount(), $smaller*10);
		// base time is 6 hours, less if the attacker is much smaller than the defender - FIXME: this and the one above overlap, maybe they could be unified?
		$base_time = 6.0 * min(1.0, ($battle->getActiveAttackersCount()*2.0) / (1+$battle->getActiveDefendersCount()));
		$time = $base_time + pow($soldiers, 1/1.666)/12;
		if ($soldiers < 20 && $battle->getActiveAttackersCount()*5 < $battle->getActiveDefendersCount()) {
			// another fix downwards for really tiny sacrifical battles
			$time *= $soldiers/20;
		}
		$time = round($time * 3600); // convert to seconds
		return $time;
	}

	public function calculateDisengageTime(Character $character) {
		$base = 15;
		$base += sqrt($character->getEntourage()->count()*10);

		$takes = 0;

		foreach ($character->getUnits() as $unit) {
			$takes += $unit->getSoldiers()->count();
			foreach ($unit->getSoldiers() as $soldier) {
				if ($soldier->isWounded()) {
					$count += 5;
				}
				switch ($soldier->getType()) {
					case 'cavalry':
					case 'mounted archer':		$takes += 3;
					case 'heavy infantry':		$takes += 2;
				}
			}
		}

		$base += sqrt($takes);

		return $base*60;
	}

	public function createDisengage(Character $character, BattleGroup $bg, Action $attack) {
		$takes = $this->calculateDisengageTime($character);
		$complete = new \DateTime("now");
		$complete->add(new \DateInterval("PT".round($takes)."S"));
		// TODO: at most until just before the battle!

		$act = new Action;
		$act->setType('military.disengage')
			->setCharacter($character)
			->setTargetBattlegroup($bg)
			->setCanCancel(true)
			->setOpposedAction($attack)
			->setComplete($complete)
			->setBlockTravel(false);
		$act->addOpposingAction($act);

		return $this->actman->queue($act);
	}

	public function addRegroupAction($battlesize=100, Character $character) {
		/* FIXME: to prevent abuse, this should be lower in very uneven battles
		FIXME: We should probably find some better logic about calculating the battlesize variable when this is called by sieges, but we can work that out later. */
		# setup regroup timer and change action
		$soldiers = 0;
		foreach ($character->getUnits() as $unit) {
			$soldiers += $unit->getLivingSoldiers()->count();
		}
		$amount = min($battlesize*5, $soldiers)+2; # to prevent regroup taking long in very uneven battles
		$regroup_time = sqrt($amount*10) * 5; # in minutes

		$act = new Action;
		$act->setType('military.regroup')->setCharacter($character);
		$act->setBlockTravel(false);
		$act->setCanCancel(false);
		$complete = new \DateTime('now');
		$complete->add(new \DateInterval('PT'.ceil($regroup_time).'M'));
		$act->setComplete($complete);
		$this->actman->queue($act, true);
	}

	public function disbandSiege(Siege $siege, Character $leader = null, $completed = FALSE) {
		# Siege disbandment and removal actually happens as part of removeCharacterFromBattlegroup.
		# This needs either completed to be true and leader to be null, or completed to be false and leader to be a Character.
		$place = null;
		$settlement = null;
		if ($siege->getSettlement()) {
			$settlement = $siege->getSettlement();
		} else {
			$place = $siege->getPlace();
		}
		$siege->setAttacker(null);
		foreach ($siege->getBattles() as $battle) {
			$battle->setSiege(null);
		}
		if ($siege->getSettlement()) {
			$siege->getSettlement()->setSiege(NULL);
			$siege->setSettlement(NULL);
		} else {
			$siege->getPlace()->setSiege(NULL);
			$siege->getPlace(NULL);
		}
		$this->em->flush();

		foreach ($siege->getGroups() as $group) {
			foreach ($group->getCharacters() as $character) {
				if (!$completed) {
					if ($settlement) {
						$this->history->logEvent(
							$character,
							'event.character.siege.disband',
							array('%link-settlement%'=>$settlement->getId(), '%link-character%'=>$leader->getId()),
							History::LOW, true
						);
					} else {
						$this->history->logEvent(
							$character,
							'event.character.siege.disband2',
							array('%link-place%'=>$place->getId(), '%link-character%'=>$leader->getId()),
							History::LOW, true
						);
					}
				} else {
					foreach ($group->getCharacters() as $char) {
						if ($group->getLeader() == $char) {
							$group->setLeader(null);
							$char->removeLeadingBattlegroup($group);
						}
					}
				}
				$this->removeCharacterFromBattlegroup($character, $group, true);
				$this->addRegroupAction(null, $character);
			}
		}
		$this->em->remove($siege);
		$this->em->flush();
		return true;
	}

	#TODO: Combine this with disbandSiege so we have less duplication of effort.
	public function disbandGroup(BattleGroup $group, $battlesize = null) {
		foreach ($group->getCharacters() as $character) {
			$this->removeCharacterFromBattlegroup($character, $group);
			$this->addRegroupAction($battlesize, $character);
		}
		$this->em->flush();
		return true;
	}

	public function removeCharacterFromBattlegroup(Character $character, BattleGroup $bg, $disbandSiege = false) {
		$bg->removeCharacter($character);
		if ($bg->getCharacters()->count()==0) {
			// there are no more participants in this battlegroup
			if ($bg->getBattle()) {
				$focus = $bg->getBattle();
				$type = 'battle';
			} else if ($bg->getSiege()) {
				$focus = $bg->getSiege();
				$type = 'siege';
			}
			foreach ($bg->getRelatedActions() as $act) {
				$this->em->remove($act);
			}
			if ($type == 'battle' && $focus->getGroups()->count() <= 2) {
				// If we're dealing with a battle, we have an empty group, we have 2 or less groups in this battle, we remove any actions relating to the battle and call the battle as failed..
				foreach ($focus->getGroups() as $group) {
					foreach ($group->getRelatedActions() as $act) {
						if ($act->getType == 'military.battle') {
							$this->em->remove($act);
						}
					}
					foreach ($group->getCharacters() as $char) {
						$this->history->logEvent(
							$char,
							'battle.failed',
							array(),
							History::HIGH, false, 25
						);
						if ($group->getLeader() == $char) {
							$group->setLeader(null);
							$char->removeLeadingBattlegroup($bg);
						}
					}
					if (!$focus->getSiege()) {
						$this->em->remove($group); # If this battle isn't tied to a siege, we can safely remove the battlegroup. Groups tied to a siege and a battle will be handled by the siege closing out (which will, if a battle remains, detach the group from the siege and just let this code handle it afterwards.)
					}
				}
			} else if ($type == 'siege' && $disbandSiege) {
				$this->log(1, "Removing".$character->getName()." (".$character->getId().") from battlegroup for siege... \n");
				// siege is terminated, as sieges don't care how many groups, only if the attacker group has no more attackers in it.
				foreach ($focus->getGroups() as $group) {
					foreach ($group->getRelatedActions() as $act) {
						if ($act->getType() == 'military.siege') {
							$this->em->remove($act); #As it's possible there are other battles related to this group, we only remove the siege.
						}
					}
					foreach ($group->getCharacters() as $char) {
						if ($group->getLeader() == $char) {
							$group->setLeader(null);
							$char->removeLeadingBattlegroup($bg);
						}
					}
					if (!$group->getBattle()) {
						$this->em->remove($group); # No battle? Remove the group. Groups can only be tied to 1x Siege, and 1x Battle.
					} else {
						$group->setSiege(NULL); # We have a battle, but we use this code to cleanup sieges, so we need to detach this group from the siege, so the siege can close properly. The battle will close out the group after it finishes.
					}
				}
				$this->log(1, "Removed.\n");
			}
			$this->em->flush(); # This *must* be here or we encounter foreign key constaint errors when removing the siege, in order to commit everything we've done above.
		}
	}

	public function leaveSiege($character, $siege) {
		foreach ($character->findActions('military.siege') as $action) {
			#This should only ever be one, but just in case, and because findActions returns an ArrayCollection...
			$this->em->remove($action);
		}
		$attacker = false;
		if ($siege->getAttacker()->getCharacters()->contains($character)) {
			$attacker = true;
		}
		foreach ($siege->getGroups() as $group) {
			if ($group->getCharacters()->contains($character)) {
				$character->removeBattlegroup($group);
				$group->removeCharacter($character);
				$this->addRegroupAction(null, $character);
			}
		}
		if ($attacker) {
			$siege->updateEncirclement();
		}
		$this->em->flush();
		return true;
	}

	public function addJoinAction(Character $character, BattleGroup $group) {
		$this->joinBattle($character, $data['group']);
		$this->em->flush();
		$success = $data['group']->getBattle();
	}

	public function buildSiegeTools() {
	#TODO
	}

	public function progressSiege(Siege $siege, Battle $battle, BattleGroup $victor = null, $flag, BattleReport $report) {
		$this->report = $report;
		$current = $siege->getStage();
		$max = $siege->getMaxStage();
		$assault = FALSE;
		$sortie = FALSE;
		$bypass = FALSE;
		$completed = FALSE;
		if ($battle->getType() == 'siegeassault') {
			$assault = TRUE;
			$this->log(1, "Siege assualt\n");
		} elseif ($battle->getType() == 'siegesortie') {
			$sortie = TRUE;
			$this->log(1, "Siege sortie\n");
		}
		$attacker = $battle->getPrimaryAttacker();
		if ($flag === 'haveAttacker') {
			$victor = $siege->getAttacker();
			$bypass = TRUE; #Defenders failed to muster any defenders.
			$this->log(1, "Bypass defenders. Default victory to attackers\n");
		} elseif ($flag === 'haveDefender') {
			$victor = $siege->getDefender();
			$bypass = TRUE; #Attackers failed to muster any attackers.
			$this->log(1, "Bypass attackers. Default victory to defenders\n");
		}
		if ($siege->getSettlement()) {
			$target = $siege->getSettlement();
		} else {
			$target = $siege->getPlace();
		}
		if ($assault) {
			$this->log(1, "Attacker matches victor and this is an assault.\n");
			if ($current < $max && !$bypass) {
				# Siege moves forward
				$siege->setStage($current+1);
				$this->log(1, "Incrememnting stage.\n");
				# "After the [link], the siege has advanced in favor of the attackers"
				$this->history->logEvent(
					$target,
					'siege.advance.attacker',
					array('%link-battle%'=>$report->getId()),
					History::MEDIUM, true, 20
				);
				foreach ($siege->getGroups() as $group) {
					foreach ($group->getCharacters() as $char) {
						$this->history->logEvent(
							$char,
							'siege.advance.defender',
							array(),
							History::MEDIUM, false, 20
						);
					}
				}
			}
			if ($current == $max || $bypass) {
				$this->log(1, "Max stage reached or bypass flag set due to failed defense.\n");
				$completed = TRUE;
				# Siege is over, attackers win.
				if (!$bypass) {
					# "After the defenders failed to muster troops in [link], the siege concluded in attacker victory."
					$this->history->logEvent(
						$target,
						'siege.victor.attacker',
						array(),
						History::MEDIUM, false
					);
				} else {
					$this->log(1, "Bypassed!\n");
					# "After the [link], the siege concluded in an attacker victory."
					$this->history->logEvent(
						$target,
						'siege.bypass.attacker',
						array('%link-battle%'=>$report->getId()),
						History::MEDIUM, false
					);
					foreach ($victor->getCharacters() as $char) {
						$this->history->logEvent(
							$char,
							'battle.failed',
							array(),
							History::MEDIUM, false, 20
						);
					}
				}

			}
		} elseif ($sortie) {
			$this->log(1, "Attacker is not victor. This must be a sortie by the defenders.\n");
			if ($current > 1 && !$bypass) {
				# Siege moves backwards.
				$siege->setStage($current-1);
				$this->log(1, "Decrementing stage.\n");
				# "After the [link], the siege has advanced in favor of the defenders"
				$this->history->logEvent(
					$target,
					'siege.advance.defender',
					array('%link-battle%'=>$report->getId()),
					History::MEDIUM, true, 20
				);
				foreach ($siege->getGroups() as $group) {
					foreach ($group->getCharacters() as $char) {
						$this->history->logEvent(
							$char,
							'siege.advance.defender',
							array(),
							History::MEDIUM, false, 20
						);
					}
				}
			}
			if ($current <= 1 || $bypass) {
				$this->log(1, "Minimum stage reached or bypass flag set due to failure by siege attackers to muster any force. Siege broken.\n");
				$completed = TRUE;
				# Siege is over, defender victory.
				if ($bypass) {
					# "After the attackers failed to muster troops in [link], the siege concluded in defender victory."
					$this->log(1, "Bypassed!\n");
					$this->history->logEvent(
						$target,
						'siege.victor.defender',
						array(),
						History::MEDIUM, false
					);
				} else {
					# "After the [link], the siege concluded in a defender victory."
					$this->history->logEvent(
						$target,
						'siege.bypass.defender',
						array('%link-battle%'=>$report->getId()),
						History::MEDIUM, false
					);
					foreach ($victor->getCharacters() as $char) {
						$this->history->logEvent(
							$char,
							'battle.failed',
							array(),
							History::MEDIUM, false, 20
						);
					}
				}
			}
		}
		# Yes, this means that if attackers lose an assault or defenders lose a sortie, nothing changes. This is intentional.
		$battle->setPrimaryAttacker(NULL);
		$battle->setPrimaryDefender(NULL);
		$this->log(1, "Unset primary flags!\n");
		foreach ($siege->getGroups() as $group) {
			$group->setBattle(NULL);
		}
		$this->log(1, "Unset group battle associations!\n");

		if ($completed) {
			$this->log(1, "Completed checks.\n");
			$realm = $siege->getRealm();
			if ($assault) {
				if ($target instanceof Settlement) {
					$this->log(1, "Target is settlement\n");
					foreach ($victor->getCharacters() as $char) {
							# Force move victorious attackers inside the settlement.
							$this->interactions->characterEnterSettlement($char, $target, true);

					}
					$leader = $victor->getLeader();
					if (!$leader) {
						$leader = $victor->getCharacters()->first(); #Get one at random.
					}
					if ($leader) {
						$this->politics->changeSettlementOccupier($leader, $target, $realm);
					}
					foreach ($target->getSuppliedUnits() as $unit) {
						if ($unit->getCharacter() != $victor->getLeader() && $unit->getSettlement() != $target) {
							$unit->setSupplier(NULL);
						}
					}
					foreach ($target->getUnits() as $unit) {
						if ($unit->getCharacter() != $victor->getLeader() && $unit->getCharacter() !== NULL) {
							$unit->setSettlement(NULL);
							$unit->setMarshal(NULL);
							if ($realm) {
								$this->history->logEvent(
									$unit,
									'event.unit.basetaken',
									array("%link-realm%"=>$realm->getId(), "%link-settlement%"=>$target->getId()),
									History::HIGH, true
								);
							} else {
								$this->history->logEvent(
									$unit,
									'event.unit.basetaken2',
									array("%link-settlement%"=>$target->getId()),
									History::HIGH, true
								);
							}
						}
					}
					foreach ($target->getDefendingUnits() as $unit) {
						$this->milman->returnUnitHome($unit, 'defenselost', $victor->getLeader());
						if ($realm) {
							$this->history->logEvent(
								$unit,
								'event.unit.defenselost',
								array("%link-settlement%"=>$target->getId()),
								History::HIGH, true
							);
						}
					}
				} else {
					$this->log(1, "Target is place\n");
					foreach ($victor->getCharacters() as $char) {
							# Force move victorious attackers inside the place.
							$this->interactions->characterEnterPlace($char, $target, true);

					}
					$leader = $victor->getLeader();
					if (!$leader) {
						$leader = $victor->getCharacters()->first(); #Get one at random.
					}
					if ($leader) {
						$this->politics->changePlaceOccupier($leader, $target, $realm);
					}
					foreach ($target->getUnits() as $unit) {
						$this->milman->returnUnitHome($unit, 'defenselost', $victor->getLeader());
						if ($realm) {
							$this->history->logEvent(
								$unit,
								'event.unit.defenselost2',
								array("%link-place%"=>$target->getId()),
								History::HIGH, true
							);
						}
					}
				}
			}
			$this->em->flush();
			$this->log(1, "Passing siege to disbandSiege function\n");
			$this->disbandSiege($siege, null, TRUE);
		}
		$this->em->flush();

	}

	public function log($level, $text) {
		if ($this->report) {
			$this->report->setDebug($this->report->getDebug().$text);
		}
		if ($level <= $this->debug) {
			$this->logger->info($text);
		}
	}
}
