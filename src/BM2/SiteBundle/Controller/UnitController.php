<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Unit;
use BM2\SiteBundle\Entity\UnitSettings;

use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\SoldiersRecruitType;
use BM2\SiteBundle\Form\SoldiersManageType;
use BM2\SiteBundle\Form\UnitSettingsType;
use BM2\SiteBundle\Form\UnitSoldiersType;

use BM2\SiteBundle\Service\GameRequestManager;
use BM2\SiteBundle\Service\MilitaryManager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UnitController extends Controller {

        /**
          * @Route("/units", name="maf_units")
          */

        public function indexAction(Request $request) {
                $character = $this->get('dispatcher')->gateway('personalAssignedUnitsTest');
                if (! $character instanceof Character) {
                        return $this->redirectToRoute($character);
                }
                $em = $this->getDoctrine()->getManager();

                if ($character->getInsideSettlement() && $character->getInsideSettlement()->getOwner() == $character) {
                        $query = $em->createQuery('SELECT u FROM BM2SiteBundle:Unit u JOIN BM2SiteBundle:UnitSettings s WHERE u.character = :char OR (u.settlement = :settlement) ORDER BY s.name ASC');
                        $query->setParameters(array('char'=>$character, 'settlement'=>$character->getInsideSettlement()));
                        $lord = true;
                } else {
                        $query = $em->createQuery('SELECT u FROM BM2SiteBundle:Unit u JOIN BM2SiteBundle:UnitSettings s WHERE u.character = :char ORDER BY s.name ASC');
                        $query->setParameter('char', $character);
                        $lord = false;
                }
                $units = $query->getResult();

                return $this->render('Unit/units.html.twig', [
                        'lord' => $lord,
                        'units' => $units,
                        'character' => $character
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

                $settlements = $this->get('game_request_manager')->getAvailableFoodSuppliers($character);
                $form = $this->createForm(new UnitSettingsType($character, true, $settlements, null, true));

                $form->handleRequest($request);
                if ($form->isValid()) {
                        $data = $form->getData();
                        $unit = $this->get('military_manager')->newUnit($character, $character->getInsideSettlement(), $data);
                        if ($unit) {
                                $this->addFlash('notice', $this->get('translator')->trans('unit.manage.created', array(), 'actions'));
                                return $this->redirectToRoute('maf_units');
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
                if ($character->getInsideSettlement() && $character == $character->getInsideSettlement()->getOwner()) {
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
                list($character, $settlement) = $this->get('dispatcher')->gateway('unitSoldiersTest', true);
                if (! $character instanceof Character) {
                        return $this->redirectToRoute($character);
                }

		$em = $this->getDoctrine()->getManager();

		$resupply=array();
		$training=array();
                $units=false;
                $canResupply=false;
                $canRecruit=false;
                $canReassign=false;
		if ($settlement = $character->getInsideSettlement()) {
			if ($this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'resupply')) {
                                $canResupply = true;
				$resupply = $this->get('military_manager')->findAvailableEquipment($settlement, false);
			}
			if ($this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'recruit')) {
                                $canRecruit = true;
				$training = $this->get('military_manager')->findAvailableEquipment($settlement, true);
			}
                        if ($settlement->getLocalUnits()->contains($unit)) {
                                if ($this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'units')) {
                                        foreach ($settlement->getLocalUnits() as $localUnit) {
                                                if ($unit != $localUnit) {
                                                        $units[] = $localUnit;
                                                }
                                        }
                                        $canReassign=true;
                                }
			} else {
                                #This unit not available for reassining soldiers due to not being local.
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
		$form = $this->createForm(new UnitSoldiersType($em, $unit->getSoldiers(), $resupply, $training, $units, $settlement, $canReassign));

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			list($success, $fail) = $this->get('military_manager')->manageUnit($character->getSoldiers(), $data, $settlement, $character, $canResupply, $canRecruit, $canReassign);
			// TODO: notice with result

			$em = $this->getDoctrine()->getManager();
			$em->flush();
			$this->get('appstate')->setSessionData($character); // update, because maybe we changed our soldiers count
			return $this->redirect($request->getUri());
		}

                return $this->render('Unit/soldiers.html.twig', [
			'soldiers' => $unit->getSoldiers(),
			'recruits' => $unit->getRecruits(),
			'resupply' => $resupply,
			'settlement' => $settlement,
			'training' => $training,
			'form' => $form->createView(),
                        'unit' => $unit,
                ]);
	}

	/**
	  * @Route("/units/{unit}/cancel/{recruit}", name="maf_unit_cancel_training", requirements={"unit"="\d+", "recruit"="\d+"})
	  */
	public function cancelTrainingAction(Request $request, Unit $unit, Soldier $recruit) {
		if ($request->isMethod('POST')) {
			list($character, $settlement) = $this->get('dispatcher')->gateway('unitCancelTrainingTest', true, true, false, $unit);
			if (! $character instanceof Character) {
				return $this->redirectToRoute($character);
			}

			$em = $this->getDoctrine()->getManager();
			$recruit = $em->getRepository('BM2SiteBundle:Soldier')->find($request->request->get('recruit'));
			if (!$recruit || !$recruit->isRecruit() || $recruit->getBase()!=$settlement) {
				throw $this->createNotFoundException('error.notfound.recruit');
			}

			// return his equipment to the stockpile:
			$this->get('military_manager')->returnItem($settlement, $recruit->getWeapon());
			$this->get('military_manager')->returnItem($settlement, $recruit->getArmour());
			$this->get('military_manager')->returnItem($settlement, $recruit->getEquipment());

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
		}
		return new Response();
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
                foreach ($character->getSettlements() as $settlement) {
                        $options->add($settlement);
                }
                if ($settlement = $charcter->getInsideSettlement()) {
                        if ($this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'units')) {
                                $options->add($settlement);
                        }
                }

                $form = $this->createForm(new UnitRebaseType($options));

                $form->handleRequest($request);
                if ($form->isValid()) {
                        $data = $form->getData();
                        $success = $this->get('military_manager')->rebaseUnit($data, $options, $unit);
                        if ($success) {
                                $this->addFlash('notice', $this->get('translator')->trans('unit.rebase.success', array(), 'actions'));
                                return $this->redirectToRoute('maf_units');
                        } else {
                                $this->addFlash('error', $this->get('translator')->trans('unit.rebase.failed', array(), 'actions'));
                        }
                }

                return $this->render('Unit/rebase.html.twig', [
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
                        $success = $this->get('military_manager')->returnUnitHome($unit, 'returned', $character->getLocation(), false);
                        if ($success) {
                                $this->addFlash('notice', $this->get('translator')->trans('unit.return.success', array(), 'actions'));
                                return $this->redirectToRoute('maf_units');
                        } else {
                                $this->addFlash('error', $this->get('translator')->trans('unit.return.failed', array(), 'actions'));
                        }
                }

                return $this->render('Unit/disband.html.twig', [
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
                        $success = $this->get('military_manager')->returnUnitHome($unit, 'recalled', $unit->getCharacter()->getLocation(), false);
                        if ($success) {
                                $this->addFlash('notice', $this->get('translator')->trans('unit.recall.success', array(), 'actions'));
                                return $this->redirectToRoute('maf_units');
                        } else {
                                $this->addFlash('error', $this->get('translator')->trans('unit.recall.failed', array(), 'actions'));
                        }
                }

                return $this->render('Unit/disband.html.twig', [
                        'form'=>$form->createView()
                ]);
        }



        /**
          * @Route("/unit/{unit}/recruit", name="maf_unit_recruit", requirements={"unit"="\d+"})
          */
     	public function unitRecruitAction(Request $request, Unit $unit) {
     		list($character, $settlement) = $this->get('dispatcher')->gateway('unitSoldiersTest', true, true, false, $unit);
                # Distpatcher->getTest('test', getSettlement, checkDuplicate, getPlace, parameter)
     		if (! $character instanceof Character) {
     			return $this->redirectToRoute($character);
     		}
     		$em = $this->getDoctrine()->getManager();

                $query = $em->createQuery('SELECT COUNT(s) as number, SUM(s.training_required) AS training FROM BM2SiteBunlde:Soldier s JOIN s.unit u WHERE u.settlement = :here AND s.training_required > 0');
     		$query->setParameter('here', $settlement);
     		$allocated = $query->getSingleResult();

     		$available = $this->get('military_manager')->findAvailableEquipment($settlement, true);
     		$form = $this->createForm(new SoldiersRecruitType($available, $units));
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
                        if ($data['number'] > $remaining = 200 - $unit->getSoldirs()->count()) {
                                $this->addFlash('notice', $this->get('translator')->trans('recruit.troops.unitmax', array('%only%'=> $remaining, '%planned%'=>$data['number']), 'actions'));
                                $data['number'] = $remaining;
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
			if ($data['unit']->getAvailable() < $data['number']) {
				$data['number'] = $data['unit']->getAvailable();
				$this->addFlash('notice', $this->get('translator')->trans('recruit.troops.availability', array('%unit%'=>$data['unit']->getName()), 'actions'));
			}
     			$corruption = $this->get('economy')->calculateCorruption($settlement);
     			for ($i=0; $i<$data['number']; $i++) {
     				if ($soldier = $generator->randomSoldier($data['weapon'], $data['armour'], $data['equipment'], $settlement, $data['unit'], $corruption, $unit)) {
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
     			if ($count < $data['number']) {
     				$this->addFlash('notice', $this->get('translator')->trans('recruit.troops.supply', array('%only%'=> $count, '%planned%'=>$data['number']), 'actions'));
     			}

     			$settlement->setPopulation($settlement->getPopulation()-$count);
     			$settlement->setRecruited($settlement->getRecruited()+$count);
     			$em->flush();
     			return $this->redirectToRoute('bm2_site_settlement_soldiers', array('id'=>$settlement->getId()));
     		}
		$soldiercount = 0;
		foreach ($settlement->getUntits() as $unit) {
			$soldiercount += $unit->getSoldiers()->count();
		}

     		return array(
     			'settlement'=>$settlement,
     			'allocated'=>$allocated,
     			'training'=>$this->get('military_manager')->findAvailableEquipment($settlement, true),
     			'soldierscount' => $soldiercount,

     			'form'=>$form->createView()
     		);
     	}
}
