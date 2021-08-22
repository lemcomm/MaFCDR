<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Soldier;
use BM2\SiteBundle\Entity\Unit;
use BM2\SiteBundle\Entity\UnitSettings;

use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\CharacterSelectType;
use BM2\SiteBundle\Form\SoldiersRecruitType;
use BM2\SiteBundle\Form\SoldiersManageType;
use BM2\SiteBundle\Form\UnitRebaseType;
use BM2\SiteBundle\Form\UnitSettingsType;
use BM2\SiteBundle\Form\UnitSoldiersType;

use BM2\SiteBundle\Service\GameRequestManager;
use BM2\SiteBundle\Service\History;
use BM2\SiteBundle\Service\MilitaryManager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UnitController extends Controller {

        private function findUnits(Character $character) {
                $em = $this->getDoctrine()->getManager();
                $pm = $this->get('permission_manager');
                $settlement = $character->getInsideSettlement();
                if ($settlement && ($pm->checkSettlementPermission($settlement, $character, 'units'))) {
                        $query = $em->createQuery('SELECT u FROM BM2SiteBundle:Unit u JOIN BM2SiteBundle:UnitSettings s WHERE u.character = :char OR u.settlement = :settlement OR (u.marshal = :char AND u.settlement = :settlement) ORDER BY s.name ASC');
                        $query->setParameters(array('char'=>$character, 'settlement'=>$character->getInsideSettlement()));
                } elseif ($character->getInsideSettlement()) {
                        $query = $em->createQuery('SELECT u FROM BM2SiteBundle:Unit u JOIN BM2SiteBundle:UnitSettings s WHERE u.character = :char OR (u.marshal = :char AND u.settlement = :settlement) ORDER BY s.name ASC');
                        $query->setParameters(array('char'=>$character, 'settlement'=>$character->getInsideSettlement()));
                } else {
                        $query = $em->createQuery('SELECT u FROM BM2SiteBundle:Unit u JOIN BM2SiteBundle:UnitSettings s WHERE u.character = :char ORDER BY s.name ASC');
                        $query->setParameter('char', $character);
                }
                return $query->getResult();
        }

        private function findMarshalledUnits(Character $character) {
                $em = $this->getDoctrine()->getManager();
                if ($character->getInsideSettlement()) {
                        $query = $em->createQuery('SELECT u FROM BM2SiteBundle:Unit u JOIN BM2SiteBundle:UnitSettings s WHERE u.marshal = :char AND u.settlement = :settlement ORDER BY s.name ASC');
                        $query->setParameters(array('char'=>$character, 'settlement'=>$character->getInsideSettlement()));
                        return $query->getResult();
                } else {
                        return null;
                }
        }

        /**
          * @Route("/units", name="maf_units")
          */

        public function indexAction(Request $request) {
                $character = $this->get('dispatcher')->gateway('personalAssignedUnitsTest');
                if (! $character instanceof Character) {
                        return $this->redirectToRoute($character);
                }
                $em = $this->getDoctrine()->getManager();
                $pm = $this->get('permission_manager');

                $all = $this->findUnits($character);
                $units = [];
                foreach ($all as $each) {
                        $id = $each->getId();
                        $units[$id] = [];
                        $units[$id]['obj'] = $each;
                        $settlement = $each->getSettlement();
                        if (!$settlement || ($settlement == $character->getInsideSettlement() && $pm->checkSettlementPermission($settlement, $character, 'units')))  {
                                $units[$id]['owner'] = true;
                        } else {
                                $units[$id]['owner'] = false;
                        }
                        if ($settlement) {
                                $units[$id]['base'] = true;
                        } else {
                                $units[$id]['base'] = false;
                        }
                        if ($each->getMarshal() == $character) {
                                $units[$id]['marshal'] = true;
                        } else {
                                $units[$id]['marshal'] = false;
                        }
                        if ($each->getCharacter() == $character) {
                                $units[$id]['mine'] = true;
                        } else {
                                $units[$id]['mine'] = false;
                        }
                }

                return $this->render('Unit/units.html.twig', [
                        'units' => $units,
                        'character' => $character
                ]);
        }

        /**
          * @Route("/units/{unit}", name="maf_units_info", requirements={"unit"="\d+"})
          */

        public function infoAction(Unit $unit) {
                $character = $this->get('dispatcher')->gateway('unitInfoTest');
                if (! $character instanceof Character) {
                        return $this->redirectToRoute($character);
                }

                return $this->render('Unit/info.html.twig', [
                        'unit' => $unit,
                        'char' => $character
                ]);
        }

        /**
          * @Route("/units/new", name="maf_unit_new")
          */

        public function createAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('unitNewTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
                $pm = $this->get('permission_manager');
                $settlements = $this->get('game_request_manager')->getAvailableFoodSuppliers($character);
                $here = $character->getInsideSettlement();
                if ($pm->checkSettlementPermission($here, $character, 'units')) {
                        $settlements[] = $here->getId();
                }

                $form = $this->createForm(new UnitSettingsType($character, true, $settlements, null, true));

                $form->handleRequest($request);
                if ($form->isValid()) {
                        $data = $form->getData();
                        if (in_array($data['supplier']->getId(), $settlements)) {
                                $unit = $this->get('military_manager')->newUnit($character, $character->getInsideSettlement(), $data);
                                if ($unit) {
                                        $this->addFlash('notice', $this->get('translator')->trans('unit.manage.created', array(), 'actions'));
                                        return $this->redirectToRoute('maf_units');
                                }
                        } else {
                                $this->addFlash('error', $this->get('translator')->trans('unit.manage.supplierinvalid', [], 'actions'));
                        }
                }

                return $this->render('Unit/create.html.twig', [
                        'form'=>$form->createView()
                ]);
        }

        /**
	  * @Route("/units/{unit}/manage", name="maf_unit_manage", requirements={"unit"="\d+"})
	  */

        public function unitManageAction(Request $request, Unit $unit) {
		$character = $this->get('dispatcher')->gateway('unitManageTest', false, true, false, $unit);
                # Distpatcher->getTest('test', default, default, default, UnitId)
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

                /*
                Character -> Lead units
                Lord -> Local units and lead units
                */
                $lord = false;
                if ($character->getInsideSettlement() && ($character == $character->getInsideSettlement()->getOwner() || $character == $character->getInsideSettlement()->getSteward())) {
                        $lord = true;
                }

                $settlements = $this->get('game_request_manager')->getAvailableFoodSuppliers($character);
                $form = $this->createForm(new UnitSettingsType($character, true, $settlements, $unit->getSettings(), $lord));

                $form->handleRequest($request);
                if ($form->isValid()) {
                        $data = $form->getData();
                        $success = $this->get('military_manager')->updateSettings($unit, $data, $character);
                        if ($success) {
                                $this->addFlash('notice', $this->get('translator')->trans('unit.manage.success', array(), 'actions'));
                                return $this->redirectToRoute('maf_units');
                        } else {
                                $this->addFlash('error', $this->get('translator')->trans('unit.manage.failed', array(), 'actions'));
                        }
                }

                return $this->render('Unit/manage.html.twig', [
                        'form'=>$form->createView()
                ]);
        }

	/**
	  * @Route("/units/{unit}/soldiers", name="maf_unit_soldiers", requirements={"unit"="\d+"})
	  */
	public function unitSoldiersAction(Request $request, Unit $unit) {
                $character = $this->get('dispatcher')->gateway('unitSoldiersTest', false, true, false, $unit);
                if (! $character instanceof Character) {
                        return $this->redirectToRoute($character);
                }

		$em = $this->getDoctrine()->getManager();
                $settlement=$character->getInsideSettlement();
		$resupply=array();
		$training=array();
                $units=false;
                $canResupply=false;
                $canRecruit=false;
                $canReassign=false;
                $hasUnitsPerm=false;

		if ($settlement) {
                        # If we can manage units, we can reassign and resupply. Build the list.
                        if ($this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'units')) {
                                foreach ($settlement->getUnits() as $each) {
                                        if (!$each->getCharacter() && !$each->getPlace()) {
                                                $units[] = $each;
                                        }
                                }
                                if ($unit->getSettlement() == $settlement) {
                                        $hasUnitsPerm = true;
                                        $canRecruit = true;
        				$training = $this->get('military_manager')->findAvailableEquipment($settlement, true);
                                }
                                $canResupply = true;
				$resupply = $this->get('military_manager')->findAvailableEquipment($settlement, false);
                        }

                        # If the unit has a settlement and either they are commanded by someone or not under anyones command (and thus in it).
			if (!$canResupply || $settlement == $unit->getSettlement() || $this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'resupply')) {
                                $canResupply = true;
				$resupply = $this->get('military_manager')->findAvailableEquipment($settlement, false);
			}
			if (!$canRecruit || $unit->getSettlement() == $settlement && $this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'recruit')) {
                                $canRecruit = true;
				$training = $this->get('military_manager')->findAvailableEquipment($settlement, true);
			}

		} else {
			foreach ($character->getEntourage() as $entourage) {
				if ($entourage->getEquipment()) {
					$item = $entourage->getEquipment()->getId();
					if (!isset($resupply[$item])) {
						$resupply[$item] = array('item'=>$entourage->getEquipment(), 'resupply'=>0);
					}
					$resupply[$item]['resupply'] += $entourage->getSupply();
				}
			}
		}

                # Check if we can also handle our own units.
                foreach ($character->getUnits() as $mine) {
                        if (!$mine->getSettlement() || ($mine->getSettlement() && $this->get('permission_manager')->checkSettlementPermission($mine->getSettlement(), $character, 'units'))) {
                                $units[] = $mine;
                        }
                }

                if ($units) {
                        $canReassign = true;
                }

		$form = $this->createForm(new UnitSoldiersType($em, $unit->getActiveSoldiers(), $resupply, $training, $units, $settlement, $canReassign, $unit, $character, $hasUnitsPerm));

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			list($success, $fail) = $this->get('military_manager')->manageUnit($unit->getSoldiers(), $data, $settlement, $character, $canResupply, $canRecruit, $canReassign);
			// TODO: notice with result

			$em = $this->getDoctrine()->getManager();
			$em->flush();
			$this->get('appstate')->setSessionData($character); // update, because maybe we changed our soldiers count
			return $this->redirect($request->getUri());
		}

                return $this->render('Unit/soldiers.html.twig', [
			'soldiers' => $unit->getActiveSoldiers(),
			'recruits' => $unit->getRecruits(),
			'resupply' => $resupply,
			'training' => $training,
			'form' => $form->createView(),
                        'unit' => $unit,
                ]);
	}

	/**
	  * @Route("/units/{unit}/cancel/{recruit}", name="maf_recruit_cancel", requirements={"unit"="\d+", "recruit"="\d+"})
	  */
        public function cancelTrainingAction(Request $request, Unit $unit, Soldier $recruit) {
                list($character, $settlement) = $this->get('dispatcher')->gateway('unitCancelTrainingTest', true, true, false, $unit);
                if (! $character instanceof Character) {
                	return $this->redirectToRoute($character);
                }

                $em = $this->getDoctrine()->getManager();
                if (!$recruit->isRecruit() || $recruit->getHome()!=$settlement) {
                	throw $this->createNotFoundException('error.notfound.recruit');
                }

                // return his equipment to the stockpile:
                if ($recruit->getOldWeapon() && $recruit->getWeapon() != $recruit->getOldWeapon()) {
                        $this->get('military_manager')->returnItem($settlement, $recruit->getWeapon());
                }
                if ($recruit->getOldArmour() && $recruit->getArmour() != $recruit->getOldArmour()) {
                        $this->get('military_manager')->returnItem($settlement, $recruit->getArmour());
                }
                if ($recruit->getOldEquipment() && $recruit->getEquipment() != $recruit->getOldEquipment()) {
                        $this->get('military_manager')->returnItem($settlement, $recruit->getEquipment());
                }

                if ($recruit->getOldWeapon() || $recruit->getOldArmour() || $recruit->getOldEquipment()) {
                	// old soldier - return to militia with his old stuff
                	$recruit->setWeapon($recruit->getOldWeapon());
                	$recruit->setArmour($recruit->getOldArmour());
                	$recruit->setEquipment($recruit->getOldEquipment());
                	$recruit->setTraining(0)->setTrainingRequired(0);
                	$this->get('history')->addToSoldierLog($recruit, 'traincancel');
                } else {
                	// fresh recruit - return to workforce
                	$settlement->setPopulation($settlement->getPopulation()+1);
                	$em->remove($recruit);
                }
                $em->flush();
                return new RedirectResponse($this->generateUrl('maf_unit_soldiers', ["unit"=>$unit->getId()]).'#recruits');
        }

        /**
	  * @Route("/units/{unit}/assign", name="maf_unit_assign", requirements={"unit"="\d+"})
	  */

        public function unitAssignAction(Request $request, Unit $unit) {
		$character = $this->get('dispatcher')->gateway('unitAssignTest', false, true, false, $unit);
                # Distpatcher->getTest('test', default, default, default, UnitId)
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
                $options = $this->get('dispatcher')->getActionableCharacters(true);
                $options[] = $character;

                $form = $this->createForm(new CharacterSelectType($options, 'unit.assign.empty', 'unit.assign.select', 'unit.assign.submit', 'actions'));

                $form->handleRequest($request);
                if ($form->isValid()) {
                        $data = $form->getData();
                        $em = $this->getDoctrine()->getManager();
                        $unit->setCharacter($data['target']);
                        $em->flush();
                        $this->get('history')->logEvent(
				$data['target'],
				'event.unit.assigned',
				array('%unit%'=>$unit->getSettings()->getName(), '%link-character%'=>$character->getId()),
				History::MEDIUM, false, 30
			);
                        $this->addFlash('notice', $this->get('translator')->trans('unit.assign.success', array(), 'actions'));
                        return $this->redirectToRoute('maf_units');
                }

                return $this->render('Unit/assign.html.twig', [
                        'unit'=>$unit,
                        'form'=>$form->createView()
                ]);
        }

        /**
	  * @Route("/units/{unit}/appoint", name="maf_unit_appoint", requirements={"unit"="\d+"})
	  */

        public function unitAppointAction(Request $request, Unit $unit) {
		$character = $this->get('dispatcher')->gateway('unitAppointTest', false, true, false, $unit);
                # Distpatcher->getTest('test', default, default, default, UnitId)
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
                $options = $this->get('dispatcher')->getActionableCharacters(true); # Returns an array.
                $options[] = $character;
                # Check if the unit has a settlement, and if so, set $realm to the realm of that settlement, if any, and check if realm exists.
                if ($unit->getSettlement() && $realm = $unit->getSettlement()->getRealm()) {
                        # Get all members of the ultimate realm of the settlement.
                        foreach ($realm->findUltimate()->findActiveMembers() as $char) {
                                # Check if we already have them, if not: add.
                                if (!in_array($char, $options)) {
                                        $options[] = $char;
                                }
                        }
                }

                $form = $this->createForm(new CharacterSelectType($options, 'unit.appoint.empty', 'unit.appoint.select', 'unit.appoint.submit', 'actions'));

                $form->handleRequest($request);
                if ($form->isValid()) {
                        $data = $form->getData();
                        $em = $this->getDoctrine()->getManager();
                        $unit->setMarshal($data['target']);
                        $em->flush();
                        $this->get('history')->logEvent(
				$data['target'],
				'event.unit.appointed',
				array('%unit%'=>$unit->getSettings()->getName(), '%link-character%'=>$character->getId()),
				History::MEDIUM, false, 30
			);
                        $this->addFlash('notice', $this->get('translator')->trans('unit.appoint.success', array(), 'actions'));
                        return $this->redirectToRoute('maf_units');
                }

                return $this->render('Unit/appoint.html.twig', [
                        'unit'=>$unit,
                        'form'=>$form->createView()
                ]);
        }

        /**
	  * @Route("/units/{unit}/revoke", name="maf_unit_revoke", requirements={"unit"="\d+"})
	  */

        public function unitRevokeAction(Request $request, Unit $unit) {
		$character = $this->get('dispatcher')->gateway('unitAppointTest', false, true, false, $unit);
                # Distpatcher->getTest('test', default, default, default, UnitId)
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

                $em = $this->getDoctrine()->getManager();
                $unit->setMarshal(null);
                $em->flush();
                $this->addFlash('notice', $this->get('translator')->trans('unit.revoke.success', array(), 'actions'));
                return $this->redirectToRoute('maf_units');
        }

        /**
	  * @Route("/units/{unit}/rebase", name="maf_unit_rebase", requirements={"unit"="\d+"})
	  */

        public function unitRebaseAction(Request $request, Unit $unit) {
		$character = $this->get('dispatcher')->gateway('unitRebaseTest', false, true, false, $unit);
                # Distpatcher->getTest('test', default, default, default, UnitId)
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
                $options = new ArrayCollection();
                foreach ($character->getOwnedSettlements() as $settlement) {
                        $options->add($settlement);
                }
                if ($settlement = $character->getInsideSettlement() && !$options->contains($settlement)) {
                        if ($this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'units')) {
                                $options->add($settlement);
                        }
                }

                $form = $this->createForm(new UnitRebaseType($options->toArray()));

                $form->handleRequest($request);
                if ($form->isValid()) {
                        $data = $form->getData();
                        $success = $this->get('military_manager')->rebaseUnit($data, $options, $unit);
                        if ($success) {
                                $this->getDoctrine()->getManager()->flush();
                                $this->addFlash('notice', $this->get('translator')->trans('unit.rebase.success', array(), 'actions'));
                                return $this->redirectToRoute('maf_units');
                        } else {
                                $this->addFlash('error', $this->get('translator')->trans('unit.rebase.failed', array(), 'actions'));
                        }
                }

                return $this->render('Unit/rebase.html.twig', [
                        'unit'=>$unit,
                        'form'=>$form->createView()
                ]);
        }

        /**
	  * @Route("/units/{unit}/disband", name="maf_unit_disband", requirements={"unit"="\d+"})
	  */

        public function unitDisbandAction(Request $request, Unit $unit) {
		$character = $this->get('dispatcher')->gateway('unitDisbandTest', false, true, false, $unit);
                # Distpatcher->getTest('test', default, default, default, UnitId)
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

                $form = $this->createForm(new AreYouSureType());

                $form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
                        $success = $this->get('military_manager')->disbandUnit($unit);
                        if ($success) {
                                $this->addFlash('notice', $this->get('translator')->trans('unit.disband.success', array(), 'actions'));
                                return $this->redirectToRoute('maf_units');
                        } else {
                                $this->addFlash('error', $this->get('translator')->trans('unit.disband.failed', array(), 'actions'));
                        }
                }

                return $this->render('Unit/disband.html.twig', [
                        'form'=>$form->createView()
                ]);
        }

        /**
	  * @Route("/units/{unit}/return", name="maf_unit_return", requirements={"unit"="\d+"})
	  */

        public function unitReturnAction(Request $request, Unit $unit) {
		$character = $this->get('dispatcher')->gateway('unitReturnTest', false, true, false, $unit);
                # Distpatcher->getTest('test', default, default, default, UnitId)
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

                $form = $this->createForm(new AreYouSureType());

                $form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
                        $success = $this->get('military_manager')->returnUnitHome($unit, 'returned', $character, false);
                        if ($success) {
                                $this->addFlash('notice', $this->get('translator')->trans('unit.return.success', array(), 'actions'));
                                return $this->redirectToRoute('maf_units');
                        } else {
                                $this->addFlash('error', $this->get('translator')->trans('unit.return.failed', array(), 'actions'));
                        }
                }

                return $this->render('Unit/return.html.twig', [
                        'unit'=>$unit,
                        'form'=>$form->createView()
                ]);
        }

        /**
	  * @Route("/units/{unit}/recall", name="maf_unit_recall", requirements={"unit"="\d+"})
	  */

        public function unitRecallAction(Request $request, Unit $unit) {
		$character = $this->get('dispatcher')->gateway('unitRecallTest', false, true, false, $unit);
                # Distpatcher->getTest('test', getSettlement, checkDuplicate, getPlace, parameter)
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

                $form = $this->createForm(new AreYouSureType());

                $form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
                        $success = $this->get('military_manager')->returnUnitHome($unit, 'recalled', $unit->getCharacter(), false);
                        if ($success) {
                                $this->get('history')->logEvent(
        				$data['target'],
        				'event.unit.recalled',
        				array('%unit%'=>$unit->getSettings()->getName(), '%link-character%'=>$character->getId()),
        				History::MEDIUM, false, 30
        			);
                                $this->addFlash('notice', $this->get('translator')->trans('unit.recall.success', array(), 'actions'));
                                return $this->redirectToRoute('maf_units');
                        } else {
                                $this->addFlash('error', $this->get('translator')->trans('unit.recall.failed', array(), 'actions'));
                        }
                }

                return $this->render('Unit/recall.html.twig', [
                        'form'=>$form->createView()
                ]);
        }

        /**
          * @Route("/units/recruit", name="maf_recruit")
          */
     	public function unitRecruitAction(Request $request) {
     		list($character, $settlement) = $this->get('dispatcher')->gateway('unitRecruitTest', true);
                # Distpatcher->getTest('test', getSettlement, checkDuplicate, getPlace, parameter)
     		if (! $character instanceof Character) {
     			return $this->redirectToRoute($character);
     		}
     		$em = $this->getDoctrine()->getManager();

                $query = $em->createQuery('SELECT COUNT(s) as number, SUM(s.training_required) AS training FROM BM2SiteBundle:Soldier s JOIN s.unit u WHERE u.settlement = :here AND s.training_required > 0');
                #$query = $em->createQuery('SELECT COUNT(s) as number, SUM(s.training_required) AS training FROM BM2SiteBundle:Soldier s WHERE s.base = :here AND s.training_required > 0');
     		$query->setParameter('here', $settlement);
     		$allocated = $query->getSingleResult();
                $allUnits = $settlement->getUnits();
                $units = [];
                foreach ($allUnits as $unit) {
                        if($unit->getSoldiers()->count() < 200 && ($unit->getSettings()->getReinforcements() || !$unit->getCharacter())) {
                                $units[] = $unit;
                        }
                }
                if (count($units) < 1) {
                        $units[] = $this->get('military_manager')->newUnit(null, $settlement, null); #Ensure we always have atleast 1!
                }

		$soldierscount = 0;
		foreach ($settlement->getUnits() as $unit) {
			$soldierscount += $unit->getSoldiers()->count();
		}
     		$available = $this->get('military_manager')->findAvailableEquipment($settlement, true);
     		$form = $this->createForm(new SoldiersRecruitType($available, $units));
     		$form->handleRequest($request);

                $renderArray = [
                        'soldierscount'=>$soldierscount,
                        'settlement'=>$settlement,
                        'allocated'=>$allocated,
                        'training'=>$this->get('military_manager')->findAvailableEquipment($settlement, true),
                        'form'=>$form->createView(),
                ];

     		if ($form->isValid()) {
     			$data = $form->getData();
     			$generator = $this->get('generator');
                        if ($data['unit']->getSettlement() != $settlement) {
                                $form->addError(new FormError("recruit.troops.unitnothere"));
                                return $this->render('Unit/recruit.html.twig', $renderArray);
                        }

     			if ($data['number'] > $settlement->getPopulation()) {
     				$form->addError(new FormError("recruit.troops.toomany"));
                                return $this->render('Unit/recruit.html.twig', $renderArray);
     			}
     			if ($data['number'] > $settlement->getRecruitLimit()) {
     				$form->addError(new FormError($this->get('translator')->trans("recruit.troops.toomany2"), null, array('%max%'=>$settlement->getRecruitLimit(true))));
                                return $this->render('Unit/recruit.html.twig', $renderArray);
     			}
                        if ($data['number'] > $remaining = 200 - $unit->getSoldiers()->count()) {
                                $this->addFlash('notice', $this->get('translator')->trans('recruit.troops.unitmax', array('%only%'=> $remaining, '%planned%'=>$data['number']), 'actions'));
                                $data['number'] = $remaining;
                        }

     			for ($i=0; $i<$data['number']; $i++) {
     				if (!$data['weapon']) {
     					$form->addError(new FormError("recruit.troops.noweapon"));
                                        return $this->render('Unit/recruit.html.twig', $renderArray);
     				}
     			}
     			$count = 0;
			if ($data['unit']->getAvailable() < $data['number']) {
				$data['number'] = $data['unit']->getAvailable();
				$this->addFlash('notice', $this->get('translator')->trans('recruit.troops.availability', array('%unit%'=>$data['unit']->getSettings()->getName()), 'actions'));
			}
     			$corruption = $this->get('economy')->calculateCorruption($settlement);
     			for ($i=0; $i<$data['number']; $i++) {
     				if ($soldier = $generator->randomSoldier($data['weapon'], $data['armour'], $data['equipment'], $data['mount'], $settlement, $corruption, $data['unit'])) {
     					$this->get('history')->addToSoldierLog(
     						$soldier, 'recruited',
     						array('%link-character%'=>$character->getId(), '%link-settlement%'=>$settlement->getId(),
     							'%link-item-1%'=>$data['weapon']?$data['weapon']->getId():0,
     							'%link-item-2%'=>$data['armour']?$data['armour']->getId():0,
     							'%link-item-3%'=>$data['equipment']?$data['equipment']->getId():0,
     							'%link-item-4%'=>$data['mount']?$data['mount']->getId():0
     						)
     					);
     					$count++;
     				}
     			}
     			if ($count < $data['number']) {
     				$this->addFlash('notice', $this->get('translator')->trans('recruit.troops.supply', array('%only%'=> $count, '%planned%'=>$data['number']), 'actions'));
     			}

     			$settlement->setPopulation($settlement->getPopulation()-$count);
     			$settlement->setRecruited($settlement->getRecruited()+$count);
     			$em->flush();
     			return $this->redirectToRoute('maf_unit_soldiers', array('unit'=>$data['unit']->getId()));
     		}

                return $this->render('Unit/recruit.html.twig', $renderArray);
     	}
}
