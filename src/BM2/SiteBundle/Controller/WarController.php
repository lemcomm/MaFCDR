<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\BattleGroup;
use BM2\SiteBundle\Entity\Siege;
use BM2\SiteBundle\Entity\War;
use BM2\SiteBundle\Entity\WarTarget;
use BM2\SiteBundle\Entity\Realm;

use BM2\SiteBundle\Form\WarType;
use BM2\SiteBundle\Form\BattleParticipateType;
use BM2\SiteBundle\Form\DamageFeatureType;
use BM2\SiteBundle\Form\InteractionType;
use BM2\SiteBundle\Form\LootType;
use BM2\SiteBundle\Form\EntityToIdentifierTransformer;

use BM2\SiteBundle\Service\History;

use Doctrine\ORM\EntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;


/**
 * @Route("/war")
 */
class WarController extends Controller {

	/**
	  * @Route("/view/{id}")
	  * @Template
	  */
	public function viewAction(War $id) {
		return array('war'=>$id);
	}

	/**
	  * @Route("/declare/{realm}")
	  * @Template
	  */
	public function declareAction(Realm $realm, Request $request) {
		$this->get('dispatcher')->setRealm($realm);
		$character = $this->get('dispatcher')->gateway('hierarchyWarTest');
		$war = new War;
/*
		$me = array();
		foreach ($realm->findAllInferiors(true) as $r) {
			$me[] = $r->getId();
		}
		$form = $this->createForm(new WarType($me), $war);
*/
		$me = array($realm->getId());

		$form = $this->createForm(new WarType($me), $war);
		$form->handleRequest($request);	
		if ($form->isValid()) {
			$em = $this->getDoctrine()->getManager();
			$em->persist($war);
			$targets = $form->get('targets')->getData();
			$target_realms = new ArrayCollection;
			foreach ($targets as $t) {
				// TODO: check that settlement is not already a target in one of our wars
				$target = new WarTarget;
				$target->setSettlement($t);
				$target->setWar($war);
				$war->addTarget($target);
				$target->setAttacked(false);
				$target->setTakenEver(false);
				$target->setTakenCurrently(false);
				if ($t->getRealm()) {
					$target_realms->add($t->getRealm());
				}
				$em->persist($target);
			}
			$amount = count($targets);
			$war->setTimer(30 + $amount*10 + round(sqrt($amount)*30));
			$war->setRealm($realm);
			$em->flush();
			$this->get('history')->logEvent(
				$war,
				'event.war.started',
				array(),
				History::HIGH, true
			);
			$this->get('history')->logEvent(
				$realm,
				'event.realm.war.declared',
				array('%link-war%'=>$war->getId()),
				History::HIGH, true
			);
			foreach ($target_realms as $tr) {
				$this->get('history')->logEvent(
					$tr,
					'event.realm.war.received',
					array('%link-realm%'=>$realm->getId(), '%link-war%'=>$war->getId()),
					History::HIGH, true
				);
			}
			$em->flush();
			return $this->redirectToRoute('bm2_site_war_view', array('id'=>$war->getId()));
		}
		return array('form'=>$form->createView());
	}


	/**
	  * @Route("/settlement/defend")
	  * @Template
	  */
	public function defendSettlementAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('militaryDefendSettlementTest', true);

		$form = $this->createFormBuilder(null, array('translation_domain'=>'actions'))
			->add('submit', 'submit', array(
				'label'=>'military.settlement.defend.submit',
				))
			->getForm();

		$form->handleRequest($request);
		if ($form->isValid()) {
			$act = new Action;
			$act->setType('settlement.defend')->setCharacter($character)->setTargetSettlement($settlement);
			$act->setBlockTravel(false);
			$result = $this->get('action_resolution')->queue($act);
			return array('settlement'=>$settlement, 'result'=>$result);
		}
		return array('settlement'=>$settlement, 'form'=>$form->createView());
	}

	/**
	  * @Route("/settlement/siege")
	  * @Template
	  */
	public function siegeSettlementAction(Request $request) {
		# Security checks and set $character and $settlement.		
		list($character, $settlement) = $this->get('dispatcher')->gateway('militarySiegeSettlementTest', true);
		# Prepare other variables.
		$siege = null;
		$leader = null;
		# Prepare entity manager referencing.
		$em = $this->getDoctrine()->getManager();

		# Figure out if we're in a siege already or not. Build appropriate form.
		if ($settlement->getSiege()) {
			$already = TRUE;
			$siege = $settlement->getSiege();
			$form = $this->createForm(new SiegeManageType($character, $settlement, $siege));
		} else {
			$already = FALSE;
			$form = $this->createForm(new SiegeNewType($character, $settlement));
		}
		
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			# Figure out which form is being submitted.
			if ($form->getName() == 'newsiege') {
				# For new sieges, this is easy, if not long. Mostly, we just need to make the siege, battle groups, and the events.
				$siege = new Siege;
				$siege->setSettlement($settlement);
				$em->persist();
				$em->flush();
				
				// FIXME: this should also be set (but differently) if everyone involved is inside the settlement
				$battle->setSettlement($settlement);
				$this->get('history')->logEvent(
					$settlement,
					'event.settlement.besieged',
					array('%link-character%'=>$character->getId()),
					History::MEDIUM, false, 60
				);

				# TODO: combine this code with the code in action resolution for battles so we have less code duplication.
				# setup attacker (i.e. me)
				$attackers = new BattleGroup;
				$attackers->setSiege($siege);
				$attackers->setAttacker(true);
				$attackers->addCharacter($character);
				$attackers->setLeader($character);
				$siege->addGroup($attackers);
				$em->persist($attackers);

				# setup defenders
				$defenders = new BattleGroup;
				$defenders->setSiege($siege);
				$defenders->setAttacker(false);
				$siege->addGroup($defenders);
				$em->persist($defenders);

				# create character action
				$act = new Action;
				$act->setCharacter($character)
					->setTargetSettlement($settlement)
					->setTargetBattlegroup($attackers)
					->setCanCancel(false)
					->setBlockTravel(true);
				$this->get('action_resolution')->queue($act);

				$character->setTravelLocked(true);

				# add everyone who has a "defend settlement" action set
				foreach ($em->getRepository('BM2SiteBundle:Action')->findBy(array('related_settlement_id' => $settlement->getId(), 'type' => 'settlement.defend')) as $defender) {
					$defenders->addCharacter($defender->getCharacter());

					$act = new Action;
					$act->setType('military.siege')
						->setCharacter($defender->getCharacter())
						->setTargetBattlegroup($defenders)
						->setStringValue('forced')
						->setCanCancel(true)
						->setBlockTravel(true);
					$this->get('action_resolution')->queue($act);

					# notify
					$this->get('history')->logEvent(
						$defender->getCharacter(),
						'resolution.defend.success', array(
							"%link-settlement%"=>$settlement->getId(),
							"%time%"=>$this->gametime->realtimeFilter($time)
						),
						History::HIGH, false, 25
					);
					$defender->getCharacter()->setTravelLocked(true);
				}
				$em->flush();
			} else {
				# Selection dependent siege management, engage!
				switch($data['action']) {
					case 'leadership':
						if ($siege->getLeader() == $character && $data['action'] == 'newleader' && $data['newleader'] != $character) {
							$siege->setLeader($data['newleader']);
						} else {
							throw $this->createNotFoundException('error.notfound.change');
						}
						break;
					case 'build':
						# Start constructing siege equipment!
					case 'assault':
						if ($siege->getLeader() == $character && data['action'] == 'assault') {
							$this->get('action_resolution')->createBattle($character, $settlement, null, $siege, $siege->getAttacker(), $siege->getDefender());
						} else {
							
						}
						break;
					case 'disband':
						# Stop the siege.
						break;
					case 'leave':
						# Leave the siege.
						break;
					case 'attack':
						# Suicide run?
						break;
								
				}


				$em->flush();
			}
		}

		return array(
			'settlement'=>$settlement,
			'leader'=>$leader,
			'form'=>$form->createView()
		);
	}

	/**
	  * @Route("/settlement/attack")
	  * @Template
	  */
	public function attackSettlementAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('militaryAttackSettlementTest', true);

		// TODO: also allow attack on travel destination

		$result=false;
		$builder = $this->createFormBuilder();
		$wars = array();
		foreach ($settlement->getWarTargets() as $target) {
			if ($character->findRealms()->contains($target->getWar()->getRealm())) {
				$wars[] = $target->getWar()->getId();
			}
		}
		if ($wars) {
			$builder->add('war', 'entity', array(
				'label'=>'military.settlement.attack.war',
				'translation_domain'=>'actions',
				'required'=>true,
				'class'=>'BM2SiteBundle:War', 'choice_label'=>'summary', 'query_builder'=>function(EntityRepository $er) use ($wars) {
					return $er->createQueryBuilder('w')->where('w IN (:wars)')->setParameters(array('wars'=>$wars));
				}
			));
		}
		$builder->add('submit', 'submit', array('label'=>'military.settlement.attack.submit', 'translation_domain'=>'actions'));
		$form = $builder->getForm();

		$form->handleRequest($request);
		if ($form->isValid()) {
			$em = $this->getDoctrine()->getManager();
			$data = $form->getData();
			$result = $this->get('action_resolution')->createBattle($character, $settlement);
			if (isset($result['battle']) && isset($data['war'])) {
				$target->setAttacked(true);
				$this->get('history')->logEvent(
					$data['war'],
					'event.war.settlement',
					array('%link-settlement%'=>$settlement->getId()),
					History::LOW, true
				);
				$data['war']->addRelatedBattle($result['battle']);
				$result['battle']->setWar($data['war']);
			}
			$em->flush();
		}
		return array('settlement'=>$settlement, 'form'=>$form->createView(), 'result'=>$result);
	}

	/**
	  * @Route("/settlement/loot")
	  * @Template
	  */
	public function lootSettlementAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryLootSettlementTest', false, true);
		$em = $this->getDoctrine()->getManager();

		if ($character->getInsideSettlement()) {
			$inside = true;
			$settlement = $character->getInsideSettlement();
		} else {
			$inside = false;
			$geo = $this->get('geography')->findMyRegion($character);
			$settlement = $geo->getSettlement();
		}
		if (!$settlement) {
			// strange, we can't find a settlement. What's going on?
			$this->get('logger')->error('looting without settlement, character #'.$character->getId().' at position '.$character->getLocation()->getX().' / '.$character->getLocation()->getY());
		}

		$form = $this->createForm(new LootType($settlement, $em, $inside, $character->isNPC()));
		$form->handleRequest($request);	
		if ($form->isValid()) {

// FIXME: shouldn't militia defend against looting?
			$my_soldiers = $character->getActiveSoldiers()->count();
			$ratio = $my_soldiers / (100 + $settlement->getFullPopulation());
			if ($ratio > 0.25) { $ratio = 0.25; }
			if (!$inside) {
				if ($settlement->isFortified()) {
					$ratio *= 0.25;
				} else {
					$ratio *= 0.1;
				}
			}

			$data = $form->getData();

			foreach ($data['method'] as $method) {
				if (($method=='thralls' || $method=='resources') && !$data['target']) {
					$form->addError(new FormError("loot.target"));
					return array('form'=>$form->createView());
				}
				if ($method=='thralls') {
					// check if target settlement allows slaves
					if ($data['target']->getAllowThralls()==false) {
						$form->addError(new FormError("loot.noslaves"));
						return array('form'=>$form->createView());						
					}
				}
			}

			// FIXME: this is too complicated for our current action resolution (among other things, it needs two target settlements) DAMN
			// hmm... since it blocks travel, maybe we can use the location and store only the destination settlement
			// or we resolve it immediately the way we do with wealth already and don't bother about it
			$methods = count($data['method']);
			$destination = $data['target'];
			$time = max(4,$methods * $methods + $methods);

			$act = new Action;
			$act->setType('settlement.loot')->setCharacter($character);
			$act->setTargetSettlement($settlement);
			$act->setBlockTravel(true)->setCanCancel(false);
			$complete = new \DateTime("now");
			$complete->add(new \DateInterval("PT".$time."H"));
			$act->setComplete($complete);
			$result = $this->get('action_resolution')->queue($act);

			if ($inside) {
				$event = 'event.settlement.loot';
			} else {
				$event = 'event.settlement.loot2';
			}
			$this->get('history')->logEvent(
				$settlement,
				$event,
				array('%link-character%'=>$character->getId()),
				History::HIGH, true, 20
			);

			$result = array();
			foreach ($data['method'] as $method) {
				switch ($method) {
					case 'thralls':
						$mod = 1;
						$cycle = $this->get('appstate')->getCycle();
						if ($settlement->getAbductionCooldown() && !$inside) {
							$cooldown = $settlement->getAbductionCooldown() - $cycle;
							if ($cooldown <= -24) {
								$mod = 1;
							} elseif ($cooldown <= -20) {
								$mod = 0.9;
							} elseif ($cooldown <= -16) {
								$mod = 0.75;
							} elseif ($cooldown <= -12) {
								$mod = 0.6;
							} elseif ($cooldown <= -8) {
								$mod = 0.45;
							} elseif ($cooldown <= -4) {
								$mod = 0.3;
							} elseif ($cooldown <= -2) {
								$mod = 0.25;
							} elseif ($cooldown <= -1) {
								$mod = 0.225;
							} elseif ($cooldown <= 0) {
								$mod = 0.2; 
							} elseif ($cooldown <= 6) {
								$mod = 0.15;
							} elseif ($cooldown <= 12) {
								$mod = 0.1;
							} elseif ($cooldown <= 18) {
								$mod = 0.05;
							} else {
								$mod = 0;
							}
						}
						$max = floor($settlement->getPopulation() * $ratio * 1.5 * $mod);
						list($taken, $lost) = $this->lootvalue($max);
						if ($taken > 0) {
							// no loss / inefficiency here
							$destination->setThralls($destination->getThralls() + $taken);
							$settlement->setPopulation($settlement->getPopulation() - $taken);
							# Now to factor in abduction cooldown so the next looting operation to abduct people won't be nearly so successful.
							# Yes, this is semi-random. It's setup to *always* increase, but the amount can be quite unpredictable.
							if ($settlement->getAbductionCooldown()) {
								$cooldown = $settlement->getAbductionCooldown() - $cycle;
							} else {
								$cooldown = 0;
							}
							if ($cooldown < 0) {
								$settlement->setAbductionCooldown($cycle);
							} elseif ($cooldown < 1) {
								$settlement->setAbductionCooldown($cycle + 1);
							} elseif ($cooldown <= 2) {
								$settlement->setAbductionCooldown($cycle + rand(1,2) + rand(2,3));
							} elseif ($cooldown <= 4) {
								$settlement->setAbductionCooldown($cycle + rand(3,4) + rand(2,3));
							} elseif ($cooldown <= 6) {
								$settlement->setAbductionCooldown($cycle + rand(5,6) + rand(2,4));
							} elseif ($cooldown <= 8) {
								$settlement->setAbductionCooldown($cycle + rand(7,8) + rand(2,4));
							} elseif ($cooldown <= 12) {
								$settlement->setAbductionCooldown($cycle + rand(9,12) + rand(4,6));
							} elseif ($cooldown <= 16) {
								$settlement->setAbductionCooldown($cycle + rand(13,16) + rand(4,6));
							} elseif ($cooldown <= 20) {
								$settlement->setAbductionCooldown($cycle + rand(17,20) + rand(4,6));
							} else {
								$settlement->setAbductionCooldown($cycle + rand(21,24) + rand(4,6));
							}
							$this->get('history')->logEvent(
								$destination,
								'event.settlement.lootgain.thralls',
								array('%amount%'=>$taken, '%link-character%'=>$character->getId(), '%link-settlement%'=>$settlement->getId()),
								History::MEDIUM, true, 15
							);
							if (rand(0,100) < 20) {
								$this->get('history')->logEvent(
									$settlement,
									'event.settlement.thrallstaken2',
									array('%amount%' => $taken, '%link-settlement%'=>$destination->getId()),
									History::MEDIUM, false, 30
								);
							} else {
								$this->get('history')->logEvent(
									$settlement,
									'event.settlement.thrallstaken',
									array('%amount%' => $taken),
									History::MEDIUM, false, 30
								);
							}
						}
						$result['thralls'] = $taken;
						break;
					case 'supply':
						$food = $em->getRepository('BM2SiteBundle:ResourceType')->findOneByName("food");
						$local_food_storage = $settlement->findResource($food);
						$can_take = ceil(20 * $ratio);

						$max_supply = $this->get('appstate')->getGlobal('supply.max_value', 800);
						$max_items = $this->get('appstate')->getGlobal('supply.max_items', 15);
						$max_food = $this->get('appstate')->getGlobal('supply.max_food', 50);

						foreach ($character->getAvailableEntourageOfType('follower') as $follower) {
							if ($follower->getEquipment()) {
								if ($inside) {
									$provider = $follower->getEquipment()->getProvider();
									if ($building = $settlement->getBuildingByType($provider)) {
										$available = round($building->getResupply() * $ratio);
										list($taken, $lost) = $this->lootvalue($available);
										if ($lost > 0) {
											$building->setResupply($building->getResupply() - $lost);
										}
										if ($taken > 0) {
											if ($follower->getSupply() < $max_supply) {
												$items = floor($taken / $follower->getEquipment()->getResupplyCost());
												if ($items > 0) { 
													$follower->setSupply(min($max_supply, min($follower->getEquipment()->getResupplyCost()*$max_items, $follower->getSupply() + $items * $follower->getEquipment()->getResupplyCost() )));
												}
												if (!isset($result['supply'][$follower->getEquipment()->getName()])) {
													$result['supply'][$follower->getEquipment()->getName()] = 0;
												}
												$result['supply'][$follower->getEquipment()->getName()]+=$items;
											}
										}
									} // else no such equipment available here
								} // else we are looting the countryside where we can get only food
							} else {
								// supply food
								// fake additional food stowed away by peasants - there is always some food to be found in a settlement or on its farms
								if ($inside) {
									$loot_max = round(min($can_take*5, $local_food_storage->getStorage() + $local_food_storage->getAmount()*0.333));
								} else {
									$loot_max = round(min($can_take*5, $local_food_storage->getStorage()*0.5 + $local_food_storage->getAmount()*0.5));
								}
								list($taken, $lost) = $this->lootvalue($loot_max);
								if ($lost > 0) {
									$local_food_storage->setStorage(max(0,$local_food_storage->getStorage() - $lost));
								}
								if ($taken > 0) {
									if ($follower->getSupply() < $max_food) {
										$follower->setSupply(min($max_food, max(0,$follower->getSupply()) + $taken));
										if (!isset($result['supply']['food'])) {
											$result['supply']['food'] = 0;
										}
										$result['supply']['food']++;
									}
								}
							}
						}
						break;
					case 'resources':
						$result['resources'] = array();
						$notice_target = false; $notice_victim = false;
						foreach ($settlement->getResources() as $resource) {
							$available = round($resource->getStorage() * $ratio);
							if ($resource->getType()->getName() == 'food') {
								$can_carry = $my_soldiers * 5;
							} else {
								$can_carry = $my_soldiers * 2;
							}
							list($taken, $lost) = $this->lootvalue(min($available, $can_carry));
							if ($lost > 0) {
								$resource->setStorage($resource->getStorage() - $lost);
								if (rand(0,100) < $lost && rand(0,100) < 50) {
									$notice_victim = true;
								}
							}
							if ($taken > 0) {
								$dres = $destination->findResource($resource->getType());
								if ($dres) {
									$dres->setStorage($dres->getStorage() + $taken); // this can bring a settlement temporarily above its max storage value
									$notice_target = true;
								} else {
									// TODO: we don't have this resource - what to we do? right now, the plunder is simply lost
								}
							}
							$result['resources'][$resource->getType()->getName()] = $taken;
						}
						if ($notice_target) {
							$this->get('history')->logEvent(
								$destination,
								'event.settlement.lootgain.resource',
								array('%link-character%'=>$character->getId(), '%link-settlement%'=>$settlement->getId()),
								History::MEDIUM, true, 15
							);
						}
						if ($notice_victim) {
							$this->get('history')->logEvent(
								$settlement,
								'event.settlement.resourcestaken2',
								array('%link-settlement%'=>$destination->getId()),
								History::MEDIUM, false, 30
							);
						}
						break;
 					case 'wealth':
 						if ($character == $settlement->getOwner()) {
 							// forced tax collection - doesn't depend on soldiers so much
 							if ($ratio >= 0.02) {
 								$mod = 0.3;
 							} else if ($ratio >= 0.01) {
 								$mod = 0.2;
 							} else if ($ratio >= 0.005) {
 								$mod = 0.1;
 							} else {
 								$mod = 0.05;
 							}
	 						$steal = rand(ceil($settlement->getGold() * $ratio), ceil($settlement->getGold() * $mod));
							$drop = $steal + ceil(rand(10,20) * $settlement->getGold() / 100);
 						} else {
	 						$steal = rand(0, ceil($settlement->getGold() * $ratio));
							$drop = ceil(rand(40,60) * $settlement->getGold() / 100);
 						}
						$steal = ceil($steal * 0.75); // your soldiers will pocket some (and we just want to make it less effective)
 						$result['gold'] = $steal; // send result to page for display
 						$character->setGold($character->getGold() + $steal); //add gold to characters purse
 						$settlement->setGold($settlement->getGold() - $drop); //remove gold from settlement ?Why do we remove a different amount of gold from the settlement?
 						break;
					case 'burn':
						$targets = min(5, floor(sqrt($my_soldiers/5)));
						$buildings = $settlement->getBuildings()->toArray();
						for ($i=0; $i<$targets; $i++) {
							$pick = array_rand($buildings);
							$target = $buildings[$pick];
							$type = $target->getType()->getName();
							list($ignore, $damage) = $this->lootvalue(round($my_soldiers * 32 / $targets));
							if (!isset($result['burn'][$type])) {
								$result['burn'][$type] = 0;
							}
							$result['burn'][$type] += $damage;
							if ($target->isActive()) {
								// damaged, inoperative now, but keep current workers as repair crew
								$workers = $target->getEmployees();
								$target->abandon($damage);
								$target->setWorkers($workers / $settlement->getPopulation());
								$this->get('history')->logEvent(
									$settlement,
									'event.settlement.burned',
									array('%link-buildingtype%'=>$target->getType()->getId()),
									History::MEDIUM, false, 30
								);
							} else {
								$target->setCondition($target->getCondition() - $damage);
								if (abs($target->getCondition()) > $target->getType()->getBuildHours()) {
									// destroyed
									$this->get('history')->logEvent(
										$settlement,
										'event.settlement.burned2',
										array('%link-buildingtype%'=>$target->getType()->getId()),
										History::HIGH, false, 30
									);
									$em->remove($target);
									$settlement->removeBuilding($target);
								} else {
									// damaged
									$this->get('history')->logEvent(
										$settlement,
										'event.settlement.burned',
										array('%link-buildingtype%'=>$target->getType()->getId()),
										History::MEDIUM, false, 30
									);
								}
							}
						}
						break;
				}
			}
			$em->flush();

			return array('result'=>$result, 'target'=>$destination);
		}

		return array('form'=>$form->createView(), 'settlement'=>$settlement);
	}

	private function lootvalue($max) {
		$a = max(rand(0, $max), rand(0, $max));
		$b = max(rand(0, $max), rand(0, $max));
		
		if ($a < $b) {
			return array($a, $b);
		} else {
			return array($b, $a);
		}
	}

	/**
	  * @Route("/disengage")
	  * @Template
	  */
	public function disengageAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryDisengageTest');

		$engagements = array();
		foreach ($character->findForcedBattles() as $act) {
			$engagements[] = $act->getTargetBattleGroup();
		}

		$form = $this->createFormBuilder();
		if (count($engagements) > 1) {
			$form->add('bg', 'entity', array(
				'empty_value' => 'military.dise',
				'label'=>'military.disengage.battles',
				'translation_domain' => 'actions',
				'multiple'=>true,
				'expanded'=>true,
				'class'=>'BM2SiteBundle:BattleGroup',
				'property'=>'battle.name',
				'query_builder'=>function(EntityRepository $er) use ($engagements) {
					return $er->createQueryBuilder('g')->where('g in (:battles)')->setParameter('battles', $engagements);
				}
			));
		}
		$form->add('submit', 'submit', array('label'=>'military.disengage.submit', 'translation_domain' => 'actions'));
		$form = $form->getForm();

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			$results = array();
			if (count($engagements) > 1) {
				foreach ($data['bg'] as $bg) {
					$action = null;
					foreach ($character->findForcedBattles() as $act) {
						if ($act->getTargetBattleGroup() == $bg) {
							$action = $act;
						}
					}
					if ($character->getActions()->exists(
						function($key, $element) use ($bg) { 
							return ($element->getType() == 'military.intercepted' && $element->getTargetBattleGroup() == $bg);
						}
					)) {
						$results[] = array("success"=>false, "message"=>"unavailable.intercepted");
					} else {
						$results[] = $this->get('action_resolution')->createDisengage($character, $bg, $action);
					}
				}
			} else {
				$bg = $engagements[0];
				if ($character->getActions()->exists(
					function($key, $element) use ($bg) { 
						return ($element->getType() == 'military.intercepted' && $element->getTargetBattleGroup() == $bg);
					}
				)) {
					$results[] = array("success"=>false, "message"=>"unavailable.intercepted");
				} else {
					$results[] = $this->get('action_resolution')->createDisengage($character, $bg, $character->findForcedBattles()->first());
				}
			}

			return array('results'=>$results);
		}

		return array(
			'takes'=>$this->get('action_resolution')->calculateDisengageTime($character),
			'form'=>$form->createView()
		);
	}

	/**
	  * @Route("/evade")
	  * @Template
	  */
	public function evadeAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryEvadeTest');

		$form = $this->createFormBuilder()
			->add('submit', 'submit', array('label'=>'military.evade.submit', 'translation_domain' => 'actions'))
			->getForm();

		$form->handleRequest($request);
		if ($form->isValid()) {
			$act = new Action;
			$act->setType('military.evade')->setCharacter($character);
			$act->setBlockTravel(false);
			$result = $this->get('action_resolution')->queue($act);

			return array('result'=>$result);
		}

		return array('form'=>$form->createView());
	}


	/**
	  * @Route("/block")
	  * @Template
	  */
	public function blockAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryBlockTest', false, true);
		$form = $this->createFormBuilder(null, array('attr'=>array('class'=>'wide')))
			->add('mode', 'choice', array(
				'required'=>true,
				'empty_value'=>'form.choose',
				'label'=>'military.block.mode.label',
				'translation_domain'=>'actions',
				'choices'=>array('allow'=>'military.block.mode.allow', 'attack'=>'military.block.mode.attack')
			))
			->add('target', 'entity', array(
				'required' => true,
				'placeholder'=>'form.choose',
				'label'=>'military.block.target',
				'translation_domain'=>'actions',
				'class'=>'BM2SiteBundle:Listing', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($character) {
					return $er->createQueryBuilder('l')->where('l.owner = :me')->setParameter('me',$character->getUser());
				}))
			->add('submit', 'submit', array('label'=>'military.block.submit', 'translation_domain'=>'actions'))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$act = new Action;
			$act->setType('military.block')->setCharacter($character)
				->setHourly(true)
				->setBlockTravel(true)
				->setStringValue($data['mode'])
				->setTargetListing($data['target']);
			$result = $this->get('action_resolution')->queue($act);

			return array('result'=>$result);
		}

		return array('form'=>$form->createView());
	}

	/**
	  * @Route("/damage")
	  * @Template
	  */
	public function damageAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryDamageFeatureTest', false, true);
		$actdistance = $this->get('geography')->calculateInteractionDistance($character);
		$spotdistance = $this->get('geography')->calculateSpottingDistance($character);

		// TODO: select feature to attack (could be more than one)
		$features = $this->get('geography')->findFeaturesNearMe($character);
		$form = $this->createForm(new DamageFeatureType($features));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();

			$target = $data['target'];
			if (in_array($target->getType()->getName(), array('signpost', 'borderpost'))) {
				$hours = 1;
			} else {
				$hours = 4;
			}
			$men = $character->getActiveSoldiers()->count();
			$damage = round(rand(sqrt($men)*$hours*25, sqrt($men*2)*$hours*25)); // for 100 men, damage = 1000 - 2000 => 5-10 attacks to destroy a tower

			$act = new Action;
			$act->setType('military.damage')->setCharacter($character);
			$act->setBlockTravel(true)->setCanCancel(false);
			$complete = new \DateTime("now");
			$complete->add(new \DateInterval("PT".$hours."H"));
			$act->setComplete($complete);
			$result = $this->get('action_resolution')->queue($act);

			if ($result['success'] == true) {
				$settlement = $target->getGeoData()->getSettlement();

				$result = $target->ApplyDamage($damage);
				$this->get('history')->logEvent(
					$settlement,
					'event.feature.'.$result,
					array('%link-character%'=>$character->getId(), '%link-featuretype%'=>$target->getType()->getId(), '%name%'=>$target->getName()),
					$result=='destroyed'?History::MEDIUM:History::LOW, true, $result=='destroyed'?30:15
				);

				// TODO on destroyed - maybe sometimes we want to remove it? but we need it as waypoint for roads, maybe

				$em->flush();
			}

			return array(
				'result' => $result,
				'featuretype' => $target->getType(),
				'actdistance'	=>	$actdistance,
				'spotdistance'	=>	$spotdistance
			);
		}

		return array(
			'features'		=> $features,
			'form'			=> $form->createView(),
			'actdistance'	=>	$actdistance,
			'spotdistance'	=>	$spotdistance
		);
	}


	/**
	  * @Route("/nobles/attack")
	  * @Template
	  */
	public function attackOthersAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('militaryAttackNoblesTest', true);

		$result = false;

		$form = $this->createForm(new InteractionType('attack', $this->get('geography')->calculateInteractionDistance($character), $character, true, true));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();

			if (count($data['target']) == 0) {
				$form->addError(new FormError("attack.nobody"));
			} else {
				$result = $this->get('action_resolution')->createBattle($character, $character->getInsideSettlement(), $data['target']);
				if ($result['outside'] && $character->getInsideSettlement()) {
					// leave settlement if we attack targets outside
					$character->setInsideSettlement(null);
				}

				// FIXME: incomplete - people joining later don't see this conversation!
				//			=> it should be linked to battle, but maybe it should just be in the new message system?
				$msg_user = $this->get('message_manager')->getMsgUser($character);
				$recipients = array();
				foreach ($data['target'] as $target) {
					$recipients[] = $this->get('message_manager')->getMsgUser($target);
				}
				if ($data['message']) {
					list($meta, $message) = $this->get('message_manager')->newConversation($msg_user, $recipients, 'attack by '.$character->getName(), $data['message']);
				}
				$em->flush();
			}
		}

		return array('form'=>$form->createView(), 'result'=>$result);
	}

	/**
	  * @Route("/battles/aid")
	  * @Template
	  */
	public function aidAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryAidTest');
		$em = $this->getDoctrine()->getManager();

		$success = false; $target = null;

		$form = $this->createFormBuilder()
			->add('target', 'hidden_entity', array('required' => true, 'entity_repository'=>'BM2SiteBundle:Character'))
			->add('duration', 'choice', array('choices'=>array('3'=>'three days', '12'=>'two weeks', '30'=>'five weeks')))
			->add('submit', 'submit')
			->getForm();

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			$complete = new \DateTime("now");
			$complete->add(new \DateInterval("P".round($data['duration'])."D"));

			$act = new Action;
			$act->setType('military.aid')
				->setCharacter($character)
				->setTargetCharacter($data['target'])
				->setComplete($complete)
				->setCanCancel(true)
				->setHourly(true)
				->setBlockTravel(false);
			$success = $this->get('action_resolution')->queue($act);
			$target = $data['target'];
		}

		return array('form'=>$form->createView(), 'success'=>$success, 'target'=>$target);
	}

	/**
	  * @Route("/battles/join")
	  * @Template
	  */
	public function battleJoinAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('militaryJoinBattleTest', true);

		$success = false;
		$nearby_battles = $this->get('geography')->findBattlesInActionRange($character);

		if ($character->getInsideSettlement()) {
			$battles = $nearby_battles;
		} else {
			$battles = array();
			foreach ($nearby_battles as $b) {
				$someone_outside = false;
				foreach ($b['battle']->getGroups() as $group) {
					foreach ($group->getCharacters() as $char) {
						if ($char->getInsideSettlement() == false) {
							$someone_outside = true;
							break 2;
						}
					}
				}
				if ($someone_outside) {
					$battles[] = $b;
				}
			}
		}

		$form = $this->createForm(new BattleParticipateType($battles));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			if (isset($data['group'])) {
				$this->get('military')->joinBattle($character, $data['group']);
				$this->getDoctrine()->getManager()->flush();
				$success = $data['group']->getBattle();
			}
		}

		return array('battles'=>$battles, 'now'=>new \DateTime("now"), 'form'=>$form->createView(), 'success'=>$success);
	}

}
