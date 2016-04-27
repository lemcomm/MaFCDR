<?php 

namespace BM2\SiteBundle\Entity;

class EquipmentType {

	public function getNametrans() {
		return 'item.'.$this->getName();
	}

}
