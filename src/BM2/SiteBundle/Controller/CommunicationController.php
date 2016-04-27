<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\MessageGroup;
use BM2\SiteBundle\Form\MessageType;
use BM2\SiteBundle\Service\Communication;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/communication")
 */
class CommunicationController extends Controller {

   /**
     * @Route("/", name="bm2_communication")
     * @Template
     */
	public function indexAction() {
		$character = $this->get('appstate')->getCharacter(true, true, true);

		$em = $this->getDoctrine()->getManager();
		$my_realms = $character->findRealms();
		$sealed = 0;
		$readable_messages = array();
		$all_tags = array();

		$query = $em->createQuery('SELECT l, m FROM BM2SiteBundle:MessageLink l JOIN l.message m WHERE l.recipient = :me ORDER BY m.ts');
		$query->setParameter('me', $character);

		foreach ($query->getResult() as $row) {
			$msg = $row->getMessage();
			if ( $msg->getSender() == $character 
				|| ($msg->getSealedCharacter() == null && $msg->getSealedGroup() == null && $msg->getSealedRealm() == null)
				|| $msg->getSealedCharacter() == $character
				|| ($msg->getSealedGroup() && $msg->getSealedGroup()->getMembers()->contains($character))
				|| $my_realms->contains($msg->getSealedRealm())) {
				$readable_messages[] = $row;
				$all_tags = array_merge($all_tags, $msg->getTags());
			} else {
				$sealed++;
				if ($msg->getSealedCharacter() && $msg->getSealedCharacter() != $character) {
					$em->remove($row);
				}
			}
		}
		$query = $em->createQuery('UPDATE BM2SiteBundle:MessageLink l SET l.read = true WHERE l.recipient = :me');
		$query->setParameter('me', $character);
		$query->execute();
		$em->flush();

		return array('messages'=>$readable_messages, 'tags'=>array_unique($all_tags), 'sealed'=>$sealed);
	}

	/**
		* @Route("/read/{which}", name="bm2_msg_read")
		*/
	public function fullreadAction($which) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		$em = $this->getDoctrine()->getManager();

		switch ($which) {
			case 'all':
				$set_characters = $em->getRepository('BM2SiteBundle:Character')->findBy(array('active'=>true, 'user'=>$character->getUser()));
				break;
			case 'me':
			default:
				$set_characters = array($character);
		}
		$query = $em->createQuery('UPDATE BM2SiteBundle:MessageLink l SET l.read = true WHERE l.recipient IN (:me)');
		$query->setParameter('me', $set_characters);
		$query->execute();
		$em->flush();

		return new Response();
	}

	/**
	  * @Route("/write", name="bm2_msg_write")
	  * @Template
	  */
	public function writeAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();

		$em = $this->getDoctrine()->getManager();
		$my_realms = $character->findRealms();

		$towers = $this->get('communication')->reachableTowers($character);
		$settlements = array(); $realms = array();
		foreach ($towers['settlements'] as $tower) {
			if ($tower['send']) {
				$settlements[] = $tower['settlement'];
			}
		}
		foreach ($towers['realms'] as $tower) {
			if ($tower['send']) {
				$realms[] = $tower['realm'];
			}
		}

		// FIXME: maybe later this will be a relation from character
		$query = $em->createQuery('SELECT g FROM BM2SiteBundle:MessageGroup g JOIN g.members m WHERE m = :me');
		$query->setParameter('me', $character);
		$my_groups = $query->getResult();

		$form = $this->createForm(new MessageType($em, $settlements, $realms, $character->findRealms()->toArray(), $my_groups) );
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			// FIXME: should enforce that only one seal can be applied, or not?

			$msg = $this->get('communication')->NewMessage($character, $data['content'], preg_split('/[\ \n\,\;\/]+/', $data['tags']), $data['seal_character'], isset($data['seal_group'])?$data['seal_group']:null, isset($data['seal_realm'])?$data['seal_realm']:null, $data['lifetime']);
			if (isset($data['broadcast_realm']) && $data['broadcast_realm']) {
				$reached = $this->get('communication')->BroadcastMessage($msg, $data['tower'], $data['broadcast_realm']);
			} else {
				$reached = 0;
				$this->get('communication')->LocalMessage($msg, $data['tower']);
			}
			$em->flush();
			return array('sent'=>true, 'reached'=>$reached);
		}
		return array('towers'=>$towers, 'form'=>$form->createView());
	}

	/**
	  * @Route("/groups", name="bm2_msg_groups")
	  * @Template
	  */
	public function groupsAction() {
		$character = $this->get('appstate')->getCharacter();

		$em = $this->getDoctrine()->getManager();

		// FIXME: maybe later this will be relations from character
		$query = $em->createQuery('SELECT g FROM BM2SiteBundle:MessageGroup g JOIN g.members m WHERE m = :me');
		$query->setParameter('me', $character);
		$memberships = $query->getResult();

		$query = $em->createQuery('SELECT g FROM BM2SiteBundle:MessageGroup g JOIN g.owners m WHERE m = :me');
		$query->setParameter('me', $character);
		$ownerships = $query->getResult();

		return array('memberships'=>$memberships, 'ownerships'=>$ownerships);
	}

	/**
	  * @Route("/joingroup/{group}", name="bm2_msg_joingroup")
	  * @Template
	  */
	public function joingroupAction(MessageGroup $group) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationLendanTowerTest', true);

		$em = $this->getDoctrine()->getManager();

		if (!$group->getOpen()) {
			throw new \Exception("not an open group");
		}
		if (!$group->getTowers()->contains($settlement)) {
			throw new \Exception("group has no tower here");
		}
		if ($group->getMembers()->contains($character)) {
			throw new \Exception("already a member");			
		}

		$this->get('communication')->joinGroup($character, $group);
		$this->getDoctrine()->getManager()->flush();

		$this->addFlash('success', $this->get('translator')->trans('message.group.joined', array('%name%'=>$group->getName())));
		return $this->redirectToRoute('bm2_site_building_lendantower');
	}

	/**
	  * @Route("/leavegroup/{group}", name="bm2_msg_leavegroup")
	  * @Template
	  */
	public function leavegroupAction(MessageGroup $group) {
		$character = $this->get('appstate')->getCharacter();

		$em = $this->getDoctrine()->getManager();

		if (!$group->getMembers()->contains($character)) {
			throw new \Exception("not a member");			
		}

		$this->get('communication')->leaveGroup($character, $group);
		$this->getDoctrine()->getManager()->flush();

		$this->addFlash('success', $this->get('translator')->trans('message.group.left', array('%name%'=>$group->getName())));
		return $this->redirectToRoute('bm2_msg_groups');
	}


	/**
	  * @Route("/network/{settlement}", name="bm2_msg_network")
	  * @Template
	  */
	public function networkAction(Settlement $settlement) {
		$character = $this->get('appstate')->getCharacter();

		return array('settlement'=>$settlement);
	}

	/**
	  * @Route("/links", name="bm2_msg_links")
	  * @Template
	  */
	public function linksAction() {
		$character = $this->get('appstate')->getCharacter();

		$can_link = false;
		$linked_towers = new ArrayCollection($this->get('communication')->linkedTowers($character));
		if ($character->getInsideSettlement() && $linked_towers->count() < Communication::MAX_LINKS && !$linked_towers->contains($character->getInsideSettlement())) {
			$check = $this->get('dispatcher')->locationLendanTowerTest();
			if (isset($check['url'])) {
				$can_link = true;
			}
		}

		return array(
			'linked_towers'=>$linked_towers,
			'can_link'=>$can_link
		);
	}

	/**
	  * @Route("/createlink/{tower}", name="bm2_createtowerlink")
	  * @Template
	  */
	public function createlinkAction(Settlement $tower) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationLendanTowerTest', true);
		if ($tower != $settlement) {
			throw new \Exception("can only link to your current location.");
		}

		$this->get('communication')->createTowerLink($character, $tower);
		$this->getDoctrine()->getManager()->flush();

		$this->addFlash('success', 'tower linked');

		return $this->redirectToRoute('bm2_msg_links');
	}

	/**
	  * @Route("/removelink/{tower}", name="bm2_removetowerlink")
	  * @Template
	  */
	public function removelinkAction(Settlement $tower) {
		$character = $this->get('appstate')->getCharacter();

		$this->get('communication')->removeTowerLink($character, $tower);
		$this->getDoctrine()->getManager()->flush();

		$this->addFlash('success', 'link removed');


		return $this->redirectToRoute('bm2_msg_links');
	}

}
