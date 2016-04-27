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
	  * @Template("BM2SiteBundle:Info:all.html.twig")
	  */
	public function allbuildingtypesAction() {
		return $this->alltypes('BuildingType');
	}

   /**
     * @Route("/buildingtype/{id}", requirements={"id"="\d+"})
     * @Template
     */
	public function buildingtypeAction($id) {
		$em = $this->getDoctrine()->getManager();
		$buildingtype = $em->getRepository('BM2SiteBundle:BuildingType')->find($id);
		if (!$buildingtype) {
			throw $this->createNotFoundException('error.notfound.buildingtype');
		}

		return array("buildingtype" => $buildingtype);
	}

	/**
	  * @Route("/featuretypes")
	  * @Template("BM2SiteBundle:Info:all.html.twig")
	  */
	public function allfeaturetypesAction() {
		return $this->alltypes('FeatureType');
	}

   /**
     * @Route("/featuretype/{id}", requirements={"id"="\d+"})
     * @Template
     */
	public function featuretypeAction($id) {
		$em = $this->getDoctrine()->getManager();
		$featuretype = $em->getRepository('BM2SiteBundle:FeatureType')->find($id);
		if (!$featuretype) {
			throw $this->createNotFoundException('error.notfound.featuretype');
		}

		return array("featuretype" => $featuretype);
	}

	/**
	  * @Route("/entouragetypes")
	  * @Template("BM2SiteBundle:Info:all.html.twig")
	  */
	public function allentouragetypesAction() {
		return $this->alltypes('EntourageType');
	}

   /**
     * @Route("/entouragetype/{id}", requirements={"id"="\d+"})
     * @Template
     */
	public function entouragetypeAction($id) {
		$em = $this->getDoctrine()->getManager();
		$entouragetype = $em->getRepository('BM2SiteBundle:EntourageType')->find($id);
		if (!$entouragetype) {
			throw $this->createNotFoundException('error.notfound.entouragetype');
		}

		return array("entouragetype" => $entouragetype);
	}

	/**
	  * @Route("/equipmenttypes")
	  * @Template("BM2SiteBundle:Info:all.html.twig")
	  */
	public function allequipmenttypesAction() {
		return $this->alltypes('EquipmentType');
	}

	/**
	  * @Route("/equipmenttype/{id}", requirements={"id"="\d+"})
	  * @Template
	  */
	public function equipmenttypeAction($id) {
		$em = $this->getDoctrine()->getManager();
		$equipmenttype = $em->getRepository('BM2SiteBundle:EquipmentType')->find($id);
		if (!$equipmenttype) {
			throw $this->createNotFoundException('error.notfound.equipmenttype');
		}

		return array("equipmenttype" => $equipmenttype);
	}


	private function alltypes($type) {
		$em = $this->getDoctrine()->getManager();
		// TOOD: sort alphabetical, it currently works mostly BY RANDOM CHANCE
		$all = $em->getRepository('BM2SiteBundle:'.$type)->findAll();
		$toc = $this->get('pagereader')->getPage('manual', 'toc', $this->getRequest()->getLocale());

		return array(
			"toc" => $toc,
			"list" => strtolower($type).'s',
			"all" => $all
		);
	}

}
