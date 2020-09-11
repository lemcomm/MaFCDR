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
	  * @Route("/{id}", name="maf_place", requirements={"id"="\d+"})
	  * @Template("BM2SiteBundle:Place:view.html.twig")
	  */
	public function indexAction(Place $id) {
		$character = $this->get('appstate')->getCharacter(false, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$place = $id;
		$em = $this->getDoctrine()->getManager();

		if ($character != $place->getOwner()) {
			$heralds = $character->getAvailableEntourageOfType('Herald')->count();
		} else {
			$heralds = 0;
		}

		# Check if we should be able to view any details on this place. A lot of places won't return much! :)
		if ($character instanceof Character) {
			$details = $this->get('interactions')->characterViewDetails($character, $place);
		}


		$militia = [];
		if ($details['spy'] || $place->getOwner() == $character) {
			foreach ($place->getUnits() as $unit) {
				if ($unit->isLocal()) {
					foreach ($unit->getActiveSoldiersByType() as $key=>$type) {
						if (array_key_exists($key, $militia)) {
							$militia[$key] += $type;
						} else {
							$militia[$key] = $type;
						}
					}
				}
			}
		} else {
			$militia = null;
		}

		if ($character->getInsidePlace() == $place) {
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
	  * @Route("/actionable", name="maf_place_actionable")
	  * @Template
	  */

	public function actionableAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('placeListTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$placeList = $this->get('geography')->findPlacesInActionRange($character);
		$places = array();
		foreach ($placeList as $place) {
			$data = array(
				'id' => $place->getId(),
				'name' => $place->getName(),
				'description' => $place->getShortDescription(),
				'canManage' => $this->canManage($place, $character),
				'canEnter' => $this->canEnter($place, $character),
				'canSiege' => $this->canSiege($place, $character),
				'canSpawn' => $this->canSpawn($place, $character)
			);
			$places[] = $data;
		}
		return array(
			'places' => $places
		);
	}

	private function canManage(Place $place, Character $character) {
		if($this->get('permission_manager')->checkPlacePermission($place, $character, 'describe')) {
			return true;
		} else {
			return false;
		}
	}

	private function canEnter(Place $place, Character $character) {
		if($this->get('permission_manager')->checkPlacePermission($place, $character, 'visit')) {
			return true;
		} else {
			return false;
		}
	}

	private function canSiege(Place $place, Character $character) {
		if(!$place->getType()->getDefensible() || $this->get('permission_manager')->checkPlacePermission($place, $character, 'visit')) {
			return false;
		} else {
			return true;
		}
	}

	private function canSpawn(Place $place, Character $character) {
		$settlement = $place->getGeoData()->getSettlement();
		if ($settlement && $place->getType()->getSpawnable()) {
			foreach ($character->findRulerships() as $realm) {
				if ($realm == $settlement->getRealm() && (
					$place->getForRealm() == $realm || $place->getForRealm() === null
				)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	  * @Route("/{id}/enter", requirements={"id"="\d+"}, name="maf_place_enter")
	  * @Template
	  */

	public function enterPlaceAction(Place $id) {
		$character = $this->get('dispatcher')->gateway('placeEnterTest', false, true, false, $id);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if ($this->get('interactions')->characterEnterPlace($character, $id)) {
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('place.enter.success', array('%name%' => $id->getName()), 'actions'));
			return $this->redirectToRoute('maf_place', ['id' => $id->getId()]);
		} else {
			$this->addFlash('error', $this->get('translator')->trans('place.enter.failure', array(), 'actions'));
			return $this->redirectToRoute('maf_place_actionable');
		}
	}

	/**
	  * @Route("/exit", name="maf_place_exit")
	  * @Template
	  */

	public function exitPlaceAction() {
		$character = $this->get('dispatcher')->gateway('placeLeaveTest', false, true, false);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$result = null;
		if ($this->get('interactions')->characterLeavePlace($character)) {
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('place.exit.success', array(), 'actions'));
			return $this->redirectToRoute('maf_place_actionable');
		} else {
			$this->addFlash('error', $this->get('translator')->trans('place.exit.failure', array(), 'actions'));
			return $this->redirectToRoute('maf_place_actionable');
		}
	}

	/**
	  * @Route("/{id}/permissions", requirements={"id"="\d+"}, name="maf_place_permissions")
	  * @Template
	  */

	public function permissionsAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('placePermissionsTest', false, true, false, $place);
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
			$change = false;
			if ($place->getAllowSpawn() && !$place->getType()->getSpawnable()) {
				$place->setAllowSpawn(false);
				$change = true;
			}
			if (!$place->getPublic() && $place->getType()->getPublic()) {
				$place->setPublic(true);
				$change = true;
			}
			if ($change) {
				$em->flush(); #No sneaky allowed!
			}
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
	  * @Route("/new", name="maf_place_new")
	  * @Template
	  */
	public function newAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('placeCreateTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		# Build the list of requirements we have.
		$rights[] = NULL;
		$notTooClose = false;
		if ($character->getInsideSettlement()) {
			$settlement = $character->getInsideSettlement();
			$canPlace = $this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'place_inside');
			$notTooClose = true;
		} elseif ($region = $this->get('geography')->findMyRegion($character)) {
			$settlement = $region->getSettlement();
			$canPlace = $this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'place_outside');
			$notTooClose = $this->get('geography')->checkPlacePlacement($character); #Too close? Returns false. Too close is under 500 meteres to nearest place or settlement.
		}

		if (!$canPlace) {
			throw new AccessDeniedHttpException('unavailable.nopermission');
		}
		if (!$notTooClose) {
			throw new AccessDeniedHttpException('unavailable.tooclose');
		}

		# Check for lord and castles...
		if ($character == $settlement->getOwner()) {
			$rights[] = 'lord';
			if ($character->getInsideSettlement() && $settlement->hasBuildingNamed('Wood Castle')) {
				$rights[] = 'castle';
			}
		}

		# Check for GMs
		if ($character->getMagic() > 0) {
			$rights[] = 'magic';
		}

		# Check for inside settlement...
		if ($character->getInsideSettlement()) {
			if ($settlement->hasBuildingNamed('Library')) {
				$rights[] = 'library';
			}
			if ($settlement->hasBuildingNamed('Inn')) {
				$rights[] = 'inn';
			}
			if ($settlement->hasBuildingNamed('Tavern')) {
				$rights[] = 'tavern';
			}
		}

		foreach ($settlement->getCapitalOf() as $realm) {
			if (!$found) {

			}
		}

		$found = false;
		$found2 = false;
		$arrivals = [];
		foreach ($settlement->getCapitalOf() as $realm) {
			if (!$found || !$found2) {
				foreach ($realm->findRulers() as $ruler) {
					if ($ruler == $character) {
						$rights[] = 'ruler';
						$found = true;
						break; #No need to continue.
					}
				}
				if ($found && !$found2) {
					if ($realm->getArrivalPlaces()->count() < 5) {
						$arrivals[] = $realm;
						$found2 = true;
					}
				}
			}
		}
		$realm = $settlement->getRealm();
		if ($settlement->getCapitalOf() == $realm) {
			if ($realm->findRulers()->contains($settlement->getOwner())) {
				$rights[] = 'ruler';
			}
		}
		if ($settlement->hasBuildingNamed('Academy')) {
			$rights[] = 'academy';
		}
		$diplomacy = $character->isDiplomat();
		if ($diplomacy) {
			$rights[] = 'diplomat';
		}
		if ($settlement->getGeoData()->getCoast() && $settlement->hasBuildingNamed('Dockyard')) {
			$rights[] = 'port';
		}


		#Now generate the list of things we can build!
		$query = $this->getDoctrine()->getManager()->createQuery("select p from BM2SiteBundle:PlaceType p where (p.requires in (:rights) OR p.requires IS NULL) AND p.visible = TRUE")->setParameter('rights', $rights);


		$form = $this->createForm(new PlaceNewType($query->getResult()));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$em = $this->getDoctrine()->getManager();
			$data = $form->getData();
			$fail = $this->checkPlaceNames($form, $data['name'], $data['formal_name']);
			if ($this->get('geography')->checkPlacePlacement($character)) {
				$fail = TRUE; #You shouldn't even have access but players will be players, best check anyways.
			}
			if (!$fail) {
				$place = new Place();
				$this->getDoctrine()->getManager()->persist($place);
				$place->setName($data['name']);
				$place->setFormalName($data['formal_name']);
				$place->setShortDescription($data['short_description']);
				$place->setCreator($character);
				$place->setOwner($character);
				$place->setType($data['type']);
				if ($character->getInsideSettlement()) {
					$place->setSettlement($character->getInsideSettlement());
					$place->setGeoData($character->getInsideSettlement()->getGeoData());
				} else {
					$geoData = $this->get('geography')->findMyRegion($character);
					$loc = $character->getLocation();
					$feat = new GeoFeature;
					$feat->setLocation($loc);
					$feat->setGeoData($geoData);
					$feat->setName($data['name']);
					$feat->setActive(true);
					$feat->setWorkers(0);
					$feat->setCondition(0);
					$feat->setType($em->getRepository('BM2SiteBundle:GeoFeatureType')->findOneByName('place'));
					$em->flush(); #We need the above to set the below and do relations.
					$place->setGeoFeature($feat);
					$place->setLocation($loc);
					#Arguably, we could just get location from the geofeature, but this leaves more possibilities open.
					$place->setGeoData($geoData);
				}
				$place->setVisible($data['type']->getVisible());
				$place->setActive(true);
				$this->getDoctrine()->getManager()->flush(); # We can't create history for something that doesn't exist yet.
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
				}
				$newdesc = $this->get('description_manager')->newDescription($place, $data['description'], $character);
				$this->getDoctrine()->getManager()->flush($place);
				$this->addFlash('notice', $this->get('translator')->trans('manage.success', array(), 'places'));
				return $this->redirectToRoute('maf_place_actionable');
			}
		}
		return array(
			'form' => $form->createView()
		);
	}

	/**
	  * @Route("/{id}/manage", requirements={"id"="\d+"}, name="maf_place_manage")
	  * @Template
	  */
	public function manageAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('placeManageTest', false, true, false, $place);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$olddescription = $place->getDescription()->getText();
		$type = $place->getType()->getName();
		$hostedRealm = null;
		$realm = null;
		if ($type == 'capital' || $type == 'embassy') {
			$realm = $place->getCapitalOf();
			if ($realm && $realm->findRulers()->contains($character)) {
				$isowner = true;
			}
		} elseif ($type == 'embassy') {
			$realm = $place->getOwningRealm();
			if ($realm && $realm->findRulers()->contains($character)) {
				$isowner = true;
			}
			$hostedRealm = $place->getForRealm();
		} else {
			if ($place->getOwner() == $character) {
				$isowner = true;
			} else {
				$isowner = false;
			}
		}
		$form = $this->createForm(new PlaceManageType($olddescription, $isowner, $id, $hostedRealm));
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

	/**
	  * @Route("/changeoccupant", requirements={"id"="\d+"}, name="maf_place_occupant")
	  */
	public function changeOccupantAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('placeChangeOccupantTest', false, true, false, $place);
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
				$act->setType('place.occupant')->setCharacter($character);
				$act->setTargetSettlement($settlement)->setTargetCharacter($data['target']);
				$act->setBlockTravel(true);
				$time_to_grant = round((sqrt($settlement->getPopulation()) + sqrt($soldiers))*3);
				$complete = new \DateTime("+1 hour");
				$act->setComplete($complete);
				$result = $this->get('action_manager')->queue($act);
				$this->addFlash('notice', $this->get('translator')->trans('event.settlement.occupant.start', ["%time%"=>$complete->format('Y-M-d H:i:s')], 'communication'));
				return $this->redirectToRoute('bm2_actions');
			}
		}

		return $this->render('BM2SiteBundle::Place/occupant.html.twig', [
			'settlement'=>$settlement, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/changeoccupier", requirements={"id"="\d+"}, name="maf_place_occupier")
	  */
	public function changeOccupierAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('placeChangeOccupierTest', false, true, false, $place);
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
		return $this->render('BM2SiteBundle::Place/occupier.html.twig', [
			'settlement'=>$settlement, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/occupation/start", requirements={"id"="\d+"}, name="maf_place_occupation_start")
	  */
	public function occupationStartAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('placeOccupationStartTest', false, true, false, $place);
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
		return $this->render('BM2SiteBundle::Place/occupationstart.html.twig', [
			'settlement'=>$settlement, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/occupation/end", requirements={"id"="\d+"}, name="maf_settlement_occupation_end")
	  */
	public function occupationEndAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('controlOccupationEndTest', false, true, false, $place);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new AreYouSureType());
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
                        $this->get('politics')->endOccupation($settlement, 'manual');
			$this->getDoctrine()->getManager()->flush();
                        $this->addFlash('notice', $this->get('translator')->trans('control.occupation.ended', array(), 'actions'));
                        return $this->redirectToRoute('bm2_actions');
                }
		return $this->render('BM2SiteBundle::Place/occupationend.html.twig', [
			'settlement'=>$settlement, 'form'=>$form->createView()
		]);
	}

}
