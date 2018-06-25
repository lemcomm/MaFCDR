<?php 

namespace BM2\SiteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;


class House {

	protected $ultimate=false;
	
	public function findUltimate() {
		if ($this->ultimate!==false) {
			return $this->ultimate;
		}
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
	
	public function findAllLiving() {
		$all_living = new ArrayCollection;
		$all_members = $this->findAllMembers();
		foreach ($all_members as $member) {
			if ($member->isAlive) {
				$all_living[] = $member;
			}
		}
		return $all_living;
	}
	
	public function findAllDead() {
		$all_dead = new ArrayCollection;
		$all_members = $this->findAllMembers();
		foreach ($all_members as $member) {
			if (!$member->isAlive) {
				$all_dead[] = $member;
			}
		}
		return $all_dead;
	}
	
	public function findAllMembers() {
		$all_members = new ArrayCollection;
		$all_cadets = $this->findAllCadets($include_myself=true);
		foreach ($allcadets as $cadet) {
			foreach ($cadet->getMembers() as $cadetmember) {
				$all_members[] = $cadetmember;
			}
		}
		return $all_members;
	}
	
	public function findAllCadets($include_myself = false) {
		$all_cadets = new ArrayCollection;
		if ($include_myself) {
			$all_cadets[] = $this;
		}
		foreach ($this->getCadets() as $cadet) {
			$all_cadets[] = $cadet;
			$suball = $cadet->findAllCadets();
			foreach ($suball as $sub) {
				if (!$all->contains($sub)) {
					$all->add($sub);
				}
			}
		}
		return $all_cadets;
	}

}
