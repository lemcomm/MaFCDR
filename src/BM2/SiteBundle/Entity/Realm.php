<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;


class Realm {

	protected $ultimate=false;
	protected $all_characters=false;
	protected $all_active_characters=false;
	protected $rulers=false;


	public function findUltimate() {
		if ($this->ultimate!==false) return $this->ultimate;
		if (!$superior=$this->getSuperior()) {
			$this->ultimate=$this;
		} else {
			while ($superior->getSuperior()) {
				$superior=$superior->getSuperior();
			}
			$this->ultimate=$superior;
		}
		return $this->ultimate;
	}

	public function isUltimate() {
		if ($this->findUltimate() == $this) return true;
		return false;
	}

	public function findTerritory($with_subs=true, $all_subs=true) {
		if (!$with_subs) return $this->getSettlements();

		$territory = new ArrayCollection;

		if ($all_subs==true) {
			$all = $this->findAllInferiors(true);
		} else {
			$all[] = $this;
			$all[] = $this->getInferiors();
		}
		foreach ($all as $realm) {
			foreach ($realm->getSettlements() as $settlement) {
				if (!$territory->contains($settlement)) {
					$territory->add($settlement);
				}
			}
		}

		return $territory;
	}

	public function findRulers() {
		if (!$this->rulers) {
			$this->rulers = new ArrayCollection;

			foreach ($this->getPositions() as $pos) {
				if ($pos->getRuler()) {
					foreach ($pos->getHolders() as $ruler) {
						$this->rulers->add($ruler);
					}
				}
			}
		}

		return $this->rulers;
	}

	public function findMembers($with_subs=true, $forceupdate = false) {
		if ($this->all_characters && $forceupdate == false) return $this->all_characters;
		$this->all_characters = new ArrayCollection;

		foreach ($this->findTerritory(false) as $settlement) {
			$owner = $settlement->getOwner();
			if ($owner) {
				$this->addRealmMember($owner);
			}
			$steward = $settlement->getSteward();
			if ($steward) {
				$this->addRealmMember($steward);
			}
			foreach ($settlement->getVassals() as $knight) {
				$this->addRealmMember($knight);
			}
		}

		foreach ($this->getPositions() as $pos) {
			foreach ($pos->getHolders() as $official) {
				$this->addRealmMember($official);
			}
			foreach ($pos->getVassals() as $knight) {
				$this->addRealmMember($knight);
			}
		}

		foreach ($this->getPlaces() as $place) {
			$owner = $place->getOwner();
			if ($owner) {
				$this->addRealmMember($owner);
			}
			foreach ($place->getVassals() as $knight) {
				$this->addRealmMember($knight);
			}
		}

		foreach ($this->getVassals() as $knight) {
			$this->addRealmMember($knight);
		}

		if ($with_subs) {
			foreach ($this->getInferiors() as $sub) {
				foreach ($sub->findMembers() as $submember) {
					$this->addRealmMember($submember);
				}
			}
		}

		return $this->all_characters;
	}

	public function findActiveMembers($with_subs=true, $forceupdate = false) {
		if ($this->all_active_characters && $forceupdate == false) return $this->all_active_characters;
		$this->all_active_characters = new ArrayCollection;

		foreach ($this->findTerritory(false) as $settlement) {
			$owner = $settlement->getOwner();
			if ($owner AND $owner->isActive(true)) {
				$this->addActiveRealmMember($owner);
			}
			$steward = $settlement->getSteward();
			if ($steward AND $steward->isActive(true)) {
				$this->addActiveRealmMember($steward);
			}
		}

		foreach ($this->getPositions() as $pos) {
			foreach ($pos->getHolders() as $official) {
				if ($official->isActive(true)) {
					$this->addActiveRealmMember($official);
				}
			}
			foreach ($pos->getVassals() as $knight) {
				if ($knight->isActive(true)) {
					$this->addActiveRealmMember($knight);
				}
			}
		}

		foreach ($this->getPlaces() as $place) {
			$owner = $place->getOwner();
			if ($owner AND $owner->isActive(true)) {
				$this->addActiveRealmMember($owner);
			}
			foreach ($place->getVassals() as $knight) {
				if ($knight->isActive(true)) {
					$this->addActiveRealmMember($knight);
				}
			}
		}

		foreach ($this->getVassals() as $knight) {
			if ($knight->isActive(true)) {
				$this->addActiveRealmMember($knight);
			}
		}

		if ($with_subs) {
			foreach ($this->getInferiors() as $sub) {
				foreach ($sub->findActiveMembers() as $submember) {
					$this->addActiveRealmMember($submember);
				}
			}
		}

		return $this->all_active_characters;
	}

	private function addRealmMember(Character $char) {
		if (!$this->all_characters->contains($char)) {
			$this->all_characters->add($char);
		}
		foreach ($char->getVassals() as $vassal) {
			if (!$this->all_characters->contains($vassal)) {
				$this->all_characters->add($vassal);
			}
		}
	}

	private function addActiveRealmMember(Character $char) {
		if (!$this->all_active_characters->contains($char)) {
			$this->all_active_characters->add($char);
		}
		foreach ($char->getVassals() as $vassal) {
			if (!$this->all_active_characters->contains($vassal)) {
				$this->all_active_characters->add($vassal);
			}
		}
	}

	public function findAllInferiors($include_myself = false) {
		$all = new ArrayCollection;
		if ($include_myself) {
			$all->add($this);
		}

		foreach ($this->getInferiors() as $subrealm) {
			$all->add($subrealm);
			$suball = $subrealm->findAllInferiors();
			foreach ($suball as $sub) {
				if (!$all->contains($sub)) {
					$all->add($sub);
				}
			}
		}

		return $all;
	}

	public function findDeadInferiors() {
		$all = new ArrayCollection;
		foreach ($this->getInferiors() as $subrealm) {
			if (!$subrealm->getActive()) {
			$all->add($subrealm);
			}
		}

		return $all;
	}

	public function findAllSuperiors($include_myself = false) {
		$all = new ArrayCollection;
		if ($include_myself) {
			$all->add($this);
		}

		if ($superior = $this->getSuperior()) {
			$all->add($superior);
			$supall = $superior->findAllSuperiors();
			foreach ($supall as $sup) {
				if (!$all->contains($sup)) {
					$all->add($sup);
				}
			}
		}

		return $all;

	}

	public function findHierarchy($include_myself = false) {
		$all = new ArrayCollection;
		if ($include_myself) {
			$all->add($this);
		}
		foreach ($this->findAllSuperiors() as $realm) {
			$all->add($realm);
		}
		foreach ($this->findAllInferiors() as $realm) {
			$all->add($realm);
		}
		return $all;
	}

	public function findFriendlyRelations() {
		$all = new ArrayCollection();
		foreach ($this->getMyRelations() as $rel) {
			if ($rel->getStatus() != 'nemesis' && $rel->getStatus() != 'war') {
				$all->add($rel->getTargetRealm());
			}
		}
		return $all;
	}

	public function findUnfriendlyRelations() {
		$all = new ArrayCollection();
		foreach ($this->getMyRelations() as $rel) {
			if ($rel->getStatus() == 'nemesis' || $rel->getStatus() == 'war') {
				$all->add($rel->getTargetRealm());
			}
		}
		return $all;
	}
	
}
