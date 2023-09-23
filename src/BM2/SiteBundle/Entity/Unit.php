<?php

namespace BM2\SiteBundle\Entity;

class Unit {

	private $maxSize = 200;

	public function getVisualSize() {
		$size = 0;
		foreach ($this->soldiers as $soldier) {
			if ($soldier->isActive()) {
				$size += $soldier->getVisualSize();
			}
		}
		return $size;
	}

	public function getMilitiaCount() {
		$c = 0;
		foreach ($this->soldiers as $each) {
			if ($each->isActive(true, true)) {
				$c++;
			}
		}
		return $c;
	}

	public function getActiveSoldiers() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return ($entry->isActive());
			}
		);
	}

	public function getTravellingSoldiers() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return ($entry->getTravelDays() > 0 && $entry->isAlive());
			}
		);
	}

	public function getWoundedSoldiers() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return ($entry->getWounded() > 0 && $entry->isAlive());
			}
		);
	}

	public function getLivingSoldiers() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return ($entry->isAlive());
			}
		);
	}

	public function getDeadSoldiers() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return (!$entry->isAlive());
			}
		);
	}

	public function getActiveSoldiersByType() {
		return $this->getSoldiersByType(true);
	}

	public function getSoldiersByType($active_only=false) {
		$data = array();
		if ($active_only) {
			$soldiers = $this->getActiveSoldiers();
		} else {
			$soldiers = $this->getSoldiers();
		}
		foreach ($soldiers as $soldier) {
			$type = $soldier->getType();
			if (isset($data[$type])) {
				$data[$type]++;
			} else {
				$data[$type] = 1;
			}
		}
		return $data;
	}

	public function getAvailable() {
		return $this->maxSize - $this->getSoldiers()->count();
	}

	public function getRecruits() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return ($entry->isRecruit());
			}
		);
	}

	public function getNotRecruits() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return (!$entry->isRecruit());
			}
		);
	}

	public function isLocal() {
		if ($this->getSettlement() && !$this->getCharacter() && !$this->getPlace() && !$this->getDefendingSettlement() && !$this->getTravelDays()) {
			return true;
		}
		return false;
	}
	
}
