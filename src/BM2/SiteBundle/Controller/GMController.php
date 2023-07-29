<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Journal;
use BM2\SiteBundle\Entity\UpdateNote;
use BM2\SiteBundle\Entity\User;
use BM2\SiteBundle\Form\UpdateNoteType;
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
	  * @Route("/olympus/user/{id}", name="maf_gm_user_reports")
	  */

	public function userReportsAction(User $id) {
		# Security is handled by Syfmony Firewall.

		return $this->render('GM/userReports.html.twig',  [
			'by'=>$id->getReports(),
			'against'=>$id->getReportsAgainst()
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

	/**
	  * @Route("/admin/update/{id}", name="maf_admin_update")
  	  * @Route("/admin/update")
  	  * @Route("/admin/update/")
	  */

	public function updateNoteAction(Request $request, UpdateNote $id=null) {
		# Security is handled by Syfmony Firewall.

		$em = $this->getDoctrine()->getManager();
		if ($request->query->get('last')) {
			$id = $em->createQuery('SELECT n FROM BM2SiteBundle:UpdateNote n ORDER BY n.id DESC')->setMaxResults(1)->getSingleResult();
		}

		$form = $this->createForm(new UpdateNoteType($id));
		$form->handleRequest($request);

		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			if (!$id) {
				$note = new UpdateNote();
				$now = new \DateTime('now');
				$note->setTs($now);
				$version = $data['version'];
				$note->setVersion($version);
				$em->persist($note);
			} else {
				$note = $id;
			}
			$note->setText($data['text']);
			$note->setTitle($data['title']);
			$em->flush();
			if (!$id) {
				$this->get('appstate')->setGlobal('game-version', $version);
				$this->get('appstate')->setGlobal('game-updated', $now->format('Y-m-d'));
			}
			$this->addFlash('notice', 'Update note created.');
			return $this->redirectToRoute('bm2_characters');
		}


		return $this->render('GM/update.html.twig', [
			'form'=>$form->createView()
		]);
	}
}
