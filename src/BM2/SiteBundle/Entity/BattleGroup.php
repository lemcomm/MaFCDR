<?php 

namespace BM2\SiteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;



class BattleGroup {

	protected $soldiers=null;
	protected $enemy;
	protected $myReport;

	/*
	 * @codeCoverageIgnore
	 */  

/* No idea why this was a thing, but it was throwing an error when sieges were implemented.
	public function __toString() {
		\Doctrine\Common\Util\Debug::dump($this, 3);

		$r = "battlegroup ".$this->id." of battle ".$this->battle->getId()." characters: ";
		foreach ($this->getCharacters() as $char) {
			$r.=", ".$char->getId();
		}

		return $r;
	}
*/
	public function setupSoldiers() {
		$this->soldiers = new ArrayCollection;
		foreach ($this->getCharacters() as $char) {
			foreach ($char->getActiveSoldiers() as $soldier) {
				$this->soldiers->add($soldier);
			}
		}

		if ($this->battle->getSettlement() && $this->isDefender()) {
			foreach ($this->battle->getSettlement()->getActiveMilitia() as $soldier) {
				if ($soldier->isActive()) {
					$this->soldiers->add($soldier);
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

	/* Legacy code from pre-sieges. 
	public function getEnemy() {
		foreach ($this->battle->getGroups() as $group) {
			if ($group != $this) return $group;
		}
		throw new \Exception('battle group '.$this->id.' has no enemy');
	} */

	public function getEnemies() {
		$enemies = array();
		if ($this->battle) {
			foreach ($this->battle->getGroups() as $group) {
				if ($group->getReinforcing() != $this && $group != $this) {
					$enemies[] = $group;
				}
			}
		} else if ($this->siege) {
			foreach ($this->siege->getGroups() as $group) {
				if ($group->getReinforcing() != $this) {
					$enemies[] = $group;
				}
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
