<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;
use BM2\SiteBundle\Entity\House;

use BM2\SiteBundle\Form\SoldierFoodType;

use BM2\SiteBundle\Service\Appstate;
use BM2\SiteBundle\Service\History;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


/**
 * @Route("/gamerequest")
 */
class GameRequestController extends Controller {

	private $house;

	private function security(Character $char, GameRequest $id) {
		/* Most other places in the game have a single dispatcher call to do security. Unfortunately, for GameRequests, it's not that easy, as this file handles *ALL* processing of the request itself.
		That means, we need a way to check whether or not a given user has rights to do things, when the things in questions could vary every time this controller is called.
		Yes, I realize this is a massive bastardization of how Symfony says Symfony is supposed to handle things, mainly that they say this should be in a Service as it's all back-end stuff, but if it works, it works.
		Maybe in the future, when I'm looking to refine things, we can move it around then. Really, all that'd change is these being moved to the service and returning a true or false--personally I like all the logic being in one place though.*/
		$result;
		switch ($id->getType()) {
			case 'soldier.food':
				if ($id->getToSettlement()->getOwner() === $char || $id->getToSettlement()->getSteward() === $char) {
					$result = true;
				} else {
					$result = false;
				}
				break;
			case 'assoc.join':
				$mbrs = $char->getAssociationMemberships();
				$result = false;
				if ($mbrs->count() > 0) {
					foreach ($mbrs as $mbr) {
						$rank = $mbr->getRank();
						if ($mbr->getAssociation() === $id->getToAssociation() && $rank && $rank->getManager()) {
							$result = true;
							break;
						}
					}
				}
				break;
			case 'house.subcreate':
			case 'house.cadet':
			case 'house.uncadet':
			case 'house.join':
				if ($char->getHeadOfHouse() != $id->getToHouse()) {
					$result = false;
				} else {
					$result = true;
				}
				break;
			case 'oath.offer':
				if ($id->getToSettlement() && ($id->getToSettlement()->getOwner() != $char)) {
					$result = false;
				} elseif ($id->getToPlace()) {
					if ($id->getToPlace()->getType() != 'embassy' && $id->getToPlace()->getOwner() != $char) {
						$result = false;
					} elseif ($id->getToPlace()->getType() == 'embassy' && $id->getToPlace()->getAmbassador() != $char) {
						$result = false;
					}
				} elseif ($id->getToPosition() && !$id->getToPosition()->getHolders()->contains($char)) {
					$result = false;
				} else {
					$result = true;
				}
				break;
			case 'realm.join':
				if (in_array($char, $id->getToRealm()->findRulers()->toArray())) {
					$result = true;
				} else {
					$result = false;
				}
				break;
		}
		return $result;
	}

	/**
	  * @Route("/{id}/approve", name="bm2_gamerequest_approve", requirements={"id"="\d+"})
	  */

	public function approveAction(Request $request, GameRequest $id, $route = 'bm2_gamerequest_manage') {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		if ($request->query->get('route')) {
			$route = $request->query->get('route');
		}
		$em = $this->getDoctrine()->getManager();
		$conv = $this->get('conversation_manager');
		# Are we allowed to act on this GR? True = yes. False = no.
		$allowed = $this->security($character, $id);
		# Do try to keep this switch and the denyAction switch in the order of most expected request. It'll save processing time.
		switch($id->getType()) {
			case 'soldier.food':
				if ($allowed) {
					$settlement = $id->getToSettlement();
					$character = $id->getFromCharacter();
					$this->get('history')->logEvent(
						$settlement,
						'event.military.supplier.food.start',
						array('%link-character%'=>$id->getFromCharacter()->getId()),
						History::LOW, true
					);
					if ($character == $settlement->getOwner()) {
						$this->get('history')->logEvent(
							$id->getFromCharacter(),
							'event.military.supplied.food.start',
							array('%link-character%'=>$settlement->getOwner()->getId(), '%link-settlement%'=>$settlement->getId()),
							History::LOW, true
						);
					} elseif ($settlement->getSteward()) {
						$this->get('history')->logEvent(
							$id->getFromCharacter(),
							'event.military.supplied.food.start',
							array('%link-character%'=>$settlement->getSteward()->getId(), '%link-settlement%'=>$settlement->getId()),
							History::LOW, true
						);
					}
					$id->setAccepted(true);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('military.settlement.food.supplied', array('%character%'=>$id->getFromCharacter()->getName(), '%settlement%'=>$id->getToSettlement()->getName()), 'actions'));
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.notlord');
				}
				break;
			case 'assoc.join':
				if ($allowed) {
					$assoc = $id->getToAssociation();
					$character = $id->getFromCharacter();
					$this->get('association_manager')->updateMember($assoc, null, $character, false);
					$this->get('history')->openLog($assoc, $character);
					$this->get('history')->logEvent(
						$assoc,
						'event.assoc.newmember',
						array('%link-character%'=>$id->getFromCharacter()->getId()),
						History::MEDIUM, true
					);
					$this->get('history')->logEvent(
						$id->getFromCharacter(),
						'event.character.joinassoc.approved',
						array('%link-assoc%'=>$assoc->getId()),
						History::ULTRA, true
					);
					$em->remove($id);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('assoc.requests.manage.applicant.approved', array('%character%'=>$character->getName(), '%assoc%'=>$assoc->getName()), 'orgs'));
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.nothead');
				}
				break;
			case 'house.join':
				if ($allowed) {
					$house = $id->getToHouse();
					$character = $id->getFromCharacter();
					$character->setHouse($house);
					$character->setHouseJoinDate(new \DateTime("now"));
					$this->get('history')->openLog($house, $character);
					$this->get('history')->logEvent(
						$house,
						'event.house.newmember',
						array('%link-character%'=>$id->getFromCharacter()->getId()),
						History::MEDIUM, true
					);
					$this->get('history')->logEvent(
						$id->getFromCharacter(),
						'event.character.joinhouse.approved',
						array('%link-house%'=>$house->getId()),
						History::ULTRA, true
					);
					$em->remove($id);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('house.manage.applicant.approved', array('%character%'=>$id->getFromCharacter()->getName()), 'politics'));
					if ($route == 'maf_house_applicants') {
						return $this->redirectToRoute($route, array('house'=>$house->getId()));
					} else {
						return $this->redirectToRoute($route);
					}
				} else {
					throw new AccessDeniedHttpException('unavailable.nothead');
				}
				break;
			case 'house.subcreate':
				if ($allowed) {
					$id->setAccepted(true);
					$this->get('history')->logEvent(
						$id->getFromCharacter(),
						'event.character.createcadet.accepted',
						array('%link-house%'=>$id->getToHouse()->getId()),
						History::HIGH, true
					);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('house.manage.subcreate.approved', array('%character%'=>$id->getFromCharacter()->getName()), 'politics'));
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.nothead');
				}
				break;
			case 'oath.offer':
				if ($allowed) {
					$character = $id->getFromCharacter();
					if ($to = $id->getToSettlement()) {
						$thing = 'settlement';
					} elseif ($to = $id->getToPlace()) {
						$thing = 'place';
					} elseif ($to = $id->getToPosition()) {
						$thing = 'realmposition';
					}
					if ($alleg = $character->findAllegiance()) {
						$this->get('politics')->breakoath($character, $alleg, $to, $thing);
					}
					if ($id->getToSettlement()) {
						$settlement = $id->getToSettlement();
						$character->setLiegeLand($settlement);
						$character->setOathCurrent(TRUE);
						$character->setRealm(NULL);
						$this->get('history')->logEvent(
							$settlement,
							'event.settlement.newknight',
							array('%link-character%'=>$id->getFromCharacter()->getId()),
							History::HIGH, true
						);
						$this->get('history')->logEvent(
							$character,
							'event.character.newliege.land',
							array('%link-settlement%'=>$settlement->getId()),
							History::ULTRA, true
						);
						$this->addFlash('notice', $this->get('translator')->trans('oath.settlement.approved', array('%name%'=>$id->getFromCharacter()->getName()), 'politics'));
						$em->remove($id);
						$em->flush();

						list($conv, $supConv) = $conv->sendExistingCharacterMsg(null, $settlement, null, null, $character);
						return $this->redirectToRoute($route);
					}
					if ($id->getToPlace()) {
						$place = $id->getToPlace();
						$character->setLiegePlace($place);
						$character->setOathCurrent(TRUE);
						$character->setRealm(NULL);
						$this->get('history')->logEvent(
							$place,
							'event.place.newknight',
							array('%link-character%'=>$id->getFromCharacter()->getId()),
							History::HIGH, true
						);
						$this->get('history')->logEvent(
							$character,
							'event.character.newliege.place',
							array('%link-place%'=>$place->getId()),
							History::ULTRA, true
						);
						$this->addFlash('notice', $this->get('translator')->trans('oath.place.approved', array('%name%'=>$id->getFromCharacter()->getName()), 'politics'));
						$em->remove($id);
						$em->flush();

						list($conv, $supConv) = $conv->sendExistingCharacterMsg(null, null, $place, null, $character);
						return $this->redirectToRoute($route);
					}
					if ($id->getToPosition()) {
						$pos = $id->getToPosition();
						$character->setLiegePosition($pos);
						$character->setOathCurrent(TRUE);
						/* FIXME: Positions don't currently have logs. Should they? Hm.
						$this->get('history')->logEvent(
							$pos,
							'event.position.newknight',
							array('%link-character%'=>$id->getFromCharacter()->getId()),
							History::HIGH, true
						);
						*/
						$this->get('history')->logEvent(
							$character,
							'event.character.newliege.position',
							array('%link-realmposition%'=>$pos->getId()),
							History::ULTRA, true
						);
						$this->addFlash('notice', $this->get('translator')->trans('oath.position.approved', array('%name%'=>$id->getFromCharacter()->getName()), 'politics'));
						$em->remove($id);
						$em->flush();

						list($conv, $supConv) = $conv->sendExistingCharacterMsg(null, null, null, $pos, $character);
						return $this->redirectToRoute($route);
					}
				} else {
					if ($id->getToSettlement()) {
						throw new AccessDeniedHttpException('unavailable.notyours2');
					}
					if ($id->getToPlace()) {
						throw new AccessDeniedHttpException('unavailable.notowner');
					}
					if ($id->getToPosition()) {
						throw new AccessDeniedHttpException('unavailable.notholder', ["%name%"=>$id->getToPosition()->getName()]);
					}
				}
				break;
			case 'realm.join':
				if ($allowed) {
					$target = $id->getToRealm();
					$realm = $id->getFromRealm();
					$query = $em->createQuery("DELETE FROM BM2SiteBundle:GameRequest r WHERE r.type = 'realm.join' AND r.id != :id AND r.from_realm = :realm");
					$query->setParameters(['id'=>$id->getId(), 'realm'=>$realm->getId()]);

					$realm->setSuperior($target);
					$target->addInferior($realm);

					$this->get('history')->logEvent(
						$realm,
						'event.realm.joined',
						array('%link-realm%'=>$target->getId()),
						History::HIGH
					);
					$this->get('history')->logEvent(
						$target,
						'event.realm.wasjoined',
						array('%link-realm%'=>$realm->getId()),
						History::MEDIUM
					);
					if ($target->findUltimate() != $target) {
						$this->get('history')->logEvent(
							$target,
							'event.realm.wasjoined',
							array('%link-realm%'=>$target->getId(), '%link-realm-2%'=>$realm->getId()),
							History::MEDIUM
						);
					}

					$this->addFlash(
						'notice',
						$this->get('translator')->trans(
							'diplomacy.join.approved', [
								'%name%'=>$id->getFromRealm()->getName(),
								'%name2%'=>$id->getToRealm()->getName()
							], 'politics'
						)
					);
					$query->execute();
					$em->remove($id);
					$em->flush();
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.notruler');
				}
				break;
			case 'house.cadet':
				if ($allowed) {
					$cadet = $id->getFromHouse();
					$sup = $id->getToHouse();
					$sup->addCadet($cadet);
					$cadet->setSuperior($sup);
					$character = $id->getFromCharacter();
					foreach ($cadet->getMembers() as $mbr) {
						if ($mbr->isAlive()) {
							$this->get('history')->openLog($sup, $mbr);
						}
					}
					$this->get('history')->logEvent(
						$sup,
						'event.house.newcadet',
						array('%link-house%'=>$cadet->getId()),
						History::HIGH, true
					);
					$this->get('history')->logEvent(
						$cadet,
						'event.house.joinhouse.approved',
						array('%link-house%'=>$sup->getId()),
						History::ULTRA, true
					);
					$em->remove($id);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('house.manage.cadet.approved', array('%house%'=>$cadet->getName(), '%character%'=>$character->getName()), 'politics'));
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.nothead');
				}
				break;
			case 'house.uncadet':
				if ($allowed) {
					$cadet = $id->getFromHouse();
					$sup = $id->getToHouse();
					$sup->removeCadet($cadet);
					$cadet->setSuperior(null);
					$character = $id->getFromCharacter();
					foreach ($cadet->getMembers() as $mbr) {
						if ($mbr->isAlive()) {
							$this->get('history')->closeLog($sup, $mbr);
						}
					}
					$this->get('history')->logEvent(
						$sup,
						'event.house.lostcadet',
						array('%link-house%'=>$cadet->getId()),
						History::HIGH, true
					);
					$this->get('history')->logEvent(
						$cadet,
						'event.house.leavehouse.approved',
						array('%link-house%'=>$sup->getId()),
						History::ULTRA, true
					);
					$em->remove($id);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('house.manage.uncadet.approved', array('%house%'=>$cadet->getName(), '%character%'=>$character->getName()), 'politics'));
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.nothead');
				}
				break;
		}

		return new Response();
	}

	/**
	  * @Route("/{id}/deny", name="bm2_gamerequest_deny", requirements={"id"="\d+"})
	  */

	public function denyAction(Request $request, GameRequest $id, $route = 'bm2_gamerequest_manage') {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		if ($request->query->get('route')) {
			$route = $request->query->get('route');
		}
		$em = $this->getDoctrine()->getManager();
		# Are we allowed to act on this GR? True = yes. False = no.
		$allowed = $this->security($character, $id);
		switch($id->getType()) {
			case 'soldier.food':
				if ($allowed) {
					$settlement = $id->getToSettlement();
					# Create event notice for denied character.
					$this->get('history')->logEvent(
						$id->getFromCharacter(),
						'event.military.supplied.food.rejected',
						array('%link-settlement%'=>$settlement->getId()),
						History::LOW, true
					);
					# Set accepted to false so we can hang on to this to prevent spamming. These get removed after a week, hence the new expiration date.
					$id->setAccepted(FALSE);
					$timeout = new \DateTime("now");
					$id->setExpires($timeout->add(new \DateInterval("P7D")));
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('military.settlement.food.rejected', array('%character%'=>$id->getFromCharacter()->getName(), '%settlement%'=>$id->getToSettlement()->getName()), 'actions'));
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.notlord');
				}
				break;
			case 'assoc.join':
				if ($allowed) {
					$assoc = $id->getToAssociation();
					$char = $id->getFromCharacter();
					$this->get('history')->logEvent(
						$char,
						'event.character.joinassoc.denied',
						array('%link-assoc%'=>$assoc->getId()),
						History::HIGH, true
					);
					$em->remove($id);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('assoc.requests.manage.applicant.denied', array('%character%'=>$char->getName(), '%assoc%'=>$assoc->getName()), 'orgs'));
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.notmanager');
				}
				break;
			case 'house.join':
				if ($allowed) {
					$house = $id->getToHouse();
					$query = $em->createQuery("DELETE FROM BM2SiteBundle:GameRequest r WHERE r.type = 'house.join' AND r.id != :id AND r.from_character = :char");
					$query->setParameters(['id'=>$id->getId(), 'char'=>$character->getId()]);
					$this->get('history')->logEvent(
						$id->getFromCharacter(),
						'event.character.joinhouse.denied',
						array('%link-house%'=>$house->getId()),
						History::HIGH, true
					);
					$em->remove($id);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('house.manage.applicant.denied', array('%character%'=>$id->getFromCharacter()->getName()), 'politics'));
					$query->execute();
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.nothead');
				}
				break;
			case 'house.subcreate':
				if ($allowed) {
					$this->get('history')->logEvent(
						$id->getFromCharacter(),
						'event.character.createcadet.denied',
						array('%link-house%'=>$id->getToHouse()->getId()),
						History::HIGH, true
					);
					$em->remove($id);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('house.manage.subcreate.denied', array('%character%'=>$id->getFromCharacter()->getName()), 'politics'));
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.nothead');
				}
				break;
			case 'oath.offer':
				if ($allowed) {
					$character = $id->getFromCharacter();
					$query = $em->createQuery("DELETE FROM BM2SiteBundle:GameRequest r WHERE r.type = 'oath.offer' AND r.id != :id AND r.from_character = :char");
					$query->setParameters(['id'=>$id->getId(), 'char'=>$character->getId()]);
					if ($settlement = $id->getToSettlement()) {
						$this->get('history')->logEvent(
							$settlement,
							'event.settlement.rejectknight',
							array('%link-character%'=>$id->getFromCharacter()->getId()),
							History::HIGH, true
						);
						$this->get('history')->logEvent(
							$character,
							'event.character.liegerejected.land',
							array('%link-settlement%'=>$settlement->getId()),
							History::ULTRA, true
						);
						$this->addFlash('notice', $this->get('translator')->trans('oath.settlement.rejected', array('%name%'=>$id->getFromCharacter()->getName()), 'politics'));
						$em->remove($id);
						$em->flush();
						$query->execute();
						return $this->redirectToRoute($route);
					}
					if ($place = $id->getToPlace()) {
						$this->get('history')->logEvent(
							$place,
							'event.place.rejectknight',
							array('%link-character%'=>$id->getFromCharacter()->getId()),
							History::HIGH, true
						);
						$this->get('history')->logEvent(
							$character,
							'event.character.liegerejected.place',
							array('%link-place%'=>$place->getId()),
							History::ULTRA, true
						);
						$this->addFlash(
							'notice',
							$this->get('translator')->trans(
								'oath.place.rejected',
								array('%name%'=>$id->getFromCharacter()->getName()),
								'politics'
							)
						);
						$em->remove($id);
						$em->flush();
						$query->execute();
						return $this->redirectToRoute($route);
					}
					if ($pos = $id->getToPosition()) {
						/*$this->get('history')->logEvent(
							$pos,
							'event.position.rejectknight',
							array('%link-character%'=>$id->getFromCharacter()->getId()),
							History::HIGH, true
						);*/
						$this->get('history')->logEvent(
							$character,
							'event.character.liegerejected.position',
							array('%link-realmposition%'=>$pos->getId()),
							History::ULTRA, true
						);
						$this->addFlash(
							'notice',
							$this->get('translator')->trans(
								'oath.position.rejected',
								array('%name%'=>$id->getFromCharacter()->getName()),
								'politics'
							)
						);
						$em->remove($id);
						$em->flush();
						$query->execute();
						return $this->redirectToRoute($route);
					}
				} else {
					if ($id->getToSettlement()) {
						throw new AccessDeniedHttpException('unavailable.notyours2');
					}
					if ($id->getToPlace()) {
						throw new AccessDeniedHttpException('unavailable.notowner');
					}
					if ($id->getToPosition()) {
						throw new AccessDeniedHttpException('unavailable.notholder', ["%name%"=>$id->getToPosition()->getName()]);
					}
				}
				break;
			case 'realm.join':
				if ($allowed) {
					$target = $id->getToRealm();
					$realm = $id->getFromRealm();
					$this->get('history')->logEvent(
						$realm,
						'event.realm.joinrejected',
						array('%link-realm%'=>$target->getId()),
						History::MEDIUM
					);

					$this->addFlash(
						'notice',
						$this->get('translator')->trans(
							'diplomacy.join.denied', [
								'%name%'=>$id->getFromRealm()->getName(),
								'%name2%'=>$id->getToRealm()->getName()
							], 'politics'
						)
					);
					$em->remove($id);
					$em->flush();
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.notruler');
				}
				break;
			case 'house.cadet':
				if ($allowed) {
					$house = $id->getToHouse();
					$query = $em->createQuery("DELETE FROM BM2SiteBundle:GameRequest r WHERE r.type = 'house.cadet' AND r.id != :id AND r.from_house = :house");
					$query->setParameters(['id'=>$id->getId(), 'house'=>$id->getFromHouse()->getId()]);
					$this->get('history')->logEvent(
						$id->getFromHouse(),
						'event.house.joinhouse.denied',
						array('%link-house%'=>$house->getId()),
						History::HIGH, true
					);
					$em->remove($id);
					$em->flush();
					$query->execute();
					$this->addFlash('notice', $this->get('translator')->trans('house.cadet.denied', array('%character%'=>$id->getFromHouse()->getName()), 'politics'));
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.nothead');
				}
				break;
			case 'house.uncadet':
				if ($allowed) {
					$house = $id->getToHouse();
					$this->get('history')->logEvent(
						$id->getFromHouse(),
						'event.house.leavehouse.denied',
						array('%link-house%'=>$house->getId()),
						History::MEDIUM, true
					);
					$em->remove($id);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('house.uncadet.denied', array('%character%'=>$id->getFromHouse()->getName()), 'politics'));
					return $this->redirectToRoute($route);
				} else {
					throw new AccessDeniedHttpException('unavailable.nothead');
				}
				break;
		}

		return new Response();
	}

	/**
	  * @Route("/manage", name="bm2_gamerequest_manage")
	  */

	public function manageAction() {
		$character = $this->get('dispatcher')->gateway('personalRequestsManageTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$requests = $this->get('game_request_manager')->findAllManageableRequests($character, false); # Not accepted/rejected
		$approved = $this->get('game_request_manager')->findAllManageableRequests($character, true); # Only accepted

		return $this->render('GameRequest/manage.html.twig', [
			'gamerequests' => $requests,
			'approved' => $approved
		]);
	}

	/**
	  * @Route("/soldierfood", name="bm2_gamerequest_soldierfood")
	  */

	public function soldierfoodAction(Request $request) {
		# Get player character from security and check their access.
		$character = $this->get('dispatcher')->gateway('personalRequestSoldierFoodTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		# Get all character realms.
		$myRealms = $character->findRealms();
		$settlements = new ArrayCollection;

		foreach ($myRealms as $realm) {
			if ($realm->getCapital()) {
				$settlements->add($realm->getCapital());
			}
		}
		if ($liege = $character->findLiege()) {
			if ($liege instanceof Collection) {
				$lieges = $liege;
				foreach ($lieges as $liege) {
					foreach ($liege->getOwnedSettlements() as $settlement) {
						if ($settlement->getFeedSoldiers() && !$settlements->contains($settlement)) {
							$settlements->add($settlement);
						}
					}
				}
			} else {
				foreach ($liege->getOwnedSettlements() as $settlement) {
					if ($settlement->getFeedSoldiers() && !$settlements->contains($settlement)) {
						$settlements->add($settlement);
					}
				}
			}
		}
		if ($character->getInsideSettlement() && !$settlements->contains($character->getInsideSettlement())) {
			$settlements->add($character->getInsideSettlement());
		}
		$soldiers = 0;
		foreach ($character->getUnits() as $unit) {
			$soldiers += $unit->getSoldiers()->count();
		}

		$form = $this->createForm(new SoldierFoodType($settlements, $character));
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			# newRequestFromCharactertoSettlement ($type, $expires = null, $numberValue = null, $stringValue = null, $subject = null, $text = null, Character $fromChar = null, Settlement $toSettlement = null)
			$this->get('game_request_manager')->newRequestFromCharacterToSettlement('soldier.food', $data['expires'], $data['limit'], null, $data['subject'], $data['text'], $character, $data['target']);
			$this->addFlash('notice', $this->get('translator')->trans('request.soldierfood.sent', array('%settlement%'=>$data['target']->getName()), 'actions'));
			return $this->redirectToRoute('bm2_actions');
		}

		return $this->render('GameRequest/soldierfood.html.twig', [
			'form' => $form->createView(),
			'size' => $character->getEntourage()->count()+$soldiers
		]);
	}


}
