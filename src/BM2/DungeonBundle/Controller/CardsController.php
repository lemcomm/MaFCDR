<?php

namespace BM2\DungeonBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;


/**
 * @Route("/dungeoncards")
 */
class CardsController extends Controller {

	/**
	  * @Route("/", name="dungeons_cards")
	  * @Template
	  */
	public function indexAction() {
		$character = $this->get('appstate')->getCharacter();
		$dungeoneer = $this->get('dungeon_master')->getcreateDungeoneer($character);

		return array('cards' => $dungeoneer->getCards());
	}

}