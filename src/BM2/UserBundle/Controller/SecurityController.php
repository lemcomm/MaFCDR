<?php

namespace BM2\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use FOS\UserBundle\Controller\SecurityController as BaseController;

class SecurityController extends BaseController {

	/**
	* @param Request $request
	*
	* @return Response
	*/
	public function loginAction(Request $request) {
		/** @var $session Session */
		$session = $request->getSession();

		$authErrorKey = Security::AUTHENTICATION_ERROR;
		$lastUsernameKey = Security::LAST_USERNAME;

		// get the error if any (works with forward and redirect -- see below)
		if ($request->attributes->has($authErrorKey)) {
		   $error = $request->attributes->get($authErrorKey);
		} elseif (null !== $session && $session->has($authErrorKey)) {
		    $error = $session->get($authErrorKey);
		    $session->remove($authErrorKey);
		} else {
		    $error = null;
		}

		if (!$error instanceof AuthenticationException) {
		    $error = null; // The value does not come from the security component.
		}

		// last username entered by the user
		$lastUsername = (null === $session) ? '' : $session->get($lastUsernameKey);
		if ($error && $error->getMessageKey() === 'Account is disabled.') {
			$em = $this->getDoctrine()->getManager();
			$query = $em->createQuery('SELECT u from BM2SiteBundle:User u where LOWER(u.username) like :name and u.watched = true and u.enabled = false');
			$query->setParameters(['name'=>$lastUsername]);
			$query->setMaxResults(1);
			$check = $query->getSingleResult();
			if ($check) {
				$this->addFlash('notice', 'This account was disabled for security reasons. To re-enable it, please reset your password using the link below.');
			}
		}

		$csrfToken = $this->has('security.csrf.token_manager')
		    ? $this->get('security.csrf.token_manager')->getToken('authenticate')->getValue()
		    : null;

		return $this->renderLogin(array(
		    'last_username' => $lastUsername,
		    'error' => $error,
		    'csrf_token' => $csrfToken,
		));
	}

	/**
	* @param Request $request
	*
	* @return Response
	*/
	public function detectAction(Request $request) {
		/* First we figure out if we have a token. If we don't, send them to login page.
		Second, check if we have a User. If not, again, login page.
		After that, do we have a Character? If we don't, character list.
		And if we do, we send them back to whence they came.
		*/
		$token = $this->get('security.token_storage')->getToken();
		if (!$token) {
			$this->addFlash('error', 'error.missing.token');
			return $this->redirectToRoute('fos_user_security_login', array());
		}
		$user = $token->getUser();
		if (!$user || ! $user instanceof UserInterface) {
			$this->addFlash('error', 'error.missing.user');
			return $this->redirectToRoute('fos_user_security_login', array());
		}
		$character = $user->getCurrentCharacter();
		if (!$character) {
			$this->addFlash('error', 'error.missing.character');
			return $this->redirectToRoute('bm2_characters', array());
		} else {
			$this->addFlash('error', 'error.missing.rights');
			return $this->redirectToRoute('bm2_recent', array());
		}
	}
}
