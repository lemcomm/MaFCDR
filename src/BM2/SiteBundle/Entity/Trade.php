<?php 

namespace BM2\SiteBundle\Entity;

class Trade {

	public function __toString() {
		return "trade {$this->id} - from ".$this->source->getId()." to ".$this->destination->getId();
	}

}
