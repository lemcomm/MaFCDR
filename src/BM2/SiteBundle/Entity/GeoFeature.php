<?php 

namespace BM2\SiteBundle\Entity;

class GeoFeature {

	public function ApplyDamage($damage) {
		$this->condition -= $damage;

		if ($this->condition <= -$this->type->getBuildHours()) {
			// destroyed
			$this->active = false;
			$this->condition = -$this->type->getBuildHours();
			return 'destroyed';
		} else if ($this->active && $this->condition < -$this->type->getBuildHours()*0.25) {
			// disabled / inoperative
			$this->active = false;
			return 'disabled';
		} else {
			return 'damaged';
		}

	}

}
