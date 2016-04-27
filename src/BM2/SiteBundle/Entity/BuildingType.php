<?php 

namespace BM2\SiteBundle\Entity;

class BuildingType {

	public function canFocus() {
		if (!$this->getProvidesEquipment()->isEmpty()) return true;
		if (!$this->getProvidesEntourage()->isEmpty()) return true;

		return false;
	}
}
