<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Unit;
use BM2\SiteBundle\Entity\UnitSettings;

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


/**
 * @Route("/unit")
 */

class UnitController extends Controller {

        /**
          * @Route("/", name="maf_units")
          * @Template("BM2SiteBundle:Unit:units.html.twig")
          */

        public function indexAction(Request $request) {
                $character = $this->get('appstate')->getCharacter();
                if (! $character instanceof Character) {
                   return $this->redirectToRoute($character);
                }

                if ($character->getUnits()->isEmpty()) {
                   # Chosen character has no units, make a new default unit.
                   $unit = $this->get('military_manager')->newUnit($character, null);
                }

                $em = $this->getDoctrine()->getManager();

                #TODO: Group DQL results by character assigned to. 'u.character'
                if ($character->getInsideSettlement() && $character->getInsideSettlement()->getOwner() == $character) {
                   $query = $em->createQuery('SELECT u FROM BM2SiteBundle:Unit u JOIN BM2SiteBundle:UnitSettings s WHERE u.character = :char OR (u.settlement = :settlement AND u.is_militia = TRUE) ORDER BY s.name ASC');
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
          * @Route("/new", name="maf_unit_new")
          * @Template
          */

        public function createAction(Request $request) {
                $character = $this->get('appstate')->getCharacter();
                if (! $character instanceof Character) {
                   return $this->redirectToRoute($character);
                }
		$em = $this->getDoctrine()->getManager();

                #TODO: Move to dispatcher.
                if (!$character->getInsideSettlement()) {
                        throw new AccessDeniedHttpException('unavailable.notinside');
                }
                if ($character != $character->getInsideSettlement()->getOwner()) {
                        throw new AccessDeniedHttpException('unavailable.notlord');
                }

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
	  * @Route("/{unit}/manage", name="maf_unit_manage", requirements={"unit"="\d+"})
	  * @Template("BM2SiteBundle:Unit:manage.html.twig")
	  */

        public function unitManageAction(Request $request, Unit $unit) {
                $character = $this->get('appstate')->getCharacter();
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

                #TODO: Move to dispatcher.
                if (!$character->getUnits()->contains($unit)) {
                        throw new AccessDeniedHttpException('unavailable.notyourunit');
                }
                if (!$unit->getCharacter()) {
                        if ($unit->getSettlement()->getOwner() != $character) {
                                throw new AccessDeniedHttpException('unavailable.notlord');
                        } elseif($unit->getSettlement() != $character->getInsideSettlement()) {
                                throw new AccessDeniedHttpException('unvailable.notinside');
                        }
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
}
