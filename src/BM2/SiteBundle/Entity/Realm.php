<?php

namespace BM2\SiteBundle\Entity;

use BM2\SiteBundle\Entity\Faction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

class Realm extends Faction {

	protected $all_characters=false;
	protected $all_active_characters=false;
	protected $rulers=false;


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

		if ($law = $this->findActiveLaw('realmPlaceMembership')) {
			foreach ($this->getPlaces() as $place) {
				# These deliberately cascade into each other.
				switch($law->getValue()) {
					case 'all':
						foreach ($place->getVassals() as $knight) {
							$this->addRealmMember($knight);
						}
					case 'owner':
						$owner = $place->getOwner();
						if ($owner) {
							$this->addRealmMember($owner);
						}
				}
			}
		}

		foreach ($this->getVassals() as $knight) {
			$this->addRealmMember($knight);
		}

		foreach ($this->getHostedEmbassies() as $embassy) {
			if ($ambassador = $embassy->getAmbassador()) {
				$this->addRealmMember($ambassador);
			}
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
