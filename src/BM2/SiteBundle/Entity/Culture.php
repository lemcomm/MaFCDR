<?php 

namespace BM2\SiteBundle\Entity;

class Culture {

	public function __toString() {
		return "culture.".$this->name;
	}

}
