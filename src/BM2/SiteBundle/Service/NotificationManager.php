<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Twig\MessageTranslateExtension;
use Doctrine\ORM\EntityManager;

class NotificationManager {

	protected $em;
	protected $appstate;
	protected $mailman;

	public function __construct(EntityManager $em, AppState $appstate, MailManager $mailman, MessageTranslateExtension $msgtrans) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->mailman = $mailman;
	}

	private function findUser(Event $event) {
		$entity = $event->getLog()->getSubject();
		if ($entity instanceof Character) {
			return [$entity->getUser()];
		}
		if ($entity instanceof Settlement) {
			return [
				$entity->getOwner()?$entity->getOwner()->getUser():null,
				$entity->getSteward()?$entity->getSteward()->getUser():null,
				$entity->getOccupant()?$entity->getOccupant()->getUser():null,
			];
		}
		if ($entity instanceof Realm) {
			$rulers = [];
			foreach ($entity->findRulers() as $ruler) {
				$user = $ruler->getUser();
				if (!in_array($user, $users)) {
					$rulers[] = $ruler->getUser();
				}
			}
			return $rulers;
		}
		if ($entity instanceof House) {
			return [$entity->getHead()?$entity->getHead()->getUser():null];
		}
		if ($entity instanceof Place) {
			return [
				$entity->getOwner()?$entity->getOwner()->getUser():null,
				$entity->getOccupant()?$entity->getOccupant()->getUser():null,
			];
		}
		if ($entity instanceof Association) {
			$users = [];
			foreach ($entity->findOwners() as $each) {
				$user = $each->getUser();
				if (!in_array($user, $users)) {
					$users[] = $user;
				}
			}
			return $users;
		}
		if ($entity instanceof Artifact) {
			return [$entity->getCreator()]; #NOTE: Creator is a User Entity.
		}
		return false;
	}

	public function spoolEvent(Event $event) {
		$users = $this->findUser($event);
		foreach ($users as $user) {
			if (!$user || !$user->getNotifications()) {
				return false; # No user to notify or user has disabled notifications.
			}
			#TODO: Expand this if we ever use other notification types. Like push notifications, or something to an app, etc.
			$this->mailman->spoolEvent($event, $user, $this->msgtrans->eventTranslate($event, true));
		}
	}

}
