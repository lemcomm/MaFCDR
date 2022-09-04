<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BattleReportObserver
 */
class BattleReportObserver {

        public function setReport($battleReport = null) {
                return $this->setBattleReport($battleReport);
        }
        
}
