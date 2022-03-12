<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Law;
use BM2\SiteBundle\Entity\Realm;

use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\LawTypeSelectType;
use BM2\SiteBundle\Form\LawCreateType;

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
	  * @Route("/r{realm}/", requirements={"realm"="\d+"})
	  * @Route("/a{assoc}", name="maf_assoc_laws", requirements={"assoc"="\d+"})
	  * @Route("/a{assoc}/", requirements={"assoc"="\d+"})
	  */
	public function lawsAction(Realm $realm=null, Association $assoc=null) {
		if ($realm) {
			$char = $this->gateway('hierarchyRealmLawsTest', $realm);
		} else {
			$char = $this->gateway('assocLawsTest', $assoc);
		}
		if ($realm) {
			$org = $realm;
			$update = 'maf_realm_laws_update';
			$type = 'realm';
		} else {
			$org = $assoc;
			$update = 'maf_assoc_laws_update';
			$type = 'assoc';
		}
		$active = new ArrayCollection;
		$inactive = new ArrayCollection;
		foreach ($org->getLaws() as $law) {
			if (!$law->getInvalidatedOn()) {
				$active->add($law);
			} else {
				$inactive->add($law);
			}
		}

		return $this->render('Law/lawsList.html.twig', [
			'org' => $org,
			'active' => $active,
			'inactive' => $inactive,
			'update' => $update,
			'orgType' => $type
		]);
	}

	#TODO: HERE DOWN!

	/**
	  * @Route("/r{realm}/new", name="maf_assoc_laws_new", requirements={"realm"="\d+"})
	  * @Route("/a{assoc}/new", name="maf_realm_laws_new", requirements={"assoc"="\d+"})
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

		return $this->render('Law/new.html.twig', [
			'org' => $org,
			'form' => $form->createView(),
		]);
	}

	/**
	  * @Route("/a{assoc}/{type}", name="maf_assoc_laws_finalize", requirements={"type"="\d+", "assoc"="\d+"})
	  * @Route("/a{assoc}/{type}/", requirements={"type"="\d+", "realm"="\d+"})
	  * @Route("/a{assoc}/{type}/{law}", name="maf_assoc_laws_update", requirements={"type"="\d+", "assoc"="\d+", "law"="\d+"})
	  * @Route("/r{realm}/{type}", name="maf_realm_laws_finalize", requirements={"type"="\d+", "realm"="\d+"})
	  * @Route("/r{realm}/{type}/", requirements={"type"="\d+", "assoc"="\d+"})
	  * @Route("/r{realm}/{type}/{law}", name="maf_realm_laws_update", requirements={"type"="\d+", "realm"="\d+", "law"="\d+"})
	  */
	public function finalizeLawAction(Realm $realm=null, Association $assoc=null, Law $law=null, LawType $type, Request $request) {
		if ($realm) {
			$char = $this->gateway('hierarchyRealmLawNewTest', $realm);
		} else {
			$char = $this->gateway('assocLawNewTest', $assoc);
		}
		if ($law && $type !== $law->getType()) {
			$this->addFlash('error', $this->get('translator')->trans('unavailable.badlawtype'));
			if ($realm) {
				return $this->redirectToRoute('maf_realm_laws', ['realm'=>$realm->getId()]);
			} else {
				return $this->redirectToRoute('maf_assoc_laws', ['assoc'=>$assoc->getId()]);
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

		$form = $this->createForm(new LawCreateType($type, $law));
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			#updateLaw($org, $type, $setting, $title, $description = null, Character $character, $allowed, $mandatory, $cascades, $sol, $flush=true)
			$this->get('law_manager')->updateLaw($org, $data['type'], $data['setting'], $data['title']);

		}

		return $this->render('Law/new.html.twig', [
			'org' => $org,
			'form' => $form->createView(),
		]);
	}

	/**
	  * @Route("/repeal/{law}", name="maf_law_repeal", requirements={"law"="\d+"})
	  */
	public function repealAction(Law $law, Request $request) {
		$char = $this->gateway('lawRepealTest', $law);

		$form = $this->createForm(new AreYouSureType());
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			if ($data['sure']) {
				$this->get('law_manager')->repealLaw($law, $char);
				$this->addFlash('notice', $this->get('translator')->trans('law.route.repeal.success', [], 'orgs'));
				if ($law->getRealm()) {
					return $this->redirectToRoute('maf_realm_laws', ['realm'=>$law->getRealm()->getId()]);
				}
				return $this->redirectToRoute('maf_assoc_laws', ['assoc'=>$law->getAssociation()->getId()]);
			}
		}

		return $this->render('Law/lawsList.html.twig', [
			'law' => $law,
			'form' => $form->createView()
		]);
	}

}
