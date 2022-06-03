<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\GeoData;
use BM2\SiteBundle\Form\EditGeoDataType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/world")
 */
class WorldController extends Controller {

	/**
	  * @Route("/regions", name="maf_regions")
	  * @Route("/regions/{page}", name="maf_regions_page")
	  */
	public function regionsAction($page = 1) {
		$em = $this->getDoctrine()->getManager();
		$count = ($page - 1) * 100;

		$query = $em->createQuery('SELECT g FROM BM2SiteBundle:GeoData g WHERE g.id > :count ORDER BY g.id ASC');
		$query->setParameters(['count'=>$count]);

		return $this->render('World/regions.html.twig', [
			'regions' => $query->getResult(),
			'page' => $page
		]);
	}

	/**
	  * @Route("/region/{id}", name="maf_region_edit", requirements={"id"="\d+"})
	  */
	public function regionEditAction(Request $request, GeoData $region) {
		$form = $this->createForm(new EditGeoDataType(), $region);
		$form->handleRequest($request);
		if ($form->isValid()) {
			# TODO: Need to add logic for handling resources here.
			#$this->getDoctrine()->getManager()->flush();
			return new RedirectResponse($this->generateUrl('maf_regions').'#'.$region->getId());
		}

		return $this->render('World/editRegion.html.twig', [
			'region'=>$region,
			'form'=>$form->createView(),
		]);
	}

}
