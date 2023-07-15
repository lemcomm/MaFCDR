<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Artifact;
use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\BattleReport;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\House;
use BM2\SiteBundle\Entity\Event;
use BM2\SiteBundle\Entity\Journal;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Twig\MessageTranslateExtension;
use Doctrine\ORM\EntityManager;
use Exception;

class NotificationManager {

	protected $em;
	protected $appstate;
	protected $mailman;
	protected $msgtrans;
	protected $trans;
	protected $discord;
	private $type;
	private $name;

	public function __construct(EntityManager $em, AppState $appstate, MailManager $mailman, MessageTranslateExtension $msgtrans, $translator, DiscordIntegrator $discord) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->mailman = $mailman;
		$this->msgtrans = $msgtrans;
		$this->trans = $translator;
		$this->discord = $discord;
	}

	private function findUser(Event $event) {
		$log = $event->getLog();
		$entity = $log->getSubject();
		$this->name = $log->getName();
		$this->type = $event->getLog()->getType();
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

	public function spoolAchievement($type, Character $char) {
		if ($type === 'dragon') {
			$text = '['.$char->getName().'](https://mightandfealty.com/character/view/'.$char->getId().') has accomplished a feat few others have, and successfully slain a dragon!';
			try {
				$this->discord->pushToGeneral($text);
			} catch (Exception $e) {
				# Nothing
			}
		}
	}

	public function spoolEvent(Event $event) {
		$users = $this->findUser($event);

		$text = $this->msgtrans->eventTranslate($event, true);
		$msg = $this->name.' ('.$this->type.') -- '.$text;

		if ($users) {
			foreach ($users as $user) {
				if (!$user || !$user->getNotifications()) {
					return false; # No user to notify or user has disabled notifications.
				}
				#TODO: Expand this if we ever use other notification types. Like push notifications, or something to an app, etc.
				$this->mailman->spoolEvent($event, $user, $msg);
			}
		}
	}

	public function spoolBattle(BattleReport $rep, $epic) {
		$em = $this->em;
		$entity = false;
		if ($loc = $rep->getLocationName()) {
			if ($rep->getPlace()) {
				$entity = $em->getRepository("BM2SiteBundle:Place")->find($loc['id']);
				$name = $entity->getName();
				$url = 'https://mightandfealty.com/place/'.$loc['id'];
			} else {
				$entity = $em->getRepository("BM2SiteBundle:Settlement")->find($loc['id']);
				$name = $entity->getName();
				$url = 'https://mightandfealty.com/settlement/'.$loc['id'];
			}
		}
		if (!$entity) {
			return;
		}
		if ($loc['key'] === 'battle.location.nowhere') {
			$str = 'in lands unknown(!?)';
		} elseif ($loc['key'] === 'battle.location.of') {
			$str = 'at ['.$name.']('.$url.')';
		} elseif ($loc['key'] === 'battle.location.siege') {
			$str = 'during the siege of ['.$name.']('.$url.')';
		} elseif ($loc['key'] === 'battle.location.sortie') {
			$str = 'started by the defenders of ['.$name.']('.$url.')';
		} elseif ($loc['key'] === 'battle.location.assault') {
			$str = 'during the assault of ['.$name.']('.$url.')';
		} elseif ($loc['key'] === 'battle.location.near') {
			$str = 'near ['.$name.']('.$url.')';
		} elseif ($loc['key'] === 'battle.location.around') {
			$str = 'in the vicinity of ['.$name.']('.$url.')';
		} elseif ($loc['key'] === 'battle.location.castle') {
			$str = 'in the halls of ['.$name.']('.$url.')';
		}
		if ($epic > 9) {
			$txt = "Tales are spun and epics created about a legendary battle ".$str."!";
		} elseif ($epic > 6) {
			$txt = "Tales are spun and epics created about a massive battle ".$str."!";
		} elseif ($epic > 4) {
			$txt = "Tales are spun and epics created about a huge battle ".$str."!";
		} elseif ($epic > 3) {
			$txt = "Tales are spun and epics created about a large battle ".$str."!";
		} else {
			$txt = "Tales are spun and epics created about a battle ".$str."!";
		}
		try {
			$this->discord->pushToGeneral($txt);
		} catch (Exception $e) {
			# Nothing.
		}
	}

	public function spoolJournal(Journal $journal) {
		$text = '['.$journal->getCharacter()->getName().'](https://mightandfealty.com/character/view/'.$journal->getCharacter()->getId().') has written ['.$journal->getTopic().'](https://mightandfealty.com/journal/'.$journal->getId().').';
		try {
			$this->discord->pushToGeneral($text);
		} catch (Exception $e) {
			# Nothing
		}
	}

	public function spoolPayment($text) {
		try {
			$this->discord->pushToPayments($text);
		} catch (Exception $e) {
			# Nothing.
		}
	}

	public function spoolNewRealm(Character $char, Realm $realm, $sub = false) {
		if ($sub) {
			$txt = $char->getName()." has created the new subrealm of ".$realm->getFormalName().". It includes the settlements of: ";
		} else {
			$txt = $char->getName()." has created the new realm of ".$realm->getFormalName().". It includes the settlements of: ";
		}
		$url = 'https://mightandfealty.com/settlement/';
		$count = $realm->getSettlements()->count();
		$i = 1;
		foreach ($realm->getSettlements() as $each) {
			if ($i > 1 && $i == $count) {
				$txt .= ', and '.$this->dLink($each->getName(), $url.$each->getId()).'.';
			} elseif ($i === 1) {
				$txt .= $this->dLink($each->getName(), $url.$each->getId());
			} else {
				$txt .= ', '.$this->dLink($each->getName(), $url.$each->getId());
			}
			$i++;
		}
		$this->discord->pushToGeneral($txt);
	}

	private function dLink($name, $url) {
		return "[".$name."](".$url.")";
	}

}
