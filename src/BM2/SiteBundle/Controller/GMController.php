<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Journal;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GMController extends Controller {

	/**
	  * @Route("/olympus/", name="maf_gm_pending")
	  */

	public function pendingAction() {
		# Security is handled by Syfmony Firewall.
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT r from BM2SiteBundle:UserReport r WHERE r.actioned = false');
		$reports = $query->getResult();

		return $this->render('GM/pending.html.twig',  [
			'reports'=>$reports,
		]);
	}

	/**
	  * @Route("/olympus/archive", name="maf_gm_pending")
	  */

	public function actionedAction() {
		# Security is handled by Syfmony Firewall.
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT r from BM2SiteBundle:UserReport r WHERE r.actioned = true');
		$reports = $query->getResult();

		return $this->render('GM/pending.html.twig',  [
			'reports'=>$reports,
		]);
	}
}
