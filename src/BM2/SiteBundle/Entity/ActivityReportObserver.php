<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ActivityReportObserver
 */
class ActivityReportObserver {

        public function setReport($report = null) {
                return $this->setActivityReport($report);
        }
        
}
