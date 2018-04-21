<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\BattleGroup;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Entourage;
use BM2\SiteBundle\Entity\EquipmentType;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Soldier;
use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Battle;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;


class Military {

	private $em;
	private $history;
	private $pm;
	private $appstate;

	private $group_assign=0;
	private $group_militia=0;
	private $group_soldier=0;
	private $max_group=25; // a=0 ... z=25
	
	public function __construct(EntityManager $em, Logger $logger, History $history, PermissionManager $pm, AppState $appstate) {
		$this->em = $em;
		$this->logger = $logger;
		$this->history = $history;
		$this->pm = $pm;
		$this->appstate = $appstate;
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
		$action->setType('military.battle')->setCharacter($character)->setTargetBattlegroup($group)->setCanCancel(false)->setHidden(false);
//		FIXME: this would be better, but impossible due to circular injections:
//		$result = $this->get('action_resolution')->queue($action);
		$action->setStarted(new \DateTime("now"));
		$max=0;
		foreach ($character->getActions() as $act) {
			if ($act->getPriority()>$max) {
				$max=$act->getPriority();
			}
		}
		$action->setPriority($max+1);
		$this->em->persist($action);

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



	public function TrainingCycle(Settlement $settlement) {
		if ($settlement->getRecruits()->isEmpty()) return;
		$training = min($settlement->getSingleTrainingPoints(), $settlement->getTrainingPoints()/$settlement->getRecruits()->count());

		// TODO: add the speed (efficiency) of the training building here, at least with some effect 
		// (not full, because you can't focus them)
		foreach ($settlement->getRecruits() as $recruit) {
			if ($recruit->getExperience()>0) {
				$bonus = round(sqrt($recruit->getExperience())/5);
			} else {
				$bonus = 0;
			}
			$recruit->setTraining($recruit->getTraining()+$training+$bonus);
			if ($recruit->getTraining() >= $recruit->getTrainingRequired()) {
				// training finished
				$recruit->setTraining(0)->setTrainingRequired(0);
				$this->history->addToSoldierLog($recruit, 'traincomplete');
			}
		}
	}

	#TODO: Move this getClassName method, and it's siblings in other files, into a single HelperService file.
	private function getClassName($entity) {
		$classname = get_class($entity);
		if ($pos = strrpos($classname, '\\')) return substr($classname, $pos + 1);
		return $pos;
	}

	public function findAvailableEquipment($entity, $with_trainers) {
		switch($this->getClassName($entity)) {
			case 'Settlement':
				if ($with_trainers) {
					$query = $this->em->createQuery('SELECT e as item, ba.resupply FROM BM2SiteBundle:EquipmentType e LEFT JOIN e.provider p LEFT JOIN p.buildings ba LEFT JOIN ba.settlement sa LEFT JOIN e.trainer t LEFT JOIN t.buildings bb LEFT JOIN bb.settlement sb WHERE sa = :location AND ba.active = true AND sb = :location AND bb.active = true ORDER BY t.name ASC, e.name ASC');
				} else {
					$query = $this->em->createQuery('SELECT e as item, b.resupply FROM BM2SiteBundle:EquipmentType e LEFT JOIN e.provider p LEFT JOIN p.buildings b LEFT JOIN b.settlement s WHERE s = :location AND b.active = true ORDER BY p.name ASC, e.name ASC');
				}
				$query->setParameter('location', $entity);
				return $query->getResult();
			case 'Place':
				return null;
		}
		
	}

	public function groupByType($soldiers) {
		$groups = array();
		$next = 1;
		foreach ($soldiers as $soldier) {
			if (!isset($groups[$soldier->getType()])) {
				$groups[$soldier->getType()] = $next++;
			}
			$soldier->setGroup($groups[$soldier->getType()]);
		}
	}

	public function groupByEquipment($soldiers) {
		$groups = array();
		$next = 0;
		foreach ($soldiers as $soldier) {
			if ($soldier->getWeapon()) {
				$w = $soldier->getWeapon()->getId();
			} else {
				$w = 0;
			}
			if ($soldier->getArmour()) {
				$a = $soldier->getArmour()->getId();
			} else {
				$a = 0;
			}
			if ($soldier->getEquipment()) {
				$e = $soldier->getEquipment()->getId();
			} else {
				$e = 0;
			}
			$index = "$w/$a/$e";
			if (!isset($groups[$index])) {
				$groups[$index] = $next++;
				if ($next > $this->max_group) {
					$next = $this->max_group;
				}
			}
			$soldier->setGroup($groups[$index]);
		}
	}

	public function manage($npcs, $data, Settlement $settlement=null, Character $character=null) {
		$assigned_soldiers = 0; $targetgroup='(no)';
		$assigned_entourage = 0;
		$success=0; $fail=0;
		foreach ($npcs as $npc) {
			$change = $data['npcs'][$npc->getId()];
			if (isset($change['group'])) {
				$npc->setGroup($change['group']); // must be prior to the below because some of the actions have auto-group functionality				
			}
			if (!isset($change['supply']) && $npc->isEntourage() && $npc->getEquipment()) {
				// changing back to food - since we use food as the empty value, we need a seperate test, the one below doesn't work
				$npc->setEquipment(null);
				$npc->setSupply(0);
			}
			if (isset($change['supply']) && $npc->getEquipment() != $change['supply']) {
				$npc->setEquipment($change['supply']);
				$npc->setSupply(0);
			}
			if (isset($change['action'])) {
				$this->logger->debug("applying action ".$change['action']." to soldier #".$npc->getId()." (".$npc->getName().")");
				switch ($change['action']) {
					case 'assign':			
						if ($data['assignto']) { 
							$tg = $this->assign($npc, $data['assignto']);
							if ($tg != "") {
								$targetgroup = $tg;
								$assigned_soldiers++;
							}
						}
						break;
					case 'assign2':		
						if ($data['assignto']) { 
							if ($this->assignEntourage($npc, $data['assignto'])) {
								$assigned_entourage++;
							}
						}
						break;
					case 'disband':		$this->disband($npc, $character); break;
					case 'disband2':		$this->disbandEntourage($npc, $character); break;
					case 'bury':			$this->bury($npc); break;
					case 'makemilitia':	if ($settlement) { $this->makeMilitia($npc, $settlement); } break;
					case 'makesoldier':	if ($settlement) { $this->makeSoldier($npc, $character); } break;
					case 'resupply':		if ($this->resupply($npc, $settlement)) { $success++; } else { $fail++; } break;
					case 'retrain':		$this->retrain($npc, $settlement, $data['weapon'], $data['armour'], $data['equipment']);
												break;
				}
			}
		}

		if ($assigned_soldiers > 0) {
			// notify target that he received soldiers
			$this->history->logEvent(
				$data['assignto'],
				'event.military.assigned',
				array('%count%'=>$assigned_soldiers, '%link-character%'=>$character->getId(), '%group%'=>$targetgroup),
				History::MEDIUM, false, 30
			);
		}

		if ($assigned_entourage > 0) {
			// notify target that he received entourage
			$this->history->logEvent(
				$data['assignto'],
				'event.military.assigned2',
				array('%count%'=>$assigned_entourage, '%link-character%'=>$character->getId()),
				History::MEDIUM, false, 30
			);
		}

		return array($success, $fail);
	}

	public function resupply(Soldier $soldier, Settlement $settlement=null) {
		if ($settlement==null) {
			$equipment_followers = $soldier->getCharacter()->getEntourage()->filter(function($entry) {
				return ($entry->getType()->getName()=='follower' && $entry->isAlive() && $entry->getEquipment() && $entry->getSupply()>0);
			})->toArray();
		}
		$success = true;

		$items = array('Weapon', 'Armour', 'Equipment');
		foreach ($items as $item) {
			$check = 'getHas'.$item;
			$trained = 'getTrained'.$item;
			$set = 'set'.$item;

			if (!$soldier->$check()) {
				if ($settlement==null) {
					// resupply from camp followers
					if ($soldier->getCharacter()) {
						foreach ($equipment_followers as $follower) {
							$my_item = $soldier->$trained();
							if ($follower->getSupply() >= $my_item->getResupplyCost() && $follower->getEquipment() == $my_item) {
								$soldier->$set($my_item);
								$follower->setSupply($follower->getSupply() - $my_item->getResupplyCost());
								break 2;
							}
						}
						$success = false;
					} else {
						$success = false;
					}
				} else {
					// resupply from settlement
					if ($this->acquireItem($settlement, $soldier->$trained(), false, true, $soldier->getCharacter())) {
						$soldier->$set($soldier->$trained());
					} else {
						$success = false;
					}
				}
			} 
		}
		return $success;
	}

	public function acquireItem(Settlement $settlement, EquipmentType $item=null, $test_trainer=false, $reduce_supply=true, Character $character=null) {
		if ($item==null) return true;

		$provider = $settlement->getBuildingByType($item->getProvider());
		if (!$provider) return false;
		if (!$provider->isActive()) return false;

		if ($test_trainer) {
			$trainer = $settlement->getBuildingByType($item->getTrainer());
			if (!$trainer) return false;
			if (!$trainer->isActive()) return false;			
		}

		if ($item->getResupplyCost() > $provider->getResupply()) return false;

		if ($reduce_supply) {
			$left = $provider->getResupply() - $item->getResupplyCost();
			if ($character) {
				list($check, $list, $level, $perm) = $this->pm->checkSettlementPermission($settlement, $character, 'resupply', true);
				if ($perm) {
					if ($item->getResupplyCost() > $perm->getValueRemaining()) return false;
					if ($perm->getReserve()!==null && $left < $perm->getReserve()) return false;
					$perm->setValueRemaining($perm->getValueRemaining() - $item->getResupplyCost());
				}
			}
			$provider->setResupply($left);
		}
		return true;
	} 

	public function returnItem(Settlement $settlement=null, EquipmentType $item=null) {
		if ($settlement==null) return true;
		if ($item==null) return true;

		$provider = $settlement->getBuildingByType($item->getProvider());
		if (!$provider) return false;

		// TODO: max stockpile!
		$provider->setResupply($provider->getResupply() + $item->getResupplyCost());			
		return true;
	}

	public function retrain(Soldier $soldier, Settlement $settlement, $weapon, $armour, $equipment) {
		$train = 10;
		$change = false;

		$fail = false;
		// first, check if our change is possible:
		if ($weapon && $weapon != $soldier->getTrainedWeapon()) {
			if (!$this->acquireItem($settlement, $weapon, true, false)) {
				$fail = true;
			}
		}
		if ($armour && $armour != $soldier->getTrainedArmour()) {
			if (!$this->acquireItem($settlement, $armour, true, false)) {
				$fail = true;
			}
		}
		if ($equipment && $equipment != $soldier->getTrainedEquipment()) {
			if (!$this->acquireItem($settlement, $equipment, true, false)) {
				$fail = true;
			}
		}
		if ($fail) {
			return false;
		}

		// store my old status
		$soldier->setOldWeapon($soldier->getWeapon());
		$soldier->setOldArmour($soldier->getArmour());
		$soldier->setOldEquipment($soldier->getEquipment());

		// now do it - we don't need to check for trainers in the acquireItem() statement anymore, because we did it above
		if ($weapon && $weapon != $soldier->getTrainedWeapon()) {
			if ($this->acquireItem($settlement, $weapon)) {
				$train += $weapon->getTrainingRequired();
				$soldier->setWeapon($weapon)->setHasWeapon(true);
				$change = true;
			}
		}
		if ($armour && $armour != $soldier->getTrainedArmour()) {
			if ($this->acquireItem($settlement, $armour)) {
				$train += $armour->getTrainingRequired();
				$soldier->setArmour($armour)->setHasArmour(true);
				$change = true;			
			}
		}
		if ($equipment && $equipment != $soldier->getTrainedEquipment()) {
			if ($this->acquireItem($settlement, $equipment)) {
				$train += $equipment->getTrainingRequired();
				$soldier->setEquipment($equipment)->setHasEquipment(true);
				$change = true;
			}
		}

		if ($change) {
			// experienced troops train faster
			$train = max(1,$train);
			$xp = sqrt($soldier->getExperience()*10);
			$soldier->setTraining(min($train-1, $xp));

			$soldier->setTrainingRequired($train);
			$soldier->setBase($settlement)->setCharacter(null);

			$this->history->addToSoldierLog(
				$soldier, 'retrain',
				array('%link-settlement%'=>$settlement->getId(), 
					'%link-item-1%'=>$weapon?$weapon->getId():0, 
					'%link-item-2%'=>$armour?$armour->getId():0, 
					'%link-item-3%'=>$equipment?$equipment->getId():0
				)
			);
		}


		return true;
	}

	public function disband(Soldier $soldier, $current) {
		// disband soldiers, who will then move towards their home
		// wounded soldiers run an accelerated heal-or-die cycle

/* disabled because I suspect it might be causing an infinite loop somehow
		while ($soldier->isWounded()) {
			$soldier->HealOrDie();
		}
*/
		// TODO: if he can still be reclaimed - maybe he will return home. But to do that, I have to move the entire logic out of ActionsController / assignedAction()
		// TODO: disband() is also run when the character dies, which is good, but a bit tricky - this part should only trigger on suicide


		if ($soldier->isAlive()) {
			if ($current && $soldier->getHome()) {
				$this->walkHome($soldier->getHome(), $current);
			}
		}

		$this->em->remove($soldier);
	}

	public function disbandEntourage(Entourage $entourage, $current) {
		// disband entourage, who will then move towards their home
		if ($current && $entourage->isAlive() && $entourage->getHome()) {
			$this->walkHome($entourage->getHome(), $current);
		}
		$current->removeEntourage($entourage);
		if ($entourage->getType()->getName() == 'follower' && $entourage->getSupply() > 0) {
			$this->salvageItem($current, $entourage->getEquipment(), $entourage->getSupply());
		} // TODO: if not, reclaim by settlement? 
		$this->em->remove($entourage);
	}

	private function walkHome($home, $current) {
		// FIXME: if recently acquired, return to militia or liege ?
		$full_classname = explode('\\', get_class($current));
		$classname = strtolower(end($full_classname));
//		$this->logger->info("walkHome to $classname ".$current->getId());
		if ($classname=="character") {
			$query = $this->em->createQuery('SELECT s as settlement, ST_Distance(g.center, c.location) as distance 
				FROM BM2SiteBundle:Settlement s JOIN s.geo_data g, BM2SiteBundle:Character c, BM2SiteBundle:GeoData h
				WHERE c = :me AND h = :home AND ST_Intersects(g.poly, ST_MakeLine(h.center, c.location))=true ORDER BY distance ASC');
			$query->setParameters(array('me'=>$current->getId(), 'home'=>$home->getGeoData()->getId()));
		} else if (in_array($classname, array('geodata', 'settlement'))) {
			if ($classname=='geodata') {
				$geo = $current;
			} else {
				$geo = $current->getGeoData();
			}
			$query = $this->em->createQuery('SELECT s as settlement, ST_Distance(g.center, c.center) as distance 
				FROM BM2SiteBundle:Settlement s JOIN s.geo_data g, BM2SiteBundle:GeoData c, BM2SiteBundle:GeoData h
				WHERE c = :here AND h = :home AND ST_Intersects(g.poly, ST_MakeLine(h.center, c.center))=true ORDER BY distance ASC');
			$query->setParameters(array('here'=>$geo, 'home'=>$home->getGeoData()));
		} else {
			// FIXME error, this should never happen!
			$this->logger->error("walHome() called with invalid class $classname");
			return false;
		}
		$result = $query->getResult();
		$count = count($result);
//		$this->logger->info("count = $count");
		if ($count<2) {
			// we're still in our home region, so we will almost always just go back to our family
			if (rand(0,100)<95) {
				$home->setPopulation($home->getPopulation()+1);
			}
		} else {
//			$this->logger->info("marching through $count regions...");
			// let's march home, checking at every point if we want to stay here
			$rate = $count+$count/4; // this is the bonus values for the start region - the further away from home, the more likely we'll just stay here
			$marching = true;
			$i=0;
			while ($marching && $place=current($result)) {
				$i++;
//				$this->logger->info("$i: ".$place['settlement']->getId());
				if ($i==$count) {
					// we have arrived back home
					$place['settlement']->setPopulation($place['settlement']->getPopulation()+1);
					$marching=false;
				} else {
					$myrate = 1;
					if ($i==1) { $myrate+=$count/4; }
					$settle_here = $myrate*100/$rate;
					if (rand(0,100)<$settle_here) {
						$place['settlement']->setPopulation($place['settlement']->getPopulation()+1);
						$marching=false;
					} else {
						// chance that something bad happens to us
						if (rand(0,100)<10) {
							$marching=false;
						}
					}
				}
				next($result);
			}
		}
//		$this->logger->info("walkHome() finished");
        return true;
	}

	// no type-hinting because it can be a soldier or entourage, and we don't use inheritance, yet.
	public function bury($npc) {
		if ($npc->isAlive()) return; // safety catch - don't bury living people
		// salvage equipment
		if ($npc->getCharacter()) {
			if ($npc->isSoldier()) {
				if ($npc->getWeapon()) { $this->salvageItem($npc->getCharacter(), $npc->getWeapon()); }
				if ($npc->getArmour()) { $this->salvageItem($npc->getCharacter(), $npc->getArmour()); }
				if ($npc->getEquipment()) { $this->salvageItem($npc->getCharacter(), $npc->getEquipment()); }
			} else {
				// TODO: salvage followers to other followers
			}
		}
		$this->em->remove($npc);
	}

	private function salvageItem(Character $character, EquipmentType $equipment=null, $amount=-1) {
		if ($equipment) {
			$max_supply = min($this->appstate->getGlobal('supply.max_value', 800), $equipment->getResupplyCost() * $this->appstate->getGlobal('supply.max_items', 15));
		} else {
			$max_supply = $this->appstate->getGlobal('supply.max_food', 50);
		}

		if ($equipment) {
			$name = $equipment->getName();
		} else {
			$name = 'food';
		}
		if ($amount==-1) {
			if ($equipment) {
				$amount = $equipment->getResupplyCost();
			} else {
				$amount = 1;
			}
		}
		$follower = $character->getEntourage()->filter(function($entry) use ($equipment, $max_supply) {
			return ($entry->getType()->getName()=='follower' && $entry->isAlive() && $entry->getEquipment() == $equipment && $entry->getSupply() < $max_supply);
		})->first();
		if ($follower) {
			$this->logger->debug("salvaged $amount $name");
			$follower->setSupply(min($max_supply, $follower->getSupply() + $amount ));
			return true;
		}
		return false;
	}

	public function makeMilitia(Soldier $soldier, Settlement $settlement) {
		if (!$soldier->getLiege()) {
			$soldier->setLiege($soldier->getCharacter())->setAssignedSince(-1);
		}
		$soldier->setCharacter(null);
		$soldier->setBase($settlement);

		if ($this->group_militia==0) {
			$query = $this->em->createQuery('SELECT MAX(s.group) FROM BM2SiteBundle:Soldier s WHERE s.base = :target');
			$query->setParameter('target', $settlement->getId());
			$this->group_militia = min($this->max_group, (int)$query->getSingleScalarResult() + 1);
		}
		$soldier->setGroup($this->group_militia);
		$this->history->addToSoldierLog($soldier, 'militia', array('%link-settlement%'=>$settlement->getId()));
	}

	public function makeSoldier(Soldier $soldier, Character $character) {
		$soldier->setCharacter($character);
		$soldier->setBase(null);
		$soldier->cleanOffers();
		if ($character == $soldier->getLiege()) {
			// clean out if he was assigned to the settlement by us
			$soldier->setLiege(null)->setAssignedSince(null);
		}

		if ($this->group_soldier==0) {
			$query = $this->em->createQuery('SELECT MAX(s.group) FROM BM2SiteBundle:Soldier s WHERE s.character = :target');
			$query->setParameter('target', $character->getId());
			$this->group_soldier = min($this->max_group, (int)$query->getSingleScalarResult() + 1);
		}
		$soldier->setGroup($this->group_soldier);
		$this->history->addToSoldierLog($soldier, 'mobilize', array('%link-character%'=>$character->getId()));
	}

	public function assign(Soldier $soldier, Character $to) {
		if ($soldier->getCharacter()) {
			if ($soldier->getCharacter()->getPrisonerOf() && $soldier->getCharacter()->getPrisonerOf() != $to) {
				// character is prisoner of someone and should only be able to assign to him
				return "";
			}
			if ($soldier->getCharacter()->isNPC()) {
				return "";
			}
			if (!$soldier->getLiege()) {
				$soldier->setLiege($soldier->getCharacter())->setAssignedSince(-1);
			}
		}
		if ($soldier->getBase()) {
			if (!$soldier->getLiege()) {
				$soldier->setLiege($soldier->getBase()->getOwner())->setAssignedSince(-1);
			}
		}
		$soldier->setCharacter($to);
		$to->getSoldiers()->add($soldier);
		if ($soldier->getCharacter() == $soldier->getLiege()) {
			// clean out if a soldier has been re-assigned to us after some time
			$soldier->setLiege(null)->setAssignedSince(null);
		}
		$soldier->setBase(null);
		$soldier->setLocked(true); // why? to prevent chain-assignements as a means of instant troop transportation
		$soldier->cleanOffers();

		if ($this->group_assign==0) {
			$query = $this->em->createQuery('SELECT MAX(s.group) FROM BM2SiteBundle:Soldier s WHERE s.character = :target');
			$query->setParameter('target', $to->getId());
			// FIXME: this will never use group a, even if the character has no groups in use
			$this->group_assign = min($this->max_group, (int)$query->getSingleScalarResult() + 1);
		}
		$soldier->setGroup($this->group_assign);
		$this->history->addToSoldierLog($soldier, 'assign', array('%link-character%'=>$to->getId()));
		return $soldier->getGroupName();
	}

	public function assignEntourage(Entourage $npc, Character $to) {
		// FIXME: they should also have a liege and reclaim function
		if ($npc->getCharacter()->getPrisonerOf() && $npc->getCharacter()->getPrisonerOf() != $to) {
			// character is prisoner of someone and should only be able to assign to him
			return false;
		}
		if ($npc->getCharacter()->isNPC()) {
			return false;
		}
		$this->logger->debug("updating ".$npc->getId().", new owner: ".$to->getName());
		$npc->getCharacter()->getEntourage()->removeElement($npc);
		$npc->setCharacter($to);
		$to->getEntourage()->add($npc);
		$npc->setLocked(true); // why? to prevent chain-assignements as a means of instant troop transportation
		return true;
	}



	public function removeCharacterFromBattlegroup(Character $character, BattleGroup $bg) {
		$bg->removeCharacter($character);
		if ($bg->getCharacters()->count()==0) {
			// there are no more participants in this battlegroup
			$battle = $bg->getBattle();
			foreach ($bg->getRelatedActions() as $act) {
				$this->em->remove($act);
			}
			$this->em->remove($bg);

			if ($battle->getGroups()->count()<=2 && $battle->getSettlement()==null) {
				// battle is terminated
				foreach ($battle->getGroups() as $group) {
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
					}
					$this->em->remove($group);
				}
				$this->em->remove($battle);
			}
		}
	}

}
