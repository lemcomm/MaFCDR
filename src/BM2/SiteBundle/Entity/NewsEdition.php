<?php 

namespace BM2\SiteBundle\Entity;

class NewsEdition {


	public function isPublished() {
		return $this->getPublished();
	}
}
