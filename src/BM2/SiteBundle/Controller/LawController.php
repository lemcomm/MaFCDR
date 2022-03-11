<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\AssociationRank;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Deity;
use BM2\SiteBundle\Entity\GameRequest;

use BM2\SiteBundle\Form\AreYouSureType;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/laws")
 */
class LawController extends Controller {

	private function gateway($test, $secondary = null) {
		$char = $this->get('dispatcher')->gateway($test, false, true, false, $secondary);
		if (!($char instanceof Character)) {
			return $this->redirectToRoute($char);
		}
		return $char;
	}

	/**
	  * @Route("/r{realm}", name="maf_realm_laws", requirements={"realm"="\d+"})
  	  * @Route("/a{assoc}", name="maf_assoc_laws", requirements={"assoc"="\d+"})
	  */
	public function lawsAction(Realm $realm=null, Association $assoc=null, Request $request) {
		if ($realm) {
			$char = $this->gateway('hierarchyRealmLawsTest', $realm);
		} else {
			$char = $this->gateway('assocLawsTest', $assoc);
		}

		return $this->render('Law/lawsList.html.twig', [
			'realm' => $realm,
			'assoc' => $assoc,
			'laws' => $realm?$realm->getLaws():$assoc->getLaws(),
		]);
	}

	/**
	  * @Route("/r{realm}/new", name="maf_assoc_laws_new", requirements={"law"="\d+", "realm"="\d+"})
  	  * @Route("/a{assoc}/new", name="maf_realm_laws_new", requirements={"law"="\d+", "assoc"="\d+"})
	  */

	public function newLawAction(Realm $realm=null, Association $assoc=null, Request $request) {
		if ($realm) {
			$char = $this->gateway('hierarchyRealmLawNewTest', $realm);
		} else {
			$char = $this->gateway('assocLawNewTest', $assoc);
		}
		if ($realm) {
			$org = $realm;
			$type = 'realm';
		} else {
			$org = $assoc;
			$type = 'assoc';
		}
		$em = $this->getDoctrine()->getManager();

		$form = $this->createForm(new LawSelectType($em->getRepository(LawType::class)->findBy(['category'=>$type])));
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			if ($realm) {
				$this->redirectToRoute('maf_realm_laws_finalize', ['realm'=>$realm->getId(), 'type'=>$data['type']->getName()]);
			} else {
				$this->redirectToRoute('maf_assoc_laws_finalize', ['assoc'=>$assoc->getId(), 'type'=>$data['type']->getName()]);
			}
		}
	}

	/**
	  * @Route("/r{realm}/{type}", name="maf_assoc_laws_finalize", requirements={"law"="\d+", "realm"="\d+"})
  	  * @Route("/a{assoc}/{type}", name="maf_realm_laws_finalize", requirements={"law"="\d+", "assoc"="\d+"})
	  */

	public function finalizeLawAction(Realm $realm=null, Association $assoc=null, Request $request) {
		if ($realm) {
			$char = $this->gateway('hierarchyRealmLawNewTest', $realm);
		} else {
			$char = $this->gateway('assocLawNewTest', $assoc);
		}
		$type = $em->getRepository(LawType::class)->findBy(['category'=>$type]);
		if (!$type) {
			$this->addFlash('error', $this->get('translator')->trans('unavailable.badlawtype'));
			if ($realm) {
				return $this->redirectToRoute('maf_realm_laws_new', ['realm'=>$realm->getId()]);
			} else {
				return $this->redirectToRoute('maf_assoc_laws_new', ['assoc'=>$assoc->getId()]);
			}
		}
		if ($realm) {
			$org = $realm;
			$type = 'realm';
		} else {
			$org = $assoc;
			$type = 'assoc';
		}
		$em = $this->getDoctrine()->getManager();

		$form = $this->createForm(new LawCreateType($type));
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			#updateLaw($org, $type, $setting, $title, $description = null, Character $character, $allowed, $mandatory, $cascades, $sol, $flush=true)
			$this->get('law_manager')->updateLaw($org, $data['type'], $data['setting'], $data['title']);

		}
	}

}
