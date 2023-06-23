<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;


class BattleGroup {

	protected $soldiers=null;
	protected $enemy;

	public function setupSoldiers() {
		$this->soldiers = new ArrayCollection;
		foreach ($this->getCharacters() as $char) {
			foreach ($char->getUnits() as $unit) {
				foreach ($unit->getActiveSoldiers() as $soldier) {
					$this->soldiers->add($soldier);
				}
			}
		}

		if ($this->battle->getSettlement() && $this->battle->getSiege() && $this->battle->getSiege()->getSettlement() === $this->battle->getSettlement()) {
			$type = $this->battle->getType();
			if (($this->isDefender() && $type === 'siegeassault') || ($this->isAttacker() && $type === 'siegesortie')) {
				foreach ($this->battle->getSettlement()->getUnits() as $unit) {
					if ($unit->isLocal()) {
						foreach ($unit->getSoldiers() as $soldier) {
							if ($soldier->isActive(true, true)) {
								$this->soldiers->add($soldier);
								$soldier->setRouted(false);
							}
						}
					}
				}
			}
		}
	}

	public function getTroopsSummary() {
		$types=array();
		foreach ($this->getSoldiers() as $soldier) {
			$type = $soldier->getType();
			if (isset($types[$type])) {
				$types[$type]++;
			} else {
				$types[$type] = 1;
			}
		}
		return $types;
	}

	public function getVisualSize() {
		$size = 0;
		foreach ($this->soldiers as $soldier) {
			$size += $soldier->getVisualSize();
		}
		return $size;
	}

	public function getSoldiers() {
		if (null === $this->soldiers) {
			$this->setupSoldiers();
		}

		return $this->soldiers;
	}

	public function getActiveSoldiers() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return ($entry->isActive());
			}
		);
	}

	public function getActiveMeleeSoldiers() {
		return $this->getActiveSoldiers()->filter(
			function($entry) {
				return (!$entry->isRanged());
			}
		);
	}

	public function getFightingSoldiers() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return ($entry->isFighting());
			}
		);
	}

	public function getRoutedSoldiers() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return ($entry->isActive(true) && ($entry->isRouted() || $entry->isNoble()) );
			}
		);
	}

	public function getLivingNobles() {
		return $this->getSoldiers()->filter(
			function($entry) {
				return ($entry->isNoble() && $entry->isAlive());
			}
		);
	}

	public function isAttacker() {
		return $this->attacker;
	}

	public function isDefender() {
		return !$this->attacker;
	}

	public function getEnemies() {
		$enemies = array();
		if ($this->battle) {
			if ($this->getReinforcing()) {
				$primary = $this->getReinforcing();
			} else {
				$primary = $this;
			}
			$enemies = new ArrayCollection;
			foreach ($this->battle->getGroups() as $group) {
				if ($group == $primary || $group->getReinforcing() == $primary) {
					# Do nothing, those are allies!
				} else {
					$enemies->add($group);
				}
			}
		} else if ($this->siege) {
			# Sieges are a lot easier, as they're always 2 sided.
			if ($this->siege->getAttackers()->contains($this)) {
				$enemies = $this->siege->getDefenders();
			} else {
				$enemies = $this->siege->getAttackers();
			}
		}
		if (!empty($enemies)) {
			return $enemies;
		} else {
			throw new \Exception('battle group '.$this->id.' has no enemies');
		}
	}

	public function getLocalId() {
		return intval($this->isDefender());
	}
	
}
