<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\SecurityContext;

/**
 * @Route("/")
 */
class SecurityController extends Controller {

	protected function buildUserForm(User $user) {
		return $this->createFormBuilder($user)
			->add('username', 'text', array('data'=>$user->getUsername()))
			->add('email', 'email', array('data'=>$user->getEmail()))
			->getForm()
		;
	}

	protected function getUserManager() {
		return $this->get('bm2.user_manager');
	}


	/**
	  * @Route("/challenge", name="bm2_challenge", defaults={"_format"="json"})
	  */
	public function challengeAction() {
		$challenge = rand(10000, 99999);
		$this->get('session')->set('challenge', $challenge);

		return new Response(json_encode($challenge));
	}

	/**
	  * @Route("/autologin", name="bm2_autologin", defaults={"_format"="json"})
	  */
	public function autologinAction(Request $request) {
		$id = $request->request->get('id');
		$response = $request->request->get('response');
		$challenge = $this->get('session')->get('challenge');
		$this->get('session')->remove('challenge');

		$result = "error";
		if ($id && $response && $challenge) {
			$user = $this->getDoctrine()->getManager()->getRepository('BM2SiteBundle:User')->find($id);
			if ($user) {
				$hash = sha1($challenge . $user->getAppKey() . $challenge);
				if ($response == $hash) {
					$result = "success";
					// TODO: how to actually log him in programmatically ?
				}
			}
		}

		return new Response(json_encode($result));
	}



}

