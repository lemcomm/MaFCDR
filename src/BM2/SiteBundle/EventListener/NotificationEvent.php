<?php

namespace BM2\SiteBundle\EventListener;

use Symfony\Component\EventDispatcher\Event;

use BM2\SiteBundle\Entity\Event as GameEvent;


class NotificationEvent extends Event {
	protected $event;
	protected $entity;

	public function __construct(GameEvent $event, $entity) {
		$this->event = $event;
		$this->entity = $entity;
	}

	public function getEvent() {
		return $this->event;
	}

	public function getEntity() {
		return $this->entity;
	}

}
