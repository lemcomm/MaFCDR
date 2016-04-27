<?php 

namespace BM2\SiteBundle\Entity;

class EventMetadata {


	public function countNewEvents() {
		$count = 0;
		if ($this->getAccessUntil()) return 0; // FIXME: this is a hack to prevent the new start lighting up for closed logs
		foreach ($this->getLog()->getEvents() as $event) {
			if ($event->getTs() > $this->last_access) {
				$count++;
			}
		}
		return $count;
	}

	public function hasNewEvents() {
		if ($this->getAccessUntil()) return false; // FIXME: this is a hack to prevent the new start lighting up for closed logs
		foreach ($this->getLog()->getEvents() as $event) {
			if ($event->getTs() > $this->last_access) {
				return true;
			}
		}
		return false;		
	}

}
