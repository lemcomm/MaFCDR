<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\BattleGroup;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Siege;
use BM2\SiteBundle\Entity\War;
use BM2\SiteBundle\Entity\WarTarget;

use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\BattleParticipateType;
use BM2\SiteBundle\Form\DamageFeatureType;
use BM2\SiteBundle\Form\EntityToIdentifierTransformer;
use BM2\SiteBundle\Form\InteractionType;
use BM2\SiteBundle\Form\LootType;
use BM2\SiteBundle\Form\SiegeType;
use BM2\SiteBundle\Form\SiegeStartType;
use BM2\SiteBundle\Form\WarType;

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
	  */
	public function viewAction(War $id) {

		return $this->render('War/view.html.twig', [
			'war'=>$id
		]);
	}

	/**
	  * @Route("/declare/{realm}")
	  */
	public function declareAction(Realm $realm, Request $request) {
		$this->get('dispatcher')->setRealm($realm);
		$character = $this->get('dispatcher')->gateway('hierarchyWarTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
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

		return $this->render('War/declare.html.twig', [
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/settlement/defend")
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
			$result = $this->get('action_manager')->queue($act);

			#TODO: Turn this into a flash and redirect.

			return $this->render('War/defendSettlement.html.twig', [
				'settlement'=>$settlement, 'result'=>$result
			]);
		}

		return $this->render('War/defendSettlement.html.twig', [
			'settlement'=>$settlement, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/place/defend")
	  */
	public function defendPlaceAction(Request $request) {
		list($character, $settlement, $place) = $this->get('dispatcher')->gateway('militaryDefendPlaceTest', true, null, true);
		$form = $this->createFormBuilder(null, array('translation_domain'=>'actions'))
			->add('submit', 'submit', array(
				'label'=>'military.place.defend.submit',
				))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			$act = new Action;
			$act->setType('place.defend')->setCharacter($character)->setTargetPlace($place);
			$act->setBlockTravel(false);
			$result = $this->get('action_manager')->queue($act);

			#TODO: Convert to flash and redirect.

			return $this->render('War/defendPlace.html.twig', [
				'place'=>$settlement, 'result'=>$result
			]);
		}

		return $this->render('War/defendPlace.html.twig', [
			'place'=>$place, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/siege")
	  * @Route("/siege/")
	  * @Route("/siege/place")
	  * @Route("/siege/place/{place}", requirements={"place"="\d+"}, name="maf_war_siege_place")
	  */
	public function siegeAction(Request $request, Place $place = null) {
		# Security check.
		list($character, $settlement) = $this->get('dispatcher')->gateway(false, true, false);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		if ($place) {
			$nearby = $this->get('geography')->findPlacesInActionRange($character);
			if ($nearby === null || ($nearby !== null && !in_array($place, $nearby))) {
				$place = null; # Nice try.
			}
		}
		if ($place) {
			$settlement = null;
		}
		if ($action = $request->query->get('action')) {
			$action = $request->query->get('action');
		} else {
			$action = 'select';
		}
		$siege = null;
		if($settlement && $settlement->getSiege()) {
			$siege = $settlement->getSiege();
		} elseif($place && $place->getSiege()) {
			$siege = $place->getSiege();
		}
		if($siege) {
			switch($action) {
				case 'leadership':
					$character = $this->get('dispatcher')->gateway('militarySiegeLeadershipTest', false, true, false, $siege);
					break;
				/*
				case 'build':
					$character = $this->get('dispatcher')->gateway('militarySiegeBuildTest', false, true, false, $siege);
					break;
				*/
				case 'assault':
					$character = $this->get('dispatcher')->gateway('militarySiegeAssaultTest', false, true, false, $siege);
					break;
				case 'disband':
					$character = $this->get('dispatcher')->gateway('militarySiegeDisbandTest', false, true, false, $siege);
					break;
				case 'leave':
					$character = $this->get('dispatcher')->gateway('militarySiegeLeaveTest', false, true, false, $siege);
					break;
				/* TODO: Make suicide runs possible.
				case 'attack':
					$character = $this->get('dispatcher')->gateway('militarySiegeAttackTest', false, true, false, $siege);
					break;
				case 'joinattack':
					$character = $this->get('dispatcher')->gateway('militarySiegeJoinAttackTest', false, true, false, $siege);
					break;
				*/
				case 'joinsiege':
					$character = $this->get('dispatcher')->gateway('militarySiegeJoinSiegeTest', false, true, false, $siege);
					break;
				case 'assume':
					$character = $this->get('dispatcher')->gateway('militarySiegeAssumeTest', false, true, false, $siege);
					break;
				case 'select':
				default:
					$character = $this->get('dispatcher')->gateway('militarySiegeGeneralTest', false, true, false, $siege);
					break;
			}
		} elseif (!$place) {
			$character = $this->get('dispatcher')->gateway('militarySiegeSettlementTest');
		} else {
			$character = $this->get('dispatcher')->gateway('militarySiegePlaceTest', false, true, false, $place);
		}

		# Prepare other variables.
		$leader = null; #TODO: Do we actually use this at all?
		# Prepare entity manager referencing.
		$em = $this->getDoctrine()->getManager();

		# Figure out if we're in a siege already or not. Build appropriate form.
		if ($siege) {
			if ($siege->getSettlement()) {
				$already = TRUE;
				$form = $this->createForm(new SiegeType($character, $settlement, $siege, $action));
			} else {
				$already = TRUE;
				$form = $this->createForm(new SiegeType($character, $place, $siege, $action));
			}
		} else {
			$already = FALSE;
			$realms = $character->findRealms();
			$wars = [];
			foreach ($realms as $realm) {
				foreach($realm->getWars() as $war) {
					$wars[] = $war;
				}
			}
			$form = $this->createForm(new SiegeStartType($realms, $wars));
		}

		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			# Figure out which form is being submitted.
			if ($request->request->has('siegestart')) {
				# For new sieges, this is easy, if not long. Mostly, we just need to make the siege, battle groups, and the events.
				$siege = new Siege;
				$em->persist($siege);
				if ($data['war']) {
					$siege->setWar($data['war']);
					$siege->setRealm($data['war']->getRealm());
				} elseif ($data['realm']) {
					$siege->setRealm($data['realm']);
				}
				$siege->setStage(1);
				if ($data['confirm'] && $settlement) {
					$place = FALSE;
					$siege->setSettlement($settlement);
					$settlement->setSiege($siege);
					$encirclement = intval($settlement->getFullPopulation()/3); #1/3 of population returned as flat integer (no decimals)
					$count = 0;
					foreach ($character->getUnits() as $unit) {
						$count += $unit->getActiveSoldiers()->count();
					}
					if ($count >= $encirclement) {
						$siege->setEncircled(TRUE);
					} else {
						$siege->setEncircled(FALSE);
					}
					$siege->setEncirclement($encirclement);
					$maxstages = 1; # No defense, no siege, thus if we have a siege, we always have atleast one stage. This means we have at least a Palisade.
					if($settlement->hasBuildingNamed('Wood Wall')) {
						$maxstages++; # It may be a wall of sticks for the most part, but it's still *something*.
					}
					if($settlement->hasBuildingNamed('Wood Castle')) {
						$maxstages++; # A small citadel, just big enough to offer a last ditch defense.
					}
					if($settlement->hasBuildingNamed('Fortress')) {
						$maxstages++; # Think "curtain wall".
					}
					if($settlement->hasBuildingNamed('Citadel')) {
						$maxstages++; # At this point, our castle has a large, enclosed compound of it's own, usually built at the same strength as the primary walls.
					}
					$siege->setMaxStage($maxstages); # Assuming we have everything, this will max out at 5.
				} elseif ($data['confirm'] && $place) {
					$settlement = FALSE;
					$siege->setPlace($place);
					$siege->setEncircled(TRUE); #For now, sieges always encircle places.
					$place->setSiege($siege);
					$siege->setMaxStage(1);
				}
				$em->flush(); # We need this flushed in order to link to it below.

				$hist = $this->get('history');
				if ($settlement) {
					$hist->logEvent(
						$settlement,
						'event.settlement.besieged',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
					if ($owner = $settlement->getOwner()) {
						$hist->logEvent(
							$owner,
							'event.settlement.besieged2',
							[
								'%link-settlement%'=>$settlement->getId(),
								'%link-character%'=>$character->getId()
							],
							History::MEDIUM, false, 60
						);
					}
					if ($steward = $settlement->getSteward()) {
						$hist->logEvent(
							$steward,
							'event.settlement.besieged2',
							[
								'%link-settlement%'=>$settlement->getId(),
								'%link-character%'=>$character->getId()
							],
							History::MEDIUM, false, 60
						);
					}
					if ($occupant = $settlement->getOccupant()) {
						$hist->logEvent(
							$occupant,
							'event.settlement.besieged2',
							[
								'%link-settlement%'=>$settlement->getId(),
								'%link-character%'=>$character->getId()
							],
							History::MEDIUM, false, 60
						);
					}
				} elseif ($place) {
					$this->get('history')->logEvent(
						$place,
						'event.place.besieged',
						array('%link-character%'=>$character->getId()),
						History::MEDIUM, false, 60
					);
					if ($owner = $place->getOwner()) {
						$hist->logEvent(
							$owner,
							'event.place.besieged2',
							[
								'%link-place%'=>$place->getId(),
								'%link-character%'=>$character->getId()
							],
							History::MEDIUM, false, 60
						);
					}
				}

				# TODO: combine this code with the code in action resolution for battles so we have less code duplication.
				# setup attacker (i.e. me)
				$attackers = new BattleGroup;
				$attackers->setSiege($siege);
				$attackers->setAttackingInSiege($siege);
				$attackers->addCharacter($character);
				$attackers->setLeader($character);
				$attackers->setAttacker(true);
				$siege->addGroup($attackers);
				$siege->setAttacker($attackers);
				$em->persist($attackers);

				# setup defenders
				$defenders = new BattleGroup;
				$defenders->setSiege($siege);
				$defenders->setAttacker(false);
				$siege->addGroup($defenders);
				$em->persist($defenders);

				# create character action
				$act = new Action;
				if ($settlement) {
					$act->setType('military.siege')
						->setCharacter($character)
						->setTargetSettlement($settlement)
						->setTargetBattlegroup($attackers)
						->setCanCancel(false)
						->setBlockTravel(true);
				} elseif ($place) {
					$act->setType('military.siege')
						->setCharacter($character)
						->setTargetPlace($place)
						->setTargetBattlegroup($attackers)
						->setCanCancel(false)
						->setBlockTravel(true);
				}
				$this->get('action_manager')->queue($act);

				$character->setTravelLocked(true);

				if ($settlement) {
					# add everyone who has a "defend settlement" action set
					foreach ($em->getRepository('BM2SiteBundle:Action')->findBy(array('target_settlement' => $settlement->getId(), 'type' => 'settlement.defend')) as $defender) {
						$defenders->addCharacter($defender->getCharacter());

						$act = new Action;
						$act->setType('military.siege')
							->setCharacter($defender->getCharacter())
							->setTargetBattlegroup($defenders)
							->setStringValue('forced')
							->setCanCancel(true)
							->setBlockTravel(true);
						$this->get('action_manager')->queue($act);

						# notify
						$this->get('history')->logEvent(
							$defender->getCharacter(),
							'resolution.defend.success2', array(
								"%link-settlement%"=>$settlement->getId(),
								"%link-character%"=>$character->getId()
							),
							History::HIGH, false, 25
						);
						$defender->getCharacter()->setTravelLocked(true);
					}
				} elseif ($place) {
					# add everyone who has a "defend place" action set
					foreach ($em->getRepository('BM2SiteBundle:Action')->findBy(array('target_place' => $place->getId(), 'type' => 'place.defend')) as $defender) {
						$defenders->addCharacter($defender->getCharacter());

						$act = new Action;
						$act->setType('military.siege')
							->setCharacter($defender->getCharacter())
							->setTargetBattlegroup($defenders)
							->setStringValue('forced')
							->setCanCancel(true)
							->setBlockTravel(true);
						$this->get('action_manager')->queue($act);

						# notify
						$this->get('history')->logEvent(
							$defender->getCharacter(),
							'resolution.defend.success3', array(
								"%link-place%"=>$settlement->getId(),
								"%link-character%"=>$character->getId()
							),
							History::HIGH, false, 25
						);
						$defender->getCharacter()->setTravelLocked(true);
					}
				}
				$em->flush();
				return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'select'));
			} else {
				# Either request doesn't have AreYouSureType or data['sure'] did not equal true.
				if($data['action'] != 'selected') {
					# if action is not already selected that means we shouldn't be here yet, rereoute the user to whatever action is.
					switch($data['action']){
						case 'leadership':
							if ($place) {
								return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'leadership'));
							} else {
								return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'leadership'));
							}
							break;
						case 'build':
							if ($place) {
								return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'build'));
							} else {
								return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'build'));
							}
							break;
						case 'assault':
							if ($place) {
								return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'assault'));
							} else {
								return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'assault'));
							}
							break;
						case 'disband':
							if ($place) {
								return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'disband'));
							} else {
								return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'disband'));
							}
							break;
						case 'leave':
							if ($place) {
								return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'leave'));
							} else {
								return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'leave'));
							}
							break;
						/*
						case 'attack':
							if ($place) {
								return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'attack'));
							} else {
								return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'attack'));
							}
							break;
						case 'joinattack':
							if ($place) {
								return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'joinattack'));
							} else {
								return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'joinattack'));
							}
							break;
							*/
						case 'joinsiege':
							if ($place) {
								return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'joinsiege'));
							} else {
								return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'joinsiege'));
							}
							break;
						case 'assume':
							if ($place) {
								return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'assume'));
							} else {
								return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'assume'));
							}
							break;
					}
				} else {
					# Selection dependent siege management, engage!
					# This only engages if we've already got action set to "selected", so we start looking at what subaction we're processing.
					switch($data['subaction']) {
						case 'leadership':
							if (($siege->getAttacker()->getLeader() == $character || $siege->getDefender()->getLeader() == $character) && $data['subaction'] == 'leadership' && $data['newleader']) {
								# We already know they're *a* leader, now to figure out what group they lead.
								#TODO: Later when we add more sides to a battle, we'll need to expand this.
								if ($siege->getAttacker()->getCharacters()->contains($character)) {
									$group = $siege->getAttacker();
								} else {
									$group = $siege->getDefender();
								}
								$group->setLeader($data['newleader']);
								$em->flush();
								if ($place) {
									return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'select'));
								} else {
									return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'select'));
								}
							} else {
								throw $this->createNotFoundException('error.notfound.change');
							}
							break;
						case 'build':
							# Start constructing siege equipment!
							if ($siege->getAttacker()->getCharacters()->contains($character) && data['subaction'] == 'build') {
								$this->get('war_manager')->buildSiegeTools($data['type'], $data['quantity']);
								if ($place) {
									return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'select'));
								} else {
									return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'select'));
								}
							}
						case 'assault':
							# We're either attackers assaulting or defenders sortieing! Battle type (assault vs sortie) is figured out by WarMan based on which group is passed as the attacker.
							if ($siege->getAttacker()->getLeader() == $character && $data['subaction'] == 'assault') {
								if ($place) {
									$result = $this->get('war_manager')->createBattle($character, null, $place, null, $siege, $siege->getAttacker(), $siege->getDefender());
								} else {
									$result = $this->get('war_manager')->createBattle($character, $settlement, null, null, $siege, $siege->getAttacker(), $siege->getDefender());
								}
								return $this->redirectToRoute('bm2_battle', array('id'=>$result['battle']->getId()));
							} else if ($siege->getDefender()->getLeader() == $character && $data['subaction'] == 'assault') {
								if ($place) {
									$result = $this->get('war_manager')->createBattle($character, null, $place, null, $siege, $siege->getDefender(), $siege->getAttacker());
								} else {
									$result = $this->get('war_manager')->createBattle($character, $settlement, null, null, $siege, $siege->getDefender(), $siege->getAttacker());
								}
								return $this->redirectToRoute('bm2_battle', array('id'=>$result['battle']->getId()));
							} else {
								throw $this->createNotFoundException('error.notfound.leader');
							}
							break;
						case 'disband':
							# Stop the siege.
							if ($siege->getAttacker()->getLeader() == $character && $data['subaction'] == 'disband' && $data['disband'] == TRUE) {
								if ($this->get('war_manager')->disbandSiege($siege, $character)) {
									$this->addFlash('notice', $this->get('translator')->trans('military.siege.disband.success', [], "actions"));
								} else {
									$this->addFlash('notice', $this->get('translator')->trans('military.siege.disband.failure', [], "actions"));
								}
								return $this->redirectToRoute('bm2_actions');
							} else {
								throw $this->createNotFoundException('error.notfound.change');
							}
							break;
						case 'leave':
							# Leave the siege.
							if ($siege->getAttacker()->getLeader() == $character) {
								# Leaders can't leave, though we may change this in the future. They must transfer leadership first, or just cancel the siege.
								throw $this->createNotFoundException('error.notfound.areleader');
							} else {
								# Leave siege will remove the siege action and add a regroup action, as well as remove them from the siege battelgroup they're in.
								if ($this->get('war_manager')->leaveSiege($character, $siege)) {
									$this->addFlash('notice', $this->get('translator')->trans('military.siege.leave.success', [], "actions"));
								} else {
									$this->addFlash('notice', $this->get('translator')->trans('military.siege.leave.failure', [], "actions"));
								}
								return $this->redirectToRoute('bm2_actions');
							}
							break;
						/* I'm very mixed on whether or not to allow this. I'll leave this here for now though, since it should be functional.
						case 'attack':
							# Suicide run.
							if ($data['action'] == 'attack') {
								# Now, figure out if this character is part of defenders or attackers...
								if ($siege->getAttacker()->getCharacters()->contains($character)) {
									# An attacker is going solo, let createBattle make his group on the fly.
									$result = $this->get('war_manager')->createBattle($character, $settlement, null, null, $siege, null, $siege->getDefender());
								} else {
									# Someone is sortieing solo, brave of them. That makes the "defenders" in this case, the besiegers.
									$result = $this->get('war_manager')->createBattle($character, $settlement, null, null, $siege, null, $siege->getAttacker());
								}
								return $this->redirectToRoute('bm2_battle', array('id'=>$result['battle']->getId()));
							}
							break;
						case 'joinattack':
							# Join someone else's suicide run.
							if ($data['subaction'] == 'joinattack' && $data['target']) {
								#TODO
								return $this->redirectToRoute('bm2_battle', array('id'=>$result['battle']->getId()));
							}
							break;
						No attack, no joinattack. */
						case 'joinsiege':
							# Join an ongoing siege.
							if ($data['subaction'] == 'joinsiege' && $data['side']) {
								if($data['side'] == 'attackers') {
									# User wants to join the attackers...
									$side = $siege->getAttacker();
									$side->addCharacter($character);
									$character->addBattleGroup($side);
								} elseif ($data['side'] == 'defenders') {
									# User wants to join the defenders...
									$side = $siege->getDefender();
									$side->addCharacter($character);
									$character->addBattleGroup($side);
								}
								$em->flush(); # So we can reference it below.
								if ($side) {
									if ($side == $siege->getAttacker() && !$siege->getEncircled()) {
										# We aren't already encircling, check if we should now!
										$siege->updateEncirclement();
									}
									# We should have a side, but just in case we don't, we don't make the action, because the user won't be able to unset this.
									$act = new Action;
									$act->setType('military.siege')
										->setCharacter($character)
										->setTargetSettlement($settlement)
										->setTargetBattlegroup($side)
										->setCanCancel(false)
										->setBlockTravel(true);
									$this->get('action_manager')->queue($act);
								}
								if ($place) {
									return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'select'));
								} else {
									return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'select'));
								}
							}
							break;
						case 'assume':
							# Someone is assuming leadership.
							if ($data['subaction'] == 'assume') {
								# First, make sure they're in a position to actually do this.
								#Yes, the form does this too, but if we don't check here you could manipulate the URL to bypass that security check.
								if ($siege->getDefender()->getCharacters()->contains($character)) {
									if ($settlement->getOwner() == $character) {
										$siege->setLeader('defenders', $character);
										$em->flush();
									} elseif (!$siege->getDefender()->getLeader() || $siege->getDefender()->getLeader()->isActive(true)) {
										$siege->setLeader('defenders', $character);
										$em->flush();
									}
								} elseif ($siege->getAttacker()->getCharacters()->contains($character) && (!$siege->getAttacker()->getLeader() || !$siege->getAttacker()->getLeader()->isActive(true))) {
									$siege->setLeader('attackers', $character);
									$em->flush();
								}
								if ($place) {
									return $this->redirectToRoute('maf_war_siege_place', array('place'=>$place->getId(), 'action'=>'select'));
								} else {
									return $this->redirectToRoute('bm2_site_war_siege', array('action'=>'select'));
								}
							}
							break;
						default:
							# This shouldn't be possible, but just in case.
							throw $this->createNotFoundException('error.notfound.noinput');
							break;
					}
				}
			}
		}

		return $this->render('War/siege.html.twig', [
			'character'=>$character,
			'settlement'=>$settlement,
			'place'=>$place,
			'siege'=>$siege,
			'leader'=>$leader,
			'action'=>$action,
			'status'=>$action,
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/settlement/loot")
	  */
	public function lootSettlementAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryLootSettlementTest', false, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
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
			$my_soldiers = 0;
			foreach ($character->getUnits() as $unit) {
				$my_soldiers += $unit->getActiveSoldiers()->count();
			}
			$ratio = $my_soldiers / (100 + $settlement->getFullPopulation());
			if ($ratio > 0.25) { $ratio = 0.25; }
			if (!$inside) {
				if ($settlement->isFortified()) {
					$ratio *= 0.1;
				} else {
					$ratio *= 0.25;
				}
			}

			$data = $form->getData();

			foreach ($data['method'] as $method) {
				if (($method=='thralls' || $method=='resources') && !$data['target']) {
					$form->addError(new FormError("loot.target"));
					return $this->render('War/lootSettlement.html.twig', [
						'form'=>$form->createView(), 'settlement'=>$settlement
					]);
				}
				if ($method=='thralls') {
					// check if target settlement allows slaves
					if ($data['target']->getAllowThralls()==false) {
						$form->addError(new FormError("loot.noslaves"));
						return $this->render('War/lootSettlement.html.twig', [
							'form'=>$form->createView(), 'settlement'=>$settlement
						]);
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
			$result = $this->get('action_manager')->queue($act);

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
 						if ($character == $settlement->getOwner() || $character == $settlement->getSteward()) {
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

			return $this->render('War/lootSettlement.html.twig', [
				'result'=>$result, 'target'=>$destination
			]);
		}

		return $this->render('War/lootSettlement.html.twig', [
			'form'=>$form->createView(), 'settlement'=>$settlement
		]);
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
	  */
	public function disengageAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryDisengageTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

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
						$results[] = $this->get('war_manager')->createDisengage($character, $bg, $action);
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
					$results[] = $this->get('war_manager')->createDisengage($character, $bg, $character->findForcedBattles()->first());
				}
			}

			return $this->render('War/disengage.html.twig', [
				'results'=>$results
			]);
		}

		return $this->render('War/disengage.html.twig', [
			'takes'=>$this->get('war_manager')->calculateDisengageTime($character),
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/evade")
	  */
	public function evadeAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryEvadeTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createFormBuilder()
			->add('submit', 'submit', array('label'=>'military.evade.submit', 'translation_domain' => 'actions'))
			->getForm();

		$form->handleRequest($request);
		if ($form->isValid()) {
			$act = new Action;
			$act->setType('military.evade')->setCharacter($character);
			$act->setBlockTravel(false);
			$result = $this->get('action_manager')->queue($act);

			return $this->render('War/evade.html.twig', [
				'result'=>$result
			]);
		}

		return $this->render('War/evade.html.twig', [
			'form'=>$form->createView()
		]);
	}


	/**
	  * @Route("/block")
	  */
	public function blockAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryBlockTest', false, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
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
			$result = $this->get('action_manager')->queue($act);

			return $this->render('War/block.html.twig', [
				'result'=>$result
			]);
		}

		return $this->render('War/block.html.twig', [
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/damage")
	  */
	public function damageAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryDamageFeatureTest', false, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
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
			$men = 0;
			foreach ($character->getUnits() as $unit) {
				$men += $unit->getActiveSoldiers()->count();
			}
			$damage = round(rand(sqrt($men)*$hours*25, sqrt($men*2)*$hours*25)); // for 100 men, damage = 1000 - 2000 => 5-10 attacks to destroy a tower

			$act = new Action;
			$act->setType('military.damage')->setCharacter($character);
			$act->setBlockTravel(true)->setCanCancel(false);
			$complete = new \DateTime("now");
			$complete->add(new \DateInterval("PT".$hours."H"));
			$act->setComplete($complete);
			$result = $this->get('action_manager')->queue($act);

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

			return $this->render('War/damage.html.twig', [
				'result' => $result,
				'featuretype' => $target->getType(),
				'actdistance'	=>	$actdistance,
				'spotdistance'	=>	$spotdistance
			]);
		}

		return $this->render('War/damage.html.twig', [
			'features'		=> $features,
			'form'			=> $form->createView(),
			'actdistance'	=>	$actdistance,
			'spotdistance'	=>	$spotdistance
		]);
	}


	/**
	  * @Route("/nobles/attack")
	  */
	public function attackOthersAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('militaryAttackNoblesTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$result = false;

		$form = $this->createForm(new InteractionType('attack', $this->get('geography')->calculateInteractionDistance($character), $character, true, true));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();

			if (count($data['target']) == 0) {
				$form->addError(new FormError("attack.nobody"));
			} else {
				$mine = 5;
				foreach ($character->getUnits() as $unit) {
					$mine += $unit->getVisualSize();
				}
				$force = 0;
				foreach ($data['target'] as $target) {
					$force += 5;
					foreach ($target->getUnits() as $unit) {
						$force += $unit->getVisualSize();
					}
				}
				if ($mine*200<=$force) {
					# The enemy force overwhelms you and immediately captures you.
					$this->get('character_manager')->imprison($character, $target);
					$this->get('history')->logEvent(
						$character,
						'event.character.overwhelmedby',
						array('%link-character%'=>$target->getId()),
						History::HIGH, true
					);
					$this->get('history')->logEvent(
						$target,
						'event.character.overwhelmed',
						array('%link-character%'=>$character->getId()),
						History::HIGH, true
					);
				} else {
					$result = $this->get('war_manager')->createBattle($character, $character->getInsideSettlement(), null, $data['target']);
					if ($result['outside'] && $character->getInsideSettlement()) {
						// leave settlement if we attack targets outside
						$character->setInsideSettlement(null);
					}
				}
				$em->flush();
			}
		}

		return $this->render('War/attackOthers.html.twig', [
			'form'=>$form->createView(),
			'result'=>$result
		]);
	}

	/**
	  * @Route("/battles/aid")
	  */
	public function aidAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('militaryAidTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
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
			$success = $this->get('action_manager')->queue($act);
			$target = $data['target'];
		}

		return $this->render('War/aid.html.twig', [
			'form'=>$form->createView(),
			'success'=>$success,
			'target'=>$target
		]);
	}

	/**
	  * @Route("/battles/join")
	  */
	public function battleJoinAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('militaryJoinBattleTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$success = false;
		$battles = $this->get('geography')->findBattlesInActionRange($character);

		$form = $this->createForm(new BattleParticipateType($battles));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$character->setInsideSettlement(NULL);
			if (isset($data['group'])) {
				$this->get('war_manager')->joinBattle($character, $data['group']);
				$this->getDoctrine()->getManager()->flush();
				$success = $data['group']->getBattle();
			}
		}

		return $this->render('War/battleJoin.html.twig', [
			'battles'=>$battles,
			'now'=>new \DateTime("now"),
			'form'=>$form->createView(),
			'success'=>$success
		]);
	}

}
