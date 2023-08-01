<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\AssociationDeity;
use BM2\SiteBundle\Entity\AssociationMember;
use BM2\SiteBundle\Entity\AssociationRank;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Deity;
use BM2\SiteBundle\Entity\GameRequest;

use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\AssocCreationType;
use BM2\SiteBundle\Form\AssocDeityType;
use BM2\SiteBundle\Form\AssocDeityUpdateType;
use BM2\SiteBundle\Form\AssocDeityWordsType;
use BM2\SiteBundle\Form\AssocUpdateType;
use BM2\SiteBundle\Form\AssocManageMemberType;
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
		if (!$public && $assoc->isPublic()) {
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

	public function updateAction(Association $id, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocUpdateTest', $assoc);

		$em = $this->getDoctrine()->getManager();
		$form = $this->createForm(new AssocUpdateType($em->getRepository('BM2SiteBundle:AssociationType')->findAll(), $char->findSubcreateableAssociations(), $assoc));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$assoc = $this->get('association_manager')->update($assoc, $form->getData(), $char);
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.updated.success', [], 'orgs'));
			return $this->redirectToRoute('maf_politics_assocs');
		}
		return $this->render('Assoc/update.html.twig', [
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
	  * @Route("/deity/{id}", name="maf_deity", requirements={"id"="\d+"})
	  */

	public function deityAction(Deity $id) {
		return $this->render('Assoc/deity.html.twig', [
			'deity' => $id
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

			$deity = $this->get('association_manager')->newDeity($assoc, $char, $data);
			# No flush needed, AssocMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.deity.created', [], 'orgs'));
			return $this->redirectToRoute('maf_assoc_deities', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/newDeity.html.twig', [
			'form' => $form->createView(),
		]);
	}

	/**
	  * @Route("/{id}/updatedeity/{deity}", name="maf_assoc_update_deity", requirements={"id"="\d+", "deity"="\d+"})
	  */

	public function updateDeityAction(Association $id, Deity $deity, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocUpdateDeityTest', [$assoc, $deity]);

		$em = $this->getDoctrine()->getManager();
		$form = $this->createForm(new AssocDeityUpdateType($deity, $em->getRepository('BM2SiteBundle:AspectType')->findAll()));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();

			$deity = $this->get('association_manager')->updateDeity($deity, $char, $data);
			foreach ($deity->getAssociations() as $bassoc) {
				if ($bassoc !== $assoc) {
					$this->get('history')->logEvent(
						$bassoc,
						'event.assoc.deity.changeother',
						array('%link-deity%'=>$deity->getId(), '%link-assoc%'=>$assoc->getId())
					);
				} else {
					$this->get('history')->logEvent(
						$bassoc,
						'event.assoc.deity.changeself',
						array('%link-deity%'=>$deity->getId())
					);
				}
			}
			# No flush needed, AssocMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.deity.updated', [], 'orgs'));
			return $this->redirectToRoute('maf_assoc_deities', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/updateDeity.html.twig', [
			'form' => $form->createView(),
		]);
	}

	/**
	  * @Route("/{id}/wordsdeity/{deity}", name="maf_assoc_words_deity", requirements={"id"="\d+", "deity"="\d+"})
	  */

	public function wordsDeityAction(Association $id, AssociationDeity $deity, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocWordsDeityTest', [$assoc, $deity]);

		$em = $this->getDoctrine()->getManager();
		$form = $this->createForm(new AssocDeityWordsType($deity));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			if ($deity->getWords() !== $data['words']) {
				$deity->setWords($data['words']);
			}
			$deity->setWordsTimestamp(new \DateTime("now"));
			$deity->setWordsFrom($char);
			$this->get('history')->logEvent(
				$assoc,
				'event.assoc.deity.newwords',
				array('%link-deity%'=>$deity->getDeity()->getId()),
				History::LOW
			);
			$this->getDoctrine()->getManager()->flush();

			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.deity.updated', [], 'orgs'));
			return $this->redirectToRoute('maf_assoc_deities', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/wordsDeity.html.twig', [
			'form' => $form->createView(),
		]);
	}

	/**
	  * @Route("/{id}/addDeity/{deity}", name="maf_assoc_deities_add", requirements={"id"="\d+", "deity"="\d+"})
	  */

	public function addDeityAction(Association $id, Deity $deity, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocAddDeityTest', [$assoc, $deity]);

		$this->get('association_manager')->addDeity($assoc, $deity, $char);

		$this->addFlash('notice', $this->get('translator')->trans('assoc.route.deity.added', ['%link-deity%'=>$deity->getId()], 'orgs'));
		return $this->redirectToRoute('maf_assoc_deities', ['id'=>$assoc->getId()]);
	}

	/**
	  * @Route("/{id}/removeDeity/{deity}", name="maf_assoc_deities_remove", requirements={"id"="\d+", "deity"="\d+"})
	  */

	public function removeDeityAction(Association $id, Deity $deity, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocRemoveDeityTest', [$assoc, $deity]);

		$this->get('association_manager')->removeDeity($assoc, $deity, $char);

		$this->addFlash('notice', $this->get('translator')->trans('assoc.route.deity.removed', ['%link-deity%'=>$deity->getId()], 'orgs'));
		return $this->redirectToRoute('maf_assoc_deities', ['id'=>$assoc->getId()]);
	}

	/**
	  * @Route("/{id}/adoptDeity/{deity}", name="maf_assoc_deities_adopt", requirements={"id"="\d+", "deity"="\d+"})
	  */

	public function adoptDeityAction(Association $id, Deity $deity, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocAdoptDeityTest', [$assoc, $deity]);

		$this->get('association_manager')->adoptDeity($assoc, $deity, $char);

		$this->addFlash('notice', $this->get('translator')->trans('assoc.route.deity.adopted', ['%link-deity%'=>$deity->getId()], 'orgs'));
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
	  * @Route("/{id}/viewMembers", name="maf_assoc_viewmembers", requirements={"id"="\d+"})
	  */

	public function viewMembersAction(Association $id, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocViewMembersTest', $assoc);

		$em = $this->getDoctrine()->getManager();
		$assocman = $this->get('association_manager');
		$member = $assocman->findMember($assoc, $char);
		$rank = false;
		$canManage = false;
		if ($member) {
			$rank = $member->getRank();
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

		return $this->render('Assoc/viewMembers.html.twig', [
			'assoc' => $assoc,
			'myMbr' => $member,
			'allMbrs' => $assoc->getMembers(),
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
		if ($myRank->isOwner()) {
			$ranks = $assoc->getRanks();
		} else {
			$ranks = $myRank->findAllKnownSubordinates();
			$ranks->add($myRank);
		}

		$form = $this->createForm(new AssocCreateRankType($ranks, false));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();

			$rank = $assocman->newRank($assoc, $myRank, $data['name'], $data['viewAll'], $data['viewUp'], $data['viewDown'], $data['viewSelf'], $data['superior'], $data['build'], $data['createSubs'], $data['manager'], $data['createAssocs']);
			if (!$rank->getDescription() || $rank->getDescription()->getText() !== $data['description']) {
				$this->get('description_manager')->newDescription($rank, $data['description'], $char);
			}
			# No flush needed, AssocMan and DescMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.rank.created', array(), 'orgs'));
			return $this->redirectToRoute('maf_assoc_viewranks', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/createRank.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/managerank/{rank}", name="maf_assoc_managerank", requirements={"rank"="\d+"})
	  */

	public function manageRankAction(AssociationRank $rank, Request $request) {
		$char = $this->gateway('assocManageRankTest', $rank);

		$em = $this->getDoctrine()->getManager();
		$assocman = $this->get('association_manager');
		$assoc = $rank->getAssociation();
		$member = $assocman->findMember($assoc, $char);
		$myRank = $member->getRank();
		if ($myRank->isOwner()) {
			$ranks = $assoc->getRanks();
		} else {
			$ranks = $myRank->findAllKnownSubordinates();
			$ranks->add($myRank);
		}

		$form = $this->createForm(new AssocCreateRankType($ranks, $rank));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			if ($rank === $myRank && $myRank->getOwner()) {
				$owner = true;
			} else {
				$owner = false;
			}

			$assocman->updateRank($myRank, $rank, $data['name'], $data['viewAll'], $data['viewUp'], $data['viewDown'], $data['viewSelf'], $data['superior'], $data['build'], $data['createSubs'], $data['manager'], $data['createAssocs'], $owner);
			if (!$rank->getDescription() || $rank->getDescription()->getText() !== $data['description']) {
				$this->get('description_manager')->newDescription($rank, $data['description'], $char);
			}
			# No flush needed, AssocMan and DescMan flushes.
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.rank.updated', array(), 'orgs'));
			return $this->redirectToRoute('maf_assoc_viewranks', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/manageRank.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/managemember/{mbr}", name="maf_assoc_managemember", requirements={"mbr"="\d+"})
	  */

	public function manageMemberAction(AssociationMember $mbr, Request $request) {
		$char = $this->gateway('assocManageMemberTest', $mbr);

		$em = $this->getDoctrine()->getManager();
		$assocman = $this->get('association_manager');
		$assoc = $mbr->getAssociation();
		$member = $assocman->findMember($assoc, $char);
		$myRank = $member->getRank();
		$subordinates = $myRank->findManageableSubordinates();

		$form = $this->createForm(new AssocManageMemberType($subordinates, $mbr));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			$newRank = $data['rank'];
			if ($newRank !== $mbr->getRank() && $subordinates->contains($newRank)) {
				$assocman->updateMember($assoc, $newRank, $mbr->getCharacter());
			}
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.manageMember.updated', array(), 'orgs'));
			return $this->redirectToRoute('maf_assoc_viewmembers', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/manageMember.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/evictmember/{mbr}", name="maf_assoc_evictmember", requirements={"mbr"="\d+"})
	  */

	public function evictMemberAction(AssociationMember $mbr, Request $request) {
		$char = $this->gateway('assocEvictMemberTest', $mbr);

		$em = $this->getDoctrine()->getManager();
		$assocman = $this->get('association_manager');
		$assoc = $mbr->getAssociation();
		$member = $assocman->findMember($assoc, $char);
		$myRank = $member->getRank();
		$subordinates = $myRank->findManageableSubordinates();

		$form = $this->createForm(new AreYouSureType());
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			if ($data['sure']) {
				$assocman->removeMember($assoc, $mbr->getCharacter());
			}
			$this->addFlash('notice', $this->get('translator')->trans('assoc.route.evictMember.success', array(), 'orgs'));
			return $this->redirectToRoute('maf_assoc_viewmembers', array('id'=>$assoc->getId()));
		}
		return $this->render('Assoc/evictMember.html.twig', [
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

	/**
	  * @Route("/{id}/leave", name="maf_assoc_leave", requirements={"id"="\d+"})
	  */

	public function leaveAction(Association $id, Request $request) {
		$assoc = $id;
		$char = $this->gateway('assocLeaveTest', $assoc);

		$form = $this->createForm(new AreYouSureType());
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			if ($data['sure']) {
				$this->get('association_manager')->removeMember($assoc, $char);
				$this->addFlash('notice', $this->get('translator')->trans('assoc.route.leave.success', ['%name%'=>$assoc->getName()], 'orgs'));
				return $this->redirectToRoute('maf_place_actionable');
			}
		}
		return $this->render('Assoc/leave.html.twig', [
			'form' => $form->createView(),
			'assoc' => $assoc,
		]);
	}

}
