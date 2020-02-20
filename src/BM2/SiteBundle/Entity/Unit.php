<?php

namespace BM2\SiteBundle\Entity;

class Unit {

	public function getVisualSize() {
		$size = 5; // the default visual size for nobles, we're not added as a pseudo-soldier like we are in battle groups
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
        
        /* TODO: Do we want entourage attached to units?
	public function getEntourageOfType($type, $only_available=false) {
		if (is_object($type)) {
			return $this->entourage->filter(
				function($entry) use ($type, $only_available) {
					if ($only_available) {
						return ($entry->getType()==$type && $entry->isAlive() && !$entry->getAction());
					} else {
						return ($entry->getType()==$type);
					}
				}
			);
		} else {
			$type = strtolower($type);
			return $this->entourage->filter(
				function($entry) use ($type, $only_available) {
					if ($only_available) {
						return ($entry->getType()->getName()==$type && $entry->isAlive() && !$entry->getAction());
					} else {
						return ($entry->getType()->getName()==$type);
					}
				}
			);
		}
	}

	public function getAvailableEntourageOfType($type) {
		return $this->getEntourageOfType($type, true);
	}

	public function getLivingEntourage() {
		return $this->getEntourage()->filter(
			function($entry) {
				return ($entry->isAlive());
			}
		);
	}

	public function getDeadEntourage() {
		return $this->getEntourage()->filter(
			function($entry) {
				return (!$entry->isAlive());
			}
		);
	}

	public function getActiveEntourageByType() {
		return $this->getEntourageByType(true);
	}

	public function getEntourageByType($active_only=false) {
		$data = array();
		if ($active_only) {
			$npcs = $this->getLivingEntourage();
		} else {
			$npcs = $this->getEntourage();
		}
		foreach ($npcs as $npc) {
			$type = $npc->getType()->getName();
			if (isset($data[$type])) {
				$data[$type]++;
			} else {
				$data[$type] = 1;
			}
		}
		return $data;
	}
        */

}
