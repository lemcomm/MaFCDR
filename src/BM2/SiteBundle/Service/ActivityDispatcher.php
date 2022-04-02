<?php

namespace BM2\SiteBundle\Service;

class ActivityDispatcher extends Dispatcher {

	protected $appstate;
	protected $pm;
	protected $geo;


	public function __construct(AppState $appstate, PermissionManager $pm, Geography $geo) {
		$this->appstate = $appstate;
		$this->pm = $pm;
		$this->geo = $geo;

	}

	/* ========== Activity Dispatchers ========== */

	public function activityTrainTest($ignored, $type) {
		switch ($type) {
			case 'shortbow':
			case 'crossbow':
			case 'longbow':
			case 'sling':
			case 'staff sling':
				$bldg = 'Archery Range';
				break;
			default:
				$bldg = false;
		}
		if (($check = $this->veryGenericTests()) !== true) {
			return array("name"=>"activity.train.name", "description"=>"unavailable.$check");
		}
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (!$settlement) {
			return array("name"=>"activity.train.name", "description"=>"unavailable.notinside");
		}
		if (!$settlement->hasBuildingNamed($bldg)) {
			return array("name"=>"activity.train.name", "description"=>"unavailable.building.$bldg");
		}
		return $this->action("activity.train", "maf_train_skill", false, ['skill'=>$type]);
	}

}
