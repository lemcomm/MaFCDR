<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Activity;
use BM2\SiteBundle\Entity\ActivityType;
use BM2\SiteBundle\Entity\ActivityBout;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;

use Doctrine\ORM\EntityManager;

/*
As you might expect, ActivityManager handles Activities.
*/

class ActionManager {

	private $em;

	public function __construct(EntityManager $em) {
		$this->em = $em;
	}

        public function create(Activity $act) {

        }

}
