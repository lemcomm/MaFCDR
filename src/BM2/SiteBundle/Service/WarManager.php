<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Battle;
use BM2\SiteBundle\Entity\BattleGroup;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Siege;

use Doctrine\ORM\EntityManager;
use BM2\SiteBundle\Service\History;
use BM2\SiteBundle\Service\ActionManager;
use BM2\SiteBundle\Service\MilitaryManager;
use BM2\SiteBundle\Twig\GameTimeExtension;

use CrEOF\Spatial\PHP\Types\Geometry\Point;

/*
War Manager exists to handle all service duties involved in battles and sieges. Things relating to specific soldiers, units, equipment, or entourage belong in Military.
*/

class WarManager {

	protected $em;
	protected $history;
	protected $milman;
	protected $actman;

	public function __construct(EntityManager $em, History $history, MilitaryManager $milman, ActionManager $actman, GameTimeExtension $gametime) {
		$this->em = $em;
		$this->history = $history;
		$this->milman = $milman;
		$this->actman = $actman;
		$this->gametime = $gametime;
	}

	public function createBattle(Character $character, Settlement $settlement=null, $targets=array(), Siege $siege=null, BattleGroup $attackers=null, BattleGroup $defenders=null) {
		/* for future reference, $outside is used to determine whether or not attackers need to leave the settlement in order to attack someone. 
		It's used by attackOthersAction of WarCon. --Andrew */
		$bothinside = false;
		$type = 'field';

		$battle = new Battle;
		if ($siege) {
			# Check for sieges first, because they'll always have settlements attached, but settlements won't always come with sieges.
			$location = $siege->getSettlement()->getGeoData()->getCenter();
			$outside = false;

			$battle->setSiege($siege);
			if ($attackers->getAttacker()) {
				# If they are the siege attackers and attacking in this battle, then they're assaulting. If not, they're sallying. It affects defensive bonuses.
				$battle->setType('siegeassault');
				$type = 'assault';
				$this->history->logEvent(
					$settlement,
					'event.settlement.siege.assault',
					array('%link-character%'=>$character->getId()),
					History::MEDIUM, false, 60
				);
			} else {
				$battle->setType('siegesortie');
				$type = 'sortie';
				$this->history->logEvent(
					$settlement,
					'event.settlement.siege.sortie',
					array('%link-character%'=>$character->getId()),
					History::MEDIUM, false, 60
				);
			}
		} else if ($settlement) {
			$battle->setSettlement($settlement);
			$foundinside = false;
			$foundoutside = false;
			$foundboth = false;
			/* Because you can only attack a settlement during a siege, that means that if we're doing this we must be attacking FROM a settlement without a siege.
			Outside of a siege this is only set if you start a battle 
			So we need to figure out if our targets are inside or outside. If we find a mismatch, we drop the outsiders and only attack those inside. */
			foreach ($targets as $target) {
				if ($target->getInsideSettlement()) {
					$foundinside = true;
				} else {
					$foundoutside = true;
				}
			}
			if ($foundinside && $foundoutside) {
				# Found people inside and outside, prioritize inside. Battle type is urban.
				$foundboth = true;
				$battle->setType('urban');
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
				$type = 'skirmish';
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
				# Only people outside. Battle type is field. Collect location data.
				$battle->setType('field');
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
				$type = 'sortie';
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

		$this->em->persist($battle);
		$this->em->persist($attackers);
		$this->em->persist($defenders);

		// setup actions and lock travel
		switch ($type) {
			case 'siegeassault':
				$acttype = 'settlement.assault';
				break;
			case 'siegesortie':
			case 'sortie':
				$acttype = 'settlement.sortie';
				break;
			case 'field':
			case 'urban':
			default:
				$acttype = 'military.battle';
				break;
		}

		$act = new Action;
		$act->setType($acttype);
		$act->setCharacter($character)
			->setTargetSettlement($settlement)
			->setTargetBattlegroup($attackers)
			->setCanCancel(false)
			->setBlockTravel(true);
		$this->actman->queue($act);

		$character->setTravelLocked(true);

		// notifications and counter-actions
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

		return array('time'=>$time, 'outside'=>$outside, 'battle'=>$battle);
	}

	public function joinBattle(Character $character, BattleGroup $group) {
		$battle = $group->getBattle();
		$soldiers = count($character->getActiveSoldiers());

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

		$takes = $character->getSoldiers()->count() * 5;
		foreach ($character->getSoldiers() as $soldier) {
			if ($soldier->isWounded()) {
				$takes += 5;
			}
			switch ($soldier->getType()) {
				case 'cavalry':
				case 'mounted archer':		$takes += 3;
				case 'heavy infantry':		$takes += 2;
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
		$amount = min($battlesize*5, $character->getLivingSoldiers()->count())+2; # to prevent regroup taking long in very uneven battles
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

	public function disbandSiege(Siege $siege, Character $leader) {
		foreach ($siege->getGroups() as $group) {
			foreach ($group->getCharacters() as $character) {
				$this->history->logEvent(
					$character,
					'event.character.siege.disband',
					array('%link-settlement%'=>$siege->getSettlement()->getId(), '%link-character%'=>$leader->getId()),
					History::LOW, true
				);
				$this->removeCharacterFromBattlegroup($character, $group);
				$this->addRegroupAction(null, $character);
			}
		}
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

	public function removeCharacterFromBattlegroup(Character $character, BattleGroup $bg) {
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
			if ($type == 'battle' && $focus->getGroups()->count() <= 2 && $focus->getSettlement()==null) {
				// battle is terminated, as battles care if we have less than two groups participating.
				foreach ($focus->getGroups() as $group) {
					foreach ($group->getRelatedActions() as $act) {
						$this->em->remove($act);
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
							$char->removeLeadingBattelgroup($bg);
						}
					}
					$this->em->remove($group);
				}
				$this->em->remove($focus);
			} else if ($type == 'siege' && $focus->getAttackers() == $bg) {
				$focus->getSettlement()->setSiege(null);
				$focus->setSettlement(null);
				// siege is terminated, as sieges don't care how many groups, only if the attacker group has no more attackers in it.
				foreach ($focus->getGroups() as $group) {
					foreach ($group->getRelatedActions() as $act) {
						$this->em->remove($act);
					}
					foreach ($group->getCharacters() as $char) {
						$this->history->logEvent(
							$char,
							'siege.failed',
							array(),
							History::HIGH, false, 25
						);
						if ($group->getLeader() == $char) {
							$group->setLeader(null);
							$char->removeLeadingBattelgroup($bg);
						}
					}
					$this->em->remove($group);
				}
				$this->em->remove($focus);
			}
		}
	}

	public function leaveSiege($character, $siege) {
		foreach ($character->findActions('military.siege') as $action) {
			#This should only ever be one, but just in case, and because findActions returns an ArrayCollection...
			$this->em->remove($action);
		}
		foreach ($siege->getGroups() as $group) {
			if ($group->getCharacters()->contains($character)) {
				$character->removeBattlegroup($group);
				$group->removeCharacter($character);
				$this->addRegroupAction(null, $character);
			}
		}
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
}
