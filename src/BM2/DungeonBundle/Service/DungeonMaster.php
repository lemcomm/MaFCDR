<?php

namespace BM2\DungeonBundle\Service;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Monolog\Logger;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Service\History;
use BM2\SiteBundle\Service\Geography;

use BM2\DungeonBundle\Entity\Dungeon;
use BM2\DungeonBundle\Entity\Dungeoneer;
use BM2\DungeonBundle\Entity\DungeonCard;
use BM2\DungeonBundle\Entity\DungeonLevel;
use BM2\DungeonBundle\Entity\DungeonMessage;
use BM2\DungeonBundle\Entity\DungeonMonster;
use BM2\DungeonBundle\Entity\DungeonTreasure;
use BM2\DungeonBundle\Entity\DungeonEvent;
use BM2\DungeonBundle\Entity\DungeonParty;


class DungeonMaster {

	private $em;
	private $creator;
	private $history;
	private $logger;
	private $router;


	private $initial_random_cards = 3;
	private $min_party_size = 1;
	private $max_party_size = 30;
	private $max_cards_per_type = 8;
	private $starting_wounds = 10;
	private $max_notify_distance = 50000; // this should be the same as the hardcoded value in CharacterController::summaryAction

	private $total_cards = 0;

	private $basicset = array(
		'basic.leave'		=> 1,
		'basic.rest'		=> 2,
		'basic.scout'		=> 3,
		'basic.plunder'	=> 4,
		'basic.fight'		=> 5
	);

	public function __construct(EntityManager $em, DungeonCreator $creator, History $history, Logger $logger, Router $router) {
		$this->em = $em;
		$this->creator = $creator;
		$this->history = $history;
		$this->logger = $logger;
		$this->router = $router;
	}


	public function getcreateDungeoneer(Character $character) {
		if ($character->getDungeoneer()) return $character->getDungeoneer();
		$this->logger->debug("creating new dungeoneer for character #".$character->getId());
		$hero = new Dungeoneer;
		$hero->setPower(100)->setModPower(0);
		$hero->setDefense(100)->setModDefense(0);
		$hero->setWounds($this->starting_wounds);
		$hero->setGold(0);
		$hero->setInDungeon(false);
		$hero->setCharacter($character);
		$character->setDungeoneer($hero);

		// basic cards set
		foreach ($this->basicset as $name=>$amount) {
			$card = $this->em->getRepository('DungeonBundle:DungeonCardType')->findOneByName($name);
			if (!$card) throw new \Exception("required card $name not found!");
			$cardset = new DungeonCard;
			$cardset->setAmount($amount);
			$cardset->setPlayed(0);
			$cardset->setType($card);
			$cardset->setOwner($hero);
			$hero->addCard($cardset);
			$this->em->persist($cardset);
		}

		// random cards
		for ($i=0; $i < $this->initial_random_cards; $i++) {
			$this->NewCard($hero, false);
		}

		$this->em->persist($hero);
		$this->em->flush();
		return $hero;
	}

	public function cleanupDungeoneer(Character $character) {
		$dungeoneer = $character->getDungeoneer();
		if (!$dungeoneer) return false; 

		$this->exitDungeon($dungeoneer, 0, 0);
		foreach ($dungeoneer->getCards() as $card) {
			$dungeoneer->removeCard($card);
			$this->em->remove($card);
		}
		return true;
	}


	public function postMessage(Dungeoneer $sender, $content) {
		if (!$sender->isInDungeon()) {
			return false;
		}

		$msg = new DungeonMessage;
		$msg->setTs(new \DateTime("now"));
		$msg->setSender($sender);
		$msg->setDungeon($sender->getCurrentDungeon());
		$msg->setContent($content);

		$this->em->persist($msg);
		return true;
	}


	public function joinDungeon(Dungeoneer $dungeoneer, Dungeon $dungeon) {
		if ($dungeoneer->isInDungeon()) {
			return 'busy';
		}
		if ($dungeon->getCurrentLevel()) {
			return 'started';
		}
		$party = $dungeon->getParty();
		if (!$party) {
			$party = $this->createParty($dungeon);
		}

		if ($party->countActiveMembers() >= $this->max_party_size) {
			return 'full';
		}
		foreach ($party->getMembers() as $other) {
			// max 1 character of the same player!©
			if ($other->getCharacter()->getUser() == $dungeoneer->getCharacter()->getUser()) {
				return 'samechar';
			}
		} 

		$dungeoneer->setWounds(max(1, ceil($this->starting_wounds * $dungeoneer->getCharacter()->healthValue())));

		$this->logger->info($dungeoneer->getCharacter()->getName()." has joined dungeon #".$dungeon->getId());

		$party->addMember($dungeoneer);
		$dungeoneer->setParty($party)->setInDungeon(true);
		$this->addEvent($party, 'play.join', array('d'=>$dungeoneer->getId()));
		return true;
	}


	public function startDungeon(Dungeon $dungeon) {
		list($party, $missing, $wait) = $this->calculateTurnTime($dungeon);
		if ($party<1) return; // party must consist of at least one dungeoneer to begin. Down from 3. --Andrew
		if ($missing > $party*0.75) return; // don't start until at least three-quarters of the party has selected an action. Up from half. --Andrew
		// FIXME: the above has the potential to blockade dungeons - long timer for kick?
		$this->logger->info("starting dungeon #".$dungeon->getId().", $missing of $party actions missing = wait ".$dungeon->getTick()." / $wait");

		if ($dungeon->getTick() >= $wait) {
			$this->addEvent($dungeon->getParty(), 'play.start');
			foreach ($dungeon->getParty()->getMembers() as $dungeoneer) {
				// TODO: remove him from the game map so he can't be attacked, etc. anymore
			}
			$level = $this->creator->createRandomLevel($dungeon, 1);
			$dungeon->getParty()->setCurrentLevel($level);
		} else {
			$dungeon->setTick($dungeon->getTick()+1);
		}
	}

	public function runDungeon(Dungeon $dungeon) {
		list($party_size, $missing, $wait) = $this->calculateTurnTime($dungeon);
		if ($party_size==0) return; // no one here
		if (!$dungeon->getCurrentLevel()) return; // not yet started
		$party = $dungeon->getParty();
		if (!$party) return; // nobody in
		$this->logger->info("running dungeon #".$dungeon->getId().", $missing of $party_size actions missing = wait ".$dungeon->getTick()." / $wait");

		if ($dungeon->getTick() >= $wait) {
			$this->addEvent($party, '---');

			foreach ($party->getActiveMembers() as $dungeoneer) {
				if (!$dungeoneer->getCurrentAction()) {
					$this->randomAction($dungeoneer);
				}
				if ($dungeoneer->getCurrentAction()) {
					$this->addEvent($party, 'play.card', array('d'=>$dungeoneer->getId(), 'card'=>$dungeoneer->getCurrentAction()->getType()->getId()));
				}
			}

			$this->Modifiers($party);
			$this->Others($party);
			$this->Scouting($party);
			$this->Fights($party);
			$this->Plunder($party);
			$this->Leave($party);
			if ($party->getDungeon()) { // if not, everyone has already left, so no need to proceed
				$this->Proceed($party);
				$dungeon->setTick(0);
			}
			foreach ($party->getMembers() as $dungeoneer) {
				$dungeoneer->setLastAction($dungeoneer->getCurrentAction());
				$dungeoneer->setCurrentAction(null);
				if ($dungeoneer->isInDungeon()) {
					$this->history->logEvent(
						$dungeoneer->getCharacter(),
						'event.character.dungeon.progress',
						array('%url%'=>$this->router->generate('bm2_dungeon_dungeon_index')),
						History::HIGH, false, 10
					);
				}
			}
		} else {
			$dungeon->setTick($dungeon->getTick()+1);
		}
		$this->logger->info("dungeon #".$dungeon->getId()." done");
	}

	private function randomAction(Dungeoneer $dungeoneer) {
		$this->logger->info($dungeoneer->getCharacter()->getName()." did not choose an action, picking a random action:");
		$cards = $dungeoneer->getCards()->filter(function($entry){
			return ($entry->getPlayed() < $entry->getAmount() && $entry->getType()->getName()!='basic.leave');
		})->toArray();
		if (empty($cards)) {
			$cards = $dungeoneer->getCards()->filter(function($entry){
				return ($entry->getPlayed() < $entry->getAmount());
			})->toArray();
		}
		if (!empty($cards)) {
			$pick = array_rand($cards);
			if ($card = $cards[$pick]) {
				$dungeoneer->setCurrentAction($card);
				$card->setPlayed($card->getPlayed()+1);
				$this->logger->info($card->getType()->getName());
			} else {
				$this->logger->warning("actions found but picking failed");
			}
		} else {
			$this->logger->warning("dungeoneer #".$dungeoneer->getId()." has no valid actions to randomly choose from");
		}
	}

	public function randomTreasure(DungeonLevel $level) {
		$treasures = $level->getTreasures()->filter(function($entry){
			return ($entry->getValue()>0);
		})->toArray();
		if (!empty($treasures)) {
			$pick = array_rand($treasures);
			if ($target=$treasures[$pick]) {
				return $target;
			}
		}
		return null;
	}

	public function calculateTurnTime(Dungeon $dungeon) {
		$missing = 0;
		$party = 0;
		if (!$dungeon->getParty()) {
			$newparty = $this->createParty($dungeon);
		}
		foreach ($dungeon->getParty()->getActiveMembers() as $dungeoneer) {
			$party++;
			if (!$dungeoneer->getCurrentAction()) {
				$missing++;
			}
		}
		if ($party==0) return array(0,0,0); // nobody here

		if ($dungeon->getCurrentLevel() == null) {
			// not yet started
			if ($party < $this->min_party_size) return array(0,0,0); // dungeon will not start without meeting the minimum party size
			switch ($party) {
				case 3:	$wait = 8; break;
				case 4:	$wait = 4; break;
				case 5:	$wait = 2; break;
				default:	$wait = 1;
			} 
			switch ($missing) {
				case 0:	break;
				case 1:	$wait += 2; break;
				case 2:	$wait += 4; break;
				case 3:	$wait += 8; break;
				default:	$wait +=12;
			}
		} else {
			// dungeon is running
			switch ($missing) {
				case 0:	$wait = 1; break;
				case 1:	$wait = 2; break;
				case 2:	$wait = 6; break;
				default:	$wait = 8;
			}
		}
		return array($party, $missing, $wait);
	} // QUERY: what is the default wait time in real time? How do these numbers correlate to the real-world timing of things? --Andrew


	public function addEvent(DungeonParty $party, $content, $data=null) {
		$event = new DungeonEvent;
		$event->setTs(new \DateTime("now"));
		$event->setParty($party);
		$event->setContent($content);
		$event->setData($data);
		$this->em->persist($event);
	}


	/* ==================== Resolution Code ==================== */

	private function Modifiers(DungeonParty $party) {
		$this->logger->info("applying modifiers...");

		// reset
		foreach ($party->getActiveMembers() as $dungeoneer) {
			$dungeoneer->setModDefense(0);
			$dungeoneer->setModPower(0);
		}

		// apply new modifiers for this round, plunder action reduces defense by 50, leaving by 20.
		foreach ($party->getActiveMembers() as $dungeoneer) {
			if ($dungeoneer->getCurrentAction()) switch ($dungeoneer->getCurrentAction()->getType()->getName()) {
				case 'basic.plunder':	$dungeoneer->setModDefense(-50); break;
				case 'basic.leave':		$dungeoneer->setModDefense(-20); break;
			}
		}
	}

	private function Others(DungeonParty $party) {
		$this->logger->info("other actions...");

		foreach ($party->getActiveMembers() as $dungeoneer) {
			if ($dungeoneer->getCurrentAction()) switch ($dungeoneer->getCurrentAction()->getType()->getName()) {
				case 'action.heal1':
					if ($target = $dungeoneer->getTargetDungeoneer()) {
						if ($target->getWounds() < $this->starting_wounds) {
							$target->setWounds($target->getWounds()+1);
							$this->addEvent($party, 'card.action.heal1.log', array('d'=>$dungeoneer->getId(), 'target'=>$target->getId()));
						}
					}
					break;
				case 'action.heal2':
					if ($target = $dungeoneer->getTargetDungeoneer()) {
						if ($target->getWounds() < $this->starting_wounds) {
							$target->setWounds(min($this->starting_wounds, $target->getWounds()+2));
							$this->addEvent($party, 'card.action.heal2.log', array('d'=>$dungeoneer->getId(), 'target'=>$target->getId()));
						}
					}
					break;
				case 'action.heal3':
					if ($target = $dungeoneer->getTargetDungeoneer()) {
						if ($target->getWounds() < $this->starting_wounds) {
							$target->setWounds(min($this->starting_wounds, $target->getWounds()+3));
							$this->addEvent($party, 'card.action.heal3.log', array('d'=>$dungeoneer->getId(), 'target'=>$target->getId()));
						}
					}
					break;
				case 'action.distracta':
					if ($monster = $dungeoneer->getTargetMonster()) {
						if (in_array($dungeoneer->getCurrentAction()->getType()->getMonsterClass(), $monster->getType()->getClass())) {
							$monster->setStunned(true);
							$this->addEvent($party, 'card.action.distracta.log', array('d'=>$dungeoneer->getId(), 'size'=>$monster->getSize(), 'monster'=>$monster->getType()->getId()));
						}
					}
					break;
				case 'basic.rest':
					$this->regainRandomAction($dungeoneer);
					break;
				case 'action.rest2':
					$this->regainRandomAction($dungeoneer);
					$this->regainRandomAction($dungeoneer);
					break;
				case 'action.rest3':
					$this->regainRandomAction($dungeoneer);
					$this->regainRandomAction($dungeoneer);
					$this->regainRandomAction($dungeoneer);
					break;
				case 'action.untrap1':
					$this->disarmTrap($dungeoneer, 50);
					break;
				case 'action.untrap2':
					$this->disarmTrap($dungeoneer, 75);
					break;
				case 'action.untrap3':
					$this->disarmTrap($dungeoneer, 95);
					break;
			}
		}
	}

	private function regainRandomAction(Dungeoneer $dungeoneer) {
		$this->logger->info("resting:");
		$cards = $dungeoneer->getCards()->filter(function($entry){
			return ($entry->getPlayed() > 0 && !strstr($entry->getType()->getName(), '.rest'));
		})->toArray();
		if (!empty($cards)) {
			$pick = array_rand($cards);
			if ($card = $cards[$pick]) {
				$this->logger->info("regained action ".$card->getType()->getName());
				$card->setPlayed($card->getPlayed()-1);
				$this->addEvent($dungeoneer->getParty(), 'card.action.rest.log', array('d'=>$dungeoneer->getId(), 'card'=>$card->getType()->getId()));
			} else {
				$this->logger->warning("actions found but picking failed");
			}
		} else {
			$this->logger->warning("no actions to regain");
		}
	}

	private function disarmTrap(Dungeoneer $dungeoneer, $chance) {
		$target = $dungeoneer->getTargetTreasure();
		if (!$target) {
			$target = $this->randomTreasure($dungeoneer->getCurrentDungeon()->getCurrentLevel());
		}
		if ($target) {
			if ($target->getTrap()>0) {
				if (rand(0,100) < $chance) {
					// disarmed
					$this->logger->info("trap disarmed");
					$this->addEvent($dungeoneer->getParty(), 'play.untrap.done', array('d'=>$dungeoneer->getid()));
				} else {
					// fail, triggered
					$damage = $target->getTrap();
					$dungeoneer->setWounds(max(0, $dungeoneer->getWounds()-$damage));
					if ($dungeoneer->getWounds()==0) {
						$this->logger->info("trap disarm failed, $damage damage - killed");
						$this->addEvent($dungeoneer->getParty(), 'play.untrap.kill', array('d'=>$dungeoneer->getid()));
					} else {
						$this->logger->info("trap disarm failed, $damage damage - wounded");
						$this->addEvent($dungeoneer->getParty(), 'play.untrap.wound', array('d'=>$dungeoneer->getid(), 'wounds'=>$damage));
					}
				}
				$target->setTrap(0); // traps only trigger once
			} else {
				$this->logger->info("no trap to disarm");
				$this->addEvent($dungeoneer->getParty(), 'play.untrap.none', array('d'=>$dungeoneer->getid()));
			}
		}
	}

	private function Scouting(DungeonParty $party) {
		$this->logger->info("Scouting...");
		$level = 0;
		foreach ($party->getActiveMembers() as $dungeoneer) {
			if ($dungeoneer->getCurrentAction()) {
				switch ($dungeoneer->getCurrentAction()->getType()->getName()) {
					case 'basic.scout':		$level++; break;
					case 'scout.double':		$level+=2; break;
					case 'scout.tripple':		$level+=3; break;
				}
			}
		}
		if ($level > $party->getCurrentLevel()->getScoutLevel()) {
			$this->logger->info("raising scout level to $level");
			$party->getCurrentLevel()->setScoutLevel($level);
		}
	}

	private function Fights(DungeonParty $party) {
		$this->logger->info("Fights...");

		// characters attack their targets:
		foreach ($party->getActiveMembers() as $dungeoneer) {
			if ($dungeoneer->getCurrentAction()) {
				switch ($dungeoneer->getCurrentAction()->getType()->getName()) {
					case 'basic.fight':
						if ($target = $this->findMonsterTarget($dungeoneer)) {	
							$this->DungeoneerAttack($dungeoneer, $target); 
						}
						$dungeoneer->setTargetMonster(null); // remove target monster so it doesn't notice we attacked it
						break;
					case 'fight.stealth':
						if ($target = $this->findMonsterTarget($dungeoneer)) {	
							$this->DungeoneerAttack($dungeoneer, $target);
						}
						break;
					case 'fight.double':
						if ($target = $this->findMonsterTarget($dungeoneer)) {
							$this->DungeoneerAttack($dungeoneer, $target); 
						}
						if ($target = $this->findMonsterTarget($dungeoneer)) {
							$this->DungeoneerAttack($dungeoneer, $target); 
						}
						break;
					case 'fight.strong':
						if ($target = $this->findMonsterTarget($dungeoneer)) {	
							$this->DungeoneerAttack($dungeoneer, $target, 2); 
						}
						break;
					case 'fight.hit':
						if ($target = $this->findMonsterTarget($dungeoneer)) {	
							$this->DungeoneerAttack($dungeoneer, $target, 1, 1.5); 
						}
						break;
					case 'fight.weak':
						if ($target = $this->findMonsterTarget($dungeoneer)) {	
							$this->DungeoneerAttack($dungeoneer, $target, 1, 0.75); 
						}
						break;
					case 'fight.sweep':
						if ($target = $this->findMonsterTarget($dungeoneer)) {	
							$this->DungeoneerAttack($dungeoneer, $target, 1, 1.0, 0.5); 
						}
						break;
					case 'fight.kill':
						if ($target = $this->findMonsterTarget($dungeoneer)) {	
							$this->DungeoneerAttack($dungeoneer, $target, 3, 1.2); 
						}
						break;
					case 'fight.tripple':
						for ($i=0;$i<3;$i++) {
							if ($target = $this->findMonsterTarget($dungeoneer)) {
								$this->DungeoneerAttack($dungeoneer, $target, 1, 0.75); 
							}
						}
						break;
					case 'fight.swing':
						if ($target = $this->findMonsterTarget($dungeoneer)) {
							for ($i=0;$i<3;$i++) {
								if ($target->getAmount() > 0) {
									$this->DungeoneerAttack($dungeoneer, $target); 
								}
							}
						}
						break;
					case 'fight.sure':
						if ($target = $this->findMonsterTarget($dungeoneer)) {	
							$this->DungeoneerAttack($dungeoneer, $target, 1, 1.5, 0.5); 
						}
						break;
					case 'fight.bomb1':
						if ($target = $this->findMonsterTarget($dungeoneer)) {
							$amount = $target->getAmount();
							for ($i=0;$i<$amount;$i++) {
								if ($target->getAmount() > 0) {
									$this->DungeoneerAttack($dungeoneer, $target, 1, 0.25, 0.5);
								}
							}
						}
						break;
					case 'fight.bomb2':
						if ($target = $this->findMonsterTarget($dungeoneer)) {
							$amount = $target->getAmount();
							for ($i=0;$i<$amount;$i++) {
								if ($target->getAmount() > 0) {
									$this->DungeoneerAttack($dungeoneer, $target, 1, 0.5, 0.5);
								}
							}
						}
						break;
					case 'fight.slime':
						if ($target = $this->findMonsterTarget($dungeoneer)) {
							if (in_array($dungeoneer->getCurrentAction()->getType()->getMonsterClass(), $monster->getType()->getClass())) {
							$this->DungeoneerAttack($dungeoneer, $target, 6); 
							} else {
							$this->DungeoneerAttack($dungeoneer, $target, 0.5);
							}
						}
						break;
				}
			}
		}

		// surviving monsters attack either their attackers or (if no attackers) random dungeoneers:
		$monsters = $party->getCurrentLevel()->getMonsters()->filter(function($entry){ return ($entry->getAmount()>0); });

		foreach ($monsters as $monster) {
			if ($monster->getStunned()) {
					$this->logger->info($monster->getName()." stunned, not attacking");
					$monster->setStunned(false);
			} else {
				// non-ranged monsters always fight whoever is attacking them
				if (!in_array("ranged", $monster->getType()->getClass()) && $monster->getTargetedBy()->count()>0) {
					$this->logger->info($monster->getName()." counterattacking...");
					$victims = $monster->getTargetedBy();
				} else { // ranged monsters and those who are not being attacked:
					$this->logger->info($monster->getName()." attacking random dungeoneer...");
					$victims = $party->getMembers();
				}
				$victims = $victims->toArray();
				$pick = array_rand($victims);
				$victim = $victims[$pick];
				if (!$victim) {
					$this->logger->error("apparently it doesn't have any valid dungeoneer targets?");
				} else {
					$this->MonsterAttack($monster, $victim, $party->getCurrentLevel()->getScoutLevel());
				}
			}
		}
	}

	private function findMonsterTarget(Dungeoneer $dungeoneer) {
		if (!$dungeoneer->getTargetMonster()) {
			$monsters = $dungeoneer->getCurrentDungeon()->getCurrentLevel()->getMonsters()->filter(function($entry){
				return ($entry->getAmount()>0 && !in_array('stealth', $entry->getType()->getClass()));
			})->toArray();
			if (!empty($monsters)) {
				$pick = array_rand($monsters);
				if ($target = $monsters[$pick]) {
					$dungeoneer->setTargetMonster($target);
					$target->addTargetedBy($dungeoneer);
				}
			}
		}

		if (!$dungeoneer->getTargetMonster()) {
			$this->logger->error("apparently there are no (unstealthed) monsters on this level");
			$this->addEvent($dungeoneer->getParty(), 'play.attack.notarget', array('d'=>$dungeoneer->getId()));
		}		
		return $dungeoneer->getTargetMonster();
	}

	private function DungeoneerAttack(Dungeoneer $dungeoneer, DungeonMonster $target, $damage=1, $powermod=1.0, $defmod=1.0) {
		$attack = rand(0, round($dungeoneer->getPower() * $powermod));
		$defense = rand(0, round($target->getType()->getDefense()* $defmod));
		$this->logger->info($dungeoneer->getCharacter()->getName()." attacks ".$target->getName()." ($attack vs. $defense)");
		$event_data = array('d'=>$dungeoneer->getId(), 'size'=>$target->getSize(), 'monster'=>$target->getType()->getName());
		if ($attack > $defense) {
			$wounds = $target->getWounds() + $damage;
			if ($attack > $defense * 10) {
				$wounds+=$damage; // critical hit = double damage
			}
			while ($wounds >= $target->getType()->getWounds()) {
				$this->logger->info("kill");
				$this->addEvent($dungeoneer->getParty(), 'play.attack.kill', $event_data);
				$target->setAmount(max(0,$target->getAmount()-1));
				$wounds-=$target->getType()->getWounds();
			}
			if ($wounds>0) {
				$this->logger->info("wounded");
				$this->addEvent($dungeoneer->getParty(), 'play.attack.wound', $event_data);
				$target->setWounds($wounds);
			}
		} else {
			$this->logger->info("no damage");
			$this->addEvent($dungeoneer->getParty(), 'play.attack.miss', $event_data);
		}
	}

	private function MonsterAttack(DungeonMonster $monster, Dungeoneer $victim, $scout_level) {
		$this->logger->info($monster->getAmount()." attacks on ".$victim->getCharacter()->getName().":");
		if ($scout_level>1) {
			$event_data = array('d'=>$victim->getId(), 'size'=>$monster->getSize(), 'monster'=>$monster->getType()->getName());
		} else {
			$event_data = array('d'=>$victim->getId(), 'size'=>'dark', 'monster'=>'dark');
		}
		for ($i=0; $i<$monster->getAmount(); $i++) {
			$attack = rand(0,$monster->getType()->getPower());
			$defense = rand(0,$victim->getDefense());
			if ($attack > $defense) {
				if ($attack > $defense * 10) {
					$wounds = 2;
				} else {
					$wounds = 1;
				}
				$victim->setWounds(max(0, $victim->getWounds()-$wounds));
				if ($victim->getWounds()==0) {
					$this->logger->info("$attack vs. $defense: $wounds damage - killed");
					$this->addEvent($victim->getParty(), 'play.monster.kill', $event_data);
					// TODO - or is this resolved in the Leave() method?
				} else {
					$this->addEvent($victim->getParty(), 'play.monster.wound', $event_data);
					$this->logger->info("$attack vs. $defense: $wounds damage - wounded");
				}
			} else {
				$this->addEvent($victim->getParty(), 'play.monster.miss', $event_data);
				$this->logger->info("$attack vs. $defense: no damage");
			}
		}
	}


	private function Plunder(DungeonParty $party) {
		$this->logger->info("Plunder...");

		// first round - assign someone to every treasure
		foreach ($party->getActiveMembers() as $dungeoneer) {
			if ($dungeoneer->getCurrentAction()) switch ($dungeoneer->getCurrentAction()->getType()->getName()) {
				case 'basic.plunder':
					if (!$dungeoneer->getTargetTreasure()) {
						if ($target = $this->randomTreasure($party->getCurrentLevel())) {
							$dungeoneer->setTargetTreasure($target);
							$target->addTargetedBy($dungeoneer);
						}
					}
					break;
			}
		}

		foreach ($party->getActiveMembers() as $dungeoneer) {
			if ($dungeoneer->getCurrentAction() && $dungeoneer->getTargetTreasure()) switch ($dungeoneer->getCurrentAction()->getType()->getName()) {
				case 'basic.plunder':
					$this->resolvePlunder($dungeoneer, $dungeoneer->getTargetTreasure());
					break;
			}
		}

		// update values
		foreach ($party->getCurrentLevel()->getTreasures() as $treasure) {
			// do we want to remove empty treasures? if so - now
			$treasure->setValue(max(0,$treasure->getValue() - $treasure->getTaken()));
			$treasure->setTaken(0);
		}
	}

	private function resolvePlunder(Dungeoneer $dungeoneer, DungeonTreasure $treasure) {
		$this->logger->info($dungeoneer->getCharacter()->getName()." plundering treasure #".$treasure->getId()." (".$treasure->getValue()." gold)");

		if ($treasure->getTrap()>0) {
			$dungeoneer->setWounds(max(0, $dungeoneer->getWounds()-$treasure->getTrap()));
			if ($dungeoneer->getWounds()==0) {
				$this->logger->info("trap does ".$treasure->getTrap()." damage - killed");
				$this->addEvent($dungeoneer->getParty(), 'play.trap.kill', array('d'=>$dungeoneer->getid()));
				return;
			} else {
				$this->logger->info("trap does ".$treasure->getTrap()." damage - wounded");
				$this->addEvent($dungeoneer->getParty(), 'play.trap.wound', array('d'=>$dungeoneer->getid(), 'wounds'=>$treasure->getTrap()));
			}
			$treasure->setTrap(0); // traps only trigger once
		}

		$share = min(max(1,ceil($treasure->getValue() / $treasure->getTargetedBy()->count())), $treasure->getValue()-$treasure->getTaken());
		$dungeoneer->setGold($dungeoneer->getGold() + $share);
		$treasure->setTaken($treasure->getTaken() + $share);
		$this->logger->info("$share gold taken (from total pile of ".$treasure->getValue().")");
		$this->addEvent($dungeoneer->getParty(), 'play.plunder', array('d'=>$dungeoneer->getId(), 'gold'=>$share));
	}


	private function Leave(DungeonParty $party) {
		$this->logger->info("Leave...");
		if ($party->getCurrentLevel()) {
			$depth = $party->getCurrentLevel()->getDepth();
		} else {
			$depth = 0;
		}

		// leave cards
		foreach ($party->getActiveMembers() as $dungeoneer) {
			if ($dungeoneer->getCurrentAction()) switch ($dungeoneer->getCurrentAction()->getType()->getName()) {
				case 'basic.leave':
					// move gold to character
					// gain new cards
					// whatever else

					// leave party and dungeon, reset cards
					$this->exitDungeon($dungeoneer, 1.0, ($depth-1));
					break;
			}

		}

		// involuntary leaving
		foreach ($party->getActiveMembers() as $dungeoneer) {
			if ($dungeoneer->getWounds()<=0) {
				// "dead"
				$this->exitDungeon($dungeoneer, 0.25, min(1, $depth-1));
			}
		}
	}

	public function exitDungeon(Dungeoneer $hero, $goldmod, $cards) {
		$dungeon = $hero->getCurrentDungeon();
		$char = $hero->getCharacter();
		$party = $hero->getParty();

		$gain = round($goldmod*$hero->getGold());
		if ($gain > 0) {
			$char->setGold($char->getGold() + $gain);
			$this->history->logEvent(
				$hero->getCharacter(),
				'event.character.dungeon.gold',
				array('%gold%'=>$gain),
				History::MEDIUM, false, 20
			);
		}
		$hero->setGold(0);

		// set character health based on wounds
		// this can get characters above mortally wounded, but will never kill them
		$health = $hero->getWounds() / $this->starting_wounds;
		$char->setWounded($char->getWounded() + ceil($health * $char->full_health));

		// the more (additional to the basic set) cards I have, the deeper I have to go in to get new ones
		$cards -= floor(($hero->getCards()->count()-5)/10);

		foreach ($hero->getCards() as $mycard) {
			$mycard->setPlayed(0);
		}
		if ($cards>0) for ($i=0;$i<$cards;$i++) {
			$this->NewCard($hero);
		}

		$hero->setInDungeon(false);
		$hero->setLastAction(null)->setCurrentAction(null);
		$hero->setTargetDungeoneer(null)->setTargetMonster(null)->setTargetTreasure(null);
		foreach ($hero->getTargetedBy() as $attacker) {
			$attacker->setTargetDungeoneer(null);
		}
		// remove the action that blocks travel:
		foreach ($char->findActions('dungeon.explore') as $action) {
			$this->em->remove($action);
		}
		$hero->getCharacter()->setSpecial(false); // turn off the special navigation menu

		if ($party) {
			if ($party->countActiveMembers() == 0) {
				$this->logger->info("Everyone in party ".$party->getId()." has left the dungeon...");
				$party->setDungeon(null);
				$party->setCounter(0);

				if ($dungeon) {
					if ($level = $dungeon->getCurrentLevel()) {
						$max_depth = $level->getDepth();
						$party->setCurrentLevel(null);
					} else {
						$max_depth = 0;
					}
					$this->logger->info("...max level reached: $max_depth");
					foreach ($dungeon->getLevels() as $level) {
						foreach ($level->getMonsters() as $monster) {
							$level->removeMonster($monster);
							$monster->setLevel(null);
							foreach ($monster->getTargetedBy() as $attacker) {
								$attacker->setTargetMonster(null);
								$monster->removeTargetedBy($attacker);
							}
							$this->em->remove($monster);
						}
						foreach ($level->getTreasures() as $treasure) {
							$level->removeTreasure($treasure);
							$treasure->setLevel(null);
							foreach ($treasure->getTargetedBy() as $attacker) {
								$attacker->setTargetTreasure(null);
								$treasure->removeTargetedBy($attacker);
							}
							$this->em->remove($treasure);
						}
						$dungeon->removeLevel($level);
						$level->setDungeon(null);
						$this->em->remove($level);
					}

					// closing the dungeon happens if we got deep enough, or it has been explored a lot
					// but some randomness to avoid people gaming the system by pulling out early
					// TODO: This is a short-term fix until I have the dungeon types coding implemented and all of this is dependent on the dungeon type. --Andrew
					if ($max_depth > 0 && rand(21,141) < $dungeon->getExplorationCount()*10) {
						$this->logger->info("...closing dungeon #".$dungeon->getId()." (exploration ".$dungeon->getExplorationCount().")");
						$query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Character c, DungeonBundle:Dungeon d WHERE c.slumbering = false AND c.alive = true and c.travel_at_sea = false AND ST_Distance(c.location, d.location) < :maxdistance');
						$query->setParameters(array('maxdistance'=>Geography::DISTANCE_DUNGEON));
						$others = $query->getResult();
						$this->logger->info("notifying ".count($others)." characters");
						foreach ($others as $char) {
							$this->history->logEvent(
								$char,
								'event.character.dungeon.closed',
								array(),
								History::LOW, false, 25
							);
						}
						$this->em->remove($dungeon);
					}
				} else {
					if ($dungeon) {
						$this->logger->info("...dungeon reset (exploration ".$dungeon->getExplorationCount().")");
						$party = $this->createParty($dungeon);
					}
				}
			} else {
				$this->addEvent($party, 'play.leave', array('d'=>$hero->getId()));
			}
		}
	}

	public function createParty(Dungeon $dungeon) {
		$party = new DungeonParty;
		$party->setDungeon($dungeon);
		$dungeon->setParty($party);
		$this->em->persist($party);
		return $party;
	}

	public function NewCard(Dungeoneer $hero, $notify = true) {
		$card = $this->RandomCard();
		$found = false;
		foreach ($hero->getCards() as $mycard) {
			if ($mycard->getType() == $card) {
				$found = true;
				if ($mycard->getAmount() < $this->max_cards_per_type) {
					$mycard->setAmount($mycard->getAmount()+1);
					if ($notify) {
						$this->history->logEvent(
							$hero->getCharacter(),
							'event.character.dungeon.newcard1',
							array('%name-card%'=>$card->getId(), 'domain'=>'dungeons'),
							History::MEDIUM, false, 20
						);
					}
				} else {
					$this->logger->info("Not adding, already got max cards of this type.");
					if (rand(0,100)<50) {
						$this->logger->info("new draw...");
					}
				}
			}
		}
		if (!$found) {
			$cardset = new DungeonCard;
			$cardset->setAmount(1);
			$cardset->setPlayed(0);
			$cardset->setType($card);
			$cardset->setOwner($hero);
			$hero->addCard($cardset);
			$this->em->persist($cardset);
			if ($notify) {
				$this->history->logEvent(
					$hero->getCharacter(),
					'event.character.dungeon.newcard2',
					array('%name-card%'=>$card->getId(), 'domain'=>'dungeons'),
					History::MEDIUM, false, 20
				);
			}
		}
	}

	public function leaveParty(Dungeoneer $hero) {
		if ($party = $hero->getParty()) {
			if ($party->getMembers()->count()==1) {
				$this->dissolveParty($party);
			} else {
				$this->RemoveFromParty($hero, $party);
			}
		}
	}

	public function dissolveParty(DungeonParty $party) {
		foreach ($party->getMessages() as $msg) {
			$party->removeMessage($msg);
			$msg->setParty(null);
			$this->em->remove($msg);
		}
		foreach ($party->getEvents() as $event) {
			$party->removeEvent($event);
			$event->setParty(null);
			$this->em->remove($event);
		}
		foreach ($party->getMembers() as $member) {
			$this->RemoveFromParty($member, $party);
		}

		$this->em->remove($party);		
	}

	private function RemoveFromParty(Dungeoneer $member, DungeonParty $party) {
		$party->removeMember($member);
		$member->setParty(null);
		$member->setInDungeon(false);
		$member->setTargetDungeoneer(null)->setTargetMonster(null)->setTargetTreasure(null);

		// this should already be removed, but let's make sure
		foreach ($member->getCharacter()->findActions('dungeon.explore') as $action) {
			$this->em->remove($action);
		}
		foreach ($member->getTargetedBy() as $attacker) {
			$attacker->setTargetDungeoneer(null);
		}
		$member->getCharacter()->setSpecial(false); // turn off the special navigation menu
	}


	private function RandomCard() {
		// find a random card from among all the cards we have
		$all_cards = $this->em->getRepository('DungeonBundle:DungeonCardType')->findAll();
		if ($this->total_cards == 0) {
			foreach ($all_cards as $card) {
				$this->total_cards += $card->getRarity();
			}
			$this->logger->info($this->total_cards." total cards rarity");
		}

		$pick = rand(0, $this->total_cards-1);
		foreach ($all_cards as $card) {
			$pick -= $card->getRarity();
			if ($pick<0) {
				$this->logger->info("random card: ".$card->getName());
				return $card;
			}
		}
		$this->logger->error("we should never get here - random card drawing error!");
	}


	private function Proceed(DungeonParty $party) {
		// check if we finished this level

		$monsters_left = 0;
		$level = $party->getCurrentLevel();
		if ($level) {
			foreach ($level->getMonsters() as $monster) {
				$monsters_left += $monster->getAmount();
			}
			$treasures_left = 0;
			foreach ($level->getTreasures() as $treasure) {
				$treasures_left += $treasure->getValue();
			}

			if ($monsters_left == 0 && $treasures_left == 0) {
				$this->logger->info("level finished, proceeding to next one...");
				$level = $this->creator->createRandomLevel($party->getDungeon(), $level->getDepth()+1);
				$party->setCurrentLevel($level);
				$this->addEvent($party, 'play.level', array('level'=>$level->getDepth()));
			} else {
				$this->logger->info($party->countActiveMembers()." characters, $monsters_left monsters and $treasures_left treasure left in this level");
			}
		} else {
			$this->logger->warning("no current level?");
		}
	}


}
