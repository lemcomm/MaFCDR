<?php

namespace BM2\DungeonBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;


/**
 * @Route("/game/dungeon")
 */
class AdminController extends Controller {

	/**
	  * @Route("/beastiary")
	  * @Template
	  */
	public function bestiaryAction() {
		$this->get('appstate')->getCharacter(); // not interested in return value, only access checks
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT t FROM DungeonBundle:DungeonMonsterType t ORDER BY t.name ASC');
		$types = $query->getResult();

		return array('beastiary' => $types);
	}

}