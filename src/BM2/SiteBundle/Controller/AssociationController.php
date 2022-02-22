<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\AssociationRank;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;

use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\AssocCreationType;
use BM2\SiteBundle\Form\AssocDeityType;
use BM2\SiteBundle\Form\AssocUpdateType;
use BM2\SiteBundle\Form\AssocCreateRankType;
use BM2\SiteBundle\Form\AssocJoinType;
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
		$char = $this->get('dispatcher')->gateway($test, false, true, false, $secondary);
		if (!($char instanceof Character)) {
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
				$rank = $member->getRank();
				if ($rank && $rank->getOwner()) {
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
		$form = $this->createForm(new AssocCreationType($em->getRepository('BM2SiteBundle:AssociationType')->findAll(), $char->findSubcreateableAssociations()));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();

			$place = $char->getInsidePlace();
			$settlement = $char->getInsideSettlement();
			$assoc = $this->get('association_manager')->create($data, $place, $char);
			# No flush needed, AssocMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.new.created', [], 'orgs'));
			return $this->redirectToRoute('maf_politics_assocs');
		}
		return $this->render('Assoc/create.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{id}/update", name="maf_assoc_update")
	  */

	public function updateAction(Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocUpdateTest', $assoc);

		$em = $this->getDoctrine()->getManager();
		$form = $this->createForm(new AssocCreationType($em->getRepository('BM2SiteBundle:AssociationType')->findAll(), $char->findSubcreateableAssociations(), $assoc));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$assoc = $this->get('association_manager')->update($assoc, $form->getData(), $char);
			# No flush needed, AssocMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.updated.success', [], 'orgs'));
			return $this->redirectToRoute('maf_politics_assocs');
		}
		return $this->render('Assoc/create.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{id}/deities", name="maf_assoc_deities", requirements={"id"="\d+"})
	  */

	public function assocDeitiesAction(Association $id, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocDeitiesMineTest', $assoc);
		$assocman = $this->get('association_manager');
		$owner = false;
		if ($member = $assocman->findMember($assoc, $char)) {
			if ($rank = $member->getRank()) {
				$owner = $rank->getOwner();
			}
		}

		return $this->render('Assoc/viewDeities.html.twig', [
			'deities' => $assoc->getDeities(),
			'owner' => $owner,
			'assoc' => $assoc
		]);
	}

	/**
	  * @Route("/{id}/allDeities", name="maf_all_deities", requirements={"id"="\d+"})
	  */

	public function allDeitiesAction(Association $id) {
		$em = $this->getDoctrine()->getManager();
		$char = $this->gateway('assocDeitiesAllTest', $id);

		return $this->render('Assoc/viewAllDeities.html.twig', [
			'deities' => $em->getRepository('BM2SiteBundle:Deity')->findAll(),
			'assoc' => $id
		]);
	}

	/**
	  * @Route("/{id}/newdeity", name="maf_assoc_new_deity", requirements={"id"="\d+"})
	  */

	public function newDeityAction(Association $id, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocNewDeityTest', $assoc);

		$em = $this->getDoctrine()->getManager();
		$form = $this->createForm(new AssocDeityType($em->getRepository('BM2SiteBundle:AspectType')->findAll()));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();

			$deity = $this->get('association_manager')->newDeity($data, $assoc, $char);
			# No flush needed, AssocMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.deity.created', [], 'orgs'));
			return $this->redirectToRoute('maf_assoc_deities', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/newDeity.html.twig', [
			'form' => $form->createView(),
		]);
	}

	/**
	  * @Route("/{id}/addDeity/{deity}", name="maf_assoc_deities_add", requirements={"id"="\d+"})
	  */

	public function addDeityAction(Association $id, Deity $deity, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocAddDeityTest', [$assoc, $deity]);

		$this->get('association_manager')->addDeity($assoc, $deity, $char);

		$this->addFlash('notice', $this->get('translator')->trans('assoc.route.deity.added', ['%link-deity%'=>$deity], 'orgs'));
		return $this->redirectToRoute('maf_assoc_deities', ['id'=>$assoc->getId()]);
	}

	/**
	  * @Route("/{id}/removeDeity/{deity}", name="maf_assoc_deities_remove", requirements={"id"="\d+"})
	  */

	public function removeDeityAction(Association $id, Deity $deity, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocRemoveDeityTest', [$assoc, $deity]);

		$this->get('association_manager')->removeDeity($assoc, $deity, $char);

		$this->addFlash('notice', $this->get('translator')->trans('assoc.route.deity.removed', ['%link-deity%'=>$deity], 'orgs'));
		return $this->redirectToRoute('maf_assoc_deities', ['id'=>$assoc->getId()]);
	}

	/**
	  * @Route("/{id}/viewranks", name="maf_assoc_viewranks", requirements={"id"="\d+"})
	  */

	public function viewRanksAction(Association $id, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocViewRanksTest', $assoc);

		$em = $this->getDoctrine()->getManager();
		$assocman = $this->get('association_manager');
		$member = $assocman->findMember($assoc, $char);
		$rank = false;
		if ($member) {
			$rank = $member->getRank();
			$canManage = false;
			if ($rank) {
				$allRanks = $member->getRank()->findAllKnownRanks();
				$mngRanks = $member->getRank()->findManageableSubordinates();
				$canManage = $rank->canManage();
				$rank = true; # Flip this back to boolean so we can resuse the below bit for those that don't hold ranks as well, without doing costly object comparisons.
			} else {
				$rank = false;
			}
		}
		if (!$member || !$rank) {
			$allRanks = $assoc->findPubliclyVisibleRanks();
			$mngRanks = new ArrayCollection; # No rank, can't manage any. Return empty collection.
		}

		return $this->render('Assoc/viewRanks.html.twig', [
			'assoc' => $assoc,
			'member' => $member,
			'ranks' => $allRanks,
			'manageable' => $mngRanks,
			'canManage' => $canManage
		]);
	}

	/**
	  * @Route("/{id}/graphranks", name="maf_assoc_graphranks", requirements={"id"="\d+"})
	  */

	public function graphRanksAction(Association $id, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocGraphRanksTest', $assoc); #Same test is deliberate.

		$em = $this->getDoctrine()->getManager();
		$assocman = $this->get('association_manager');
		$member = $assocman->findMember($assoc, $char);
		$rank = false;
		$me = null;
		if ($member) {
			$rank = $member->getRank();
			$canManage = false;
			if ($rank) {
				$allRanks = $member->getRank()->findAllKnownRanks();
				$mngRanks = $member->getRank()->findManageableSubordinates();
				$canManage = $rank->canManage();
				$me = $rank;
				$rank = true; # Flip this back to boolean so we can resuse the below bit for those that don't hold ranks as well, without doing costly object comparisons.
			} else {
				$rank = false;
			}
		}
		if (!$member || !$rank) {
			$allRanks = $assoc->findPubliclyVisibleRanks();
			$mngRanks = new ArrayCollection; # No rank, can't manage any. Return empty collection.
		}

	   	$descriptorspec = array(
			   0 => array("pipe", "r"),  // stdin
			   1 => array("pipe", "w"),  // stdout
			   2 => array("pipe", "w") // stderr
			);

   		$process = proc_open('dot -Tsvg', $descriptorspec, $pipes, '/tmp', array());

	   	if (is_resource($process)) {
	   		$dot = $this->renderView('Assoc/graphRanks.dot.twig', array('hierarchy'=>$allRanks, 'me'=>$me));

	   		fwrite($pipes[0], $dot);
	   		fclose($pipes[0]);

	   		$svg = stream_get_contents($pipes[1]);
	   		fclose($pipes[1]);

	   		$return_value = proc_close($process);
	   	}

		return $this->render('Assoc/graphRanks.html.twig', [
			'svg'=>$svg
		]);
	}

	/**
	  * @Route("/{id}/createrank", name="maf_assoc_createrank", requirements={"id"="\d+"})
	  */

	public function createRankAction(Association $id, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocCreateRankTest', $assoc);
		$assocman = $this->get('association_manager');
		$member = $assocman->findMember($assoc, $char);
		$myRank = $member->getRank();
		$ranks = $myRank->findAllKnownSubordinates();

		$form = $this->createForm(new AssocCreateRankType($ranks, false));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();

			$assocman->createRank($assoc, $myRank, $data['name'], $data['viewAll'], $data['viewUp'], $data['viewDown'], $data['viewSelf'], $data['superior'], $data['createSubs'], $data['manager'], $data['createAssocs']);
			# No flush needed, AssocMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.rank.created', array(), 'orgs'));
			return $this->redirectToRoute('maf_assoc_viewranks', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/createRank.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{id}/managerank/{rank}", name="maf_assoc_managerank", requirements={"id"="\d+", "rank"="\d+"})
	  */

	public function manageRankAction(Association $id, AssociationRank $rank, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocManageRankTest', [$assoc, $rank]);

		$em = $this->getDoctrine()->getManager();
		$assocman = $this->get('association_manager');
		$member = $assocman->findMember($assoc, $char);
		$myRank = $member->getRank();
		$subordinates = $myRank->findAllKnownSubordinates();

		$form = $this->createForm(new AssocCreateRankType($subordinates, $rank));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();

			$assocman->updateRank($rank, $myRank, $data['name'], $data['viewAll'], $data['viewUp'], $data['viewDown'], $data['viewSelf'], $data['superior'], $data['createSubs'], $data['manager'], $data['createAssocs']);
			# No flush needed, AssocMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.rank.updated', array(), 'orgs'));
			return $this->redirectToRoute('maf_assoc_viewranks', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/manageRank.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/{id}/join", name="maf_assoc_join", requirements={"id"="\d+"})
	  */

	public function joinAction(Association $id, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocJoinTest', $assoc);

		$form = $this->createForm(new AssocJoinType());
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			if ($data['sure']) {
				$this->get('game_request_manager')->newRequestFromCharacterToAssociation('assoc.join', null, null, null, $data['subject'], $data['text'], $char, $assoc);
				$this->addFlash('notice', $this->get('translator')->trans('assoc.route.join.success', ['%name%'=>$assoc->getName()], 'orgs'));
				return $this->redirectToRoute('maf_assoc', array('id'=>$assoc->getId()));
			} else {
				$this->addFlash('notice', $this->get('translator')->trans('assoc.route.member.joinfail', array(), 'orgs'));
				return $this->redirectToRoute('maf_assoc_join', ['id'=>$assoc->getId()]);
			}
		}
		return $this->render('Assoc/join.html.twig', [
			'form' => $form->createView()
		]);
	}

}
