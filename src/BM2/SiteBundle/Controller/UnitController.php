<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Unit;
use BM2\SiteBundle\Entity\UnitSettings;

use BM2\SiteBundle\Form\SoldiersManageType;
use BM2\SiteBundle\Form\UnitSettingsType;

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
          * @Template("BM2SiteBundle:Unit:units.html.twig")
          */

        public function indexAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('personalAssignedUnitsTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

                if (($character->getUnits()->isEmpty() && !$character->getSoldiers()->isEmpty()) OR (!$character->getUnits()->isEmpty() && !$character->getSoldiers()->isEmpty())) {
                   # Chosen character has no units, make a new default unit, or has both units and soldiers directly, so make another one because something clearly went wrong.
                   $unit = $this->get('military_manager')->newUnit($character, null);
                }

                $em = $this->getDoctrine()->getManager();

                if ($character->getInsideSettlement() && $character->getInsideSettlement()->getOwner() == $character) {
                   $query = $em->createQuery('SELECT u FROM BM2SiteBundle:Unit u JOIN BM2SiteBundle:UnitSettings s WHERE u.character = :char OR (u.settlement = :settlement AND u.is_militia = TRUE) GROUP BY u.character ORDER BY s.name ASC');
                   $query->setParameters(array('char'=>$character, 'settlement'=>$character->getInsideSettlement()));
                } else {
                   $query = $em->createQuery('SELECT u FROM BM2SiteBundle:Unit u JOIN BM2SiteBundle:UnitSettings s WHERE u.character = :char ORDER BY s.name ASC');
                   $query->setParameter('char', $character);
                }
                $units = $query->getResult();

                return array(
                   'units' => $units,
                   'character' => $character
           );
        }

        /**
          * @Route("/units/new", name="maf_unit_new")
          * @Template
          */

        public function createAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('personalUnitNewTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

                $settlements = $this->get('game_request_manager')->getAvailableFoodSuppliers($character);
                $form = $this->createForm(new UnitSettingsType($character, true, $settlements, null, true));

                $form->handelRequest($request);
                if ($form-isValid()) {
                        $data = $form->getData();
                        $unit = $this->get('military_manager')->newUnit($character, $character->getInsideSettlement(), false, $data);
                        if ($unit) {
                                $this->addFlash('notice', $this->get('translator')->trans('unit.manage.created', array(), 'actions'));
                                return $this->redirectToRoute('maf_units');
                        }
                }
                return array(
                        'form' => $form->createView()
                );
        }

        /**
	  * @Route("/units/{unit}/manage", name="maf_unit_manage", requirements={"unit"="\d+"})
	  * @Template("BM2SiteBundle:Unit:manage.html.twig")
	  */

        public function unitManageAction(Request $request, Unit $unit) {
		$character = $this->get('dispatcher')->gateway('personalUnitManageTest', false, true, false, $unit);
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
                        $unit = $this->get('military_manager')->newUnit($character, $character->getInsideSettlement(), false, $data);
                        if ($unit) {
                                $this->addFlash('notice', $this->get('translator')->trans('unit.manage.created', array(), 'actions'));
                                return $this->redirectToRoute('maf_units');
                        }
                }

                return array(
                        'form' => $form->createView()
                );

        }

	/**
	  * @Route("/units/{unit}/soldiers", name="maf_unit_soldiers", requirements={"unit"="\d+"})
	  * @Template
	  */
	public function soldiersAction(Request $request, Unit $unit) {
		# TODO: An AppState call followed by a Dispatcher call. Can we combine these? --Andrew 20181210
		$character = $this->get('appstate')->getCharacter();
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
		$form = $this->createForm(new UnitSoldiersType($em, $unit->getSoldiers(), $resupply, $training, $units, $settlement));

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

		return array(
			'soldiers' => $unit->getSoldiers(),
			'recruits' => $unit->getRecruits(),
			'resupply' => $resupply,
			'settlement' => $settlement,
			'training' => $training,
			'form' => $form->createView(),
			'limit' => $this->get('appstate')->getGlobal('pagerlimit', 100),
                        'unit' => $unit,
		);
	}

	/**
	  * @Route("/units/{unit}/canceltraining", name="maf_unit_soldiers", requirements={"unit"="\d+", "recruit"="\d+"})
	  */
	public function cancelTrainingAction(Request $request, Unit $unit, Soldier $recruit) {
		if ($request->isMethod('POST')) {
			list($character, $settlement) = $this->get('dispatcher')->gateway('personalMilitiaTest', true);
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
}
