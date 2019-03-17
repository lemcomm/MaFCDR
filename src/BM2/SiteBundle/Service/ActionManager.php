<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Action;

use Doctrine\ORM\EntityManager;

/*
This mostly exists to get the queue function out of ActionResolution, in order to avoid circular dependencies.
*/

class ActionManager {

	private $em;

	public function __construct(EntityManager $em) {
		$this->em = $em;
	}

	public function queue(Action $action) {
		$action->setStarted(new \DateTime("now"));

		// store in database and queue
		$success = true;
		$max=0;
		foreach ($action->getCharacter()->getActions() as $act) {
			if ($act->getPriority()>$max) {
				$max=$act->getPriority();
			}
		}
		$action->setPriority($max+1);

		// some defaults, otherwise I'd have to set it explicitly everywhere
		if ($action->getHidden()===null) {
			$action->setHidden(false);
		}
		if ($action->getHourly()===null) {
			$action->setHourly(false);
		}
		if ($action->getCanCancel()===null) {
			$action->setCanCancel(true);
		}
		$this->em->persist($action);

		$this->em->flush();

		return array('success'=>$success);
	}

}
