<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\ORM\EntityRepository;

class Siege {

	public function getLeader() {
		$leader = null;
		foreach ($this->groups as $group) {
			if ($group->isAttacker) {
				$leader = $group->getLeader();
			}
		}
		return $leader;
	}
	
	public function setLeader($character) {
		foreach ($this->groups as $group) {
			if ($group->isAttacker) {
				$group->setLeader($character);
			}
		}
		$this->getDoctrine()->getManager()->flush();
	}
  
}
