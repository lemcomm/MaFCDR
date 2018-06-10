<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\House;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;

use BM2\SiteBundle\Form\HouseCreationType;
use BM2\SiteBundle\Form\HouseJoinType;
use BM2\SiteBundle\Form\AreYouSureType;

use BM2\SiteBundle\Service\GameRequestManager;
use BM2\SiteBundle\Service\Geography;
use BM2\SiteBundle\Service\History;
use BM2\SiteBundle\Service\DescriptionManager;

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

	private $house;

	/**
	  * @Route("/{house}", name="bm2_house", requirements={"house"="\d+"})
	  * @Template("BM2SiteBundle:House:view.html.twig")
	  */
	
	public function viewAction(House $house) {
		$inhouse = false;
		$details = false;
		$head = false;
		$character = $this->get('appstate')->getCharacter(false, true, true);
		if ($character) {
			if ($character->getHouse() == $house) {
				$details = true;
				if ($character->getHeadOfHouses()->contains($house)) {
					$head = true;
				}
			}
		}
		
		return array(
			'house' => $house,
			'details' => $details,
			'head' => $head
		);
	}

	/**
	  * @Route("/create", name="bm2_house_create")
	  * @Template
	  */	
	
	public function createAction(Request $request) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		if ($character->getInsideSettlement()) {
			$settlement = $character->getInsideSettlement();
		} else {
			throw createNotFoundException('unvailable.notinside');
		}
		if ($character->getHouse()) {
			throw createNotFoundException('error.found.house');
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
			$house = $this->get('house_manager')->create($data['name'], $data['description'], $data['private'], $data['secret'], null, $settlement, $crest, $character);
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('house.updated.created', array(), 'actions'));
			return $this->redirectToRoute('bm2_house', array('house'=>$house->getId()));
		}
		return array(
			'form' => $form->createView()
		);
	}
	
	/**
	  * @Route("/{house}/edit", name="bm2_house_manage", requirements={"house"="\d+"})
	  * @Template
	  */
		
	public function editAction(House $house, Request $request) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		$em = $this->getDoctrine()->getManager();
		
		$name = $house->getName();

		$form = $this->createForm(new HouseCreationType($name, $desc, $priv, $secret));
		$form->handleRequest($request);
		if ($character != $house->getHead()) {
			throw createNotFoundException('error.noaccess.nothead');
		}
		if ($form->isValid()) {
			$data = $form->getData();
			$change = FALSE;
			// FIXME: this causes the (valid markdown) like "> and &" to be converted - maybe strip-tags is better?;
			// FIXME: need to apply this here - maybe data transformers or something?
			// htmlspecialchars($data['subject'], ENT_NOQUOTES);
			if ((!$house->getDescription() AND $data['description'] != NULL) OR ($data['description'] != NULL AND $house->getDescription() != $data['description'])) {
				$this->get('description_manager')->newDescription($house, $data['description'], $character);
				$change = TRUE;
			} else if ($house->getDescription() AND $data['description'] != $house->getDescription()->getText()) {
				$this->get('description_manager')->newDescription($house, $data['description'], $character);
				$change = TRUE;
			}
			if ($data['secret'] != $house->getSecret()) {
				$house->setSecret($data['secret']);
				$change = TRUE;
			}
			if ($data['private'] != $house->getPrivate()) {
				$house->setPrivate($data['secret']);
				$change = TRUE;
			}
			if ($change) {
				$em->flush();
			}
			$this->addFlash('notice', $this->get('translator')->trans('house.updated.background', array(), 'actions'));
			return $this->redirectToRoute('bm2_house', array('house'=>$house->getId()));
		}
		return array(
			'form' => $form->createView()
		);
	}
					  
	/**
	  * @Route("/{house}/join", name="bm2_house_join", requirements={"house"="\d+"})
	  * @Template
	  */
	
		/* TODO: Review all of this file below this line. The above should be good.
		We'll want to be sure to work joinAction and approveAction into the GameRequest system.*/
	
	public function joinAction(House $house, Request $request) {
		$hashouse = FALSE;
		$character = $this->get('appstate')->getCharacter(true, true, true);
		
		$em = $this->getDoctrine()->getManager();
		# TODO: Rework this later to allow for Houses at Places.
		if (!$character->getInsideSettlement()) {
			throw createNotFoundException('unvailable.notinside');
		}
		if ($house->getInsideSettlement() != $character->getInsideSettlement()) {
			throw createNotFoundException('error.notfound.housenothere');
		}
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
				$this->addFlash('notice', $this->get('translator')->trans('house.member.joinfail', array(), 'actions'));
			}
			$this->addFlash('notice', $this->get('translator')->trans('house.member.join', array(), 'actions'));
			return $this->redirectToRoute('bm2_house', array('house'=>$house->getId()));
		}
		return array(
			'form' => $form->createView()
		);
	}

	/**
	  * @Route("/{house}/applicants", name="bm2_house_applicants", requirements={"house"="\d+"})
	  * @Template
	  */
	
	public function applicantsAction(House $house, Request $request) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		$em = $this->getDoctrine()->getManager();
		if (!$character->getHouse()) {
			throw createNotFoundException('error.noaccess.nohouse');
		}
		if ($character->getHouse()->getHead() != $house->getHead()) {
			throw createNotFoundException('error.noaccess.nothead');
		}
		$joinrequests = $em->getRepository('BM2SiteBundle\GameRequest')->findBy(array('type' => 'house.join', 'toHouse' => $house));
		return array(
			'joinrequests' => $joinrequests
		);
	}

	/**
	  * @Route("/{house}/approve/{id}", name="bm2_house_approve", requirements={"house"="\d+", "id"="\d+"})
	  * @Template
	  */
	
	public function approveAction(House $house, GameRequest $id, Request $request) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		$em = $this->getDoctrine()->getManager();
		if (!$character->getHouse()) {
			throw createNotFoundException('error.noaccess.nohouse');
		}
		if ($character->getHouse()->getHead() != $house->getHead()) {
			throw createNotFoundException('error.noaccess.nothead');
		}
		$form = $this->createForm(new HouseJoinRequestType(), $id);
	}
}
