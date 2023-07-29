<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Election;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\RealmPosition;
use BM2\SiteBundle\Entity\RealmRelation;
use BM2\SiteBundle\Entity\Spawn;
use BM2\SiteBundle\Entity\Vote;
use BM2\SiteBundle\Form\ElectionType;
use BM2\SiteBundle\Form\InteractionType;
use BM2\SiteBundle\Form\DescriptionNewType;
use BM2\SiteBundle\Form\RealmCapitalType;
use BM2\SiteBundle\Form\RealmCreationType;
use BM2\SiteBundle\Form\RealmManageType;
use BM2\SiteBundle\Form\RealmOfficialsType;
use BM2\SiteBundle\Form\RealmPositionType;
use BM2\SiteBundle\Form\RealmRelationType;
use BM2\SiteBundle\Form\RealmRestoreType;
use BM2\SiteBundle\Form\RealmSelectType;
use BM2\SiteBundle\Form\SubrealmType;
use BM2\SiteBundle\Service\Appstate;
use BM2\SiteBundle\Service\History;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/*
	FIXME: some of this stuff should be moved to the new realm manager service
*/

/*
	TODO: ability to group up sub-realms into a larger sub-realm (i.e. insert a level)
*/

/**
 * @Route("/realm")
 */
class RealmController extends Controller {

	private $hierarchy=array();
	private $realm;

	private function gateway($realm=false, $test=false) {
		if ($realm) {
			$this->realm = $realm;
			$this->get('dispatcher')->setRealm($realm);
		}
		$character = $this->get('dispatcher')->gateway($test);
		if (! $character instanceof Character) {
			return $character;
		}
		if ($realm && !$test) {
			if (!$character->findRealms()->contains($realm)) {
				throw $this->createAccessDeniedException('actions::unavailable.notmember');
			}
		}
		return $character;
	}


	/**
	  * @Route("/{id}/view", name="bm2_realm", requirements={"id"="\d+"})
	  */
	public function viewAction(Realm $id) {
		$realm = $id;
		$character = $this->get('appstate')->getCharacter(false, true, true);
		# NOTE: Character onject checking not conducted because we don't need it.
		# $character isn't checked in a context that would require it to be NULL or an Object.

		$superrulers = array();

		$territory = $realm->findTerritory();
		$population = 0;
		$restorable = FALSE;
		foreach ($territory as $settlement) {
			$population += $settlement->getPopulation() + $settlement->getThralls();
		}

		if ($realm->getSuperior()) {
			$parentpoly =	$this->get('geography')->findRealmPolygon($realm->getSuperior());
			$superrulers = $realm->getSuperior()->findRulers();
		} else {
			$parentpoly = null;
		}

		$subpolygons = array();
		foreach ($realm->getInferiors() as $child) {
			$subpolygons[] = $this->get('geography')->findRealmPolygon($child);
		}

		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT r FROM BM2SiteBundle:RealmRelation r WHERE r.source_realm = :me OR r.target_realm = :me');
		$query->setParameter('me', $realm);

		$diplomacy = array();
		foreach ($query->getResult() as $relation) {
			if ($relation->getSourceRealm() == $realm) {
				$target = $relation->getTargetRealm();
				$side = 'we';
			} else {
				$target = $relation->getSourceRealm();
				$side = 'they';
			}
			$index = $target->getId();
			if (!isset($diplomacy[$index])) {
				$diplomacy[$index] = array('target'=>$target, 'we'=>null, 'they'=>null);
			}
			$diplomacy[$index][$side] = $relation->getStatus();
		}
		 foreach ($superrulers as $superruler) {
			if ($superruler == $character) {
				if (!$realm->getActive()) {
					$restorable = TRUE;
				}
			}
		}

		return $this->render('Realm/view.html.twig', [
			'realm' =>		$realm,
			'realmpoly' =>	$this->get('geography')->findRealmPolygon($realm),
			'parentpoly' => $parentpoly,
			'subpolygons' => $subpolygons,
			'settlements' =>	$territory->count(),
			'population'=>	$population,
			'area' =>		$this->get('geography')->calculateRealmArea($realm),
			'nobles' =>		$realm->findMembers()->count(),
			'diplomacy' =>	$diplomacy,
			'restorable' => $restorable
		]);
	}

	/**
	  * @Route("/new")
	  */
	public function newAction(Request $request) {
		$character = $this->gateway(false, 'hierarchyCreateRealmTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new RealmCreationType());

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$fail = $this->checkRealmNames($form, $data['name'], $data['formal_name']);
			if (!$fail) {
				// good name, create realm
				$realm = $this->get('realm_manager')->create($data['name'], $data['formal_name'], $data['type'], $character);
				$this->getDoctrine()->getManager()->flush();
				// and create the initial realm conversation, making sure our ruler is set up for the messaging system

				$topic = $realm->getName().' Announcements';
				$this->get('conversation_manager')->newConversation(null, null, $topic, null, null, $realm, 'announcements');
				$topic = $realm->getName().' General Discussion';
				$this->get('conversation_manager')->newConversation(null, null, $topic, null, null, $realm, 'general');

				$this->get('notification_manager')->spoolNewRealm($character, $realm);

				$this->get('appstate')->setSessionData($character); // update, because we changed our realm count
				return $this->redirectToRoute('bm2_site_realm_manage', array('realm'=>$realm->getId()));
			}
		}

		return $this->render('Realm/new.html.twig', [
			'form'=>$form->createView()
		]);
	}

	private function checkRealmNames($form, $name, $formalname, $me=null) {
		$fail = false;
		$em = $this->getDoctrine()->getManager();
		$allrealms = $em->getRepository('BM2SiteBundle:Realm')->findAll();
		foreach ($allrealms as $other) {
			if ($other == $me) continue;
			if (levenshtein($name, $other->getName()) < min(3, min(strlen($name), strlen($other->getName()))*0.75)) {
				$form->addError(new FormError($this->get('translator')->trans("realm.new.toosimilar.name"), null, array('%other%'=>$other->getName())));
				$fail=true;
			}
			if (levenshtein($formalname, $other->getFormalName()) <  min(5, min(strlen($formalname), strlen($other->getFormalName()))*0.75)) {
				$form->addError(new FormError($this->get('translator')->trans("realm.new.toosimilar.formalname"), null, array('%other%'=>$other->getFormalName())));
				$fail=true;
			}
		}
		return $fail;
	}


	/**
	  * @Route("/{realm}/manage", requirements={"realm"="\d+"}, name="bm2_site_realm_manage")
	  */
	public function manageAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'hierarchyManageRealmTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$min = 0;
		foreach ($realm->getInferiors() as $inferior) {
			if ($inferior->getType()>$min) { $min = $inferior->getType(); }
		}
		if ($realm->getSuperior()) {
			$max = $realm->getSuperior()->getType();
		} else {
			$max = 0;
		}
		$form = $this->createForm(new RealmManageType($min, $max), $realm);
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$coldata = preg_match('/rgba?\(([0-9]+), ?([0-9]+), ?([0-9]+)(,.*)?\)/', $data->getColourRgb(), $matches);
			if (isset($matches[3])) {
				$data->setColourRgb($matches[1].','.$matches[2].','.$matches[3]);
			} else {
				// invalid colour value
				$data->setColourRgb(255,255,255);
			}
			$fail = $this->checkRealmNames($form, $data->getName(), $data->getFormalName(), $realm);
			if (!$fail) {
				foreach ($realm->getConversations() as $convo) {
					if ($convo->getSystem() == 'announcements') {
						$convo->setTopic($realm->getName().' Announcements');
					}
					if ($convo->getSystem() == 'general') {
						$convo->setTopic($realm->getName().' General Discussion');
					}
				}
				$this->getDoctrine()->getManager()->flush();
				$this->addFlash('notice', $this->get('translator')->trans('realm.manage.success', array(), 'politics'));
			}
		}

		return $this->render('Realm/manage.html.twig', [
			'realm'=>$realm,
			'form'=>$form->createView()
		]);
	}


	/**
	  * @Route("/{realm}/description", requirements={"realm"="\d+"}, name="bm2_site_realm_description")
	  */
	public function descriptionAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'hierarchyManageDescriptionTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$desc = $realm->getDescription();
		if ($desc) {
			$text = $desc->getText();
		} else if ($realm->getOldDescription()) {
			$text = $realm->getOldDescription();
		} else {
			$text = null;
		}
		$form = $this->createForm(new DescriptionNewType($text));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			if ($text != $data['text']) {
				$desc = $this->get('description_manager')->newDescription($realm, $data['text'], $character);
			}
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('control.description.success', array(), 'actions'));
		}

		return $this->render('Realm/description.html.twig', [
			'realm'=>$realm,
			'form'=>$form->createView()
		]);
	}


	/**
	  * @Route("/{realm}/newplayer", requirements={"realm"="\d+"}, name="bm2_site_realm_newplayer")
	  */
	public function newplayerAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'hierarchyNewPlayerInfoTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$desc = $realm->getSpawnDescription();
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
				$desc = $this->get('description_manager')->newSpawnDescription($realm, $data['text'], $character);
			}
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('control.description.success', array(), 'actions'));
		}

		return $this->render('Realm/newplayer.html.twig', [
			'realm'=>$realm,
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/{realm}/spawns", requirements={"realm"="\d+"}, name="maf_realm_spawn")
	  */
	public function realmSpawnAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'hierarchyRealmSpawnsTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Realm/realmSpawn.html.twig', [
			'realm'=>$realm
		]);
	}

	/**
	  * @Route("/{realm}/spawns/{spawn}", requirements={"realm"="\d+","spawn"="\d+"}, name="maf_realm_spawn_toggle")
	  */
	public function realmSpawnToggleAction(Realm $realm, Spawn $spawn) {
		$character = $this->gateway($realm, 'hierarchyRealmSpawnsTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		if($spawn->getActive()) {
			$spawn->setActive(false);
			$this->addFlash('notice', $this->get('translator')->trans('control.spawn.manage.stop', ["%name%"=>$spawn->getPlace()->getName()], 'actions'));
		} else {
			$spawn->setActive(true);
			$this->addFlash('notice', $this->get('translator')->trans('control.spawn.manage.start', ["%name%"=>$spawn->getPlace()->getName()], 'actions'));
		}
		$em->flush();
		return new RedirectResponse($this->generateUrl('maf_realm_spawn', ['realm' => $realm->getId()]).'#'.$spawn->getPlace()->getId());
	}


	/**
	  * @Route("/{realm}/abdicate", requirements={"realm"="\d+"})
	  */
	public function abdicateAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'hierarchyAbdicateTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$success=false;
		$form = $this->createForm(new InteractionType('abdicate', $this->get('geography')->calculateInteractionDistance($character), $character, false, true));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			if (isset($data['target']) && $data['target']->isNPC()) {
				$this->addFlash('error', $this->get('translator')->trans('unavailable.npc'));
				return array('realm'=>$realm, 'form'=>$form->createView(), 'success'=>false);
			}

			$this->get('realm_manager')->abdicate($realm, $character, $data['target']);
			$this->getDoctrine()->getManager()->flush();
			$success=true;
		}

		return $this->render('Realm/abdicate.html.twig', [
			'realm'=>$realm,
			'form'=>$form->createView(),
			'success'=>$success
		]);
	}


	/**
	  * @Route("/{realm}/abolish", requirements={"realm"="\d+"}, name="bm2_site_realm_abolish")
	  */
	public function abolishAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'hierarchyAbolishRealmTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createFormBuilder()
			->add('sure', 'checkbox', array(
				'required'=>true,
				'label'=>'realm.abolish.sure',
				'translation_domain' => 'politics'
				))
			->getForm();

		$success=false;
		$form->handleRequest($request);
		if ($form->isValid()) {
			$fail = false;
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();
			if ($data['sure'] != true) {
				$fail = true;
			}
			if (!$fail) {
				$sovereign = false;
				$inferiors = false;
				if (!$realm->getSuperior()) {
					$sovereign = true;
				}
				if ($realm->getInferiors()) {
					$inferiors = true;
				}
				if ($sovereign && $inferiors) {
					$this->get('realm_manager')->dismantleRealm($character, $realm, $sovereign); # Free the esates, remove position holders.
					foreach ($realm->getInferiors() as $subrealm) {
						$this->get('history')->logEvent(
							$subrealm,
							'event.realm.abolished.sovereign.inferior.subrealm',
							array('%link-realm%'=>$realm->getId()),
							History::HIGH
						); # 'With the abolishment of %link-realm%, the realm has become autonomous.'
						$this->get('history')->logEvent(
							$realm,
							'event.realm.abolished.sovereign.inferior.realm',
							array('%link-realm%'=>$subrealm->getId()),
							History::HIGH
						); # 'With the dismantling of the realm, the formal vassal of %link-realm% has gained it's autonomy.'
						$subrealm->setSuperior(null);
						$realm->removeInferior($subrealm);
						$realm->setActive(false);
						$em->flush();
					}
				}
				if ($sovereign && !$inferiors) {
					$this->get('realm_manager')->dismantleRealm($character, $realm, $sovereign); # Free the esates, remove position holders.
					$realm->setActive(false);
					$em->flush();
				}
				if (!$sovereign && $inferiors) {
					$this->get('realm_manager')->dismantleRealm($character, $realm); # Move settlements up a level, remove position holders.
					$superior = $realm->getSuperior();
					foreach ($realm->getInferiors() as $subrealm) {
						$this->get('history')->logEvent(
							$subrealm,
							'event.realm.abolished.notsovereign.inferior.subrealm',
							array('%link-realm-1%'=>$realm->getId(), '%link-realm-2%'=>$subrealm->getId(), '%link-realm-3%'=>$realm->getId()),
							History::HIGH
						); # 'With the abolishment of its superior realm, %link-realm-1%, %link-realm-2%'s superior is now %link-realm-3%.'
						$this->get('history')->logEvent(
							$realm,
							'event.realm.abolished.notsovereign.inferior.realm',
							array('%link-realm-1%'=>$superior->getId(), '%link-realm-2%'=>$subrealm->getId()),
							History::HIGH
						); # 'With the abolishment of the realm, %link-realm-1% assumes superiorship role over %link-realm-2%.'
						$this->get('history')->logEvent(
							$superior,
							'event.realm.abolished.notsovereign.inferior.superior',
							array('%link-realm-1%'=>$realm->getId(), '%link-realm-2%'=>$subrealm->getId()),
							History::HIGH
						); # 'With the abolishment of its inferior realm, %link-realm-1%, the realm assumes superiorship over %link-realm-2%.'
						$realm->removeInferior($subrealm); # Remove inferior from the abolished realm.
						$superior->addInferior($subrealm); # Add inferior to next level up realm.
						$subrealm->setSuperior($superior); # Set next level superior as direct superior of inferior realm.
						$em->flush();
					}
					$realm->setActive(false);
					$em->flush();
				}
				if (!$sovereign && !$inferiors) {
					$this->get('realm_manager')->dimsantleRealm($character, $realm); # Move settlements up a level, remove position holders.
					$realm->setActive(false);
					$em->flush();
				}
				$this->addFlash('notice', $this->get('translator')->trans('realm.abolish.done', array('%link-realm%'=>$realm->getId()), 'politics')); #'The realm of %link-realm% has been dismantled.
				return $this->redirectToRoute('bm2_politics');
			} else {
				$this->addFlash('error', $this->get('translator')->trans('realm.abolish.fail', array(), 'politics')); # 'You have not validated your certainty.'
			}

		}

		return $this->render('Realm/abolish.html.twig', [
			'realm'=>$realm,
			'form'=>$form->createView()
		]);
	}


	/**
	  * @Route("/{realm}/positions", requirements={"realm"="\d+"})
	  */
	public function positionsAction(Realm $realm, Request $request) {
		// FIXME: these should be visible to all realm members - seperate method or same?
		$character = $this->gateway($realm, 'hierarchyRealmPositionsTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Realm/positions.html.twig', [
			'realm' => $realm,
			'positions' => $realm->getPositions(),
		]);
	}

	/**
	  * @Route("/viewposition/{id}", requirements={"id"="\d+"}, name="bm2_position")
	  */
	public function viewpositionAction(RealmPosition $id) {

		return $this->render('Realm/viewposition.html.twig', [
			'position'=>$id
		]);
	}

	/**
	  * @Route("/{realm}/position/{position}", requirements={"realm"="\d+", "position"="\d+"})
	  */
	public function positionAction(Realm $realm, Request $request, RealmPosition $position=null) {
		$character = $this->gateway($realm, 'hierarchyRealmPositionsTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		$cycle = $this->get('appstate')->getCycle();

		if ($position == null) {
			$is_new = true;
			$position = new RealmPosition;
			$position->setRealm($realm);
			$position->setRuler(false);

		} else {
			$is_new = false;
			if ($position->getRealm() != $realm) {
				throw $this->createNotFoundException('error.notfound.position');
			}
		}

		$original_permissions = clone $position->getPermissions();
		$form = $this->createForm(new RealmPositionType(), $position);
		$form->handleRequest($request);
		if ($form->isValid()) {
			$fail = false;
			$data = $form->getData();
			$year = $data->getYear();
			$week = $data->getWeek();
			$term = $data->getTerm();
			$elected = $data->getElected();
			if ($week < 0 OR $week > 60) {
				$fail = true;
			}

			if (!$fail) {
				foreach ($position->getPermissions() as $permission) {
					if (!$permission->getId()) {
						$em->persist($permission);
					}
				}
				foreach ($original_permissions as $orig) {
					if (!$position->getPermissions()->contains($orig)) {
						$em->remove($orig);
					}
				}
				if ($is_new) {
					$em->persist($position);
				}
				if ($year > 1 AND $week > 1 AND $term != 0) {
					/* This is explained a bit better below, BUT, we set week and year manually here just in case
					the game decides to do something wonky. Also, if the term is anything other than lifetime,
					which is what 0 equates to, then we care about election years n stuff. */
					$position->setCycle((($year-1)*360)+(($week-1)*6));
					$position->setWeek($week);
					$position->setYear($year);
				}
				if ($term == 0 OR $year < 2) {
					/* This sounds kind of dumb, but basically, on null inputs the form builder submits a 1.
					So, when we get a 1 for the year, or anything less than 2 really, we assume that this is
					actually a null input done by the formbuilder, and set cycle, week, and year to null.
					This is because the formbuilder doesn't accept null integers on it's own. */
					$position->setCycle(null);
					$position->setWeek(null);
					$position->setYear(null);
				}
				if ($elected) {
					$position->setDropCycle((($year-1)*360)+(($week-1)*6)+12);
				}
				$em->flush();
				return $this->redirectToRoute('bm2_site_realm_positions', array('realm'=>$realm->getId()));
			}
		}

		return $this->render('Realm/position.html.twig', [
			'realm' => $realm,
			'position' => $position,
			'permissions' => $em->getRepository('BM2SiteBundle:Permission')->findByClass('realm'),
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{realm}/officials/{position}", requirements={"realm"="\d+", "position"="\d+"})
	  */
	public function officialsAction(Realm $realm, RealmPosition $position, Request $request) {
		$character = $this->gateway($realm, 'hierarchyRealmPositionsTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();

		if (!$position || $position->getRealm() != $realm) {
			throw $this->createNotFoundException('error.notfound.position');
		}

		$original_holders = clone $position->getHolders();

		if ($position->getKeepOnSlumber()) {
			$candidates = $position->getRealm()->findMembers();
		} else {
			$candidates = $position->getRealm()->findActiveMembers();
		}
		$form = $this->createForm(new RealmOfficialsType($candidates, $position->getHolders()));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			// TODO: to prevent spam and other abuses, put a time limit on this or make it a timed action
			$nodemoruler=false; $ok=false;
			foreach ($candidates as $candidate) {
				if ($position->getHolders()->contains($candidate)) {
					if (!$original_holders->contains($candidate)) {
						// appointed
						$ok = true;
						if ($position->getRuler()) {
							$this->get('history')->logEvent(
								$candidate,
								'event.character.appointed',
								array('%link-realm%'=>$realm->getId(), '%link-character%'=>$character->getId()),
								History::HIGH
							);
							$this->get('realm_manager')->makeRuler($realm, $candidate, true);
						} else {
							$this->get('history')->logEvent(
								$candidate,
								'event.character.position.appointed',
								array('%link-realm%'=>$realm->getId(), '%link-realmposition%'=>$position->getId()),
								History::MEDIUM
							);
						}
					}
				} else {
					if ($original_holders->contains($candidate)) {
						if ($position->getRuler()) {
							$nodemoruler=true;
						} else {
							// demoted
							$ok = true;
							$this->get('history')->logEvent(
								$candidate,
								'event.character.position.demoted',
								array('%link-realm%'=>$realm->getId(), '%link-realmposition%'=>$position->getId()),
								History::MEDIUM
							);
						}
					}
				}

			}
			$em->flush();
			if ($ok) {
				$this->addFlash('notice', $this->get('translator')->trans('position.appoint.done', array(), 'politics'));
			}
			if ($nodemoruler) {
				$this->addFlash('error', $this->get('translator')->trans('position.appoint.nodemoruler', array(), 'politics'));
			}
			return $this->redirectToRoute('bm2_site_realm_positions', array('realm'=>$realm->getId()));
		}

		return $this->render('Realm/officials.html.twig', [
			'realm' => $realm,
			'position' => $position,
			'form' => $form->createView()
		]);
	}


	/**
	  * @Route("/{realm}/accolades", requirements={"realm"="\d+"})
	  */
	public function triumphsAction(Realm $realm, Request $request) {
		$this->addFlash('notice', "This feature isn't quite ready yet, sorry!");
		return $this->redirectToRoute('bm2_homepage');
		// FIXME: these should be visible to all realm members - seperate method or same?
		$character = $this->gateway($realm, 'hierarchyRealmPositionsTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Realm/positions.html.twig', [
			'realm' => $realm,
			'positions' => $realm->getPositions(),
		]);
	}

   /**
     * @Route("/{realm}/diplomacy", requirements={"realm"="\d+"})
     */
	public function diplomacyAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'hierarchyDiplomacyTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}


		return $this->render('Realm/diplomacy.html.twig', [
			'realm'=>$realm
		]);
	}

   /**
     * @Route("/{realm}/hierarchy", requirements={"realm"="\d+"})
     */
	public function hierarchyAction(Realm $realm) {
		$this->addToHierarchy($realm);

	   	$descriptorspec = array(
			   0 => array("pipe", "r"),  // stdin
			   1 => array("pipe", "w"),  // stdout
			   2 => array("pipe", "w") // stderr
			);

   		$process = proc_open('dot -Tsvg', $descriptorspec, $pipes, '/tmp', array());

	   	if (is_resource($process)) {
	   		$dot = $this->renderView('Realm/hierarchy.dot.twig', array('hierarchy'=>$this->hierarchy, 'me'=>$realm));

	   		fwrite($pipes[0], $dot);
	   		fclose($pipes[0]);

	   		$svg = stream_get_contents($pipes[1]);
	   		fclose($pipes[1]);

	   		$return_value = proc_close($process);
	   	}

		return $this->render('Realm/hierarchy.html.twig', [
			'svg'=>$svg
		]);
	}

	private function addToHierarchy(Realm $realm) {
		if (!isset($this->hierarchy[$realm->getId()])) {
			$this->hierarchy[$realm->getId()] = $realm;
			if ($realm->getSuperior()) {
				$this->addToHierarchy($realm->getSuperior());
			}
			foreach ($realm->getInferiors() as $inferiors) {
				$this->addToHierarchy($inferiors);
			}
		}
	}



   /**
     * @Route("/{realm}/join", requirements={"id"="\d+"})
     */
	public function joinAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'diplomacyHierarchyTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		// TODO: more transparency - who is near and why can't I join some realms?

		$available = array();
		$unavailable = array();
		$realms = new ArrayCollection;
		$nearby = $this->get('dispatcher')->getActionableCharacters();
		foreach ($nearby as $near) {
			$char = $near['character'];
			foreach ($char->findRealms() as $myrealm) {
				$id = $myrealm->getId();
				if ($myrealm->getType() > $realm->getType()) {
					if ($myrealm != $realm->getSuperior()) {
						if (isset($available[$id])) {
							$available[$id]['via'][] = $char;
						} else {
							$available[$id] = array('realm'=>$myrealm, 'via'=>array($char));
						}
						if (!$realms->contains($myrealm)) {
							$realms->add($myrealm);
						}
					} else {
						if (!isset($unavailable[$id])) {
							$unavailable[$id] = array('realm'=>$myrealm, 'reason'=>'current');
						}
					}
				} else {
					if (!isset($available[$id])) {
						$unavailable[$id] = array('realm'=>$myrealm, 'reason'=>'type');
					}
				}
			}
		}
		foreach ($character->findRealms() as $myrealm) {
			if ($myrealm->getType() > $realm->getType()) {
				if ($myrealm !== $realm->getSuperior()) {
					if (isset($available[$id])) {
						$available[$id]['via'][] = $char;
					} else {
						$available[$id] = array('realm'=>$myrealm, 'via'=>array($char));
					}
					if (!$realms->contains($myrealm)) {
						$realms->add($myrealm);
					}
				} else {
					if (!isset($unavailable[$id])) {
						$unavailable[$id] = array('realm'=>$myrealm, 'reason'=>'current');
					}
				}
			} else {
				if (!isset($available[$id])) {
					$unavailable[$id] = array('realm'=>$myrealm, 'reason'=>'type');
				}
			}
		}

		if ($realms->isEmpty()) {

			return $this->render('Realm/join.html.twig', [
				'realm'=>$realm,
				'unavailable'=>$unavailable
			]);
		}

		$form = $this->createForm(new RealmSelectType($realms, 'join'));

		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			$target = $data['target'];
			$msg = $data['message'];

			$data = $form->getData();
			if ($target->getType() > $realm->getType()) {
				$timeout = new \DateTime("now");
				$timeout->add(new \DateInterval("P7D"));
				# newRequestFromRealmToRealm($type, $expires = null, $numberValue = null, $stringValue = null, $subject = null, $text = null, Character $fromChar = null, Realm $fromRealm = null, Realm $toRealm = null, Character $includeChar = null, Settlement $includeSettlement = null, Realm $includeRealm = null, Place $includePlace, RealmPosition $includePos = null)
				$this->get('game_request_manager')->newRequestFromRealmToRealm('realm.join', $timeout, null, null, $realm->getName().' Request to Join', $msg, $character, $realm, $target);
				$this->addFlash('success', $this->get('translator')->trans('realm.join.sent', ['%target%'=>$target->getName()], 'politics'));
				return $this->redirectToRoute('bm2_site_realm_diplomacy', ['realm'=>$realm->getId()]);
			} else {
				$form->addError(new FormError($this->get('translator')->trans("diplomacy.join.unavail.type", array(), 'politics')));
			}

		}

		return $this->render('Realm/join.html.twig', [
			'realm'=>$realm,
			'unavailable'=>$unavailable,
			'choices'=>$available,
			'form'=>$form->createView()
		]);
	}


   /**
     * @Route("/{realm}/subrealm", requirements={"realm"="\d+"})
     */
	public function subrealmAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'diplomacySubrealmTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new SubrealmType($realm));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$fail = false;

			$newsize = 0;
			$chars = new ArrayCollection;
			foreach ($data['settlement'] as $e) {
				$newsize++;
				if ($e->getOwner()) {
					$chars->add($e->getOwner());
				}
				if ($e->getSteward()) {
					$chars->add($e->getSteward());
				}
			}
			if ($newsize==0 || $newsize==$realm->getSettlements()->count()) {
				$form->addError(new FormError($this->get('translator')->trans("diplomacy.subrealm.invalid.size", array(), 'politics')));
				$fail=true;
			}

			if (!$chars->contains($data['ruler'])) {
				$form->addError(new FormError($this->get('translator')->trans("diplomacy.subrealm.invalid.ruler", array(), 'politics')));
				$fail=true;
			}
			if (!$fail) {
				$fail = $this->checkRealmNames($form, $data['name'], $data['formal_name']);
			}
			if (!$fail) {
				if ($data['type'] >= $realm->getType()) {
					$form->addError(new FormError($this->get('translator')->trans("diplomacy.join.unavail.type", array(), 'politics')));
					$fail=true;
				}
			}
			if (!$fail) {
				$subrealm = $this->get('realm_manager')->subcreate($data['name'], $data['formal_name'], $data['type'], $data['ruler'], $character, $realm);
				foreach ($data['settlement'] as $e) {
					$this->get('politics')->changeSettlementRealm($e, $subrealm, 'subrealm');
				}
				$this->getDoctrine()->getManager()->flush();

				// and setup the realm conversation
				$topic = $subrealm->getName().' Announcements';
				$this->get('conversation_manager')->newConversation(null, null, $topic, null, null, $subrealm, 'announcements');
				$topic = $subrealm->getName().' General Discussion';
				$this->get('conversation_manager')->newConversation(null, null, $topic, null, null, $subrealm, 'general');

				$this->getDoctrine()->getManager()->flush();
				$this->addFlash('notice', $this->get('translator')->trans('diplomacy.subrealm.success', array(), 'politics'));
				return $this->redirectToRoute('bm2_site_realm_diplomacy', array('realm'=>$realm->getId()));
			}
		}

		return $this->render('Realm/subrealm.html.twig', [
			'realm' => $realm,
			'realmpoly' =>	$this->get('geography')->findRealmPolygon($realm),
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{realm}/capital", requirements={"realm"="\d+"})
	  */
	public function capitalAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'hierarchySelectCapitalTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new RealmCapitalType($realm));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$fail = false;

			if ($data['capital'] == $realm->getCapital()) {
				$fail = true;
				$form->addError(new FormError($this->get('translator')->trans("realm.capital.error.already", array(), 'politics')));
			}
			if (!$fail AND !$data['capital']) {
				$fail = true;
				$form->addError(new FormError($this->get('translator')->trans("realm.capital.error.none", array(), 'politics')));
			}
			if (!$fail) {
				if ($realm->getCapital()) {
					$realm->getCapital()->removeCapitalOf($realm);
				}
				$realm->setCapital($data['capital']);
				$data['capital']->addCapitalOf($realm);
				$this->get('history')->logEvent(
					$realm,
					'event.realm.capital',
					array('%link-settlement%'=>$data['capital']->getId()),
					History::HIGH
				);
				$this->getDoctrine()->getManager()->flush();
				$this->addFlash('notice', $this->get('translator')->trans('realm.capital.success', array(), 'politics'));
				return $this->redirectToRoute('bm2_site_realm_capital', array('realm'=>$realm->getId()));
				#return $this->redirectToRoute('bm2_politics');
			}
		}

		return $this->render('Realm/capital.html.twig', [
			'realm' => $realm,
			'realmpoly' =>	$this->get('geography')->findRealmPolygon($realm),
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{id}/restore", requirements={"id"="\d+"})
	  */

	public function restoreAction(Realm $id) {
		$realm = $id;
		$character = $this->gateway($realm, 'diplomacyRestoreTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();

		$this->get('realm_manager')->makeRuler($realm, $character);
		$realm->setActive(TRUE);
		$this->get('history')->logEvent(
			$realm,
			'event.realm.restored',
			array('%link-realm%'=>$realm->getSuperior()->getID(), '%link-character%'=>$character->getId()),
			History::ULTRA, true
		);
		$this->get('history')->logEvent(
			$character,
			'event.realm.restorer',
			array('%link-realm%'=>$realm->getID()),
			History::HIGH, true
		);
		$em->flush();
		$this->addFlash('notice', $this->get('translator')->trans('realm.restore.success', array(), 'politics'));
		return $this->redirectToRoute('bm2_realm', ["id"=>$realm->getId()]);
	}

	/**
	  * @Route("/{realm}/break", requirements={"realm"="\d+"})
	  */
	public function breakAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'diplomacyBreakHierarchyTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		if ($request->isMethod('POST')) {
			$parent = $realm->getSuperior();

			$realm->getSuperior()->getInferiors()->removeElement($realm);
			$realm->setSuperior(null);

			$this->get('history')->logEvent(
				$realm,
				'event.realm.left',
				array('%link-realm%'=>$parent->getId()),
				History::HIGH
			);
			$this->get('history')->logEvent(
				$parent,
				'event.realm.wasleft',
				array('%link-realm%'=>$realm->getId()),
				History::MEDIUM
			);

			// TODO: messaging everyone who needs to know

			$em = $this->getDoctrine()->getManager();
			$em->flush();

			return $this->render('Realm/break.html.twig', [
				'realm'=>$realm,
				'success'=>true
			]);
		}

		return $this->render('Realm/break.html.twig', [
			'realm'=>$realm
		]);
	}


	/**
	  * @Route("/{realm}/relations", requirements={"realm"="\d+"})
	  */
	public function relationsAction(Realm $realm, Request $request) {
		$relations = array();
		foreach ($realm->getMyRelations() as $rel) {
			$relations[$rel->getTargetRealm()->getId()]['link'] = $rel->getTargetRealm();
			$relations[$rel->getTargetRealm()->getId()]['we'] = $rel;
		}
		foreach ($realm->getForeignRelations() as $rel) {
			$relations[$rel->getSourceRealm()->getId()]['link'] = $rel->getSourceRealm();
			$relations[$rel->getSourceRealm()->getId()]['they'] = $rel;
		}

		$this->get('dispatcher')->setRealm($realm);
		$test = $this->get('dispatcher')->diplomacyRelationsTest();
		$canedit = isset($test['url']);

		return $this->render('Realm/relations.html.twig', [
			'realm' => $realm,
			'relations' => $relations,
			'canedit' => $canedit
		]);
	}


	/**
	  * @Route("/{realm}/editrelation/{relation}/{target}", requirements={"id"="\d+", "relation"="\d+", "target"="\d+"}, defaults={"target":0})
	  */
	public function editrelationAction(Realm $realm, Request $request, RealmRelation $relation=null, Realm $target=null) {
		$character = $this->gateway($realm, 'diplomacyRelationsTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		if ($relation==null) {
			// make sure we don't duplicate a relation, e.g. when the player opens two tabs
			$relation = $this->getDoctrine()->getManager()->getRepository('BM2SiteBundle:RealmRelation')->findOneBy(array('source_realm'=>$realm, 'target_realm'=>$target));
			if ($relation == null) {
				$relation = new RealmRelation;
				if ($target) {
					$relation->setTargetRealm($target);
				}
			}
		} else {
			if (!$realm->getMyRelations()->contains($relation)) {
				throw $this->createNotFoundException('error.notfound.realmrelation');
			}
		}
		// FIXME: should not be possible to have relations with yourself...

		$form = $this->createForm(new RealmRelationType(), $relation);
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$data->setSourceRealm($realm);
			$data->setLastChange(new \DateTime("now"));
			// make sure important fields are not empty/null - which would cause fatal errors
			if (!$data->getPublic()) $data->setPublic("");
			if (!$data->getInternal()) $data->setInternal("");
			if (!$data->getDelivered()) $data->setDelivered("");

			if (!$data->getId()) {
				$this->getDoctrine()->getManager()->persist($data);
			}

			// TODO: announce change to both realms
			//		 however, to prevent spam we need to limit changes to once per game day or something
			//		 to do that properly, we should probably change LastChange() to be an integer/cycle instead of datetime

			$this->getDoctrine()->getManager()->flush();
			return $this->redirectToRoute('bm2_site_realm_relations', array('realm'=>$realm->getId()));
		}

		return $this->render('Realm/editrelation.html.twig', [
			'realm' => $realm,
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{realm}/deleterelation/{relation}/{target}", requirements={"id"="\d+", "relation"="\d+", "target"="\d+"}, defaults={"target":0})
	  */
	public function deleterelationAction(Realm $realm, Request $request, RealmRelation $relation=null, Realm $target=null) {
		$character = $this->gateway($realm, 'diplomacyRelationsTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		if ($relation!=null && $relation->getSourceRealm() == $realm) {
			$em = $this->getDoctrine()->getManager();

			$em->remove($relation);
			$em->flush();
		}

		return $this->redirectToRoute('bm2_site_realm_relations', array('realm'=>$realm->getId()));
	}

	/**
	  * @Route("/{realm}/viewrelations/{target}", requirements={"realm"="\d+", "target"="\d+"})
	  */
	public function viewrelationsAction(Realm $realm, Realm $target) {
		$character = $this->gateway();
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT r FROM BM2SiteBundle:RealmRelation r WHERE r.source_realm = :me AND r.target_realm = :they');
		$query->setParameters(array(
			'me' => $realm,
			'they' => $target
		));
		$we_to_them = $query->getOneOrNullResult();

		$query = $em->createQuery('SELECT r FROM BM2SiteBundle:RealmRelation r WHERE r.source_realm = :they AND r.target_realm = :me');
		$query->setParameters(array(
			'me' => $realm,
			'they' => $target
		));
		$they_to_us = $query->getOneOrNullResult();

		$my_realms = $character->findRealms();
		if ($my_realms->contains($realm)) {
			$member_of_source = true;
		} else {
			$member_of_source = false;
		}
		if ($my_realms->contains($target)) {
			$member_of_target = true;
		} else {
			$member_of_target = false;
		}

		return $this->render('Realm/viewrelations.html.twig', [
			'myrealm' => $realm,
			'targetrealm' => $target,
			'we_to_them' => $we_to_them,
			'they_to_us' => $they_to_us,
			'member_of_source' => $member_of_source,
			'member_of_target' => $member_of_target
		]);
	}

	/**
	  * @Route("/{realm}/elections", requirements={"realm"="\d+"})
	  */
	public function electionsAction(Realm $realm) {
		$character = $this->gateway($realm, 'hierarchyElectionsTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Realm/elections.html.twig', [
			'realm'=>$realm,
			'nopriest'=>($character->getEntourageOfType('priest')->count()==0)
		]);
	}

	/**
	  * @Route("/{realm}/election/{election}", requirements={"realm"="\d+", "election"="\d+"})
	  */
	public function electionAction(Realm $realm, Request $request, Election $election=null) {
		$character = $this->gateway($realm, 'hierarchyElectionsTest');
		if (!($character instanceof Character)) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();

		if ($election == null) {
			if ($character->getEntourageOfType('priest')->count()==0) {
				return array(
					'realm' => $realm,
					'nopriest' => true
				);
			}
			$is_new = true;
			$election = new Election;
			$election->setRealm($realm);
			$election->setOwner($character);
			$election->setClosed(false);
		} else {
			$is_new = false;
			if ($election->getRealm() != $realm) {
				throw $this->createNotFoundException('error.notfound.election');
			}
		}

		if (!$election->getClosed()) {
			$form = $this->createForm(new ElectionType(), $election);
			$form->handleRequest($request);
			if ($form->isValid()) {
				$data = $form->getData();

				// FIXME: only ruler or those with appropriate permissions should be able to start an election for a position

				$complete = new \DateTime("now");
				$duration = $form->get('duration')->getData();
				switch ($duration) {
					case 1:
					case 3:
					case 5:
					case 7:
					case 10:
						$complete->add(new \DateInterval("P".$duration."D"));
						break;
					default:
						$complete->add(new \DateInterval("P3D"));
				}
				$election->setComplete($complete);

				if ($is_new) {
					$em->persist($election);
				}
				$em->flush();
				return $this->redirectToRoute('bm2_site_realm_elections', array('realm'=>$realm->getId()));
			}
		}

		return $this->render('Realm/election.html.twig', [
			'realm' => $realm,
			'form' => $form->createView()
		]);
	}


	/**
	  * @Route("/vote/{id}", requirements={"id"="\d+"})
	  */
	public function voteAction(Election $id, Request $request) {
		if ($id->getRealm()) {
			$character = $this->gateway($id->getRealm(), 'hierarchyElectionsTest');
			if (!($character instanceof Character)) {
				return $this->redirectToRoute($character);
			}
		}

		# Because people were sneaking random outsiders into elections.
		# This method will also allow us to setup alternative security checks later for this page, if it gets expanded.
		$election = $id; // we use ID in the route because the links extension always uses id
		$em = $this->getDoctrine()->getManager();

		// TODO: if completion date is past, allow no more changes, just display and show winner.


		$form = $this->createFormBuilder(null, array('translation_domain'=>'politics', 'attr'=>array('class'=>'wide')))
			->add('candidate', 'text', array(
				'required'=>true,
				'label'=>'votes.add.label',
				))
			->add('vote', 'choice', array(
				'required'=>true,
				'label'=>'votes.add.procontra',
				'choices'=>array(1=>'votes.pro', -1=>'votes.contra')
				))
			->add('submit', 'submit', array(
				'label'=>'votes.add.submit',
				))
			->getForm();

		$addform=false; $voteform=false;
		if ($request->isMethod('POST') && $submitted_form = $request->request->get("form")) {
			if (isset($submitted_form['targets'])) {
				$voteform=true;
			}
			if (isset($submitted_form['candidate'])) {
				$addform=true;
			}
		}


		if ($addform) {
			$form->handleRequest($request);
			if ($form->isValid()) {
				$data = $form->getData();
				$em = $this->getDoctrine()->getManager();
				if ($data['vote']==-1) {
					$apply = -1;
				} else {
					$apply = 1;
				}

				$input = $data['candidate'];
				# First strip it of all non-numeric characters and see if we can find a character.
				$id = preg_replace('/(?:[^1234567890]*)/', '', $input);
				if ($id) {
					$candidate = $em->getRepository('BM2SiteBundle:Character')->findOneBy(array('id'=>$id, 'alive' => TRUE));
				} else {
					# Presumably, that wasn't an ID. Assume it's just a name.
					$name = trim(preg_replace('/(?:[1234567890()]*)/', '', $input));
					$candidate = $em->getRepository('BM2SiteBundle:Character')->findOneBy(array('name' => $name, 'alive' => TRUE), array('id' => 'ASC'));
				}
				if ($candidate) {
					$vote = new Vote;
					$vote->setVote($apply);
					$vote->setCharacter($character);
					$vote->setElection($election);
					$vote->setTargetCharacter($candidate);
					$em->persist($vote);
				}
				$em->flush();
				$this->addFlash('notice', $this->get('translator')->trans('votes.add.done', array(), 'politics'));
			}
		}

		$form_votes = $this->createFormBuilder(null, array('translation_domain'=>'politics'))
			->add('targets', 'collection', array(
			'type'		=> "text",
			'allow_add'	=> true,
			'allow_delete' => true,
		))->getForm();

		if ($voteform) {
			$form_votes->handleRequest($request);
			if ($form_votes->isValid()) {
				$data = $form_votes->getData();
				foreach ($data['targets'] as $id=>$procontra) {
					$myvote = $em->getRepository('BM2SiteBundle:Vote')->findOneBy(array('character'=>$character, 'election'=>$election, 'target_character'=>$id));
					if ($myvote) {
						switch ($procontra) {
							case "pro":				$myvote->setVote(1); break;
							case "contra":			$myvote->setVote(-1); break;
							case "neutral":		$character->removeVote($myvote); $election->removeVote($myvote); $em->remove($myvote); break;
						}
					} else {
						if ($procontra == "pro" || $procontra == "contra") {
							if ($candidate = $em->getRepository('BM2SiteBundle:Character')->find($id)) {
								$vote = new Vote;
								$vote->setCharacter($character);
								$vote->setElection($election);
								$vote->setTargetCharacter($candidate);
								if ($procontra=="pro") {
									$vote->setVote(1);
								} else {
									$vote->setVote(-1);
								}
								$em->persist($vote);
							}
						}
					}
				}
				$em->flush();
				$this->addFlash('notice', $this->get('translator')->trans('votes.updated', array(), 'politics'));
			}
		}

		$votes = $this->getVotes($election);

		$my_weight = $this->get('realm_manager')->getVoteWeight($election, $character);

		return $this->render('Realm/vote.html.twig', [
			'election' => $election,
			'votes' => $votes,
			'my_weight' => $my_weight,
			'form' => $form->createView(),
			'form_votes' => $form_votes->createView()
		]);
	}


	private function getVotes(Election $election) {
		$votes = array();
		foreach ($election->getVotes() as $vote) {
			$id = $vote->getTargetCharacter()->getId();
			if (!isset($votes[$id])) {
				$votes[$id] = array(
					'candidate' => $vote->getTargetCharacter(),
					'pro' => array(),
					'contra' => array()
				);
			}
			$weight = $this->get('realm_manager')->getVoteWeight($election, $vote->getCharacter());
			if ($vote->getVote() < 0) {
				$votes[$id]['contra'][] = array('voter'=>$vote->getCharacter(), 'votes'=>$weight);
			} else {
				$votes[$id]['pro'][] = array('voter'=>$vote->getCharacter(), 'votes'=>$weight);
			}
		}
		return $votes;
	}

}
