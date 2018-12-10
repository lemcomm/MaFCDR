<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Description;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\GeoFeature;
use BM2\SiteBundle\Form\PlacePermissionsSetType;
use BM2\SiteBundle\Form\SoldiersManageType;
use BM2\SiteBundle\Form\PlaceManageType;
use BM2\SiteBundle\Form\PlaceNewType;
use BM2\SiteBundle\Service\History;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @Route("/place")
 */
class PlaceController extends Controller {

	/**
	  * @Route("/{id}", name="bm2_place", requirements={"id"="\d+"})
	  * @Template("BM2SiteBundle:Place:view.html.twig")
	  */
	public function indexAction(Place $id) {
		$place = $id;
		$em = $this->getDoctrine()->getManager();

		$character = $this->get('appstate')->getCharacter(false, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		
		if ($character instanceof Character && $character != $place->getOwner()) {
			$heralds = $character->getAvailableEntourageOfType('Herald')->count();
		} else {
			$heralds = 0;
		}
		
		# Check if we should be able to view any details on this place. A lot of places won't return much! :)
		if ($character instanceof Character) {
			$details = $this->get('interactions')->characterViewDetails($character, $place);
		}

		/* Leaving this here for later implementation...
		if ($details['spy'] || $place->getOwner() == $character) {
			$militia = $place->getActiveMilitiaByType();
		} else {
			$militia = null;
		} 
		When we add this, get rid of $militia below this. */
		$militia = null;
		
		if ($character instanceof Character && $character->getInsidePlace() == $place) {
			$inside = true;
		} else {
			$inside = false;
		}
		
		return array(
			'place' => $place,
			'details' => $details,
			'inside' => $inside,
			'militia' => $militia,
			'heralds' => $heralds
		);
	}

	/**
	  * @Route("/{id}/permissions", requirements={"id"="\d+"})
	  * @Template
	  */
	public function permissionsAction(Place $id, Request $request) {
		$character = $this->get('dispatcher')->gateway($place, 'placePermissionsTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		/* Not sure if we'll need this just yet.
		$place = $em->getRepository('BM2SiteBundle:Place')->find($id);
			throw $this->createNotFoundException('error.notfound.place');
		}
		if ($place->getOwner() !== $character) {
			throw $this->createNotFoundException('error.noaccess.place');
		}
		*/

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
	  * @Route("/new")
	  * @Template
	  */
	public function newAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('placeCreateTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		
		# Build the list of requirements we have.
		$rights[] = NULL;
		$settlement = $character->getInsideSettlement();
		if ($settlement && $this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'placeinside')) {
			if ($character == $settlement->getOwner()) {
				$rights[] = 'lord';
				if ($settlement->hasBuildingNamed('Wood Castle')) {
					$rights[] = 'castle';
				}
				if ($settlement->getRealm()->findRulers()->contains($character)) {
					$rights[] = 'ruler';
				}
			}
			$realm = $settlement->getRealm();
			if ($settlement->getCapitalOf() == $realm) {
				if (is_array($realm->findRulers()) && in_array($settlement->getOwner(), $realm->findRulers())) {
					$rights[] = 'ruler';
				} else if (!is_array($realm->findRulers()) && $settlement->getOwner() == $realm->findRulers()) {
					$rights[] = 'ruler';
				}
			}
			if ($settlement->hasBuildingNamed('Library')) {
				$rights[] = 'library';
			}
			if ($settlement->hasBuildingNamed('Academy')) {
				$rights[] = 'academy';
			}
		}
		if ($character->getMagic() > 0) {
			$rights[] = 'magic';
		}
		/* TODO: Not yet implemented yet, but it's an idea what this query may one day look like.
		if ($character->getWorldLevel() < 1) {
			$rights[] = 'warren';
		}
		*/
		
		#Now generate the list of things we can build!
		$query = $this->getDoctrine()->getManager()->createQuery("select r from BM2SiteBundle:PlaceType r where (r.requires in (:rights) OR r.requires IS NULL) AND r.visible = TRUE")->setParameter('rights', $rights);
		
		$form = $this->createForm(new PlaceNewType($query->getResult()));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$fail = $this->checkPlaceNames($form, $data['name'], $data['formal_name']);
			if (!$fail) {
				$place = new Place();
				$this->getDoctrine()->getManager()->persist($place);
				$place->setName($data['name']);
				$place->setFormalName($data['formal_name']);
				$place->setShortDescription($data['short_description']);
				$place->setOwner($character);
				if ($settlement) {
					$place->setSettlement($settlement);
					$place->setGeoData($settlement->getGeoData());
				} else {
					$place->setLocation($character->getLocation());
					$place->setGeoData($this->get('geography')->findMyRegion($character));
				}
				$place->setVisible($data['type']->getVisible());
				$this->getDoctrine()->getManager()->flush($place); # We can't create history for something that doesn't exist yet.
				$this->get('history')->logEvent(
					$place, 
					'event.place.formalized',
					array('%link-settlement%'=>$settlement->getId(), '%link-character%'=>$character->getId()),
					History::HIGH, true
				);
				if ($place->getVisible()) {
					$this->get('history')->logEvent(
						$settlement,
						'event.settlement.newplace',
						array('%link-place%'=>$place->getId(), '%link-character%'=>$character->getId()),
						History::MEDIUM, 
						true
					);
					$this->get('history')->logEvent(
						$character,
						'event.character.newplace',
						array('%link-place%'=>$place->getId(), '%link-character%'=>$character->getId()),
						History::HIGH, 
						true
					);
				} else {
					$this->get('history')->logEvent(
						$character,
						'event.character.newplace',
						array('%link-place%'=>$place->getId(), '%link-character%'=>$character->getId()),
						History::MEDIUM, 
						false
					);
				}
				$newdesc = $this->get('description_manager')->newDescription($place, $data['description'], $character);
				$this->getDoctrine()->getManager()->flush($place);
				$this->addFlash('notice', $this->get('translator')->trans('manage.success', array(), 'places'));
			}
		}
		return array(
			'form' => $form->createView()
		);
	}

	/**
	  * @Route("/{id}/manage", requirements={"id"="\d+"})
	  * @Template
	  */
	public function manageAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		
		$olddescription = $place->getDescription()->getText();
		if ($place->getOwner() == $character) {
			$isowner = true;
		} else {
			$isowner = false;
		}
		$form = $this->createForm(new PlaceManageType($olddescription, $isowner, $id));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$fail = $this->checkPlaceNames($form, $data['name'], $data['formal_name'], $place);
			if (!$fail) {
				if ($place->getName() != $data['name']) {
					$place->setName($data['name']);
				}
				if ($place->getFormalName() != $data['formal_name']) {
					$place->setFormalName($data['formal_name']);
				}
				if ($place->getShortDescription() != $data['short_description']) {
					$place->setShortDescription($data['short_description']);
				}
				if ($olddescription != $data['description']) {
					$desc = $this->get('description_manager')->newDescription($place, $data['description'], $character);
				}
				$this->getDoctrine()->getManager()->flush();
				$this->addFlash('notice', $this->get('translator')->trans('manage.success', array(), 'places'));
			}
		}
		return array(
			'place'=>$place, 
			'form'=>$form->createView()
		);
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
