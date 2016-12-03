<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Character;

# use BM2\SiteBundle\Form\CharacterBackgroundType;
# use BM2\SiteBundle\Form\CharacterPlacementType;
# use BM2\SiteBundle\Form\CharacterRatingType;
# use BM2\SiteBundle\Form\EntourageManageType;
# use BM2\SiteBundle\Form\SoldiersManageType;
# use BM2\SiteBundle\Form\InteractionType;

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

	private $house;

	/**
	  * @Route("/{id}", name="bm2_house", requirements={"id"="\d+"})
	  * @Template("BM2SiteBundle:House:house.html.twig")
	  */
	
	public function viewAction(House $id) {
		$house = $id;
		$inhouse = false;
		
		$character = $this->get('appstate')getCharacters(false, true, true);
		if ($character) {
			if ($character->getHouse() == $house) {
				$inhouse = true;
			}
		}
	}
	
	/**
	  * @Route("/{id}/edit", requirements={"id"="\d+"})
	  * @Template
	  */
		
	public function backgroundAction(Request $request) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		$house = $id;
		$em = $this->getDoctrine()->getManager();

		$form = $this->createForm(new HouseBackgroundType($house);
		$form->handleRequest($request);
		if ($character->getHouse()->getHead() !== $house->getHead()) {
			throw createNotFoundException('error.notfound.head');
		}
		if ($form->isValid()) {
			// FIXME: this causes the (valid markdown) like "> and &" to be converted - maybe strip-tags is better?;
			// FIXME: need to apply this here - maybe data transformers or something?
			// htmlspecialchars($data['subject'], ENT_NOQUOTES);
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('house.background.updated', array(), 'actions'));
		}
		return array(
			'form' => $form->createView(),
		);
	}
					  
