<?php

namespace BM2\SiteBundle\Entity;

class Unit {

	private $maxSize = 200;

	public function getVisualSize() {
		foreach ($this->soldiers as $soldier) {
			$size += $soldier->getVisualSize();
		}
		return $size;
	}

	public function getActiveSoldiers() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return ($entry->isActive());
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
	
}
