<?php

namespace Calitarus\MessagingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller {

	public function indexAction($name) {
		return $this->render('MsgBundle:Default:index.html.twig', array('name' => $name));
	}

}
