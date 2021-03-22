<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;
use BM2\SiteBundle\Entity\House;

use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\DescriptionNewType;
use BM2\SiteBundle\Form\HouseCadetType;
use BM2\SiteBundle\Form\HouseCreationType;
use BM2\SiteBundle\Form\HouseJoinType;
use BM2\SiteBundle\Form\HouseMembersType;

use BM2\SiteBundle\Service\DescriptionManager;
use BM2\SiteBundle\Service\GameRequestManager;
use BM2\SiteBundle\Service\Geography;
use BM2\SiteBundle\Service\History;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/house")
 */
class HouseController extends Controller {

	/**
	  * @Route("/{id}", name="maf_house", requirements={"id"="\d+"})
	  */

	public function viewAction(House $house) {
		$details = false;
		$head = false;
		$character = $this->get('appstate')->getCharacter(false, true, true);
		if ($character instanceof Character) {
			if ($character->getHouse() == $house) {
				$details = true;
				if ($character->getHeadOfHouse() && $character->getHeadOfHouse() == $house) {
					$head = true;
				}
			}
		}
		
		return $this->render('BM2SiteBundle::House/view.html.twig', [
			'house' => $house,
			'details' => $details,
			'head' => $head
		]);
	}

	/**
	  * @Route("/nearby", name="maf_house_nearby")
	  */

	public function nearbyAction() {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$houses = [];
		if ($character->getInsideSettlement()) {
			$houses = $character->getInsideSettlement()->getHousesPresent();
		} else {
			#TODO: Add code for houses as places here.
		}
		$already = false;
		if ($character->getHouse()) {
			$already = true;
		}

		foreach ($houses as $house) {
			$member = false;
			$head = false;
			if ($house->getMembers()->contains($character)) {
				$member = true;
				if ($house->getHead() == $character) {
					$head = true;
				}
			}
		}
		
		return $this->render('BM2SiteBundle::House/nearby.html.twig', [
			'houses' => $houses,
			'already' => $already
		]);
	}

	/**
	  * @Route("/create", name="maf_house_create")
	  */

	public function createAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('houseCreateHouseTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$crest = $character->getCrest();
		$form = $this->createForm(new HouseCreationType());
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			// FIXME: this causes the (valid markdown) like "> and &" to be converted - maybe strip-tags is better?;
			// FIXME: need to apply this here - maybe data transformers or something?
			// htmlspecialchars($data['subject'], ENT_NOQUOTES);
			if ($character->getCrest()); {
				$crest = $character->getCrest();
			}
			if ($settlement = $character->getInsideSettlement()) {
				$house = $this->get('house_manager')->create($data['name'], $data['motto'], $data['description'], $data['private'], $data['secret'], null, null, $settlement, $crest, $character);
			} else {
				$house = $this->get('house_manager')->create($data['name'], $data['motto'], $data['description'], $data['private'], $data['secret'], null, $character->getInsidePlace(), null, $crest, $character);
			}
			# No flush needed, HouseMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('house.updated.created', array(), 'messages'));
			return $this->redirectToRoute('maf_house', array('id'=>$house->getId()));
		}
		return $this->render('BM2SiteBundle::House/create.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{house}/manage", name="maf_house_manage", requirements={"house"="\d+"})
	  */

	public function manageAction(House $house, Request $request) {
		$character = $this->get('dispatcher')->gateway('houseManageHouseTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		$name = $house->getName();
		$motto = $house->getMotto();
		if ($house->getDescription()) {
			$desc = $house->getDescription()->getText();
		} else {
			$desc = null;
		}
		$priv = $house->getPrivate();
		$secret = $house->getSecret();

		$form = $this->createForm(new HouseCreationType($name, $motto, $desc, $priv, $secret));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$change = FALSE;
			if ($data['name'] != $name) {
				$change = TRUE;
				$house->setName($data['name']);
				$this->get('history')->logEvent(
					$house,
					'event.house.newname',
					array('%name%'=>$data['name']),
					History::ULTRA, true
				);
			}
			if ($data['motto'] != $motto) {
				$change = TRUE;
				$house->setMotto($data['motto']);
			}
			if ((!$house->getDescription() AND $data['description'] != NULL) OR ($data['description'] != NULL AND ($house->getDescription() AND $desc != $data['description']))) {
				$this->get('description_manager')->newDescription($house, $data['description'], $character);
				$change = TRUE;
			} else if ($house->getDescription() AND $data['description'] != $desc) {
				$this->get('description_manager')->newDescription($house, $data['description'], $character);
				$change = TRUE;
			}
			if ($data['secret'] != $secret) {
				$house->setSecret($data['secret']);
				$change = TRUE;
			}
			if ($data['private'] != $priv) {
				$house->setPrivate($data['private']);
				$change = TRUE;
			}
			if ($change) {
				$em->flush();
			}
			$this->addFlash('notice', $this->get('translator')->trans('house.updated.background', array(), 'messages'));
			return $this->redirectToRoute('maf_house', array('id'=>$house->getId()));
		}
		return $this->render('BM2SiteBundle::House/manage.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{house}/join", name="maf_house_join", requirements={"house"="\d+"})
	  */

	public function joinAction(House $house, Request $request) {
		$character = $this->get('dispatcher')->gateway('houseJoinHouseTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$hashouse = FALSE;
		$form = $this->createForm(new HouseJoinType());
		$form->handleRequest($request);
		if ($form->isValid()) {
			$fail = true;
			$data = $form->getData();
			if ($data['sure']) {
				$fail = false;
			} else {
				$fail = true;
			}
			if (!$fail) {
				$this->get('game_request_manager')->newRequestFromCharacterToHouse('house.join', null, null, null, $data['subject'], $data['text'], $character, $house);
			} else {
				$this->addFlash('notice', $this->get('translator')->trans('house.member.joinfail', array(), 'messages'));
			}
			$this->addFlash('notice', $this->get('translator')->trans('house.member.join', array(), 'actions'));
			return $this->redirectToRoute('maf_house', array('id'=>$house->getId()));
		}
		return $this->render('BM2SiteBundle::House/join.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{house}/applicants", name="maf_house_applicants", requirements={"house"="\d+"})
	  */

	public function applicantsAction(House $house, Request $request) {
		# TODO: Make this a sub-route of the manage GameRequests route.
		$character = $this->get('dispatcher')->gateway('houseManageApplicantsTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$joinrequests = $em->getRepository('BM2SiteBundle:GameRequest')->findBy(array('type' => 'house.join', 'to_house' => $house));

		foreach ($joinrequests as $joinrequest) {
			$id = $joinrequest->getId();
			$subject = $joinrequest->getSubject();
			$text = $joinrequest->getText();
		}

		return $this->render('BM2SiteBundle::House/applicants.html.twig', [
			'name' => $house->getName(),
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{house}/disown", name="maf_house_disown", requirements={"house"="\d+"})
	  */

	public function disownAction(House $house, Request $request) {
		$character = $this->get('dispatcher')->gateway('houseManageDisownTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$members = $house->findAllMembers();

		$form = $this->createForm(new HouseMembersType($members));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$exile = $data['member'];
			if ($exile) {
				$exile->setHouse(null);
				if ($exile->isRuler()) {
					$this->get('history')->logEvent(
						$house,
						'event.house.exile.ruler',
						array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$exile->getId(), '%link-realm%'=>$exile->findHighestRulership()->getId()),
						History::HIGH, true
					);
				} else {
					$this->get('history')->logEvent(
						$house,
						'event.house.exile.knight',
						array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$exile->getId()),
						History::MEDIUM, true
					);
				}
				$this->get('history')->closeLog($house, $character);
				$em->flush();
				$this->addFlash('notice', $this->get('translator')->trans('house.member.exile', array('%link-character%'=>$exile->getId()), 'messages'));
				return $this->redirectToRoute('bm2_politics', array());
			}
		}

		return $this->render('BM2SiteBundle::House/disown.html.twig', [
			'name' => $house->getName(),
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{house}/successor", name="maf_house_successor", requirements={"house"="\d+"})
	  */

	public function successorAction(House $house, Request $request) {
		$character = $this->get('dispatcher')->gateway('houseManageSuccessorTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		$members = $house->findAllMembers();

		$form = $this->createForm(new HouseMembersType($members));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$member = $data['member'];
			if ($member) {
				$house->setSuccessor($member);
				$em->flush();
				$this->addFlash('notice', $this->get('translator')->trans('house.member.successor', array(), 'messages'));
				return $this->redirectToRoute('bm2_politics', array());
			}
		}

		return $this->render('BM2SiteBundle::House/successor.html.twig', [
			'name' => $house->getName(),
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/relocate", name="maf_house_relocate")
	  */

	public function relocateAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('houseManageRelocateTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$settlement = $character->getInsideSettlement();
		$place = $character->getInsidePlace();
		$house = $character->getHouse();
		# TODO: Rework this to use dispatcher.
		$em = $this->getDoctrine()->getManager();
		$form = $this->createForm(new AreYouSureType());
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$fail = true;
			if ($data['sure'] == true) {
				$fail = false;
			}
			if (!$fail) {
				#Update House location
				if (!$place) {
					$place->setHouse(null);
					$house->setInsideSettlement($settlement);
					#Create relocation event in House's event log
					$this->get('history')->logEvent(
						$house,
						'event.house.relocated.settlement',
						array('%link-settlement%'=>$settlement->getId()),
						History::HIGH, true
					);
				} else {
					$place->setHouse($house);
					$house->setInsideSettlement(null);
					#Create relocation event in House's event log
					$this->get('history')->logEvent(
						$house,
						'event.house.relocated.place',
						array('%link-place%'=>$place->getId()),
						History::HIGH, true
					);
				}
				$em->flush();
				#Add "success" flash message to the top of the redirected page for feedback.
				$this->addFlash('notice', $this->get('translator')->trans('house.updated.relocated', array(), 'messages'));
				return $this->redirectToRoute('bm2_politics', array());
			} else {
				/* You shouldn't ever reach this. The form requires input. */
			}
		}
		return $this->render('BM2SiteBundle::House/relocate.html.twig', [
			'name' => $house->getName(),
			'form' => $form->createView()
		]);
	}


	/**
	  * @Route("/{house}/newplayer", requirements={"house"="\d+"}, name="maf_house_newplayer")
	  */
	public function newplayerAction(House $house, Request $request) {
		$character = $this->get('dispatcher')->gateway('houseNewPlayerInfoTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$desc = $house->getSpawnDescription();
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
				$desc = $this->get('description_manager')->newSpawnDescription($house, $data['text'], $character);
			}
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('control.description.success', array(), 'actions'));
		}
		return $this->render('BM2SiteBundle::House/newplayer.html.twig', [
			'house'=>$house, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/{house}/spawntoggle", requirements={"house"="\d+"}, name="maf_house_spawn_toggle")
	  */
	public function houseSpawnToggleAction(House $house) {
		$character = $this->get('dispatcher')->gateway('houseSpawnToggleTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		if($spawn->getActive()) {
			$spawn->setActive(false);
		} else {
			$spawn->setActive(true);
		}
		$em->flush();
		return new RedirectResponse($this->generateUrl('bm2_politics'));
	}

	/**
	  * @Route("/{house}/cadet", name="maf_house_cadetship", requirements={"house"="\d+"})
	  */

	public function cadetAction(House $house, Request $request) {
		$character = $this->get('dispatcher')->gateway('houseManageCadetTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$myHouse = $character->getHouse();
		$form = $this->createForm(new HouseCadetType());
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$yes = $form->getData()['sure']
			if ($yes) {
				$this->get('game_request_manager')->newRequestFromHouseToHouse('house.cadet', null, null, null, $data['subject'], $data['text'], $character->getHouse(), $house);
			} else {
				$this->addFlash('notice', $this->get('translator')->trans('house.fail.cadet', array(), 'messages'));
			}
			$this->addFlash('notice', $this->get('translator')->trans('house.success.cadet', array(), 'messages'));
			return $this->redirectToRoute('maf_house', array('id'=>$house->getId()));
		}
		return $this->render('BM2SiteBundle::House/cadet.html.twig', [
			'house'=>$house,
			'myHouse'=>$myHouse,
			'form'=>$form->createView()
		]);
	}
}
