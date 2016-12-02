<?php 

namespace BM2\SiteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;


class Realm {

	protected $ultimate=false;
	protected $all_characters=false;
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

	public function findTerritory($with_subs=true) {
		if (!$with_subs) return $this->getEstates();

		$territory = new ArrayCollection;

		$all = $this->findAllInferiors(true);
		foreach ($all as $realm) {
			foreach ($realm->getEstates() as $estate) {
				if (!$territory->contains($estate)) {
					$territory->add($estate);					
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

	public function findMembers($with_subs=true) {
		if ($this->all_characters) return $this->all_characters;
		$this->all_characters = new ArrayCollection;

		foreach ($this->findTerritory(false) as $estate) {
			$owner = $estate->getOwner();
			if ($owner) {
				$this->addRealmMember($owner);
			}
		}

		foreach ($this->getPositions() as $pos) {
			foreach ($pos->getHolders() as $official) {
				$this->addRealmMember($official);
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
}
