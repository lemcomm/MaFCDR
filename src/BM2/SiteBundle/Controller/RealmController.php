<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Election;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\RealmPosition;
use BM2\SiteBundle\Entity\RealmRelation;
use BM2\SiteBundle\Entity\Vote;
use BM2\SiteBundle\Form\ElectionType;
use BM2\SiteBundle\Form\InteractionType;
use BM2\SiteBundle\Form\RealmCreationType;
use BM2\SiteBundle\Form\RealmManageType;
use BM2\SiteBundle\Form\RealmOfficialsType;
use BM2\SiteBundle\Form\RealmPositionType;
use BM2\SiteBundle\Form\RealmRelationType;
use BM2\SiteBundle\Form\RealmRestoreType;
use BM2\SiteBundle\Form\RealmSelectType;
use BM2\SiteBundle\Form\SubrealmType;
use BM2\SiteBundle\Service\History;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;


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
		if ($realm && !$test) {
			if (!$character->findRealms()->contains($realm)) {
				throw $this->createAccessDeniedException('actions::unavailable.notmember');
			}
		}
		return $character;
	}


	/**
	  * @Route("/{id}/view", name="bm2_realm", requirements={"id"="\d+"})
	  * @Template("BM2SiteBundle:Realm:view.html.twig")
	  */
	public function viewAction(Realm $id) {
		$realm = $id;
		$character = $this->get('appstate')->getCharacter(false, true, true);

		$territory = $realm->findTerritory();
		$population = 0;
		foreach ($territory as $estate) {
			$population += $estate->getPopulation() + $estate->getThralls();
		}

		if ($realm->getSuperior()) {
			$parentpoly =	$this->get('geography')->findRealmPolygon($realm->getSuperior());
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

		return array(
			'realm' =>		$realm,
			'realmpoly' =>	$this->get('geography')->findRealmPolygon($realm),
			'parentpoly' => $parentpoly,
			'subpolygons' => $subpolygons,
			'estates' =>	$territory->count(),
			'population'=>	$population,
			'area' =>		$this->get('geography')->calculateRealmArea($realm),
			'nobles' =>		$realm->findMembers()->count(),
			'diplomacy' =>	$diplomacy
		);
	}

	/**
	  * @Route("/new")
	  * @Template
	  */
   public function newAction(Request $request) {
		$character = $this->gateway(false, 'hierarchyCreateRealmTest');

		$form = $this->createForm(new RealmCreationType());

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$fail = $this->checkRealmNames($form, $data['name'], $data['formal_name']);
			if (!$fail) {
				// good name, create realm
				$realm = $this->get('realm_manager')->create($data['name'], $data['formal_name'], $data['type'], $character);
				// and create the initial realm conversation, making sure our ruler is set up for the messaging system
				$msguser = $this->get('message_manager')->getMsgUser($character);
				list($meta,$conversation) = $this->get('message_manager')->createConversation($msguser, $data['formal_name'], null, $realm);

				$this->getDoctrine()->getManager()->flush();
				$this->get('appstate')->setSessionData($character); // update, because we changed our realm count
				return $this->redirectToRoute('bm2_site_realm_manage', array('realm'=>$realm->getId()));
			}
		}

		return array('form'=>$form->createView());
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
	  * @Route("/{realm}/manage", requirements={"realm"="\d+"})
	  * @Template
	  */
	public function manageAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'hierarchyManageRealmTest');

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
				$this->getDoctrine()->getManager()->flush();
				$this->addFlash('notice', $this->get('translator')->trans('realm.manage.success', array(), 'politics'));
			}
		}
		return array('realm'=>$realm, 'form'=>$form->createView());
	}


	/**
	  * @Route("/{realm}/abdicate", requirements={"realm"="\d+"})
	  * @Template
	  */
	public function abdicateAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'hierarchyAbdicateTest');

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
		return array('realm'=>$realm, 'form'=>$form->createView(), 'success'=>$success);
	}


	/**
	  * @Route("/{realm}/laws", requirements={"realm"="\d+"})
	  * @Template
	  */
	public function lawsAction(Realm $realm, Request $request) {
		// FIXME: these should be visible to all realm members - seperate method or same?
		$character = $this->gateway($realm, 'hierarchyRealmLawsTest');

		return array(
			'realm' => $realm,
			'laws' => $realm->getLaws(),
		);
	}


	/**
	  * @Route("/{realm}/positions", requirements={"realm"="\d+"})
	  * @Template
	  */
	public function positionsAction(Realm $realm, Request $request) {
		// FIXME: these should be visible to all realm members - seperate method or same?
		$character = $this->gateway($realm, 'hierarchyRealmPositionsTest');

		return array(
			'realm' => $realm,
			'positions' => $realm->getPositions(),
		);
	}

	/**
	  * @Route("/viewposition/{id}", requirements={"id"="\d+"}, name="bm2_position")
	  * @Template
	  */
	public function viewpositionAction(RealmPosition $id) {
		$character = $this->gateway();

		return array('position'=>$id);
	}

	/**
	  * @Route("/{realm}/position/{position}", requirements={"realm"="\d+", "position"="\d+"})
	  * @Template
	  */
	public function positionAction(Realm $realm, Request $request, RealmPosition $position=null) {
		$character = $this->gateway($realm, 'hierarchyRealmPositionsTest');
		$em = $this->getDoctrine()->getManager();

		if ($position == null) {
			$is_new = true;
			$position = new RealmPosition;
			$position->setRealm($realm);
			$position->setRuler(false);
			$position->setDescription("");
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
			$data = $form->getData();

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
			$em->flush();
			return $this->redirectToRoute('bm2_site_realm_positions', array('realm'=>$realm->getId()));
		}

		return array(
			'realm' => $realm,
			'position' => $position,
			'permissions' => $em->getRepository('BM2SiteBundle:Permission')->findByClass('realm'),
			'form' => $form->createView()
		);
	}

	/**
	  * @Route("/{realm}/officials/{position}", requirements={"realm"="\d+", "position"="\d+"})
	  * @Template
	  */
	public function officialsAction(Realm $realm, RealmPosition $position, Request $request) {
		$character = $this->gateway($realm, 'hierarchyRealmPositionsTest');
		$em = $this->getDoctrine()->getManager();

		if (!$position || $position->getRealm() != $realm) {
			throw $this->createNotFoundException('error.notfound.position');
		}

		$original_holders = clone $position->getHolders();

		/* FIXME: if elections can summon anyone, then so should appointment - or maybe we want to limit both to the contacts list? */
		$candidates = $position->getRealm()->findMembers();
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
		return array(
			'realm' => $realm,
			'position' => $position,
			'form' => $form->createView()
		);
	}

   /**
     * @Route("/{realm}/diplomacy", requirements={"realm"="\d+"})
     * @Template
     */
	public function diplomacyAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'hierarchyDiplomacyTest');

		return array('realm'=>$realm);
	}

   /**
     * @Route("/{realm}/hierarchy", requirements={"realm"="\d+"})
     * @Template
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
   		$dot = $this->renderView('BM2SiteBundle:Realm:hierarchy.dot.twig', array('hierarchy'=>$this->hierarchy, 'me'=>$realm));

   		fwrite($pipes[0], $dot);
   		fclose($pipes[0]);

   		$svg = stream_get_contents($pipes[1]);
   		fclose($pipes[1]);

   		$return_value = proc_close($process);
   	}

		return array('svg'=>$svg);
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
     * @Template
     */
	public function joinAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'diplomacyHierarchyTest');

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

		if ($realms->isEmpty()) {
			return array('realm'=>$realm, 'unavailable'=>$unavailable);
		}

		$form = $this->createForm(new RealmSelectType($realms, 'join'));

		if ($request->isMethod('POST')) {
			$form->bind($request);
			if ($form->isValid()) {		
				$data = $form->getData();
				$target = $data['target'];

				if ($target->getType() > $realm->getType()) {
					$realm->setSuperior($target);
					$target->addInferior($realm);

					$this->get('history')->logEvent(
						$this->realm,
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

					// TODO: messaging everyone who needs to know

					$em = $this->getDoctrine()->getManager();
					$em->flush();

					return array('realm'=>$realm, 'success'=>true, 'target'=>$target);
				} else {
					$form->addError(new FormError($this->get('translator')->trans("diplomacy.join.unavail.type", array(), 'politics')));
				}

			}
		}

		return array('realm'=>$realm, 'unavailable'=>$unavailable, 'choices'=>$available, 'form'=>$form->createView());
	}


   /**
     * @Route("/{realm}/subrealm", requirements={"realm"="\d+"})
     * @Template
     */
	public function subrealmAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'diplomacySubrealmTest');

		$form = $this->createForm(new SubrealmType($realm));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$fail = false;

			$newsize = 0;
			$chars = new ArrayCollection;
			foreach ($data['estate'] as $e) {
				$newsize++;
				if ($e->getOwner()) {
					$chars->add($e->getOwner());
				}
			}
			if ($newsize==0 || $newsize==$realm->getEstates()->count()) {
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
				foreach ($data['estate'] as $e) {
					$this->get('politics')->changeSettlementRealm($e, $subrealm, 'subrealm');
				}

				// and setup the realm conversation
				$msguser = $this->get('message_manager')->getMsgUser($data['ruler']);
				list($meta,$conversation) = $this->get('message_manager')->createConversation($msguser, $data['formal_name'], null, $subrealm);

				$this->getDoctrine()->getManager()->flush();
				$this->addFlash('notice', $this->get('translator')->trans('diplomacy.subrealm.success', array(), 'politics'));
				return $this->redirectToRoute('bm2_site_realm_diplomacy', array('realm'=>$realm->getId()));
			}
		}

		return array(
			'realm' => $realm,
			'realmpoly' =>	$this->get('geography')->findRealmPolygon($realm),
			'form' => $form->createView()
		);
	}

	/**
	  * @Route("/{realm}/restore", requirements={"realm"="\d+"})
	  * @Template
	  */

	public function restoreAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'diplomacyRestoreTest');
		
		$form = $this->createForm(new RealmRestoreType($realm));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$fail = false;

			if (!$fail) {
                		$this->get('realm_manager')->restoreSubRealm($realm, $deadrealm['deadrealm'], $character);
            		}

			$em = $this->getDoctrine()->getManager();
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('diplomacy.restore.success', array(), 'politics'));
			return $this->redirectToRoute('bm2_site_realm_diplomacy', array('realm'=>$realm->getId()));
		}
	}

	/**
	  * @Route("/{realm}/break", requirements={"realm"="\d+"})
	  * @Template
	  */
	public function breakAction(Realm $realm, Request $request) {
		$character = $this->gateway($realm, 'diplomacyBreakHierarchyTest');

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
			return array('realm'=>$realm, 'success'=>true);
		}

		return array('realm'=>$realm);
	}


	/**
	  * @Route("/{realm}/relations", requirements={"realm"="\d+"})
	  * @Template
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

		return array(
			'realm' => $realm,
			'relations' => $relations,
			'canedit' => $canedit
		);
	}


	/**
	  * @Route("/{realm}/editrelation/{relation}/{target}", requirements={"id"="\d+", "relation"="\d+", "target"="\d+"}, defaults={"target":0})
	  * @Template
	  */
	public function editrelationAction(Realm $realm, Request $request, RealmRelation $relation=null, Realm $target=null) {
		$character = $this->gateway($realm, 'diplomacyRelationsTest');

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

		return array(
			'realm' => $realm,
			'form' => $form->createView()
		);
	}

	/**
	  * @Route("/{realm}/deleterelation/{relation}/{target}", requirements={"id"="\d+", "relation"="\d+", "target"="\d+"}, defaults={"target":0})
	  * @Template
	  */
	public function deleterelationAction(Realm $realm, Request $request, RealmRelation $relation=null, Realm $target=null) {
		$character = $this->gateway($realm, 'diplomacyRelationsTest');

		if ($relation!=null && $relation->getSourceRealm() == $realm) {
			$em = $this->getDoctrine()->getManager();

			$em->remove($relation);
			$em->flush();

		}

		return $this->redirectToRoute('bm2_site_realm_relations', array('realm'=>$realm->getId()));
	}

	/**
	  * @Route("/{realm}/viewrelations/{target}", requirements={"realm"="\d+", "target"="\d+"})
	  * @Template
	  */
	public function viewrelationsAction(Realm $realm, Realm $target) {
		$character = $this->gateway();

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

		return array(
			'myrealm'		=> $realm,
			'targetrealm'	=> $target,
			'we_to_them'	=> $we_to_them,
			'they_to_us'	=> $they_to_us,
			'member_of_source' => $member_of_source,
			'member_of_target' => $member_of_target
		);

	}

	/**
	  * @Route("/{realm}/elections", requirements={"realm"="\d+"})
	  * @Template
	  */
	public function electionsAction(Realm $realm) {
		$character = $this->gateway($realm, 'hierarchyElectionsTest');

		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT e FROM BM2SiteBundle:Election e WHERE e.closed = false AND e.complete < :now');
		$query->setParameter('now', new \DateTime("now"));
		foreach ($query->getResult() as $election) {
			$this->get('realm_manager')->countElection($election);
		}
		$em->flush();

		return array(
			'realm'=>$realm,
			'nopriest'=>($character->getEntourageOfType('priest')->count()==0)
		);
	}

	/**
	  * @Route("/{realm}/election/{election}", requirements={"realm"="\d+", "election"="\d+"})
	  * @Template
	  */
	public function electionAction(Realm $realm, Request $request, Election $election=null) {
		$character = $this->gateway($realm, 'hierarchyElectionsTest');
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

		return array(
			'realm' => $realm,
			'form' => $form->createView()
		);
	}


	/**
	  * @Route("/vote/{id}", requirements={"id"="\d+"})
	  * @Template
	  */
	public function voteAction(Election $id, Request $request) {
		$character = $this->gateway();
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

				// FIXME: this works on character names, WHICH ARE NOT UNIQUE! -- our current hack: add them all
				$candidates = $em->getRepository('BM2SiteBundle:Character')->findByName($data['candidate']);
				foreach ($candidates as $candidate) {
					if (!$candidate->isNPC()) {
						// TODO: filter out already existing candidates
						$vote = new Vote;
						$vote->setVote($apply);
						$vote->setCharacter($character);
						$vote->setElection($election);
						$vote->setTargetCharacter($candidate);
						$em->persist($vote);
					}
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

		$my_weight = 1; // TODO: different voting mechanism

		return array(
			'election' => $election,
			'votes' => $votes,
			'my_weight' => $my_weight,
			'form' => $form->createView(),
			'form_votes' => $form_votes->createView()
		);
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
			$weight = 1; // TODO: different voting mechanism
			if ($vote->getVote() < 0) {
				$votes[$id]['contra'][] = array('voter'=>$vote->getCharacter(), 'votes'=>$weight);
			} else {
				$votes[$id]['pro'][] = array('voter'=>$vote->getCharacter(), 'votes'=>$weight);
			}
		}
		return $votes;
	}

}
