<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\GeoFeature;
use BM2\SiteBundle\Form\PlacePermissionsSetType;
use BM2\SiteBundle\Form\SoldiersManageType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @Route("/place")
 */
class PlaceController extends Controller {

	/**
	  * @Route("/{id}", name="bm2_place", requirements={"id"="\d+"})
	  * @Template("BM2SiteBundle:Place:place.html.twig")
	  */
	public function indexAction(Settlement $id) {
		$em = $this->getDoctrine()->getManager();
		$place = $id; // we use $id because it's hardcoded in the linkhelper

		$character = $this->get('appstate')->getCharacter(false, true, true);
		
		if ($character != $place->getOwner()) {
			$heralds = $character->getAvailableEntourageOfType('Herald')->count();
		} else {
			$heralds = 0;
		}
		
		$details = $this->get('interactions')->characterViewPlace($character, $place);
		
		if ($details['spy'] || $place->getOwner() == $character) {
			$militia = $place->getActiveMilitiaByType();
		} else {
			$militia = null;
		}
		
		return array(
			'place' => $place,
			'details' => $details,
			'militia' => $militia,
			'heralds' => $heralds
		);
	}

	/**
	  * @Route("/{id}/permissions", requirements={"id"="\d+"})
	  * @Template
	  */
	public function permissionsAction(Place $id, Request $request) {
		$character = $this->get('dispatcher')->gateway();
		$em = $this->getDoctrine()->getManager();
		$place = $em->getRepository('BM2SiteBundle:Place')->find($id);
			throw $this->createNotFoundException('error.notfound.place');
		}
		if ($place->getOwner() !== $character) {
			throw $this->createNotFoundException('error.noaccess.place');
		}

		$original_permissions = clone $place->getPermissions();

		$form = $this->createForm(new PlacePermissionsSetType($character, $this->getDoctrine()->getManager()), $place);

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			foreach ($place->getPermissions() as $permission) {
				$permission->setValueRemaining($permission->getValue());
				if (!$permission->getId()) {
					$em->persist($permission);
				}
			}
			foreach ($original_permissions as $orig) {
				if (!$place->getPermissions()->contains($orig)) {
					$em->remove($orig);
				}
			}
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('control.permissions.success', array(), 'actions'));
			return $this->redirect($request->getUri());
		}
	
		return array(
			'place' => $place,
			'permissions' => $em->getRepository('BM2SiteBundle:Permission')->findByClass('place'),
			'form' => $form->createView()
		);
	}

	/**
	  * @Route("/{id}/manage", requirements={"id"="\d+"})
	  * @Template
	  */
	public function manageAction(Place $place, Request $request) {
		$character = $this->gateway($place, 'hierarchyManagePlaceTest');

		$form = $this->createForm(new PlaceManageType(), $place);
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$fail = $this->checkPlaceNames($form, $data->getName(), $data->getFormalName(), $place);
			if (!$fail) {
				$this->get('description_manager')->newDescription($place, $data->getDescription(), $character);
				$this->getDoctrine()->getManager()->flush();
				$this->addFlash('notice', $this->get('translator')->trans('place.manage.success', array(), 'politics'));
			}
		}
		return array('place'=>$place, 'form'=>$form->createView());
	}

	#TODO: Combine this and checkRealmNames into a single thing in a HelperService.
	private function checkPlaceNames($form, $name, $formalname, $me=null) {
		$fail = false;
		$em = $this->getDoctrine()->getManager();
		$allplaces = $em->getRepository('BM2SiteBundle:Place')->findAll();
		foreach ($allplaces as $other) {
			if ($other == $me) continue;
			if (levenshtein($name, $other->getName()) < min(3, min(strlen($name), strlen($other->getName()))*0.75)) {
				$form->addError(new FormError($this->get('translator')->trans("place.new.toosimilar.name"), null, array('%other%'=>$other->getName())));
				$fail=true;
			}
			if (levenshtein($formalname, $other->getFormalName()) <  min(5, min(strlen($formalname), strlen($other->getFormalName()))*0.75)) {
				$form->addError(new FormError($this->get('translator')->trans("place.new.toosimilar.formalname"), null, array('%other%'=>$other->getFormalName())));
				$fail=true;
			}
		}
		return $fail;
	}

}
