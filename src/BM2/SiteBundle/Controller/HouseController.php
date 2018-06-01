<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\House;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;

use BM2\SiteBundle\Form\HouseCreationType;

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
		
		$character = $this->get('appstate')->getCharacter(false, true, true);
		if ($character) {
			if ($character->getHouse() == $house) {
				$inhouse = true;
			}
		}
		
		return array(
			'inhouse' => $inhouse,
		);
	}

	/**
	  * @Route("/create", name="bm2_house_create")
	  * @Template
	  */	
	
	public function createAction(Request $request) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		$em = $this->getDoctrine()->getManager();
		$crest = $character->getCrest();
		if ($character->getInsideSettlement()) {
			$settlement = $character->getInsideSettlement();
		} else {
			throw createNotFoundException('unvailable.notinside');
		}
		if ($character->getHouse()) {
			throw createNotFoundException('error.found.house');
		}
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
		}
		return array(
			'form' => $form->createView(),
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
			if (!$house->getDescription() AND $data['description'] != NULL) {
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
		}
		return array(
			'form' => $form->createView(),
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
		if (!in_array($house, $character->getInsideSettlement()->getHouses())) {
			throw createNotFoundException('error.notfound.nohouse');
		} 
		$form = $this->createForm(new HouseJoinType($house));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('house.member.join', array(), 'actions'));
		}
		return array(
			'form' => $form->createView(),
		);
	}

	/**
	  * @Route("/{house}/approve", name="bm2_house_approve", requirements={"house"="\d+"})
	  * @Template
	  */
	
	public function approveAction(House $house, Request $request) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		$em = $this->getDoctrine()->getManager();
		if (!$character->getHouse()) {
			throw createNotFoundException('error.noaccess.nohouse');
		}
		if ($character->getHouse()->getHead() !== $house->getHead()) {
			throw createNotFoundException('error.noaccess.nothead');
		}
		$form = $this->createForm(new HouseApproveType($house));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('house.member.approve', array(), 'actions'));
		}
		return array(
			'form' => $form->createView(),
		);
	}
}
