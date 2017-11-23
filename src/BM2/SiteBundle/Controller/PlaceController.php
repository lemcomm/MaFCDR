<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\GeoFeature;
use BM2\SiteBundle\Form\PlacePermissionsSetType;
use BM2\SiteBundle\Form\SoldiersManageType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @Route("/place")
 */
class PlaceController extends Controller {


	private $slice_size = 500;

	/**
	  * @Route("/{id}", name="bm2_place", requirements={"id"="\d+"})
	  * @Template("BM2SiteBundle:Place:place.html.twig")
	  */
	public function indexAction(Settlement $id) {
		$em = $this->getDoctrine()->getManager();
		$place = $id; // we use $id because it's hardcoded in the linkhelper

		$character = $this->get('appstate')->getCharacter(false, true, true);
		if ($character != $place->getOwner()) {
			$heralds = $character->getAvailableEntourageOfType('Herald')->count();
		} else {
			$heralds = 0;
		}
		$details = $this->get('interactions')->characterViewPlace($character, $place);

		return array(
			'place' => $place,
			'details' => $details,
			'heralds' => $heralds
		);
	}


	/**
	  * @Route("/{id}/enter", requirements={"id"="\d+", "start"="\d+"}, defaults={"start"=0})
	  * @Template
	  */
