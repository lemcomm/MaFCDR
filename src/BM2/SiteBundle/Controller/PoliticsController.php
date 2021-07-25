<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Listing;
use BM2\SiteBundle\Entity\Partnership;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\RealmPosition;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Form\CharacterSelectType;
use BM2\SiteBundle\Form\ListingType;
use BM2\SiteBundle\Form\PartnershipsType;
use BM2\SiteBundle\Form\PrisonersManageType;
use BM2\SiteBundle\Service\History;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


/**
 * @Route("/politics")
 */
class PoliticsController extends Controller {

	private $hierarchy=array();

   /**
     * @Route("/", name="bm2_politics")
     */
	public function indexAction() {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/politics.html.twig');
	}

   /**
     * @Route("/realms", name="bm2_politics_realms")
     */
	public function realmsAction() {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/realms.html.twig');
	}

   /**
     * @Route("/relations", name="bm2_relations")
     */
	public function relationsAction() {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/relations.html.twig');
	}

   /**
     * @Route("/hierarchy")
     */
	public function hierarchyAction() {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$this->addToHierarchy($character);

	   	$descriptorspec = array(
			   0 => array("pipe", "r"),  // stdin
			   1 => array("pipe", "w"),  // stdout
			   2 => array("pipe", "w") // stderr
			);

	   	$process = proc_open('dot -Tsvg', $descriptorspec, $pipes, '/tmp', array());

	   	if (is_resource($process)) {
	   		$dot = $this->renderView('Politics/hierarchy.dot.twig', array('hierarchy'=>$this->hierarchy, 'me'=>$character));

	   		fwrite($pipes[0], $dot);
	   		fclose($pipes[0]);

	   		$svg = stream_get_contents($pipes[1]);
	   		fclose($pipes[1]);

	   		$return_value = proc_close($process);
	   	}

		return $this->render('Politics/hierarchy.html.twig', [
			'svg'=>$svg
		]);
	}

	private function addToHierarchy(Character $character) {
		if (!isset($this->hierarchy[$character->getId()])) {
			$this->hierarchy[$character->getId()] = $character;
			if ($liege = $character->findLiege()) {
				if ($liege instanceof ArrayCollection) {
					foreach ($liege as $one) {
						$this->addToHierarchy($one);
					}
				} else {
					$this->addToHierarchy($liege);
				}
			}
			foreach ($character->findVassals() as $vassal) {
				$this->addToHierarchy($vassal);
			}
		}
	}

	/**
	  * @Route("/vassals")
	  */
	public function vassalsAction() {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/vassals.html.twig', [
			'vassals'=>$character->findVassals()
		]);
	}

	/**
	  * @Route("/disown/{vassal}")
	  */
	public function disownAction(Request $request, Character $vassal) {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if (!$character->findVassals()->contains($vassal)) {
			throw new AccessDeniedHttpException("error.noaccess.vassal");
		}

		$form = $this->createFormBuilder()
				->add('submit', 'submit', array('label'=>$this->get('translator')->trans('vassals.disown.submit', array(), "politics")))
				->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			$this->get('politics')->disown($vassal);
			$em = $this->getDoctrine()->getManager();
			$em->flush();
			return $this->render('Politics/disown.html.twig', [
				'vassal'=>$vassal,
				'success'=>true
			]);
		}

		return $this->render('Politics/disown.html.twig', [
			'vassal'=>$vassal,
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/offeroath", name="maf_politics_oath_offer")
	  */
	public function offerOathAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('hierarchyOfferOathTest');
		if (!$character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$others = $this->get('dispatcher')->getActionableCharacters();
		$options = [];
		if ($character->getInsideSettlement()) {
			$options[] = $character->getInsideSettlement();
		}
		if ($character->getInsidePlace()) {
			$options[] = $character->getInsidePlace();
		}
		foreach ($others as $other) {
			$otherchar = $other['character'];
			if ($otherchar->getOwnedSettlements()){
				foreach ($otherchar->getOwnedSettlements() as $settlement) {
					if (!in_array($settlement, $options)) {
						$options[] = $settlement;
					}
				}
			}
			if ($otherchar->getPositions()) {
				foreach ($otherchar->getPositions() as $pos) {
					if ($pos->getVassals() && !in_array($pos, $options)) {
						$options[] = $pos;
					}
				}
			}
			if ($otherchar->getOwnedPlaces()) {
				foreach ($otherchar->getOwnedPlaces() as $place) {
					if ($place->getType()->getVassals()) {
						$options[] = $place;
					}
				}
			}
		}
		$form = $this->createFormBuilder()
			->add('liege', ChoiceType::class, [
			'label'=>'oath.offerto',
			'required'=>true,
			'empty_value'=>'oath.choose',
			'translation_domain'=>'politics',
			'choices'=>$options,
			'choices_as_values'=>true,
			'choice_label' => function ($choice, $key, $value) {
				if ($choice instanceof Settlement) {
					if ($choice->getRealm()) {
						return $choice->getName().' ('.$choice->getRealm()->getName().')';
					} else {
						return $choice->getName();
					}
				}
				if ($choice instanceof RealmPosition) {
					if ($choice->getName() == 'ruler') {
						return 'Ruler of '.$choice->getRealm()->getName();
					}
					return $choice->getName().' ('.$choice->getRealm()->getName().')';
				}
				if ($choice instanceof Place) {
					if ($choice->getRealm()) {
						if ($choice->getHouse()) {
							return $choice->getName().' - '.ucfirst($choice->getType()->getName()).' of '.$choice->getHouse()->getName().' in '.$choice->getRealm()->getName();
						} else {
							return $choice->getName().' - '.ucfirst($choice->getType()->getName()).' in '.$choice->getRealm()->getName();
						}
					} elseif ($choice->getHouse()) {
						return $choice->getName().' - '.ucfirst($choice->getType()->getName()).' of '.$choice->getHouse()->getName();
					} else {
						return $choice->getName().' - '.ucfirst($choice->getType()->getName());
					}
				}
			},
			'group_by' => function($choice, $key, $value) {
				if ($choice instanceof Settlement) {
					return 'Settlements';
				}
				if ($choice instanceof RealmPosition) {
					if ($choice->getRuler()) {
						return 'Rulers';
					}
					return 'Other Positions';
				}
				if ($choice instanceof Place) {
					return 'Places';
				}
			},
		])->add('message', TextareaType::class, [
			'label' => 'oath.message',
			'translation_domain'=>'politics',
			'required' => true
		])->add('submit', SubmitType::class, [
			'label'=>'oath.submit',
			'translation_domain'=>'politics',
		])->getForm();

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$this->get('game_request_manager')->newOathOffer($character, $data['message'], $data['liege']);
			$this->addFlash('success', $this->get('translator')->trans('oath.offered', array(), 'politics'));
			return $this->redirectToRoute('bm2_relations');
		}

		return $this->render('Politics/offerOath.html.twig', [
			'form' => $form->createView(),
		]);
	}

   /**
     * @Route("/breakoath")
     */
	public function breakoathAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('hierarchyIndependenceTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		if ($request->isMethod('POST')) {
			$this->get('politics')->breakoath($character);
			$em = $this->getDoctrine()->getManager()->flush();
			$this->addFlash('success', $this->get('translator')->trans('oath.broken', array(), 'politics'));
			return $this->redirectToRoute('bm2_relations');
		}

		return $this->render('Politics/breakoath.html.twig');
	}

   /**
     * @Route("/successor")
     */
	public function successorAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('InheritanceSuccessorTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		$others = $this->get('dispatcher')->getActionableCharacters();
		$availableLords = array();
		foreach ($others as $other) {
			if (!$other['character']->isNPC() && $other['character'] != $character->getSuccessor()) {
				$availableLords[] = $other['character'];
			}
		}
		foreach ($character->getUser()->getCharacters() as $mychar) {
			if (!$mychar->isNPC() && !$mychar->getRetired() && $mychar != $character && $mychar->isAlive() && $mychar != $character->getSuccessor()) {
				$availableLords[] = $mychar;
			}
		}
		foreach ($character->getPartnerships() as $partnership) {
			$mychar = $partnership->getOtherPartner($character);
			if (!$mychar->isNPC() && !$mychar->getRetired() && $mychar != $character && $mychar->isAlive() && $mychar != $character->getSuccessor()) {
				$availableLords[] = $mychar;
			}
		}


		$form = $this->createForm(new CharacterSelectType($availableLords, 'successor.choose', 'successor.submit', 'successor.submit', 'politics'));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$successor = $data['target'];

			if ($character->getSuccessor()) {
				$this->get('history')->logEvent(
					$character->getSuccessor(),
					'politics.successor.removed',
					array('%link-character%'=>$character->getId()),
					History::MEDIUM
				);

				// FIXME: can $successor be NULL? (i.e. setting no successor) => in such case, this throws an exception
				// 		 (and the one in the else as well)
				$this->get('history')->logEvent(
					$character,
					'politics.successor.changed',
					array('%link-character-1%'=>$character->getSuccessor()->getId(), '%link-character-2%'=>$successor->getId()),
					History::LOW
				);
			} else {
				$this->get('history')->logEvent(
					$character,
					'politics.successor.set',
					array('%link-character%'=>$successor->getId()),
					History::LOW
				);
			}

			$character->setSuccessor($successor);

			// message to new successor
			$this->get('history')->logEvent(
				$successor,
				'politics.successor.new',
				array('%link-character%'=>$character->getId()),
				History::MEDIUM
			);

			$em->flush();

			#TODO: Convert this to a redirect and flash.
			return $this->render('Politics/successor.html.twig', [
				'success'=>true
			]);
		}

		return $this->render('Politics/successor.html.twig', [
			'form'=>$form->createView()
		]);
	}


   /**
     * @Route("/partners/{type}", defaults={"type":null})
     */
	public function partnersAction(Request $request, $type=null) {
		$character = $this->get('dispatcher')->gateway('partnershipsTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$newavailable = false;
		$form_old_view=null; $form_new_view=null;

		if ($type==null || $type=='old') {
			$query = $em->createQuery('SELECT DISTINCT p FROM BM2SiteBundle:Partnership p JOIN p.partners c WHERE c = :me AND p.end_date IS NULL');
			$query->setParameter('me', $character);
			$currentRelations = $query->getResult();
			$form_old = $this->createForm(new PartnershipsType($character, false, $currentRelations));
			$form_old_view = $form_old->createView();
		}
		if ($type==null || $type=='new') {
			// FIXME: shouldn't this be in the dispatcher?
			$others = $this->get('dispatcher')->getActionableCharacters();
			$choices=array();
			if ($character->getPartnerships()) {
				foreach ($character->getPartnerships() as $partnership) {
					if (!$partnership->getEndDate()) {
						$existingpartners[] = $partnership->getOtherPartner($character);
					}
				}
			}
			foreach ($others as $other) {
				$char = $other['character'];
				if ($character->getNonHeteroOptions()) {
					if (!$char->isNPC() && $char->isActive(true) && !in_array($char, $existingpartners)) {
						$choices[$char->getId()] = $char->getName();
					}
				} else {
					if (!$char->isNPC() && $char->isActive(true) && !in_array($char, $existingpartners) && $char->getMale() != $character->getMale()) {
						$choices[$char->getId()] = $char->getName();
					}
				}
				// TODO: filter out existing partnerships
			}
			$form_new = $this->createForm(new PartnershipsType($character, true, $choices));
			$form_new_view = $form_new->createView();
			if (empty($choices)) { $newavailable=false; } else { $newavailable=true; }
		}

		if ($request->isMethod('POST')) {
			if ($type=='old') {
				$form_old->bind($request);
				if ($form_old->isValid()) {
					$data = $form_old->getData();

					foreach ($data['partnership'] as $id=>$change) {
						if (!$change) continue;
						$relation = $em->getRepository('BM2SiteBundle:Partnership')->find($id);
						switch ($change) {
							case 'public':
								// TODO: event posting
								$relation->setPublic(true);
								break;
							case 'nosex':
								$relation->setWithSex(false);
								break;
							case 'cancel':
								// TODO: event posting
								$relation->setActive(false);
								$relation->setEndDate(new \DateTime("now"));
								break;
							case 'withdraw':
								// TODO: notify other
								$em->remove($relation);
								break;
							case 'accept':
								$relation->setActive(true);
								$relation->setStartDate(new \DateTime("now"));
								if (in_array($relation->getType(), array("marriage", "engagement")) && $relation->getPublic()) {
									if ($relation->getType()=="marriage") {
										$priority = History::HIGH;
									} else {
										$priority = History::MEDIUM;
									}
									foreach ($relation->getPartners() as $partner) {
										$other = $relation->getOtherPartner($partner);
										$this->get('history')->logEvent(
											$partner,
											'event.character.public.'.$relation->getType(),
											array('%link-character%'=>$other->getId()),
											$priority, true
										);
									}
								} else {
									foreach ($relation->getPartners() as $partner) {
										$other = $relation->getOtherPartner($partner);
										$this->get('history')->logEvent(
											$partner,
											'event.character.secret.'.$relation->getType(),
											array('%link-character%'=>$other->getId()),
											HISTORY::MEDIUM, false
										);
									}
								}
								break;
							case 'reject':
								// inform the other
								$other = $relation->getOtherPartner($character);
								$this->get('history')->logEvent(
									$other,
									'event.character.rejected.'.$relation->getType(),
									array('%link-character%'=>$character->getId()),
									HISTORY::HIGH, false, 20
								);
								$em->remove($relation);
								break;
						}
					}
					$em->flush();

					return $this->redirectToRoute('bm2_site_politics_partners');
				}
			} else if ($type=='new') {
				$form_new->bind($request);
				if ($form_new->isValid()) {
					$data = $form_new->getData();

					$partner = $em->getRepository('BM2SiteBundle:Character')->find($data['partner']);
					$relation = new Partnership;
					$relation->setType($data['type']);
					$relation->setPublic($data['public']);
					$relation->setWithSex($data['sex']);
					$relation->setActive(false);
					$relation->setInitiator($character);
					$relation->setPartnerMayUseCrest($data['crest']);
					$relation->addPartner($character);
					$relation->addPartner($partner);
					$em->persist($relation);

					// inform the other
					$this->get('history')->logEvent(
						$partner,
						'event.character.proposed.'.$relation->getType(),
						array('%link-character%'=>$character->getId()),
						HISTORY::HIGH, false, 20
					);
					$em->flush();

					return $this->redirectToRoute('bm2_site_politics_partners');
				}
			}
		}

		return $this->render('Politics/partners.html.twig', [
			'newavailable' => $newavailable,
			'form_old'=>$form_old_view,
			'form_new'=>$form_new_view
		]);
	}


	/**
	  * @Route("/lists", name="bm2_lists")
	  */
	public function listsAction(Request $request) {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/lists.html.twig', [
			'listings' => $character->getUser()->getListings(),
		]);
	}

	/**
	  * @Route("/list/{id}", requirements={"id"="\d+"})
	  */
	public function listAction($id, Request $request) {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$using = false;

		if ($id>0) {
			$listing = $character->getUser()->getListings()->filter(
				function($entry) use ($id) {
					return ($entry->getId()==$id);
				}
			)->first();
			if (!$listing) {
				throw $this->createNotFoundException('error.notfound.listing');
			}
			$can_delete = true;
			if ($id > 0) {
				$locked_reasons = array();
				if (!$listing->getDescendants()->isEmpty()) {
					$can_delete = false;
					$locked_reasons[] = "descendants";
				}
				$using = $em->getRepository('BM2SiteBundle:SettlementPermission')->findByListing($listing);
				if ($using && !empty($using)) {
					$can_delete = false;
					$locked_reasons[] = "used";
				}
			}
			$is_new = false;
		} else {
			$listing = new Listing;
			$listing->setName('new list'); // this prevents SQL errors below, somehow the required for name doesn't catch
			$can_delete = false;
			$locked_reasons = array();
			$is_new = true;
		}

		$available = array();
		foreach ($character->getUser()->getListings() as $l) {
			if ($listing != $l) {
				$available[] = $l->getId();
			}
		}

		$form = $this->createForm(new ListingType($this->getDoctrine()->getManager(), $available), $listing);
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			if ($id==0) {
				$listing->setOwner($character->getUser());
				$listing->setCreator($character);
				$em->persist($listing);
			}
			if ($listing->getInheritFrom()) {
				// check for loops
				$seen = new ArrayCollection;
				$seen->add($listing);
				$current = $listing;
				while ($parent = $current->getInheritFrom()) {
					if ($seen->contains($parent)) {
						// loop!
						$listing->setInheritFrom(null);
						// FIXME: is never actually displayed due to the redirect below :-(
						$form->addError(new FormError("listing.loop"));
					}
					$seen->add($parent);
					$current = $parent;
				}
			}
			// FIXME: this works on character names, WHICH ARE NOT UNIQUE!
			foreach ($listing->getMembers() as $member) {
				if (!$member->getId()) {
					if ($id==0) {
						$member->setListing($listing);
					}
					$em->persist($member);
				} elseif (!$member->getTargetRealm() && !$member->getTargetCharacter()) {
					$listing->removeMember($member);
					$em->remove($member);
				}
			}
			$em->flush();
			$this->addFlash('success', $this->get('translator')->trans('lists.updated', array(), 'politics'));
			return $this->redirectToRoute('bm2_site_politics_list', array('id'=>$listing->getId()));
		}

		if ($can_delete) {
			$form_delete = $this->createFormBuilder()
				->add('submit', 'submit', array(
					'label'=>'lists.delete.submit',
					'translation_domain' => 'politics'
					))
				->getForm();
			$form_delete->handleRequest($request);
			if ($form_delete->isValid()) {
				$name = $listing->getName();
				foreach ($listing->getMembers() as $member) {
					$em->remove($member);
				}
				$em->remove($listing);
				$em->flush();
				$this->addFlash('notice', $this->get('translator')->trans('lists.delete.done', array("%name%"=>$name), 'politics'));
				return $this->redirectToRoute('bm2_lists');
			}
		}

		$used_by = array();
		if ($using) foreach ($using as $perm) {
			if (!in_array($perm->getSettlement(), $used_by)) {
				$used_by[] = $perm->getSettlement();
			}
		}

		return $this->render('Politics/list.html.twig', [
			'listing' => $listing,
			'used_by' => $used_by,
			'can_delete' => $can_delete,
			'locked_reasons' => $locked_reasons,
			'is_new' => $is_new,
			'form' => $form->createView(),
			'form_delete' => $can_delete?$form_delete->createView():null
		]);
	}


	/**
	  * @Route("/prisoners")
	  */
	public function prisonersAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('personalPrisonersTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$results = array();
		$others = $this->get('dispatcher')->getActionableCharacters();
		$prisoners = $character->getPrisoners();
		$form = $this->createForm(new PrisonersManageType($prisoners, $others));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$change_others = false;

			foreach ($data['prisoners'] as $id=>$do) {
				$prisoner = $prisoners[$id];
				switch ($do['action']) {
					case 'free':
						$prisoner->setPrisonerOf(null);
						$character->removePrisoner($prisoner);
						$this->get('history')->logEvent(
							$prisoner,
							'event.character.prison.free',
							null,
							History::HIGH, true, 30
						);
						$change_others = true;
						$results[] = array('prisoner'=>$prisoner, 'action'=>'free');
						break;
					case 'execute':
						if ($do['method']) {
							$prisoner->setPrisonerOf(null);
							$character->removePrisoner($prisoner);
							$this->get('history')->logEvent(
								$character,
								'event.character.prison.killer.'.$do['method'],
								array('%link-character%'=>$prisoner->getId()),
								History::MEDIUM, true
							);
							$this->get('character_manager')->kill($prisoner, $character, false, 'prison.kill.'.$do['method']);
							$results[] = array('prisoner'=>$prisoner, 'action'=>'execute');
						}
						break;
					case 'assign':
						if ($data['assignto'] && !$prisoner->hasAction('personal.prisonassign')) {
							$prisoner->setPrisonerOf($data['assignto']);
							$character->removePrisoner($prisoner);
							$data['assignto']->addPrisoner($prisoner);

							// 2 hour blocking action
							$act = new Action;
							$act->setType('personal.prisonassign')->setCharacter($prisoner);
							$complete = new \DateTime("now");
							$complete->add(new \DateInterval("PT2H"));
							$act->setComplete($complete);
							$act->setBlockTravel(false);
							$this->get('action_manager')->queue($act);

							$this->get('history')->logEvent(
								$prisoner,
								'event.character.prison.assign',
								array('%link-character%'=>$data['assignto']->getId()),
								History::MEDIUM, true, 20
							);
							$this->get('history')->logEvent(
								$data['assignto'],
								'event.character.prison.received',
								array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$prisoner->getId()),
								History::MEDIUM, true, 20
							);
							$results[] = array('prisoner'=>$prisoner, 'action'=>'assign', 'target'=>$data['assignto']);
						}
						break;
				}
			}
			$this->getDoctrine()->getManager()->flush();
			if ($change_others) {
				$others = $this->get('dispatcher')->getActionableCharacters();
			}
			$form = $this->createForm(new PrisonersManageType($prisoners, $others));
		}

		return $this->render('Politics/prisoners.html.twig', [
			'form' => $form->createView(),
			'results' => $results
		]);
	}

	/**
	  * @Route("/claims")
	  */
	public function claimsAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('personalClaimsTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/claims.html.twig', [
			'claims'=>$character->getSettlementClaims()
		]);
	}



	/**
	  * @Route("/claimadd/{settlement}")
	  */
	public function claimaddAction(Settlement $settlement) {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if (!$settlement) {
			throw $this->createNotFoundException('error.notfound.settlement');
		}

		if ($character->getSettlementClaims()->contains($settlement)) {
			$this->addFlash('error', $this->get('translator')->trans('claim.already', array(), 'politics'));
		} else {
			$heralds = $character->getAvailableEntourageOfType('Herald');
			if ($heralds->count() > 0) {
				$em = $this->getDoctrine()->getManager();
				$em->remove($heralds->first());
				$this->get('politics')->addClaim($character, $settlement);
				$this->get('history')->logEvent(
					$settlement,
					'event.settlement.claim.added',
					array('%link-character%'=>$character->getId()),
					History::MEDIUM, true, 90
				);
				$em->flush();
				$this->addFlash('success', $this->get('translator')->trans('claim.added', array(), 'politics'));
			} else {
				$this->addFlash('error', $this->get('translator')->trans('claim.noherald', array(), 'politics'));
			}
		}

		return $this->redirectToRoute('bm2_settlement', array('id'=>$settlement->getId()));
	}

	/**
	  * @Route("/claimcancel/{settlement}")
	  */
	public function claimcancelAction(Settlement $settlement) {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if (!$settlement) {
			throw $this->createNotFoundException('error.notfound.settlement');
		}

		if ($this->get('politics')->removeClaim($character, $settlement)) {
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('success', $this->get('translator')->trans('claim.cancelled', array(), 'politics'));
			$this->get('history')->logEvent(
				$settlement,
				'event.settlement.claim.cancelled',
				array('%link-character%'=>$character->getId()),
				History::MEDIUM, true, 90
			);
		} else {
			$this->addFlash('error', $this->get('translator')->trans('claim.donthave', array(), 'politics'));
		}

		return $this->redirectToRoute('bm2_settlement', array('id'=>$settlement->getId()));
	}

}
