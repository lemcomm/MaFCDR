<?php

namespace BM2\SiteBundle\EventListener;

use Symfony\Component\Security\Core\Event\AuthenticationEvent;
use Doctrine\Common\Persistence\ObjectManager;

use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Security\LoginManager;


class AuthenticationEventListener {
	protected $objectManager;
	protected $loginManager;
	protected $fwname;
	
	public function __construct(ObjectManager $objectManager, LoginManager $loginManager, $fwname) {
		$this->objectManager = $objectManager;
		$this->loginManager = $loginManager;
		$this->fwname = $fwname;
	}
	
	public function onAuthenticationSuccess(AuthenticationEvent $event) {
		$response = null;
		$user = $event->getAuthenticationToken()->getUser();
		if ( $user instanceof UserInterface ) {
			try {
				$this->loginManager->loginUser(
					$this->fwname,
					$user,
					$response);
			} catch (AccountStatusException $ex) {
				// We simply do not authenticate users which do not pass the user
				// checker (not enabled, expired, etc.).
			}
		}
	}
}
