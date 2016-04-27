<?php

namespace BM2\DungeonBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


/**
 * @Route("/dungeonparty")
 */
class PartyController extends Controller {

	/**
	  * @Route("/", name="dungeons_party")
	  * @Template
	  */
	public function indexAction() {
		$character = $this->get('appstate')->getCharacter();
		$dungeoneer = $this->get('dungeon_master')->getcreateDungeoneer($character);

		return array('dungeoneer'=>$dungeoneer, 'party' => $dungeoneer->getParty());
	}

	/**
	  * @Route("/leave", name="dungeons_leaveparty")
	  * @Template
	  */
	public function leaveAction() {
		$character = $this->get('appstate')->getCharacter();
		$dungeoneer = $this->get('dungeon_master')->getcreateDungeoneer($character);
		if (!$dungeoneer->getParty()) {
			throw new AccessDeniedHttpException("dungeons::error.noparty");
		}
		if ($dungeoneer->isInDungeon()) {
			throw new AccessDeniedHttpException("dungeons::error.inside");
		}

		$this->get('dungeon_master')->leaveParty($dungeoneer);
		$this->getDoctrine()->getManager()->flush();

		return array('dungeoneer'=>$dungeoneer);
	}
}