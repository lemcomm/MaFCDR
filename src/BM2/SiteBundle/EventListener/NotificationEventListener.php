<?php

namespace BM2\SiteBundle\EventListener;

use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Monolog\Logger;

use BM2\SiteBundle\Twig\MessageTranslateExtension;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Realm;



class NotificationEventListener {

	protected $mailer;
	protected $logger;
	protected $msgtrans;


	// FIXME: type hinting for $translator removed because the addition of LoggingTranslator is breaking it
	public function __construct($translator, \Swift_Mailer $mailer, Logger $logger, MessageTranslateExtension $msgtrans) {
		$this->translator = $translator;
		$this->mailer = $mailer;
		$this->logger = $logger;
		$this->msgtrans = $msgtrans;
	}

	
	public function onNotificationEvent(NotificationEvent $event) {
		$this->logger->debug('event notification triggered');
		$entity = $event->getEntity();

		$text = false;
		if ($entity instanceof Character) {
			$user = $entity->getUser();
			if (!$user) return false;
			if ($user->getNotifications()) {
				$text = $this->translator->trans('mail.intro', array('%name%'=>$entity->getName()), "communication")."<br /><br />\n\n";
			}
		} else if ($entity instanceof Settlement && $entity->getOwner()) {
			$user = $entity->getOwner()->getUser();
			if (!$user) return false;
			if ($user->getNotifications()) {
				$text = $this->translator->trans('mail.intro2', array('%character%'=>$entity->getOwner()->getName(), '%settlement%'=>$entity->getName()), "communication")."<br /><br />\n\n";
			}
		} else if ($entity instanceof Realm) {
			foreach ($entity->findRulers() as $ruler) {
				$user = $ruler->getUser();
				if (!$user) return false;
				if ($user->getNotifications()) {
					$text = $this->translator->trans('mail.intro3', array('%character%'=>$ruler->getName(), '%realm%'=>$entity->getName()), "communication")."<br /><br />\n\n";
				}
			}
		}
		
		$twoMonths = new \DateTime("-2 months");
		if ($text != false && $user && $user->getNotifications() != false && $user->getLastLogin() > $twoMonths) {
			$text.= $this->msgtrans->eventTranslate($event->getEvent(), true)."<br /><br />\n\n";
			$text.= $this->translator->trans('mail.footer', array(), "communication");

			$message = \Swift_Message::newInstance()
				->setSubject($this->translator->trans('mail.subject', array(), "communication"))
				->setFrom(array('mafserver@lemuriacommunity.org' => $this->translator->trans('mail.sender', array(), "communication")))
				->setReplyTo('mafteam@lemuriacommunity.org')
				->setTo($user->getEmail())
				->setBody(strip_tags($text))
				->addPart($text, 'text/html')
			;
			$numSent = $this->mailer->send($message);
			$this->logger->debug('sent event notification email: ('.$numSent.') - '.$text);
		}
	}
}
