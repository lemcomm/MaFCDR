<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Trade;
use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\CultureType;
use BM2\SiteBundle\Form\EntourageRecruitType;
use BM2\SiteBundle\Form\InteractionType;
use BM2\SiteBundle\Form\RealmSelectType;
use BM2\SiteBundle\Form\TradeCancelType;
use BM2\SiteBundle\Form\TradeType;
use BM2\SiteBundle\Service\Geography;
use BM2\SiteBundle\Service\History;
use BM2\SiteBundle\Service\Dispatcher;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/actions")
 */
class ActionsController extends Controller {

   /**
     * @Route("/", name="bm2_actions")
     */
	public function indexAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway(false, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if ($settlement) {
			$pagetitle = $this->get('translator')->trans('settlement.title', array(
				'%type%' => $this->get('translator')->trans($settlement->getType()),
				'%name%' => $this->get('twig.extension.links')->ObjectLink($settlement) ));
		} else {
			$nearest = $this->get('geography')->findNearestSettlement($character);
			$settlement=array_shift($nearest);
			$pagetitle = $this->get('translator')->trans('settlement.area', array(
				'%name%' => $this->get('twig.extension.links')->ObjectLink($settlement) ));
		}
		# I can't think of an instnace where we'd have a siege with no groups, but just in case...
		$siege = $settlement->getSiege()?true:false;
		return $this->render('Actions/actions.html.twig', [
			'pagetitle'=>$pagetitle,
			'siege'=>$siege
		]);
	}

	/**
	  * @Route("/support", name="bm2_actionsupport")
	  */
	public function supportAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway(false, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if ($request->isMethod('POST') && $request->request->has("id")) {
			$em = $this->getDoctrine()->getManager();
			$action = $em->getRepository('BM2SiteBundle:Action')->find($request->request->get("id"));
			if (!$action) {
				return array('action'=>null, 'result'=>array('success'=>false, 'message'=>'either.invalid.wrongid'));
			}
			// validate that we can support this action!
			switch ($action->getType()) {
				case 'settlement.take': // if we could take control ourselves, we can support
					$check = $this->get('dispatcher')->controlTakeTest();
					break;
				default:
					return array('action'=>null, 'result'=>array('success'=>false, 'message'=>'either.invalid.action'));
			}
			if (!isset($check['url'])) {
				return array('action'=>null, 'result'=>array('success'=>false, 'message'=>$check['description']));
			}

			// check that we are not already opposing or supporting it
			$have = $em->getRepository('BM2SiteBundle:Action')->findBy(array('type'=>array('oppose','support'), 'character'=>$character, 'opposed_action'=>$action));
			if ($have) {
				return array('action'=>null, 'result'=>array('success'=>false, 'message'=>'either.invalid.already'));
			}

			$support = new Action;
			$support->setCharacter($character);
			$support->setType('support');
			$support->setSupportedAction($action);
			$support->setStarted(new \DateTime("now"));
			$support->setHidden(false)->setCanCancel(true);
			$support->setBlockTravel($action->getBlockTravel());
			$em->persist($support);

			// update action
			$this->get('action_resolution')->update($action);

			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('support.success.'.$action->getType(), ["%character%"=>$character->getName(), "%target"=>$action->getTargetSettlement()->getName()], 'actions'));
			return $this->redirectToRoute('bm2_actions');
		} else {
			return $this->render('Actions/support.html.twig', [
				'action'=>null,
				'result'=>[
					'success'=>false,
					'message'=>'either.invalid.noid'
				]
			]);
		}
	}

	/**
	  * @Route("/oppose", name="bm2_actionoppose")
	  */
	public function opposeAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway(false, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if ($request->isMethod('POST') && $request->request->has("id")) {
			$em = $this->getDoctrine()->getManager();
			$action = $em->getRepository('BM2SiteBundle:Action')->find($request->request->get("id"));
			if (!$action) {
				return array('action'=>null, 'result'=>array('success'=>false, 'message'=>'either.invalid.wrongid'));
			}
			// validate that we can support this action!
			switch ($action->getType()) {
				case 'settlement.take':
					break;
				default:
					return array('action'=>null, 'result'=>array('success'=>false, 'message'=>'either.invalid.action'));
			}

			// check that we are not already opposing or supporting it
			$have = $em->getRepository('BM2SiteBundle:Action')->findBy(array('type'=>array('oppose','support'), 'character'=>$character, 'opposed_action'=>$action));
			if ($have) {
				return array('action'=>null, 'result'=>array('success'=>false, 'message'=>'either.invalid.already'));
			}

			$oppose = new Action;
			$oppose->setCharacter($character);
			$oppose->setType('oppose');
			$oppose->setOpposedAction($action);
			$oppose->setStarted(new \DateTime("now"));
			$oppose->setHidden(false)->setCanCancel(true);
			$oppose->setBlockTravel($action->getBlockTravel());
			$em->persist($oppose);

			// update action
			$this->get('action_resolution')->update($action);

			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('oppose.success.'.$action->getType(), ["%character%"=>$character->getName(), "%target"=>$action->getTargetSettlement()->getName()], 'actions'));
			return $this->redirectToRoute('bm2_actions');
		} else {
			return $this->render('Actions/oppose.html.twig', [
				'action'=>null,
				'result'=>[
					'success'=>false,
					'message'=>'either.invalid.noid'
				]
			]);
		}
	}


	/**
	  * @Route("/enter")
	  */
	public function enterAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationEnterTest', true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if ($this->get('interactions')->characterEnterSettlement($character, $settlement)) {
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('location.enter.result.entered', array("%settlement%"=>$settlement->getName()), "actions"));
			return $this->redirectToRoute('bm2_actions');
		} else {
			$this->addFlash($this->get('translator')->trans('location.enter.result.denied', array("%settlement%"=>$settlement->getName()), "actions"));
			return $this->redirectToRoute('bm2_actions');
		}
	}

	/**
	  * @Route("/exit")
	  */
	public function exitAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationLeaveTest', true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if ($this->get('interactions')->characterLeaveSettlement($character)) {
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('location.exit.result.left', array("%settlement%"=>$settlement->getName()), "actions"));
			return $this->redirectToRoute('bm2_actions');
		} else {
			$this->addFlash($this->get('translator')->trans('location.exit.result.denied', array("%settlement%"=>$settlement->getName()), "actions"));
			return $this->redirectToRoute('bm2_actions');
		}
	}

	   /**
	     * @Route("/embark")
	     */
	public function embarkAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationEmbarkTest', true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$act = $this->get('geography')->calculateInteractionDistance($character);
		$embark_ship = false;

		$em = $this->getDoctrine()->getManager();
		$my_ship = $em->getRepository('BM2SiteBundle:Ship')->findOneByOwner($character);
		if ($my_ship) {
			$nearest = $this->get('geography')->findMyShip($character);
			$ship_distance = $nearest['distance'];
			if ($ship_distance <= $act) {
				$embark_ship = true;
			}
		}

		if (!$embark_ship) {
			$nearest = $this->get('geography')->findNearestDock($character);
			$dock=array_shift($nearest);
		}

		$embark = $this->get('geography')->findEmbarkPoint($character);
		$character->setLocation($embark);
		$character->setTravelAtSea(true);
		foreach ($character->getPrisoners() as $prisoner) {
			$prisoner->setLocation($embark);
			$prisoner->setTravelAtSea(true);
		}

		// remove my ship
		if ($my_ship) {
			$em->remove($my_ship);
		}

		$em->flush();

		if ($embark_ship) {
			return $this->render('Actions/embark.html.twig', [
				'ships'=>true
			]);
		} else {
			return $this->render('Actions/embark.html.twig', [
				'dockname'=>$dock->getName()
			]);
		}
	}

   /**
     * @Route("/givegold")
     */
	public function giveGoldAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('locationGiveGoldTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new InteractionType('givegold', $this->get('geography')->calculateInteractionDistance($character), $character));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();

			if ($data['amount'] > $character->getGold()) {
				throw new \Exception("You cannot give more gold than you have.");
			}
			if ($data['amount'] < 0) {
				throw new \Exception("You cannot give negative gold.");
			}
			$character->setGold($character->getGold() - $data['amount']);
			$data['target']->setGold($data['target']->getGold() + $data['amount']);

			$this->get('history')->logEvent(
				$data['target'],
				'event.character.gotgold',
				array('%link-character%'=>$character->getId(), '%amount%'=>$data['amount']),
				History::MEDIUM, true, 20
			);
			$em->flush();
			return $this->render('Actions/giveGold.html.twig', [
				'success'=>true, 'amount'=>$data['amount'], 'target'=>$data['target']
			]);
		}

		return $this->render('Actions/giveGold.html.twig', [
			'form'=>$form->createView(), 'gold'=>$character->getGold()
		]);
	}

   /**
     * @Route("/giveship")
     */
	public function giveShipAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationGiveShipTest', true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new InteractionType('giveship', $this->get('geography')->calculateInteractionDistance($character), $character));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();
			list($his_ship, $distance) = $this->get('geography')->findMyShip($data['target']);
			if ($his_ship) {
				// FIXME: this should NOT automatically remove my old ship, due to small abuse potential, but for now that's the fastest solution
				$em->remove($his_ship);
			}
			$query = $em->createQuery("SELECT s FROM BM2SiteBundle:Ship s WHERE s.owner = :me")->setParameter('me', $character);
			$ship = $query->getOneOrNullResult();
			if ($ship) {
				$ship->setOwner($data['target']);
				$current_cycle = intval($this->get('appstate')->getGlobal('cycle'));
				$this->get('history')->logEvent(
					$data['target'],
					'event.character.gotship',
					array('%link-character%'=>$character->getId(), '%remain%'=>$current_cycle-$ship->getCycle()),
					History::MEDIUM, true, 20
				);
				$em->flush();

				return $this->render('Actions/giveShip.html.twig', [
					'success'=>true
				]);
				return array('success'=>true);
			} else {
				// TODO: form error, but this should never happen!
			}
		}

		return $this->render('Actions/giveShip.html.twig', [
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/spy")
	  */
	public function spyAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('nearbySpyTest', true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Actions/spy.html.twig', [
			'settlement'=>$settlement
		]);
	}


	/**
	  * @Route("/take")
	  */
	public function takeAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlTakeTest', true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		if ($place = $character->getInsidePlace()) {
			#Only taverns will pass this check, so we know what is going on here.
			if ($place->getType()->getName() === 'tavern') {
				$type = 'tavern';
			} else {
				$type = 'inn';
			}
			return $this->render('Actions/takeTavern.html.twig', [
				'type' => $type,
				'char' => $char,
				'settlement' => $settlement,
				'place'=> $place,
				'morale' => rand(0,25)
			]);

		}

		$realms = $character->findRealms();
		if ($realms->isEmpty()) {
			$form = $this->createFormBuilder()
						->add('submit', 'submit', array('label'=>$this->get('translator')->trans('control.take.submit', array(), "actions")))
						->getForm();
		} else {
			$form = $this->createForm(new RealmSelectType($realms, 'take'));
		}

		// TODO: select war here as well?

		$others = $settlement->getRelatedActions()->filter(
			function($entry) {
				return ($entry->getType()=='settlement.take');
			}
		);

		$time_to_take = $settlement->getTimeToTake($character);

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			if (isset($data['target'])) {
				$targetrealm = $data['target'];
			} else {
				$targetrealm = null;
			}

			$act = new Action;
			$act->setType('settlement.take')->setCharacter($character);
			$act->setTargetSettlement($settlement)->setTargetRealm($targetrealm);
			$act->setBlockTravel(true);
			$complete = new \DateTime("now");
			$complete->add(new \DateInterval("PT".$time_to_take."S"));
			$act->setComplete($complete);
			$result = $this->get('action_manager')->queue($act);

			$this->get('history')->logEvent(
				$settlement,
				'event.settlement.take.started',
				array('%link-character%'=>$character->getId()),
				History::HIGH, true, 20
			);
			if ($owner = $settlement->getOwner()) {
				$this->get('history')->logEvent(
					$owner,
					'event.character.take.start',
					array('%link-character%'=>$character->getId(), '%link-settlement'=>$settlement->getId()),
					History::HIGH, false, 20
				);
			}
			if ($steward = $settlement->getSteward()) {
				$this->get('history')->logEvent(
					$steward,
					'event.character.take.start2',
					array('%link-character%'=>$character->getId(), '%link-settlement'=>$settlement->getId()),
					History::HIGH, false, 20
				);
			}
			foreach ($settlement->getVassals() as $vassal) {
				$this->get('history')->logEvent(
					$vassal,
					'event.character.take.start3',
					array('%link-character%'=>$character->getId(), '%link-settlement'=>$settlement->getId()),
					History::HIGH, false, 20
				);
			}
			$this->getDoctrine()->getManager()->flush();
			$endTime = new \DateTime("+ ".$time_to_take." Seconds");

			if ($result) {
				$this->addFlash('notice', $this->get('translator')->trans('event.settlement.take.start', ["%time%"=>$endTime->format('Y-M-d H:i:s')], 'communication'));
				return $this->redirectToRoute('bm2_actions');
			}
		}

		return $this->render('Actions/take.html.twig', [
			'settlement' => $settlement,
			'others' => $others,
			'timetotake' => $time_to_take,
			'limit' => -1,
			'form' => $form->createView()
		]);
	}

   /**
     * @Route("/changerealm/{id}", requirements={"id"="\d+"})
     */
	public function changeRealmAction(Settlement $id, Request $request) {
		$character = $this->get('dispatcher')->gateway('controlChangeRealmTest', false, true, false, $id);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$settlement = $id;

		$form = $this->createForm(new RealmSelectType($character->findRealms(), 'changerealm'));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$targetrealm = $data['target'];

			if ($settlement->getRealm() == $targetrealm) {
				$result = array(
					'success'=>false,
					'message'=>'control.changerealm.fail.same'
				);
			} else {
				$result = array(
					'success'=>true
				);

				$oldrealm = $settlement->getRealm();
				$this->get('politics')->changeSettlementRealm($settlement, $targetrealm, 'change');
				$this->getDoctrine()->getManager()->flush();

				if ($oldrealm) {
					$realms = $character->findRealms();
					if (!$realms->contains($oldrealm)) {
						$result['leaving'] = $oldrealm;
					}
				}
			}

			return $this->render('Actions/changeRealm.html.twig', [
				'settlement'=>$settlement,
				'result'=>$result,
				'newrealm'=>$targetrealm
			]);
		}

		return $this->render('Actions/changeRealm.html.twig', [
			'settlement'=>$settlement,
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/grant")
	  */
	public function grantAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlGrantTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new InteractionType(
			$settlement->getRealm()?'grant':'grant2',
			$this->get('geography')->calculateInteractionDistance($character), $character)
		);

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$result = array(
				'success'=>true
			);
			if ($settlement->getRealm() && $data['withrealm']==false) {
				$extra = 'clear_realm';
			} else {
				$extra = '';
			}
			if ($data['keepclaim']==true) {
				$extra.="/keep_claim";
			}

			if ($data['target']) {
				if ($data['target']->isNPC()) {
					$form->addError(new FormError("settlement.grant.npc"));
				} else {
					$act = new Action;
					$act->setType('settlement.grant')->setStringValue($extra)->setCharacter($character);
					$act->setTargetSettlement($settlement)->setTargetCharacter($data['target']);
					$act->setBlockTravel(true);
					// depending on size of settlement and soldiers count, this gives values roughly between
					// an hour for a small village and 10 hours for a large city with many soldiers
					$soldiers = 0;
					foreach ($settlement->getUnits() as $unit) {
						$soldiers += $unit->getSoldiers()->count();
					}
					$time_to_grant = round((sqrt($settlement->getPopulation()) + sqrt($soldiers))*3);
					$complete = new \DateTime("now");
					$complete->add(new \DateInterval("PT".$time_to_grant."M"));
					$act->setComplete($complete);
					$result = $this->get('action_manager')->queue($act);

					return $this->render('Actions/grant.html.twig', [
						'settlement'=>$settlement,
						'result'=>$result,
						'newowner'=>$data['target']
					]);
				}
			}

		}

		return $this->render('Actions/grant.html.twig', [
			'settlement'=>$settlement,
			'form'=>$form->createView()
		]);
	}

   /**
     * @Route("/steward", name="maf_actions_steward")
     */
	public function stewardAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlStewardTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new InteractionType(
			'steward',
			$this->get('geography')->calculateInteractionDistance($character),
			$character,
			false, false, false
		));

		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			if ($data['target'] != $character) {
				$settlement->setSteward($data['target']);

				if ($data['target']) {
					$this->get('history')->logEvent(
						$settlement,
						'event.settlement.steward',
						array('%link-character%'=>$data['target']->getId()),
						History::MEDIUM, true, 20
					);
				} else {
					$this->get('history')->logEvent(
						$settlement,
						'event.settlement.nosteward',
						array(),
						History::MEDIUM, true, 20
					);
				}
				$this->get('history')->logEvent(
					$data['target'],
					'event.character.steward',
					array('%link-settlement%'=>$settlement->getId()),
					History::MEDIUM, true, 20
				);
				$this->addFlash('notice', $this->get('translator')->trans('control.steward.success', ["%name%"=>$data['target']->getName()], 'actions'));
				$this->getDoctrine()->getManager()->flush();
				return $this->redirectToRoute('bm2_actions');
			}

		}

		return $this->render('Actions/steward.html.twig', [
			'settlement'=>$settlement,
			'form'=>$form->createView()
		]);
	}

   /**
     * @Route("/rename")
     */
	public function renameAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlRenameTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createFormBuilder(null, array('translation_domain'=>'actions', 'attr'=>array('class'=>'wide')))
			->add('name', 'text', array(
				'required'=>true,
				'label'=>'control.rename.newname',
				))
			->add('submit', 'submit', array(
				'label'=>'control.rename.submit',
				))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$newname=$data['name'];

			if (strlen($newname) < 3 ) {
				$form->addError(new FormError("settlement.rename.tooshort"));
			} else {
				$act = new Action;
				$act->setType('settlement.rename')->setCharacter($character);
				$act->setTargetSettlement($settlement)->setStringValue($newname);
				$act->setBlockTravel(true);
				$complete = new \DateTime("now");
				$complete->add(new \DateInterval("PT6H"));
				$act->setComplete($complete);
				$result = $this->get('action_manager')->queue($act);

				return $this->render('Actions/rename.html.twig', [
					'settlement'=>$settlement,
					'result'=>$result,
					'newname'=>$newname
				]);
			}
		}

		return $this->render('Actions/rename.html.twig', [
			'settlement'=>$settlement,
			'form'=>$form->createView()
		]);
	}

   /**
     * @Route("/changeculture")
     */
	public function changecultureAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlCultureTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new CultureType($character->getUser(), true, $settlement->getCulture()));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$culture=$data['culture'];
			// this is a meta action and thus executed immediately
			$settlement->setCulture($culture);
			$this->getDoctrine()->getManager()->flush();

			return $this->render('Actions/changeculture.html.twig', [
				'settlement'=>$settlement,
				'result'=>[
					'success'=>true,
					'immediate'=>true
				],
				'culture'=>$culture->getName()
			]);
		}

		return $this->render('Actions/changeculture.html.twig', [
			'settlement'=>$settlement,
			'form'=>$form->createView()
		]);
	}


  /**
     * @Route("/trade")
     */
	public function tradeAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('economyTradeTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		$resources = $em->getRepository('BM2SiteBundle:ResourceType')->findAll();

		/*
		The lines below this comment exist to check if a given character is not the owner but has owner-level trade access to this settlement.
		Because we'd have to build the owned settlements list for the foreach after these we just build it ourselves first, check if we have not-owner trade rights,
		add the local settlement if we do, and move on.

		Technically speaking, it'd also be possible to get all lists a character is on that grant them trade rights, and also build that into this,
		but that means people have even less they have to travel for in game, so no. If you own it, fine. If you only have permission to it, you have to travel to each.
		*/
		$manageable = new ArrayCollection();
		$sources = [];
		foreach ($character->getOwnedSettlements() as $owned) {
			if (!$owned->getOccupier() && !$owned->getOccupant()) {
				$manageable->add($owned);
				$sources[] = $owned->getId();
			}
		}
		foreach ($character->getStewardingSettlements() as $stewarded) {
			if (!$manageable->contains($stewarded) && !$stewarded->getOccupier() && !$stewarded->getOccupant()) {
				$manageable->add($stewarded);
			}
			$sources[] = $stewarded->getId();
		}
		$permission = $this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'trade', true);
		# permission[0] returns true or false depending on if they have permission by any means.
		if ($permission[0]) {
			$allowed = true;
			if ($permission[2] != 'owner') {
				if (!$manageable->contains($settlement)) {
					$manageable->add($settlement);
				}
				$sources[] = $settlement->getId();
			}
		} else {
			$allowed = false;
		}

		# This is here so we can abuse the fact that we know if we have permissions or not already.
		if ($allowed) {
			$query = $em->createQuery('SELECT t FROM BM2SiteBundle:Trade t JOIN t.source s JOIN t.destination d WHERE (t.source=:here OR t.destination=:here)');
			$query->setParameters(array('here'=>$settlement));
		} else{
			$query = $em->createQuery('SELECT t FROM BM2SiteBundle:Trade t JOIN t.source s JOIN t.destination d WHERE (t.source=:here OR t.destination=:here) AND (s.owner=:me OR d.owner=:me)');
			$query->setParameters(array('here'=>$settlement, 'me'=>$character));
		}
		$trades = $query->getResult();

		$trade = new Trade;

		$sources = array_unique($sources);
		$dests = $sources;
		if (!$permission) {
			# No permission, can't source here.
			$key = array_search($settlement->getId(), $sources); #Find this settlement ID,
			unset($sources[$key]); #Remove it.
		} else {
			# If we do have permission, we can send back to anyone sending us stuff. Add their IDs.
			foreach ($trades as $t) {
				$dests[] = $t->getSource()->getId();
			}

			# Add law based destinations.
			foreach ($character->findRealms() as $realm) {
				foreach ($this->get('law_manager')->findTaxLaws($realm) as $law) {
					if ($law->getSettlement()) {
						$dests[] = $law->getSettlement()->getId();
					}
				}
			}
			# Remove duplicates.
			$dests = array_unique($dests);
		}

		$form = $this->createForm(new TradeType($character, $settlement, $sources, $dests, $allowed), $trade);
		$cancelform = $this->createForm(new TradeCancelType($trades, $character));

		$merchants = $character->getAvailableEntourageOfType('Merchant');

		// FIXME: check which form was submitted - code snippet to do this:
		//   194  		if ($request->isMethod('POST') && $request->request->has("charactercreation")) {
		if ($request->isMethod('POST') && $request->request->has('trade')) {
	                $form->handleRequest($request);
                	if ($form->isValid()) {
				if ($manageable->contains($trade->getSource())) {
					if ($trade->getAmount()>0) {
						if ($trade->getSource()!=$settlement && $trade->getDestination()!=$settlement) {
							$form->addError(new FormError("trade.allremote"));
						} elseif ($trade->getSource()==$trade->getDestination()) {
							$form->addError(new FormError("trade.same"));
						} else {
							// TODO: check if we don't already have such a deal (same source, destination and resource)
							// FIXME: $trade->getResourceType() is NULL sometimes, causing an error here?
							$available = $this->get('economy')->ResourceProduction($trade->getSource(), $trade->getResourceType()) + $this->get('economy')->TradeBalance($trade->getSource(), $trade->getResourceType());
							if ($trade->getAmount() > $available) {
								$form->addError(new FormError("trade.toomuch"));
							} else {
								$trade->setTradecost($this->get('economy')->TradeCostBetween($trade->getSource(), $trade->getDestination(), $merchants->count()>0));
								if ($merchants->count() > 0 ) {
									// remove a merchant!
									$stay = $merchants->first();
									$em->remove($stay);
								}
								$em->persist($trade);
								$em->flush();
								return $this->redirect($request->getUri());
							}
						}
					}
				} else {
					$form->addError(new FormError("trade.notmanaged"));
				}
			}
		} elseif ($request->isMethod('POST') && $request->request->has('tradecancel')) {
			$cancelform->handleRequest($request);
			if ($cancelform->isValid()) {
				$data = $cancelform->getData();
				$trade = $data['trade'];
				$source = $trade->getSource();
				$dest = $trade->getDestination();
				if (($allowed && ($source == $settlement || $dest == $settlement)) || (($dest->getOwner() == $character || $dest->getSteward() == $character) || ($source->getOwner() == $character || $dest->getSteward() == $character))) {
					$this->get('history')->logEvent(
						$trade->getDestination(),
						'event.settlement.tradestop',
						array('%amount%'=>$trade->getAmount(), '%resource%'=>$trade->getResourceType()->getName(), '%link-settlement%'=>$trade->getSource()->getId()),
						History::MEDIUM, false, 20
					);
					$em->remove($trade);
					$em->flush();
					return $this->redirect($request->getUri());
				} else {
					$form->addError(new FormError("trade.notyourtrade"));
				}
			}
		}

		$settlementsdata = array();
		foreach ($manageable as $other) {
			$tradecost = $this->get('economy')->TradeCostBetween($settlement, $other, $merchants->count()>0);
			$settlement_resources = array();
			foreach ($resources as $resource) {
				$production = $this->get('economy')->ResourceProduction($other, $resource);
				$demand = $this->get('economy')->ResourceDemand($other, $resource);
				$trade = $this->get('economy')->TradeBalance($other, $resource);

				if ($production!=0 || $demand!=0 || $trade!=0) {
					$settlement_resources[] = array(
						'type' => $resource,
						'production' => $production,
						'demand' => $demand,
						'trade' => $trade,
						'cost' => $tradecost
					);
				}
			}
			$settlementsdata[] = array(
				'settlement' => $other,
				'resources' => $settlement_resources
			);
		}

		$local_resources = array();
		if ($settlement->getOwner() == $character || $settlement->getSteward() == $character || $permission[0]) {
			// TODO: maybe require a merchant and/or prospector ?
			foreach ($resources as $resource) {
				$production = $this->get('economy')->ResourceProduction($settlement, $resource);
				$demand = $this->get('economy')->ResourceDemand($settlement, $resource);
				$trade = $this->get('economy')->TradeBalance($settlement, $resource);

				if ($production!=0 || $demand!=0 || $trade!=0) {
					$local_resources[] = array(
						'type' => $resource,
						'production' => $production,
						'demand' => $demand,
						'trade' => $trade,
						'cost' => $tradecost
					);
				}
			}
		}


		return $this->render('Actions/trade.html.twig', [
			'settlement'=>$settlement,
			'owned' => $permission[0],
			'settlements' => $settlementsdata,
			'local' => $local_resources,
			'trades' => $trades,
			'form' => $form->createView(),
			'cancelform' => $cancelform->createView()
		]);
	}


   /**
     * @Route("/entourage")
     */
	public function entourageAction(Request $request) {
		list($character, $settlement) = $this->get('unit_dispatcher')->gateway('personalEntourageTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		$query = $em->createQuery('SELECT e as type, p as provider FROM BM2SiteBundle:EntourageType e LEFT JOIN e.provider p LEFT JOIN p.buildings b
			WHERE p.id IS NULL OR (b.settlement=:here AND b.active=true)');
		$query->setParameter('here', $settlement);
		$entourage = $query->getResult();

		$form = $this->createForm(new EntourageRecruitType($entourage));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$generator = $this->get('generator');

			$total = 0;
			foreach ($data['recruits'] as $id=>$amount) {
				if ($amount>0) { $total+= $amount; }
			}
			if ($total > $settlement->getPopulation()) {
				$form->addError(new FormError("recruit.entourage.toomany"));
				return $this->render('Actions/entourage.html.twig', [
					'settlement'=>$settlement,
					'entourage'=>$entourage,
					'form'=>$form->createView()
				]);
			}
			if ($total > $settlement->getRecruitLimit()) {
				$form->addError(new FormError($this->get('translator')->trans("recruit.entourage.toomany2", array('%max%'=>$settlement->getRecruitLimit(true)))));
				return $this->render('Actions/entourage.html.twig', [
					'settlement'=>$settlement,
					'entourage'=>$entourage,
					'form'=>$form->createView()
				]);
			}

			foreach ($data['recruits'] as $id=>$amount) {
				if ($amount>0) {
					$fail = 0;
					$type = $em->getRepository('BM2SiteBundle:EntourageType')->find($id);
					if (!$type) { /* TODO: throw exception */}

					// TODO: use the resupply limit we already display
					for ($i=0;$i<$amount;$i++) {
						$trainer = $settlement->getBuildingByType($type->getProvider());
						if (!$trainer) {
							new \Exception("invalid trainer");
						}
						if ($trainer->getResupply() < $type->getTraining()) {
							$fail++;
						} else {
							$servant = $generator->randomEntourageMember($type, $settlement);
							$servant->setCharacter($character);
							$character->addEntourage($servant);
							$servant->setAlive(true);

							$trainer->setResupply($trainer->getResupply() - $type->getTraining());
						}
					}
					$settlement->setPopulation($settlement->getPopulation()-$amount);
					if ($fail > 0) {
						$this->addFlash('notice', $this->get('translator')->trans('recruit.entourage.supply', array('%only%'=> ($amount-$fail), '%planned%'=>$amount, '%type%'=>$this->get('translator')->transchoice('npc.'.$type->getName(), ($amount-$fail))), 'actions'));
					} else {
						$this->addFlash('notice', $this->get('translator')->trans('recruit.entourage.success', array('%number%'=> $amount, '%type%'=>$this->get('translator')->transchoice('npc.'.$type->getName(), $amount)), 'actions'));
					}
				}
			}
			$settlement->setRecruited($settlement->getRecruited()+$total);
			$em->flush();
			$this->get('appstate')->setSessionData($character); // update, because maybe we changed our entourage count

			return $this->redirect($request->getUri());
		}

		return $this->render('Actions/entourage.html.twig', [
			'settlement'=>$settlement,
			'entourage'=>$entourage,
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/dungeons", name="bm2_dungeons")
	  */
	public function dungeonsAction() {
		$character = $this->get('dispatcher')->gateway('locationDungeonsTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Actions/dungeons.html.twig', [
			'dungeons'=>$this->get('geography')->findDungeonsInActionRange($character)
		]);
	}

	/**
	  * @Route("/changeoccupant", name="maf_settlement_occupant")
	  */
	public function changeOccupantAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlChangeOccupantTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new InteractionType('occupier',
			$this->get('geography')->calculateInteractionDistance($character),
			$character
		));

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$result = array(
				'success'=>true
			);
			if ($data['target']) {
				$act = new Action;
				$act->setType('settlement.occupant')->setCharacter($character);
				$act->setTargetSettlement($settlement)->setTargetCharacter($data['target']);
				$act->setBlockTravel(true);
				$time_to_grant = round((sqrt($settlement->getPopulation()) + sqrt($soldiers))*3);
				$complete = new \DateTime("+2 hours");
				$act->setComplete($complete);
				$result = $this->get('action_manager')->queue($act);
				$this->addFlash('notice', $this->get('translator')->trans('event.settlement.occupant.start', ["%time%"=>$complete->format('Y-M-d H:i:s')], 'communication'));
				return $this->redirectToRoute('bm2_actions');
			}
		}

		return $this->render('Settlement/occupant.html.twig', [
			'settlement'=>$settlement, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/changeoccupier", name="maf_settlement_occupier")
	  */
	public function changeOccupierAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlChangeOccupierTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		$this->get('dispatcher')->setSettlement($settlement);

		$form = $this->createForm(new RealmSelectType($character->findRealms(), 'changeoccupier'));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$targetrealm = $data['target'];

			if ($settlement->getOccupier() == $targetrealm) {
				$result = 'same';
			} else {
				$result = 'success';
				$this->get('politics')->changeSettlementOccupier($character, $settlement, $targetrealm);
				$this->getDoctrine()->getManager()->flush();
			}
			$this->addFlash('notice', $this->get('translator')->trans('event.settlement.occupier.'.$result, [], 'communication'));
			return $this->redirectToRoute('bm2_actions');
		}
		return $this->render('Settlement/occupier.html.twig', [
			'settlement'=>$settlement, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/occupation/start", name="maf_settlement_occupation_start")
	  */
	public function occupationStartAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlOccupationStartTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$form = $this->createForm(new RealmSelectType($character->findRealms(), 'occupy'));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$targetrealm = $data['target'];

			$result = $this->get('politics')->changeSettlementOccupier($character, $settlement, $targetrealm);
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('event.settlement.occupier.start', [], 'communication'));
			return $this->redirectToRoute('bm2_actions');
		}
		return $this->render('Settlement/occupationstart.html.twig', [
			'settlement'=>$settlement, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/occupation/end", name="maf_settlement_occupation_end")
	  */
	public function occupationEndAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlOccupationEndTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new AreYouSureType());
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
			$type = 'manual';
			if ($character !== $settlement->getOccupant()) {
				$type = 'forced';
			}
                        $this->get('politics')->endOccupation($settlement, $type, false, $character);
			$this->getDoctrine()->getManager()->flush();
                        $this->addFlash('notice', $this->get('translator')->trans('control.occupation.ended', array(), 'actions'));
                        return $this->redirectToRoute('bm2_actions');
                }
		return $this->render('Settlement/occupationend.html.twig', [
			'settlement'=>$settlement, 'form'=>$form->createView()
		]);
	}
}
