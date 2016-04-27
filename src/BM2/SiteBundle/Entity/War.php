<?php 

namespace BM2\SiteBundle\Entity;

class War {

	private $attackers=false;
	private $defenders=false;

	public function getName() {
		return $this->getSummary();
	}

	public function getScore() {
		$score = 0;
		if ($this->getTimer() > 60) {
			$scores = array('now'=>1, 'ever'=>0, 'else'=>0);
		} elseif ($this->getTimer() > 30) {
			$scores = array('now'=>1, 'ever'=>0, 'else'=>-1);
		} else {
			$scores = array('now'=>1, 'ever'=>-1, 'else'=>-3);
		}
		foreach ($this->getTargets() as $target) {
			if ($target->getTakenCurrently()) {
				if ($this->getTimer() <= 0) {
					$score+=3;
				} else {
					$score+=$scores['now'];
				}
			} elseif ($target->getTakenEver()) {
				$score+=$scores['ever'];
			} else {
				$score+=$scores['else'];
			}
		}
		return round($score*100 / count($this->getTargets())*3);
	}


	public function getAttackers($include_self=true) {
		if (!$this->attackers) {
			$this->attackers = array();

			foreach ($this->getTargets() as $target) {
				if ($target->getSettlement()->getRealm()) {
					foreach ($this->getRealm()->getInferiors() as $inferior) {
						if ($inferior->findAllInferiors(true)->contains($target->getSettlement()->getRealm())) {
						// we attack one of our inferior realms - exclude the branch that contains it as attackers
						} else {
							foreach ($inferior->findAllInferiors(true) as $sub) {
								if ($sub->getActive()) {
									$this->attackers[$sub->getId()] = $sub;
								}
							}
						}
					}
				}
			}
		}

		$attackers = $this->attackers;
		if ($include_self) {
			$attackers[$this->getRealm()->getId()] = $this->getRealm();
		}

		return $attackers;
	}


	public function getDefenders() {
		if (!$this->defenders) {
			$this->defenders = array();
			foreach ($this->getTargets() as $target) {
				if ($target->getSettlement()->getRealm()) {
					$this->defenders[$target->getSettlement()->getRealm()->getId()] = $target->getSettlement()->getRealm();
					if ($target->getSettlement()->getRealm()->findAllSuperiors()->contains($this->getRealm())) {
						// one of my superior realms attacks me - don't include the upwards hierarchy as defenders
					} else {
						foreach ($target->getSettlement()->getRealm()->findAllSuperiors() as $superior) {
							if ($superior->getActive()) {
								$this->defenders[$superior->getId()] = $superior;
							}
						}
					}
					foreach ($target->getSettlement()->getRealm()->getInferiors() as $inferior) {
						if ($inferior->findAllInferiors(true)->contains($this->getRealm())) {
						// one of my inferior realms attacks me - exclude the branch that contains it
						} else {
							foreach ($inferior->findAllInferiors(true) as $sub) {
								if ($sub->getActive()) {
									$this->defenders[$sub->getId()] = $sub;
								}
							}
						}
					}
				}
			}
		}
		return $this->defenders;
	}
}
