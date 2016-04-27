<?php 

namespace BM2\SiteBundle\Entity;

class FeatureType {

	public function getNametrans() {
		return 'feature.'.$this->getName();
	}

}
