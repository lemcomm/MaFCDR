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

	private function dispatcher($test, $assoc = null) {
		$char = $this->get('dispatcher')->gateway($test, $assoc);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		return $char;
	}

	/**
	  * @Route("/{id}", name="maf_assoc", requirements={"id"="\d+"})
	  */

	public function viewAction(Association $id) {
		$this->addFlash('notice', "This isn't ready yet, come back later you silly person!");
		return $this->redirectToRoute('bm2_homepage');
		$assoc = $id;
		$details = false;
		$head = false;
		$public = false;
		$char = $this->get('appstate')->getCharacter(false, true, true);
		if ($char instanceof Character) {
			foreach ($char->getAssociationMembership() as $member) {
				if ($member->getAssocation() === $assoc) {
					$details = true;
					$public = true;
					if ($member->getRank()->isHead()) {
						$head = true;
					}
					break;
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
			'head' => $head
		]);
	}

	/**
	  * @Route("/create", name="maf_assoc_create")
	  */

	public function createAction(Request $request) {
		$this->addFlash('notice', "This isn't ready yet, come back later you silly person!");
		return $this->redirectToRoute('bm2_homepage');

		$char = $this->gateway('assocCreateTest');

		$em = $this->getDoctrine()->getManager();
		$form = $this->createForm(new AssocCreationType());
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			$place = $character->getInsidePlace();
			$settlement = $character->getInsideSettlement();
			$assoc = $this->get('association_manager')->create($data, null, $place, $settlement, $character);
			# No flush needed, AssocMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.new.created', array(), 'orgs'));
			return $this->redirectToRoute('maf_house', array('id'=>$house->getId()));
		}
		return $this->render('Assoc/create.html.twig', [
			'form' => $form->createView()
		]);
	}

}
