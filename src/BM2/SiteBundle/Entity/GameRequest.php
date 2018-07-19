<?php 

namespace BM2\SiteBundle\Entity;

class GameRequest {

	public function __toString() {
		return "request {$this->id} - {$this->type}";
	}
	
	
}
