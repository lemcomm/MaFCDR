<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Law;
use BM2\SiteBundle\Entity\LawType;
use BM2\SiteBundle\Entity\Realm;

use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\LawTypeSelectType;
use BM2\SiteBundle\Form\LawEditType;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/laws")
 */
class LawController extends Controller {

	private function gateway($test, $secondary = null) {
		return $this->get('dispatcher')->gateway($test, false, true, false, $secondary);
	}

	/**
	  * @Route("/r{realm}", name="maf_realm_laws", requirements={"realm"="\d+"})
	  * @Route("/r{realm}/", requirements={"realm"="\d+"})
	  * @Route("/a{assoc}", name="maf_assoc_laws", requirements={"assoc"="\d+"})
	  * @Route("/a{assoc}/", requirements={"assoc"="\d+"})
	  */
	public function lawsAction(Realm $realm=null, Association $assoc=null) {
		if (!$realm && !$assoc) {
			$this->addFlash('error', $this->get('translator')->trans('law.route.lawsList.noorg', [], 'orgs'));
			return $this->redirectToRoute('bm2_actions');
		}
		if ($realm) {
			$char = $this->gateway('hierarchyRealmLawsTest', $realm);
		} else {
			$char = $this->gateway('assocLawsTest', $assoc);
		}
		if (!($char instanceof Character)) {
			return $this->redirectToRoute($char);
		}
		$change = false;
		if ($realm) {
			$org = $realm;
			$update = 'maf_realm_laws_update';
			$new = 'maf_realm_laws_new';
			$type = 'realm';
			foreach ($realm->getPositions() as $pos) {
				if ($pos->getRuler()) {
					$change = true;
					break;
				} elseif ($pos->getLegislative()) {
					$change = true;
					break;
				}
			}
		} else {
			$org = $assoc;
			$mbr = $assoc->findMember($char);
			if ($rank = $mbr->getRank()) {
				if ($rank->isOwner()) {
					$change = true;
				}
			}
			$update = 'maf_assoc_laws_update';
			$new = 'maf_assoc_laws_new';
			$type = 'assoc';
		}

		#TODO: Add inactive laws display.
		return $this->render('Law/lawsList.html.twig', [
			'org' => $org,
			'active' => $org->findActiveLaws(),
			'update' => $update,
			'orgType' => $type,
			'change' => $change,
			'new' => $new
		]);
	}

	/**
	  * @Route("/r{realm}/new", name="maf_realm_laws_new", requirements={"realm"="\d+"})
	  * @Route("/a{assoc}/new", name="maf_assoc_laws_new", requirements={"assoc"="\d+"})
	  */
	public function newLawAction(Realm $realm=null, Association $assoc=null, Request $request) {
		if ($realm) {
			$char = $this->gateway('hierarchyRealmLawNewTest', $realm);
		} else {
			$char = $this->gateway('assocLawNewTest', $assoc);
		}
		if (!($char instanceof Character)) {
			return $this->redirectToRoute($char);
		}
		if ($realm) {
			$org = $realm;
			$type = 'realm';
		} else {
			$org = $assoc;
			$type = 'assoc';
		}
		$em = $this->getDoctrine()->getManager();

		$form = $this->createForm(new LawTypeSelectType($em->getRepository(LawType::class)->findBy(['category'=>$type])));
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			if ($realm) {
				return $this->redirectToRoute('maf_realm_laws_finalize', ['realm'=>$realm->getId(), 'type'=>$data['target']->getId()]);
			} else {
				return $this->redirectToRoute('maf_assoc_laws_finalize', ['assoc'=>$assoc->getId(), 'type'=>$data['target']->getId()]);
			}
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
		if (!($char instanceof Character)) {
			return $this->redirectToRoute($char);
		}

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

		return $this->render('Law/repeal.html.twig', [
			'law' => $law,
			'form' => $form->createView(),
			'org' => $law->getOrg()
		]);
	}

	/**
	  * @Route("/a{assoc}/{type}", name="maf_assoc_laws_finalize", requirements={"type"="\d+", "assoc"="\d+"})
	  * @Route("/a{assoc}/{type}/", requirements={"type"="\d+", "assoc"="\d+"})
	  * @Route("/a{assoc}/{type}/{law}", name="maf_assoc_laws_update", requirements={"assoc"="\d+", "type"="\d+", "law"="\d+"})
	  * @Route("/r{realm}/{type}", name="maf_realm_laws_finalize", requirements={"type"="\d+", "realm"="\d+"})
	  * @Route("/r{realm}/{type}/", requirements={"type"="\d+", "realm"="\d+"})
	  * @Route("/r{realm}/{type}/{law}", name="maf_realm_laws_update", requirements={"realm"="\d+", "type"="\d+", "law"="\d+"})
	  */
	public function finalizeLawAction(Realm $realm=null, Association $assoc=null, Law $law=null, LawType $type, Request $request) {
		if (in_array($request->get('_route'), ['maf_realm_laws_finalize', 'maf_realm_laws_update'])) {
			$char = $this->gateway('hierarchyRealmLawNewTest', $realm);
			$rCheck = true;
			$aCheck = false;
		} else {
			$char = $this->gateway('assocLawNewTest', $assoc);
			$rCheck = false;
			$aCheck = true;
		}
		if (!($char instanceof Character)) {
			return $this->redirectToRoute($char);
		}

		if ($law && $type !== $law->getType()) {
			$this->addFlash('error', $this->get('translator')->trans('unavailable.badlawtype'));
			if ($rCheck) {
				return $this->redirectToRoute('maf_realm_laws', ['realm'=>$realm->getId()]);
			} else {
				return $this->redirectToRoute('maf_assoc_laws', ['assoc'=>$assoc->getId()]);
			}
		}
		if ($rCheck) {
			$org = $realm;
			$settlements = $realm->findTerritory();
		} else {
			$org = $assoc;
			$settlements = false;
		}
		$lawMan = $this->get('law_manager');

		$form = $this->createForm(new LawEditType($type, $law, $lawMan->choices, $settlements));
		$form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			#updateLaw($org, $type, $setting, $title, $description = null, Character $character, $allowed, $mandatory, $cascades, $sol, $flush=true)
			$result = $this->get('law_manager')->updateLaw($org, $type, $data['value'], $data['title'], $data['description'], $char, $data['mandatory'], $data['cascades'], $data['sol'], $data['settlement'], $law, true);
			if ($result instanceof Law) {
				$this->addFlash('error', $this->get('translator')->trans('law.form.edit.success', [], 'orgs'));
				# These return a different redirect due to how the route is built. if you use the other ones ($this->redirectToRoute) Symfony complains that the controller isn't returning a response.
				if ($rCheck) {
					return new RedirectResponse($this->generateUrl('maf_realm_laws', ['realm'=>$realm->getId()]).'#'.$result->getId());
				} else {
					return new RedirectResponse($this->generateUrl('maf_assoc_laws', ['assoc'=>$assoc->getId()]).'#'.$result->getId());
				}
			} else {
				$this->addFlash('error', $this->get('translator')->trans('law.form.edit.fail'.$result['error'], [], 'orgs'));
			}
		}

		return $this->render('Law/edit.html.twig', [
			'org' => $org,
			'type' => $type,
			'form' => $form->createView(),
			'law' => $law,
		]);
	}

}
