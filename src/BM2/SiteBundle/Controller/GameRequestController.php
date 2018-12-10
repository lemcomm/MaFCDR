<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;
use BM2\SiteBundle\Entity\House;

use BM2\SiteBundle\Service\Appstate;
use BM2\SiteBundle\Service\History;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


/**
 * @Route("/gamerequest")
 */
class GameRequestController extends Controller {

	private $house;

	private function security(Character $char, GameRequest $id) {
		/* Most other places in the game have a single dispatcher call to do security. Unfortunately, for GameRequests, it's not that easy, as this file handles *ALL* processing of the request itself.
		That means, we need a way to check whether or not a given user has rights to do things, when the things in questions could vary every time this controller is called. */
		$result;
		switch ($id->getType()) {
			case 'house.join':
				if ($char->getHeadOfHouse() != $id->getToHouse()) {
					$result = false;
				} else {
					$result = true;
				}
				break;
		}
		return $result;
	}

	/**
	  * @Route("/{id}/approve", name="bm2_gamerequest_approve", requirements={"id"="\d+"})
	  */
	
	public function approveAction(GameRequest $id) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		# Are we allowed to act on this GR? True = yes. False = no.
		$allowed = $this->security($character, $id);
		# Do try to keep this switch and the denyAction switch in the order of most expected request. It'll save processing time.
		switch($id->getType()) {
			case 'house.join':
				if ($allowed) {
					$house = $id->getToHouse();
					$character = $id->getFromCharacter();
					$character->setHouse($house);
					$character->setHouseJoinDate(new \DateTime("now"));
					$this->get('history')->openLog($house, $character);
					$this->get('history')->logEvent(
						$house,
						'event.house.newmember',
						array('%link-character%'=>$id->getFromCharacter()->getId()),
						History::MEDIUM, true
					);
					$this->get('history')->logEvent(
						$id->getFromCharacter(),
						'event.character.joinhouse.approved',
						array('%link-house%'=>$house->getId()),
						History::ULTRA, true
					);
					$em->remove($id);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('house.manage.applicant.approved', array('%character%'=>$id->getFromCharacter()->getName()), 'politics'));
					return $this->redirectToRoute('bm2_house_applicants', array('id'=>$house->getId()));
				} else {
					throw new AccessDeniedHttpException('unavailable.nothead');
				}
				break;
		}
		
		return new Response();
	}

	/**
	  * @Route("/{id}/deny", name="bm2_gamerequest_deny", requirements={"id"="\d+"})
	  */
	
	public function denyAction(GameRequest $id) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		# Are we allowed to act on this GR? True = yes. False = no.
		$allowed = $this->security($character, $id);
		switch($id->getType()) {
			case 'house.join':
				if ($allowed) {
					$house = $id->getToHouse();
					$this->get('history')->logEvent(
						$id->getFromCharacter(),
						'event.character.joinhouse.denied',
						array('%link-house%'=>$house->getId()),
						History::HIGH, true
					);
					$em->remove($id);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('house.manage.applicant.denied', array('%link-character%'=>$id->getFromCharacter()->getId()), 'politics'));
					return $this->redirectToRoute('bm2_house_applicants', array('house'=>$house->getId()));
				} else {
					throw new AccessDeniedHttpException('unavailable.nothead');
				}
				break;
		}
		
		return new Response();
	}
}
