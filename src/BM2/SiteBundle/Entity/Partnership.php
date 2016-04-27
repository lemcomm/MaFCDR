<?php 

namespace BM2\SiteBundle\Entity;

class Partnership {

	public function getOtherPartner(Character $me) {
		foreach ($this->getPartners() as $partner) {
			if ($partner != $me) return $partner;
		}
		return false; // should never happen
	}

}
