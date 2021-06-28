<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Form\RegisterType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;


/**
 * @Route("/info")
 */
class InfoController extends Controller {

	/**
	  * @Route("/buildingtypes")
	  */
	public function allbuildingtypesAction() {

		return $this->render('Info/all.html.twig', $this->alltypes('BuildingType'));
	}

   /**
     * @Route("/buildingtype/{id}", requirements={"id"="\d+"})
     */
	public function buildingtypeAction($id) {
		$em = $this->getDoctrine()->getManager();
		$buildingtype = $em->getRepository('BM2SiteBundle:BuildingType')->find($id);
		if (!$buildingtype) {
			throw $this->createNotFoundException('error.notfound.buildingtype');
		}

		return $this->render('Info/buildingtype.html.twig', [
			"buildingtype" => $buildingtype
		]);
	}

	/**
	  * @Route("/featuretypes")
	  */
	public function allfeaturetypesAction() {

		return $this->render('Info/all.html.twig', $this->alltypes('FeatureType'));
	}

   /**
     * @Route("/featuretype/{id}", requirements={"id"="\d+"})
     */
	public function featuretypeAction($id) {
		$em = $this->getDoctrine()->getManager();
		$featuretype = $em->getRepository('BM2SiteBundle:FeatureType')->find($id);
		if (!$featuretype) {
			throw $this->createNotFoundException('error.notfound.featuretype');
		}

		return $this->render('Info/featuretype.html.twig', [
			"featuretype" => $featuretype
		]);
	}

	/**
	  * @Route("/entouragetypes")
	  */
	public function allentouragetypesAction() {

		return $this->render('Info/all.html.twig', $this->alltypes('EntourageType'));
	}

   /**
     * @Route("/entouragetype/{id}", requirements={"id"="\d+"})
     */
	public function entouragetypeAction($id) {
		$em = $this->getDoctrine()->getManager();
		$entouragetype = $em->getRepository('BM2SiteBundle:EntourageType')->find($id);
		if (!$entouragetype) {
			throw $this->createNotFoundException('error.notfound.entouragetype');
		}

		return $this->render('Info/entouragetype.html.twig', [
			"entouragetype" => $entouragetype
		]);
	}

	/**
	  * @Route("/equipmenttypes")
	  */
	public function allequipmenttypesAction() {
		
		return $this->render('Info/all.html.twig', $this->alltypes('EquipmentType'));
	}

	/**
	  * @Route("/equipmenttype/{id}", requirements={"id"="\d+"})
	  */
	public function equipmenttypeAction($id) {
		$em = $this->getDoctrine()->getManager();
		$equipmenttype = $em->getRepository('BM2SiteBundle:EquipmentType')->find($id);
		if (!$equipmenttype) {
			throw $this->createNotFoundException('error.notfound.equipmenttype');
		}

		return $this->render('Info/equipmenttype.html.twig', [
			"equipmenttype" => $equipmenttype
		]);
	}


	private function alltypes($type) {
		$em = $this->getDoctrine()->getManager();
		$all = $em->getRepository('BM2SiteBundle:'.$type)->findBy([], ['name'=>'asc']);
		$toc = $this->get('pagereader')->getPage('manual', 'toc', $this->getRequest()->getLocale());

		return array(
			"toc" => $toc,
			"list" => strtolower($type).'s',
			"all" => $all
		);
	}

}
