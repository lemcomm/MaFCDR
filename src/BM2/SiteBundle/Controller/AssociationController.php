<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;

use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\AssocCreationType;
use BM2\SiteBundle\Form\DescriptionNewType;

use BM2\SiteBundle\Service\DescriptionManager;
use BM2\SiteBundle\Service\GameRequestManager;
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
 * @Route("/assoc")
 */
class AssociationController extends Controller {

	private function gateway($test, $secondary = null) {
		$char = $this->get('dispatcher')->gateway($test, $secondary);
		if (! $char instanceof Character) {
			return $this->redirectToRoute($char);
		}
		return $char;
	}

	/**
	  * @Route("/{id}", name="maf_assoc", requirements={"id"="\d+"})
	  */

	public function viewAction(Association $id) {
		$assoc = $id;
		$details = false;
		$owner = false;
		$public = false;
		$char = $this->get('appstate')->getCharacter(false, true, true);
		if ($char instanceof Character) {
			if ($member = $this->get('association_manager')->findMember($id, $char)) {
				$details = true;
				$public = true;
				if ($member->getRank()->getOwner()) {
					$owner = true;
				}
			}
		}
		if (!$public && $assoc->getPublic()) {
			$public = true;
		}

		return $this->render('Assoc/view.html.twig', [
			'assoc' => $assoc,
			'public' => $public,
			'details' => $details,
			'owner' => $owner
		]);
	}

	/**
	  * @Route("/create", name="maf_assoc_create")
	  */

	public function createAction(Request $request) {
		$char = $this->gateway('assocCreateTest');

		$em = $this->getDoctrine()->getManager();
		$form = $this->createForm(new AssocCreationType($em->getRepository('BM2SiteBundle:AssociationType')->findAll()));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();

			$place = $char->getInsidePlace();
			$settlement = $char->getInsideSettlement();
			$assoc = $this->get('association_manager')->create($data, $place, $char);
			# No flush needed, AssocMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.new.created', [], 'orgs'));
			return $this->redirectToRoute('maf_assoc', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/create.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("{id}/createrank", name="maf_assoc_createrank", requirements={"id"="\d+"})
	  */

	public function createRankAction(Association $id, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocManageRankTest', $assoc);
		$assocman = $this->get('association_manager');
		$member = $assocman->findMember($char);
		$ranks = $member->getRank()->findAllKnownSubordinates();

		$form = $this->createForm(new AssocCreateRankType($ranks));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();

			$assocman->createRank($assoc, $data['name'], $data['viewAll'], $data['viewUp'], $data['viewDown'], $data['superior'], $data['createSubs'], $data['manager']);
			# No flush needed, AssocMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.rank.created', array(), 'orgs'));
			return $this->redirectToRoute('maf_assoc_manage', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/createRank.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("{id}/managerank/{rank}", name="maf_assoc_managerank", requirements={"id"="\d+", "rank"="\d+"})
	  */

	public function manageRankAction(Association $id, AssociationRank $rank, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocManageRankTest', [$assoc, $rank]);

		$em = $this->getDoctrine()->getManager();
		$assocman = $this->get('association_manager');
		$member = $assocman->findMember($char);
		$ranks = $member->getRank()->findAllKnownSubordinates();

		$form = $this->createForm(new AssocManageRankType($ranks));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();

			$assocman->updateRank($rank, $data['name'], $data['viewAll'], $data['viewUp'], $data['viewDown'], $data['superior'], $data['createSubs'], $data['manager']);
			# No flush needed, AssocMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.rank.updated', array(), 'orgs'));
			return $this->redirectToRoute('maf_assoc_manage', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/manageRank.html.twig', [
			'form' => $form->createView()
		]);
	}

}
