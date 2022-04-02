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

	public function pendingAction(Journal $id) {
		# Security is handled by Syfmony Firewall.
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT r from BM2SiteBundle:UserReport r WHERE r.pending = true');
		$journals = $query->getResult();

		return $this->render('GM/pending.html.twig',  [
			'journal'=>$id,
		]);
	}

}
