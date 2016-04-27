<?php

namespace BM2\DungeonBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;


use BM2\DungeonBundle\Entity\DungeonMonsterType;

/**
 * @Route("/monsters")
 */
class MonstersController extends Controller {

	/**
	  * @Route("/{type}", requirements={"id"="\d+"})
	  * @Template
	  */
	public function indexAction(DungeonMonsterType $type) {

		return array(
			'type' => $type
		);
	}

}