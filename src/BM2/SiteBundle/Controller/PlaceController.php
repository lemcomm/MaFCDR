<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Description;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\GeoFeature;
use BM2\SiteBundle\Entity\Spawn;
use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\AssocSelectType;
use BM2\SiteBundle\Form\DescriptionNewType;
use BM2\SiteBundle\Form\InteractionType;
use BM2\SiteBundle\Form\PlacePermissionsSetType;
use BM2\SiteBundle\Form\RealmSelectType;
use BM2\SiteBundle\Form\SoldiersManageType;
use BM2\SiteBundle\Form\PlaceManageType;
use BM2\SiteBundle\Form\PlaceNewType;
use BM2\SiteBundle\Service\History;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @Route("/place")
 */
class PlaceController extends Controller {

	/**
	  * @Route("/{id}", name="maf_place", requirements={"id"="\d+"})
	  */
	public function indexAction(Place $id) {
		$character = $this->get('appstate')->getCharacter(false, true, true);

		$place = $id;
		$em = $this->getDoctrine()->getManager();

		if ($character && $character != $place->getOwner()) {
			$heralds = $character->getAvailableEntourageOfType('Herald')->count();
		} else {
			$heralds = 0;
		}

		# Check if we should be able to view any details on this place. A lot of places won't return much! :)
		$details = $this->get('interactions')->characterViewDetails($character, $place);

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

		if ($character && $character->getInsidePlace() == $place) {
			$inside = true;
		} else {
			$inside = false;
		}

		return $this->render('Place/view.html.twig', [
			'place' => $place,
			'details' => $details,
			'inside' => $inside,
			'militia' => $militia,
			'heralds' => $heralds
		]);
	}

	/**
	  * @Route("/actionable", name="maf_place_actionable")
	  */

	public function actionableAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('placeListTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$places = $this->get('geography')->findPlacesInActionRange($character);

		$coll = new ArrayCollection($places);
		$iterator = $coll->getIterator();
		$iterator->uasort(function ($a, $b) {
		    return ($a->getName() < $b->getName()) ? -1 : 1;
		});
		$places = new ArrayCollection(iterator_to_array($iterator));


		return $this->render('Place/actionable.html.twig', [
			'places' => $places,
			'myHouse' => $character->getHouse(),
			'character' => $character
		]);
	}

	/**
	  * @Route("/{id}/enter", requirements={"id"="\d+"}, name="maf_place_enter")
	  */

	public function enterPlaceAction(Place $id) {
		$character = $this->get('dispatcher')->gateway('placeEnterTest', false, true, false, $id);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if ($this->get('interactions')->characterEnterPlace($character, $id)) {
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('place.enter.success', array('%name%' => $id->getName()), 'actions'));
			return $this->redirectToRoute('maf_place_actionable');
		} else {
			$this->addFlash('error', $this->get('translator')->trans('place.enter.failure', array(), 'actions'));
			return $this->redirectToRoute('maf_place_actionable');
		}
	}

	/**
	  * @Route("/exit", name="maf_place_exit")
	  */

	public function exitPlaceAction() {
		$character = $this->get('dispatcher')->gateway('placeLeaveTest', false, true, false);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$id = $character->getInsidePlace();

		$result = null;
		if ($this->get('interactions')->characterLeavePlace($character)) {
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('place.exit.success', array('%name%' => $id->getName()), 'actions'));
			return $this->redirectToRoute('maf_place_actionable');
		} else {
			$this->addFlash('error', $this->get('translator')->trans('place.exit.failure', array(), 'actions'));
			return $this->redirectToRoute('maf_place_actionable');
		}
	}

	/**
	  * @Route("/{id}/permissions", requirements={"id"="\d+"}, name="maf_place_permissions")
	  */

	public function permissionsAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('placePermissionsTest', false, true, false, $place);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		if ($place->getOwner() == $character) {
			$owner = true;
			$original_permissions = clone $place->getPermissions();
			$page = 'Place/permissions.html.twig';
		} else {
			$owner = false;
			$original_permissions = clone $place->getOccupationPermissions();
			$page = 'Place/occupationPermissions.html.twig';
		}

		$form = $this->createForm(new PlacePermissionsSetType($character, $this->getDoctrine()->getManager(), $owner, $place), $place);

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			# TODO: This can be combined with the code in SettlementController as part of a service function.
			if ($owner) {
				foreach ($place->getPermissions() as $permission) {
					$permission->setValueRemaining($permission->getValue());
					if (!$permission->getId()) {
						$em->persist($permission);
					}
				}
				foreach ($original_permissions as $orig) {
					if (!$place->getPermissions()->contains($orig)) {
						$em->remove($orig);
					} else {
						$em->persist($orig);
					}
				}
			} else {
				foreach ($place->getOccupationPermissions() as $permission) {
					$permission->setValueRemaining($permission->getValue());
					if (!$permission->getId()) {
						$em->persist($permission);
					}
				}
				foreach ($original_permissions as $orig) {
					if (!$place->getOccupationPermissions()->contains($orig)) {
						$em->remove($orig);
					}
				}
			}
			$em->flush();
			$change = false;
			if (!$place->getPublic() && $place->getType()->getPublic()) {
				#Check for invalid settings.
				$place->setPublic(true);
				$change = true;
			}
			if ($change) {
				$em->flush(); #No sneaky allowed!
			}
			$this->addFlash('notice', $this->get('translator')->trans('control.permissions.success', array(), 'actions'));
			return $this->redirect($request->getUri());
		}

		return $this->render($page, [
			'place' => $place,
			'permissions' => $em->getRepository('BM2SiteBundle:Permission')->findByClass('place'),
			'form' => $form->createView(),
			'owner' => $owner
		]);
	}

	/**
	  * @Route("/new", name="maf_place_new")
	  */
	public function newAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('placeCreateTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		# Build the list of requirements we have.
		$rights[] = NULL;
		$notTooClose = false;
		$canPlace = false;
		if ($character->getInsideSettlement()) {
			$settlement = $character->getInsideSettlement();
			$canPlace = $this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'placeinside');
			$notTooClose = true;
		} elseif ($region = $this->get('geography')->findMyRegion($character)) {
			$settlement = $region->getSettlement();
			$canPlace = $this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'placeoutside');
			$notTooClose = $this->get('geography')->checkPlacePlacement($character); #Too close? Returns false. Too close is under 500 meteres to nearest place or settlement.
		}

		if (!$canPlace) {
			throw new AccessDeniedHttpException('unavailable.nopermission');
		}
		if (!$notTooClose) {
			throw new AccessDeniedHttpException('unavailable.tooclose');
		}

		# Check for lord and castles...
		if ($character == $settlement->getOwner() || $character == $settlement->getSteward()) {
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
			foreach ($settlement->getBuildings() as $bldg) {
				$name = $bldg->getType()->getName();
				if ($name == 'Library') {
					$rights[] = 'library';
				}
				if ($name == 'Inn') {
					$rights[] = 'inn';
				}
				if ($name == 'Tavern') {
					$rights[] = 'tavern';
				}
				if ($name == 'Arena') {
					$rights[] = 'arena';
				}
				if ($name == 'Blacksmith') {
					$rights[] = 'smith';
				}
				if ($name == 'List Field') {
					$rights[] = 'list field';
				}
				if ($name == 'Racetrack') {
					$rights[] = 'track';
				}
				if ($name == 'Temple') {
					$rights[] = 'temple';
				}
				if ($name == 'Warehouse') {
					$rights[] = 'warehouse';
				}
				if ($name == 'Tournament Grounds') {
					$rights[] = 'tournament';
				}
				if ($name == 'Academy') {
					$rights[] = 'academy';
				}
			}
		} else {
			$rights[] = 'outside';
		}

		$realm = $settlement->getRealm();
		foreach ($settlement->getCapitalOf() as $capitals) {
			if ($capitals->findRulers()->contains($character)) {
				$rights[] = 'ruler';
				break;
			}
		}
		$diplomacy = $character->findForeignAffairsRealms(); #Returns realms or null.
		if ($diplomacy) {
			$rights[] = 'ambassador';
		}
		/* Disabling this until I can update ports to be more porty and tie into docks.
		if ($settlement->getGeoData()->getCoast() && $settlement->hasBuildingNamed('Dockyard')) {
			$rights[] = 'port';
		}
		*/

		if ($character->getHouse() && $character->getHouse()->getHead() == $character) {
			$rights[] = 'dynasty head';
		}

		# Economy checks.
		$econ = $this->get('economy');
		if ($econ->checkSpecialConditions($settlement, 'mine')) {
			$rights[] = 'metals';
		}
		if ($econ->checkSpecialConditions($settlement, 'quarry')) {
			$rights[] = 'stone';
		}
		if ($econ->checkSpecialConditions($settlement, 'lumber yard')) {
			$rights[] = 'forested';
		}


		#Now generate the list of things we can build!
		$query = $this->getDoctrine()->getManager()->createQuery("select p from BM2SiteBundle:PlaceType p where (p.requires in (:rights) OR p.requires IS NULL) AND p.visible = TRUE")->setParameter('rights', $rights);


		$form = $this->createForm(new PlaceNewType($query->getResult(), $character->findRealms()));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$em = $this->getDoctrine()->getManager();
			$data = $form->getData();
			$fail = $this->checkPlaceNames($form, $data['name'], $data['formal_name']);
			if (!$fail && $this->get('geography')->checkPlacePlacement($character)) {
				$fail = TRUE; #You shouldn't even have access but players will be players, best check anyways.
				$this->addFlash('error', $this->get('translator')->trans('unavailable.placestooclose', [], 'messages'));
			}
			if (!$fail && $data['type']->getRequires()=='ruler') {
				if (!$character->findRulerships()->contains($data['realm'])) {
					$fail = TRUE;
					$this->addFlash('error', $this->get('translator')->trans('unavailable.notrulerofthatrealm', [], 'messages'));
				}
			}
			if (!$fail && $data['type']->getRequires()=='ambassador') {
				if ($character->findRealms()->isEmpty()) {
					$fail = TRUE;
					$this->addFlash('error', $this->get('translator')->trans('unavailable.norealm', [], 'messages'));
				}
			}
			if (!$fail) {
				$place = new Place();
				$this->getDoctrine()->getManager()->persist($place);
				$place->setName($data['name']);
				$place->setFormalName($data['formal_name']);
				$place->setShortDescription($data['short_description']);
				$place->setCreator($character);
				$place->setType($data['type']);
				$place->setRealm($data['realm']);
				$place->setDestroyed(false);
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
					$em->flush(); #We need the above to set the below and do relations
					$place->setGeoMarker($feat);
					$place->setLocation($loc);
					#Arguably, we could just get location from the geofeature, but this leaves more possibilities open.
					$place->setGeoData($geoData);
				}
				$place->setVisible($data['type']->getVisible());
				if ($data['type'] != 'embassy' && $data['type'] != 'capital') {
					$place->setActive(true);
				} else {
					$place->setActive(false);
				}
				if ($data['type'] != 'capital') {
					$place->setOwner($character);
				}
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
				$this->getDoctrine()->getManager()->flush();
				$this->addFlash('notice', $this->get('translator')->trans('new.success', ["%name%"=>$place->getName()], 'places'));
				return $this->redirectToRoute('maf_place_actionable');
			}
		}

		return $this->render('Place/new.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{id}/transfer", requirements={"id"="\d+"}, name="maf_place_transfer")
	  */
	public function transferAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('placeTransferTest', false, true, false, $place);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new InteractionType('placetransfer',
			$this->get('geography')->calculateInteractionDistance($character),
			$character
		));
		$form->handleRequest($request);

		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			if ($data['target'] != $character) {
				$place->setOwner($data['target']);

				$this->get('history')->logEvent(
					$place,
					'event.place.newowner',
					array('%link-character%'=>$data['target']->getId()),
					History::MEDIUM, true, 20
				);
				if ($place->getSettlement()) {
					$this->get('history')->logEvent(
						$data['target'],
						'event.character.recvdplace',
						array('%link-settlement%'=>$place->getSettlement()->getId()),
						History::MEDIUM, true, 20
					);
				}
				foreach ($place->getVassals() as $vassal) {
					$vassal->setOathCurrent(false);
					$this->get('history')->logEvent(
						$vassal,
						'politics.oath.notcurrent2',
						array('%link-place%'=>$place->getId()),
						History::HIGH, true
					);
				}
				$this->addFlash('notice', $this->get('translator')->trans('control.placetransfer.success', ["%name%"=>$data['target']->getName()], 'actions'));
				$this->getDoctrine()->getManager()->flush();
				return $this->redirectToRoute('maf_place_actionable');
			}
		}

		return $this->render('Place/transfer.html.twig', [
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/{id}/manage", requirements={"id"="\d+"}, name="maf_place_manage")
	  */
	public function manageAction(Place $id, Request $request) {
		$place = $id;

		if ($place->getType()->getName() == 'embassy') {
			$character = $this->get('dispatcher')->gateway('placeManageEmbassyTest', false, true, false, $place);
			$type = 'embassy';
		} elseif ($place->getType()->getName() == 'capital') {
			$character = $this->get('dispatcher')->gateway('placeManageRulersTest', false, true, false, $place);
			$type = 'capital';
		} else {
			$type = 'generic';
			$character = $this->get('dispatcher')->gateway('placeManageTest', false, true, false, $place);
		}

		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if ($oldDescription = $place->getDescription()) {
			$oldDescription = $place->getDescription()->getText();
		} else {
			$oldDescription = null;
		}

		$form = $this->createForm(new PlaceManageType($oldDescription, $type, $place, $character));
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
				if ($oldDescription != $data['description']) {
					$this->get('description_manager')->newDescription($place, $data['description'], $character);
				}
				$pol = $this->get('politics');
				if ($place->getRealm() != $data['realm']) {
					$pol->changePlaceRealm($place, $data['realm'], 'change');
				}
				if ($type=='embassy') {
					if ($place->getHostingRealm() != $data['hosting_realm']) {
						$place->setHostingRealm($data['hosting_realm']);
						$place->setOwningRealm(null);
						$place->setAmbassador(null);
					}
					if ($place->getOwningRealm() != $data['owning_realm']) {
						$place->setOwningRealm($data['owning_realm']);
						$place->setAmbassador(null);
					}
					if ($place->getAmbassador() != $data['ambassador']) {
						$place->setAmbassador($data['ambassador']);
					}
				}

				$this->getDoctrine()->getManager()->flush();
				$this->addFlash('notice', $this->get('translator')->trans('place.manage.success', array(), 'places'));
				return $this->redirectToRoute('maf_place_actionable');
			}
		}

		return $this->render('Place/manage.html.twig', [
			'place'=>$place,
			'form'=>$form->createView()
		]);
	}

	#TODO: Combine this and checkRealmNames into a single thing in a HelperService.
	private function checkPlaceNames($form, $name, $formalname, $me=null) {
		$fail = false;
		$em = $this->getDoctrine()->getManager();
		$allplaces = $em->getRepository('BM2SiteBundle:Place')->findAll();
		foreach ($allplaces as $other) {
			if ($other == $me || $other->getDestroyed()) continue;
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
	  * @Route("/{id}/changeoccupant", requirements={"id"="\d+"}, name="maf_place_occupant")
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
			if ($data['target']) {
				$act = new Action;
				$act->setType('place.occupant')->setCharacter($character);
				$act->setTargetPlace($place)->setTargetCharacter($data['target']);
				$act->setBlockTravel(true);
				$complete = new \DateTime("+1 hour");
				$act->setComplete($complete);
				$this->get('action_manager')->queue($act);
				$this->addFlash('notice', $this->get('translator')->trans('event.place.occupant.start', ["%time%"=>$complete->format('Y-M-d H:i:s')], 'communication'));
				return $this->redirectToRoute('bm2_actions');
			}
		}

		return $this->render('Place/occupant.html.twig', [
			'place'=>$place, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/{id}/changeoccupier", requirements={"id"="\d+"}, name="maf_place_occupier")
	  */
	public function changeOccupierAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('placeChangeOccupierTest', false, true, false, $place);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new RealmSelectType($character->findRealms(), 'changeoccupier'));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$targetrealm = $data['target'];

			if ($place->getOccupier() == $targetrealm) {
				$result = 'same';
			} else {
				$result = 'success';
				$this->get('politics')->changePlaceOccupier($character, $place, $targetrealm);
				$this->getDoctrine()->getManager()->flush();
			}
			$this->addFlash('notice', $this->get('translator')->trans('event.settlement.occupier.'.$result, [], 'communication'));
			return $this->redirectToRoute('bm2_actions');
		}
		return $this->render('Place/occupier.html.twig', [
			'place'=>$place, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/{id}/occupation/end", requirements={"id"="\d+"}, name="maf_place_occupation_end")
	  */
	public function occupationEndAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('placeOccupationEndTest', false, true, false, $place);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new AreYouSureType());
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
                        $this->get('politics')->endOccupation($place, 'manual');
			$this->getDoctrine()->getManager()->flush();
                        $this->addFlash('notice', $this->get('translator')->trans('control.occupation.ended', array(), 'actions'));
                        return $this->redirectToRoute('bm2_actions');
                }
		return $this->render('Place/occupationend.html.twig', [
			'place'=>$place, 'form'=>$form->createView()
		]);
	}


	/**
	  * @Route("/{place}/newplayer", requirements={"realm"="\d+"}, name="maf_place_newplayer")
	  */
	public function newplayerAction(Place $place, Request $request) {
		$character = $this->get('dispatcher')->gateway('placeNewPlayerInfoTest', false, true, false, $place);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$desc = $place->getSpawnDescription();
		if ($desc) {
			$text = $desc->getText();
		} else {
			$text = null;
		}
		$form = $this->createForm(new DescriptionNewType($text));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			if ($text != $data['text']) {
				$desc = $this->get('description_manager')->newSpawnDescription($place, $data['text'], $character);
			}
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('control.description.success', array(), 'actions'));
		}
		return $this->render('Place/newplayer.html.twig', [
			'place'=>$place, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/{place}/spawn", requirements={"place"="\d+"}, name="maf_place_spawn_toggle")
	  */
	public function placeSpawnToggleAction(Place $place) {
		$character = $this->get('dispatcher')->gateway('placeSpawnToggleTest', false, true, false, $place);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		if($place->getSpawn()) {
			$em->remove($place->getSpawn());
			$this->addFlash('notice', $this->get('translator')->trans('control.spawn.success.stop', ["%name%"=>$place->getName()], 'actions'));
		} else {
			if($place->getType()->getName() == 'home' && $place->getHouse()) {
				if ($old = $place->getHouse()->getSpawn()) {
					$em->remove($old);
					$em->flush();
				}
				#This need to be after the flush above or we get entity persistence errors from doctrine for creating this and not persisting it.
				$spawn = new Spawn();
				$spawn->setPlace($place);
				$spawn->setHouse($place->getHouse());
			} else {
				$spawn = new Spawn();
				$spawn->setPlace($place);
				$spawn->setRealm($place->getRealm());
			}
			$em->persist($spawn);
			$spawn->setActive(false);
			$this->addFlash('notice', $this->get('translator')->trans('control.spawn.success.start', ["%name%"=>$place->getName()], 'actions'));
		}
		$em->flush();
		return new RedirectResponse($this->generateUrl('maf_place_actionable').'#'.$place->getId());
	}

	/**
	  * @Route("/{id}/destroy", requirements={"id"="\d+"}, name="maf_place_destroy")
	  */
	public function destroyAction(Place $id, Request $request) {
		$place = $id;
		if ($place->getType()->getName() == 'capital') {
			$character = $this->get('dispatcher')->gateway('placeManageRulersTest', false, true, false, $place);
		} else {
			# No exception for embassies here.
			$character = $this->get('dispatcher')->gateway('placeManageTest', false, true, false, $place);
		}
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new AreYouSureType());
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
			$em = $this->getDoctrine()->getManager();
                        $place->setDestroyed(true);
			if ($spawn = $place->getSpawn()) {
				$em->remove($spawn);
			}
			$this->get('history')->logEvent(
				$place,
				'event.place.destroyed',
				array('%link-character%'=>$character->getId()),
				History::HIGH, true
			);

			$em->flush();
                        return $this->redirectToRoute('maf_place_actionable');
                }
		return $this->render('Place/destroy.html.twig', [
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/{id}/addAssoc", requirements={"id"="\d+"}, name="maf_place_assoc_add")
	  */
	public function addAssocAction(Place $id, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('placeAddAssocTest', false, true, false, $place);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$assocs = new ArrayCollection();
		foreach($character->getAssociationMemberships() as $mbr) {
			if ($rank = $mbr->getRank()) {
				if ($rank->canBuild()) {
					$assocs->add($rank->getAssociation());
				}
			}
		}

		$form = $this->createForm(new AssocSelectType($assocs, 'addToPlace', $character));
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			$this->get('association_manager')->newLocation($data['target'], $place);
			$this->get('history')->logEvent(
				$place,
				'event.place.assoc.new',
				array('%link-character%'=>$character->getId(), '%link-assoc%'=>$data['target']->getId()),
				History::HIGH, true
			);
			$this->get('history')->logEvent(
				$place,
				'event.assoc.place.new',
				array('%link-character%'=>$character->getId(), '%link-place%'=>$place->getId()),
				History::HIGH, true
			);
			$this->getDoctrine()->getManager()->flush();

                        return $this->redirectToRoute('maf_place_actionable');
                }
		return $this->render('Place/addAssoc.html.twig', [
			'place'=>$place,
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/{id}/evictAssoc/{assoc}", requirements={"id"="\d+", "assoc"="\d+"}, name="maf_place_assoc_evict")
	  */
	public function evictAssocAction(Place $id, Association $assoc, Request $request) {
		$place = $id;
		$character = $this->get('dispatcher')->gateway('placeEvictAssocTest', false, true, false, [$place, $assoc]);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new AreYouSureType());
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
			$this->get('association_manager')->removeLocation($assoc, $place);
			$this->get('history')->logEvent(
				$place,
				'event.place.assoc.evict',
				array('%link-character%'=>$character->getId(), '%link-assoc%'=>$assoc->getId()),
				History::HIGH, true
			);
			$this->get('history')->logEvent(
				$place,
				'event.assoc.place.evict',
				array('%link-character%'=>$character->getId(), '%link-place%'=>$place->getId()),
				History::HIGH, true
			);
			$this->getDoctrine()->getManager()->flush();

                        return $this->redirectToRoute('maf_place_actionable');
                }
		return $this->render('Place/evictAssoc.html.twig', [
			'place'=>$place,
			'assoc'=>$assoc,
			'form'=>$form->createView()
		]);
	}

}
