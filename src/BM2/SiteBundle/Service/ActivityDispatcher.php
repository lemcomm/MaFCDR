<?php

namespace BM2\SiteBundle\Service;

class ActivityDispatcher extends Dispatcher {

	protected $appstate;


	public function __construct(AppState $appstate) {
		$this->appstate = $appstate;

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
			case 'long sword':
			case 'morning star':
			case 'great axe':
				$bldg = 'Garrison';
				break;
			case 'sword':
			case 'mace':
				$bldg = 'Barracks';
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
