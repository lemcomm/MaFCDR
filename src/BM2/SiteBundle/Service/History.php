<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\BattleReport;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Event;
use BM2\SiteBundle\Entity\EventLog;
use BM2\SiteBundle\Entity\EventMetadata;
use BM2\SiteBundle\Entity\Soldier;
use BM2\SiteBundle\Entity\SoldierLog;
use Doctrine\ORM\EntityManager;


class History {

	const LOW = 0;
	const MEDIUM = 10;
	const HIGH = 20;
	const ULTRA = 30;

	const NOTIFY = 20;

	protected $em;
	protected $appstate;
	protected $noteman;


	public function __construct(EntityManager $em, AppState $appstate, NotificationManager $noteman) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->noteman = $noteman;
	}


	public function logEvent($entity, $translationKey, $data=null, $priority=History::MEDIUM, $public=false, $limited=null) {
		// so we can call this with null values without checking all the time, for example, if a settlement owner exists
		if (!$entity) return null;

		$log = $this->findLog($entity);
		$event = new Event();
		$event->setLog($log);
		$event->setContent($translationKey);
		// TODO: to catch errors, we should at least check that $data does not contain objects (only their ids)
		$event->setData($data);
		$event->setPublic($public);
		$event->setPriority($priority);
		$event->setLifetime($limited);
		$event->setTs(new \DateTime("now"));
		$event->setCycle($this->appstate->getCycle());
		$this->em->persist($event);

		// notify player by mail of important events
		if ($priority >= History::NOTIFY) {
			$this->noteman->spoolEvent($event);
		}

		return $event;
	}

	public function addToSoldierLog(Soldier $soldier, $translationKey, $data=null) {
		if ($soldier->isNoble()) {
			return $this->logEvent($soldier->getCharacter(), 'soldier.'.$translationKey, $data, HISTORY::MEDIUM, false, 60);
		} else {
			$event = new SoldierLog;
			$event->setSoldier($soldier);
			$event->setContent('soldier.'.$translationKey);
			$event->setData($data);
			$event->setTs(new \DateTime("now"));
			$event->setCycle($this->appstate->getCycle());
			$this->em->persist($event);
			$soldier->addEvent($event);

			return $event;
		}
	}


	/*
		open and close access to event logs
		we can theoretically have multiple logs on the same entity open - non-overlapping intervalls
	*/
	public function closeLog($entity, Character $reader) {
		if ($entity instanceof EventLog) {
			$log = $entity;
		} else {
			$log = $this->findLog($entity);
		}
		// no more access to new events in this log, but can still read old entries
		$metadata = $this->em->getRepository('BM2SiteBundle:EventMetadata')->findBy(array('log'=>$log, 'reader'=>$reader, 'access_until'=>null));
		foreach ($metadata as $meta) {
			$meta->setAccessUntil($this->appstate->getCycle());
		}
	}

	public function openLog($entity, Character $reader) {
		$self = false;
		if ($entity === $reader) {
			$self = true;
		}
		$log = $this->findLog($entity);
		$exists = $this->em->getRepository('BM2SiteBundle:EventMetadata')->findBy(array('log'=>$log, 'reader'=>$reader, 'access_until'=>null));
		if (!$exists) {
			$meta = new EventMetadata();
			// we get all events from the past 5 days automatically. Older events we will have to research
			$meta->setAccessFrom(max(1, $this->appstate->getCycle() - 5));
			$meta->setLastAccess(new \DateTime("now"));
			$meta->setLog($log);
			$meta->setReader($reader);
			$this->em->persist($meta);
			return $meta;
		} elseif ($self) {
			$last = $log->getMetadatas()->last();
			if ($last instanceof EventMetadata) {
				$last->setAccessUntil();
			}
		}
		return $exists;
	}

	public function visitLog($entity, Character $reader) {
		$log = $this->findLog($entity);
		$metadata = new EventMetadata();
		$metadata->setAccessFrom($this->appstate->getCycle());
		$metadata->setLastAccess(new \DateTime("now"));
		$metadata->setLog($log);
		$metadata->setReader($reader);
		$this->em->persist($metadata);
	}

	public function investigateLog($log, Character $reader, $interval) {
		// TODO: move your access back in time by spending time, money, whatever on gaining more knowledge
		// this also requires changes to the above - openLog would be more limited, going back x days -
		// -- so it would be pretty much just like visitlog. but then this here would also have to merge log
		// accesses if they start overlapping...
	}


	private function findLog($entity) {
		$log = $entity->getLog();
		if (!$log) {
			// create new log
			$log = new EventLog();
			$this->em->persist($log);
			$entity->setLog($log);
			$this->em->flush($log); // need this here or later code accessing the log will fail because it doesn't have a database reference
		}
		return $log;
	}

	public function evaluateBattle(BattleReport $report) {
		$size = $report->getCount();
		if ($size <100) {
			$epic = 0;
		} elseif ($size <250) {
			$epic = 1;
		} elseif ($size <500) {
			$epic = 2;
		} elseif ($size <1000) {
			$epic = 3;
		} elseif ($size <1500) {
			$epic = 4;
		} elseif ($size <2000) {
			$epic = 5;
		} elseif ($size <3000) {
			$epic = 6;
		} elseif ($size <5000) {
			$epic = 7;
		} elseif ($size <10000) {
			$epic = 8;
		} else {
			$epic = 9;
		}
		$report->setEpicness($epic);
		if ($epic > 2) {
			$aSize = $report->getGroups()->first()->getCount();
			$ratio = $size / $aSize;
			if ($ratio <= 5 && $ratio >= 1.25) {
				$this->noteman->spoolBattle($report, $epic);
			}
		}
	}
}
