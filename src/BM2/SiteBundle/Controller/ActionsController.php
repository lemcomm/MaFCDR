<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\KnightOffer;
use BM2\SiteBundle\Entity\Trade;
use BM2\SiteBundle\Form\AssignedSoldiersType;
use BM2\SiteBundle\Form\CultureType;
use BM2\SiteBundle\Form\EntourageRecruitType;
use BM2\SiteBundle\Form\InteractionType;
use BM2\SiteBundle\Form\KnightOfferType;
use BM2\SiteBundle\Form\RealmSelectType;
use BM2\SiteBundle\Form\SoldiersRecruitType;
use BM2\SiteBundle\Form\TradeCancelType;
use BM2\SiteBundle\Form\TradeType;
use BM2\SiteBundle\Service\Geography;
use BM2\SiteBundle\Service\History;
use BM2\SiteBundle\Service\Dispatcher;
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
     * @Template("BM2SiteBundle:Actions:actions.html.twig")
     */
	public function indexAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway(false, true);

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

		return array('pagetitle'=>$pagetitle);
	}

	/**
	  * @Route("/support", name="bm2_actionsupport")
	  * @Template
	  */
	public function supportAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway(false, true);

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

			return array(
				'action'=>$action,
				'result'=>array(
					'success' => true,
					'target' => $action->getTargetSettlement()
				)
			);
		} else {
			return array('action'=>null, 'result'=>array('success'=>false, 'message'=>'either.invalid.noid'));
		}
	}

	/**
	  * @Route("/oppose", name="bm2_actionoppose")
	  * @Template
	  */
	public function opposeAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway(false, true);


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

			return array(
				'action'=>$action,
				'result'=>array(
					'success' => true,
					'target' => $action->getTargetSettlement()
				)
			);
		} else {
			return array('action'=>null, 'result'=>array('success'=>false, 'message'=>'either.invalid.noid'));
		}
	}


	/**
	  * @Route("/enter")
	  * @Template
	  */
	public function enterAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationEnterTest', true, true);

		$result = null;
		if ($this->get('interactions')->characterEnterSettlement($character, $settlement)) {
			$result = 'entered';
		} else {
			$result = 'denied';
		}

		$this->getDoctrine()->getManager()->flush();
		return array('settlement'=>$settlement, 'result'=>$result);
	}

   /**
     * @Route("/embark")
     * @Template
     */
	public function embarkAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationEmbarkTest', true, true);

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
			return array('ships'=>true);
		} else {
			return array('dockname'=>$dock->getName());
		}
	}

   /**
     * @Route("/givegold")
     * @Template
     */
	public function giveGoldAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('locationGiveGoldTest');

		$form = $this->createForm(new InteractionType('givegold', $this->get('geography')->calculateInteractionDistance($character), $character));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();

			if ($data['amount'] > $character->getGold()) {
				throw new \Exception("you cannot give more gold than you have.");
			}
			if ($data['amount'] < 0) {
				throw new \Exception("you cannot give negative gold.");
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
			return array('success'=>true, 'amount'=>$data['amount'], 'target'=>$data['target']);
		}

		return array('form'=>$form->createView(), 'gold'=>$character->getGold());
	}

   /**
     * @Route("/giveship")
     * @Template
     */
	public function giveShipAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationGiveShipTest', true, true);

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
				return array('success'=>true);
			} else {
				// TODO: form error, but this should never happen!
			}
		}

		return array('form'=>$form->createView());
	}

	/**
	  * @Route("/spy")
	  * @Template
	  */
	public function spyAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('nearbySpyTest', true, true);


		return array('settlement'=>$settlement);
	}


	/**
	  * @Route("/take")
	  * @Template
	  */
	public function takeAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlTakeTest', true, true);

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
			$result = $this->get('action_resolution')->queue($act);

			$this->get('history')->logEvent(
				$settlement,
				'event.settlement.take.started',
				array('%link-character%'=>$character->getId()),
				History::HIGH, true, 20
			);
			$this->getDoctrine()->getManager()->flush();

			return array(
				'settlement'	=> $settlement,
				'timetotake' 	=> $time_to_take,
				'result'		=> $result
			);
		}

		return array(
			'settlement' => $settlement,
			'others' => $others,
			'timetotake' => $time_to_take,
			'limit' => $character->isTrial()?Dispatcher::FREE_ACCOUNT_ESTATE_LIMIT:-1,
			'form' => $form->createView()
		);
	}

   /**
     * @Route("/changerealm/{id}", requirements={"id"="\d+"})
     * @Template
     */
	public function changeRealmAction($id, Request $request) {
		$em = $this->getDoctrine()->getManager();
		$settlement = $em->getRepository('BM2SiteBundle:Settlement')->find($id);
		if (!$settlement) {
			throw $this->createNotFoundException('error.notfound.settlement');
		}
		$this->get('dispatcher')->setSettlement($settlement);
		$character = $this->get('dispatcher')->gateway('controlChangeRealmTest');

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
			return array('settlement'=>$settlement, 'result'=>$result, 'newrealm'=>$targetrealm);
		}
		return array('settlement'=>$settlement, 'form'=>$form->createView());
	}

   /**
     * @Route("/grant")
     * @Template
     */
	public function grantAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlGrantTest', true);

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
				if ($data['target']->isTrial() && $data['target']->getEstates()->count() >= 3) {
					$form->addError(new FormError("settlement.grant.free2"));
				} elseif ($data['target']->isNPC()) {
					$form->addError(new FormError("settlement.grant.npc"));
				} else {
					$act = new Action;
					$act->setType('settlement.grant')->setStringValue($extra)->setCharacter($character);
					$act->setTargetSettlement($settlement)->setTargetCharacter($data['target']);
					$act->setBlockTravel(true);
					// depending on size of settlement and soldiers count, this gives values roughly between
					// an hour for a small village and 10 hours for a large city with many soldiers
					$time_to_grant = round((sqrt($settlement->getPopulation()) + sqrt($settlement->getSoldiers()->count()))*3);
					$complete = new \DateTime("now");
					$complete->add(new \DateInterval("PT".$time_to_grant."M"));
					$act->setComplete($complete);
					$result = $this->get('action_resolution')->queue($act);
					return array('settlement'=>$settlement, 'result'=>$result, 'newowner'=>$data['target']);
				}
			}

		}

		return array('settlement'=>$settlement, 'form'=>$form->createView());
	}

   /**
     * @Route("/rename")
     * @Template
     */
	public function renameAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlRenameTest', true);

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
				$result = $this->get('action_resolution')->queue($act);

				return array('settlement'=>$settlement, 'result'=>$result, 'newname'=>$newname);
			}
		}

		return array('settlement'=>$settlement, 'form'=>$form->createView());
	}

   /**
     * @Route("/changeculture")
     * @Template
     */
	public function changecultureAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('controlCultureTest', true);

		$form = $this->createForm(new CultureType($character->getUser(), true, $settlement->getCulture()));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$culture=$data['culture'];
			// this is a meta action and thus executed immediately
			$settlement->setCulture($culture);
			$this->getDoctrine()->getManager()->flush();
			return array('settlement'=>$settlement, 'result'=>array('success'=>true, 'immediate'=>true), 'culture'=>$culture->getName());
		}

		return array('settlement'=>$settlement, 'form'=>$form->createView());
	}


  /**
     * @Route("/trade")
     * @Template
     */
	public function tradeAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('economyTradeTest', true);

		$em = $this->getDoctrine()->getManager();
		$resources = $em->getRepository('BM2SiteBundle:ResourceType')->findAll();

		$query = $em->createQuery('SELECT t FROM BM2SiteBundle:Trade t JOIN t.source s JOIN t.destination d WHERE (t.source=:here OR t.destination=:here) AND (s.owner=:me OR d.owner=:me)');
		$query->setParameters(array('here'=>$settlement, 'me'=>$character));
		$trades = $query->getResult();

		$trade = new Trade;

		// FIXME: to get trade permissions working, this and the code in TradeType.php need to be refactored to include permissions
		$sources = array();
		foreach ($character->getEstates() as $e) {
			$sources[] = $e->getId();
		}
		foreach ($trades as $t) {
			$sources[] = $t->getSource()->getId();
		}
		$sources = array_unique($sources);

		$form = $this->createForm(new TradeType($character, $settlement, $sources), $trade);
		$cancelform = $this->createForm(new TradeCancelType($trades, $character));

		$merchants = $character->getAvailableEntourageOfType('Merchant');

		// FIXME: check which form was submitted - code snippet to do this:
		//   194  		if ($request->isMethod('POST') && $request->request->has("charactercreation")) {
		if ($request->isMethod('POST')) {
			if ($form) {
                $form->handleRequest($request);
                if ($form->isValid()) {
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
				}
			}
			$cancelform->handleRequest($request);
			if ($cancelform->isValid()) {
				$data = $cancelform->getData();
				$trade = $data['trade'];
				$this->get('history')->logEvent(
					$trade->getDestination(),
					'event.settlement.tradestop',
					array('%amount%'=>$trade->getAmount(), '%resource%'=>$trade->getResourceType()->getName(), '%link-settlement%'=>$trade->getSource()->getId()),
					History::MEDIUM, false, 20
				);
				$em->remove($trade);
				$em->flush();
				return $this->redirect($request->getUri());
			}
		}

		$estatesdata = array();
		foreach ($character->getEstates() as $estate) {
			$tradecost = $this->get('economy')->TradeCostBetween($settlement, $estate, $merchants->count()>0);
			$estate_resources = array();
			foreach ($resources as $resource) {
				$production = $this->get('economy')->ResourceProduction($estate, $resource);
				$demand = $this->get('economy')->ResourceDemand($estate, $resource);
				$trade = $this->get('economy')->TradeBalance($estate, $resource);

				if ($production!=0 || $demand!=0 || $trade!=0) {
					$estate_resources[] = array(
						'type' => $resource,
						'production' => $production,
						'demand' => $demand,
						'trade' => $trade,
						'cost' => $tradecost
					);
				}
			}
			$estatesdata[] = array(
				'settlement' => $estate,
				'resources' => $estate_resources
			);
		}

		$local_resources = array();
		if ($settlement->getOwner() == $character) {
			// TODO: maybe require a merchant and/or prospector ?
			foreach ($resources as $resource) {
				$production = $this->get('economy')->ResourceProduction($estate, $resource);
				$demand = $this->get('economy')->ResourceDemand($estate, $resource);
				$trade = $this->get('economy')->TradeBalance($estate, $resource);

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

		return array(
			'settlement'=>$settlement,
			'owned' => ($settlement->getOwner()==$character?true:false),
			'estates' => $estatesdata,
			'local' => $local_resources,
			'trades' => $trades,
			'form' => $form->createView(),
			'cancelform' => $cancelform->createView()
		);
	}


   /**
     * @Route("/entourage")
     * @Template
     */
	public function entourageAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('personalEntourageTest', true);
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
				return array('settlement'=>$settlement, 'entourage'=>$entourage, 'form'=>$form->createView());
			}
			if ($total > $settlement->getRecruitLimit()) {
				$form->addError(new FormError($this->get('translator')->trans("recruit.entourage.toomany2", array('%max%'=>$settlement->getRecruitLimit(true)))));
				return array('settlement'=>$settlement, 'entourage'=>$entourage, 'form'=>$form->createView());
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

		return array('settlement'=>$settlement, 'entourage'=>$entourage, 'form'=>$form->createView());
	}

   /**
     * @Route("/soldiers")
     * @Template
     */
	public function soldiersAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('personalSoldiersTest', true);
		$em = $this->getDoctrine()->getManager();

		$query = $em->createQuery('SELECT COUNT(s) as number, SUM(s.training_required) AS training FROM BM2SiteBundle:Soldier s WHERE s.base = :here AND s.training_required > 0');
		$query->setParameter('here', $settlement);
		$allocated = $query->getSingleResult();

		$available = $this->get('military')->findAvailableEquipment($settlement, true);
		$form = $this->createForm(new SoldiersRecruitType($available));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$generator = $this->get('generator');

			if ($data['number'] > $settlement->getPopulation()) {
				$form->addError(new FormError("recruit.troops.toomany"));
				return array(
					'settlement'=>$settlement,
					'allocated'=>$allocated,
					'form'=>$form->createView()
				);
			}
			if ($data['number'] > $settlement->getRecruitLimit()) {
				$form->addError(new FormError($this->get('translator')->trans("recruit.troops.toomany2"), null, array('%max%'=>$settlement->getRecruitLimit(true))));
				return array(
					'settlement'=>$settlement,
					'allocated'=>$allocated,
					'form'=>$form->createView()
				);
			}

			for ($i=0; $i<$data['number']; $i++) {
				if (!$data['weapon']) {
					$form->addError(new FormError("recruit.troops.noweapon"));
					return array(
						'settlement'=>$settlement,
						'allocated'=>$allocated,
						'form'=>$form->createView()
					);
				}
			}
			$count = 0;
			$corruption = $this->get('economy')->calculateCorruption($settlement);
			for ($i=0; $i<$data['number']; $i++) {
				if ($soldier = $generator->randomSoldier($data['weapon'], $data['armour'], $data['equipment'], $settlement, $corruption)) {
					$this->get('history')->addToSoldierLog(
						$soldier, 'recruited',
						array('%link-character%'=>$character->getId(), '%link-settlement%'=>$settlement->getId(), 
							'%link-item-1%'=>$data['weapon']?$data['weapon']->getId():0, 
							'%link-item-2%'=>$data['armour']?$data['armour']->getId():0, 
							'%link-item-3%'=>$data['equipment']?$data['equipment']->getId():0
						)
					);
					$count++;
				}
			}
			// TODO: if $count < $data['number'] then some couldn't be recruited
			if ($count < $data['number']) {
				$this->addFlash('notice', $this->get('translator')->trans('recruit.troops.supply', array('%only%'=> $count, '%planned%'=>$data['number']), 'actions'));
			}

			$settlement->setPopulation($settlement->getPopulation()-$count);
			$settlement->setRecruited($settlement->getRecruited()+$count);
			$em->flush();
			return $this->redirectToRoute('bm2_site_settlement_soldiers', array('id'=>$settlement->getId()));
		}

		return array(
			'settlement'=>$settlement,
			'allocated'=>$allocated,
			'training'=>$this->get('military')->findAvailableEquipment($settlement, true),
			'soldierscount' => $settlement->getSoldiers()->count(),

			'form'=>$form->createView()
		);
	}

   /**
     * @Route("/offers")
     * @Template
     */
	public function offersAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('personalOffersTest', true);

		$form = $this->createForm(new KnightOfferType($settlement));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			if ($data['givesettlement']==false && count($data['soldiers']) < 1) {
				$form->addError(new FormError("recruit.offer.empty"));
			} else {
				$ok = true;
				if ($data['givesettlement']) {
					$already = false;
					foreach ($settlement->getKnightOffers() as $offer) {
						if ($offer->getGiveSettlement()) {
							$already = true;
							break;
						}
					}
					if ($already) {
						$ok = false;
						$form->addError(new FormError("recruit.offer.givealready"));
					}
				}
				if ($ok) {
					$em = $this->getDoctrine()->getManager();

					$offer = new KnightOffer;
					$offer->setSettlement($settlement);
					$offer->setDescription($data['intro']);
					$offer->setGiveSettlement($data['givesettlement']);
					$em->persist($offer);
					if ($data['givesettlement'] == false) {
						foreach ($data['soldiers'] as $soldier) {
							$soldier->setOfferedAs($offer);
						}
					}
					$em->flush();
					return $this->redirect($request->getUri());
				}
			}
		}

		return array(
			'settlement'=>$settlement,
			'militia'=>$settlement->getSoldiers(),
			'offers'=>$settlement->getKnightOffers(),
			'form'=>$form->createView()
		);
	}

	/**
	  * @Route("/offerdetails/{offer}")
	  * @Template
	  */
	public function offerdetailsAction(KnightOffer $offer) {
		return array('offer'=>$offer);
	}

	/**
	  * @Route("/offerdelete/{offer}")
	  * @Template
	  */
	public function offerdeleteAction(KnightOffer $offer) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('personalOffersTest', true);

		if ($offer->getSettlement() == $settlement) {
			$em = $this->getDoctrine()->getManager();
			$settlement->removeKnightOffer($offer);
			foreach ($offer->getSoldiers() as $soldier) {
				$soldier->setOfferedAs(null);
			}
			foreach ($offer->getEntourage() as $entourage) {
				$entourage->setOfferedAs(null);
			}
			$em->remove($offer);
			$em->flush();
		}

		return $this->redirectToRoute('bm2_site_actions_offers');
	}


	/**
	  * @Route("/assigned")
	  * @Template
	  */
	public function assignedAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('personalAssignedSoldiersTest');

		$form = $this->createForm(new AssignedSoldiersType($character));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();

			$cycle = $this->get('appstate')->getCycle();
			$deserting = 0;
			$returning = 0;
			$staying = 0;
			$reclaimed = array();
			$group = '(no)';

			foreach ($data['soldiers'] as $soldier) {
				if (!$soldier->isAlive()) continue; // skip dead soldiers
				// FIXME: if in battle, can't recall but there should be a message
				if ($soldier->getCharacter() && $soldier->getCharacter()->isInBattle()) continue;
				if ($soldier->getCharacter() && $soldier->getCharacter()->isDoingAction('military.regroup')) continue;
				// FIXME: can't recall from your own estates or characters, but there should also be a message:
				// if ($soldier->getCharacter() && $soldier->getCharacter()->getUser() == $character->getUser()) continue;
				// if ($soldier->getBase() && $soldier->getBase()->getOwner() && $soldier->getBase()->getOwner()->getUser() == $character->getUser()) continue;

				if ($soldier->getAssignedSince() == -1) {
					$days = 0;
				} else {
					$days = $cycle - $soldier->getAssignedSince();
				}
				if ($days<=0 || $days>=50) {
					$desert = 0;
				} else {
					if ($days <= 25) {
						$desert = round($days/1.25);
					} else {
						$desert = round((50-$days)/1.25);
					}
				}
				if ($soldier->getCharacter()) {
					if (!isset($reclaimed[$soldier->getCharacter()->getId()])) {
						$reclaimed[$soldier->getCharacter()->getId()] = array('char'=>$soldier->getCharacter(), 'total'=>0, 'stay'=>0);
					}
					$reclaimed[$soldier->getCharacter()->getId()]['total']++;
				}
				if (rand(0,99)<$desert) {
					// deserts - vanish
					$this->get('military')->disband($soldier, $soldier->getCharacter()?$soldier->getCharacter():$soldier->getBase());
					$deserting++;
				} else if (rand(0,99) < ($days*2)) {
					// stays with new lord
					$soldier->setLiege(null)->setAssignedSince(null);
					$staying++;
					if ($soldier->getCharacter()) {
						$reclaimed[$soldier->getCharacter()->getId()]['stay']++;
					}
				} else {
					// returns to liege
					$settlement = $soldier->getBase();
					$group = $this->get('military')->assign($soldier, $character);
					$soldier->setLiege(null)->setAssignedSince(null);
					// in training - interrupt that and reset equipment
					if ($soldier->getTrainingRequired() > 0) {
						if ($soldier->getOldWeapon() || $soldier->getOldArmour() || $soldier->getOldEquipment()) {
							if ($soldier->getOldWeapon() != $soldier->getWeapon()) {
								$this->get('military')->returnItem($settlement, $soldier->getWeapon());
								$soldier->setWeapon($soldier->getOldWeapon());
							}
							if ($soldier->getOldArmour() != $soldier->getArmour()) {
								$this->get('military')->returnItem($settlement, $soldier->getArmour());
								$soldier->setArmour($soldier->getOldArmour());
							}
							if ($soldier->getOldEquipment() != $soldier->getEquipment()) {
								$this->get('military')->returnItem($settlement, $soldier->getEquipment());
								$soldier->setEquipment($soldier->getOldEquipment());
							}
						}
						$soldier->setTraining(0)->setTrainingRequired(0);
					}
					$returning++;
				}
			}

			if ($returning > 0) {
				$act = new Action;
				$act->setType('military.reclaim')->setCharacter($character);
				$act->setBlockTravel(true);
				$complete = new \DateTime("now");
				$takes = 16;
				$complete->add(new \DateInterval("PT".$takes."H"));
				$act->setComplete($complete);
				$this->get('action_resolution')->queue($act);
			}

			foreach ($reclaimed as $rec) {
				$this->get('history')->logEvent(
					$rec['char'],
					'event.military.reclaimed',
					array('%link-character%'=>$character->getId(),'%count%'=>$rec['total'],'%stay%'=>$rec['stay']),
					History::MEDIUM, false, 40
				);
			}

			$em->flush();

			return array(
				'return' => $returning,
				'lost' => $deserting+$staying,
				'group' => $group
			);
		}

		return array(
			'assigned' => $character->getSoldiersGiven(),
			'form'=>$form->createView()
		);
	}


	/**
	  * @Route("/dungeons", name="bm2_dungeons"))
	  * @Template
	  */
	public function dungeonsAction() {
		$character = $this->get('dispatcher')->gateway('locationDungeonsTest');

		return array('dungeons'=>$this->get('geography')->findDungeonsInActionRange($character));
	}


}
