<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Unit;

class UnitDispatcher extends Dispatcher {

	protected $appstate;
	protected $pm;
	protected $milman;

	public function __construct(AppState $appstate, PermissionManager $pm, MilitaryManager $milman) {
		$this->appstate = $appstate;
		$this->pm = $pm;
		$this->milman = $milman;

	}

	/* =========== Tests ========== */

	private function recruitActionsGenericTests(Settlement $settlement=null, $test='recruit') {
		if ($this->getCharacter()->isNPC()) {
			return 'npc';
		}
		if (!$settlement) {
			return 'notinside';
		}
		if (!$this->pm->checkSettlementPermission($settlement, $this->getCharacter(), $test)) {
			if ($test == 'recruit' && $this->pm->checkSettlementPermission($settlement, $this->getCharacter(), 'units')) {
				return $this->veryGenericTests();
			} else {
				return 'notyours';
			}
		}

		return $this->veryGenericTests();
	}

	/* =========== Menus ========== */

	public function recruitActions() {
		$actions=array();
		if ($this->getCharacter()->getUser()->getRestricted()) {
			return array("name"=>"recruit.name", "elements"=>array(array("name"=>"recruit.all", "description"=>"unavailable.restricted")));
		}
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"recruit.name", "description"=>"unavailable.npc");
		}
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (!$settlement) {
			$actions[] = array("name"=>"recruit.all", "description"=>"unavailable.notinside");
		} else {
			if ($this->pm->checkSettlementPermission($settlement, $this->getCharacter(), 'recruit', false)) {
				$actions[] = $this->unitNewTest();
				$actions[] = $this->personalEntourageTest();
				$actions[] = $this->unitRecruitTest(); #This page handles recruiting.
			} else {
				$actions[] = array("name"=>"recruit.all", "description"=>"unavailable.notyours");
			}
		}

		$actions[] = $this->personalAssignedUnitsTest();

		return array("name"=>"recruit.name", "elements"=>$actions);
	}

	/* ========== Unit Tests ========== */

	public function personalEntourageTest() {
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->recruitActionsGenericTests($settlement)) !== true) {
			return array("name"=>"recruit.entourage.name", "description"=>"unavailable.$check");
		}

		return $this->action("recruit.entourage", "bm2_site_actions_entourage");
	}

	public function personalAssignedUnitsTest() {
		# No restrictions on this page, yet.
		return $this->action("unit.list", "maf_units");
	}

	public function unitInfoTest() {
		# No restrictions on this page, yet.
		return $this->action("unit.info", "maf_units_info");
	}

	public function unitNewTest() {
		$character = $this->getCharacter();
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->recruitActionsGenericTests($settlement)) !== true) {
			return array("name"=>"unit.new.name", "description"=>"unavailable.$check");
		}
		if (!$this->pm->checkSettlementPermission($settlement, $character, 'units')) {
			return array("name"=>"unit.new.name", "description"=>"unavailable.notyours2");
		}

		return $this->action("unit.new", "maf_unit_new");
	}

	public function unitAssignTest($ignored, Unit $unit) {
		$character = $this->getCharacter();
		$settlement = $unit->getSettlement();
		if ($unit->getTravelDays() > 0) {
			return array("name"=>"unit.assign.name", "description"=>"unavailable.rebasing");
		}
		# TODO: Break this function into a separate unitAssignTest and unitSettingsTest. Combining them like this is going to be a problem eventually.
		if (!$character->getUnits()->contains($unit)) {
			if($settlement && (!$this->pm->checkSettlementPermission($settlement, $character, 'units') && $unit->getMarshal() != $character)) {
				if($unit->getSettlement() != $character->getInsideSettlement()) {
					return array("name"=>"unit.assign.name", "description"=>"unavailable.notinside");
				}
				return array("name"=>"unit.assign.name", "description"=>"unavailable.notmarshal");
			}
		} elseif ($unit->getCharacter() != $character) {
			return array("name"=>"unit.assign.name", "description"=>"unavailable.notyourunit");
		}
		return $this->action("unit.assign.name", "maf_unit_manage");
	}

	public function unitManageTest($ignored, Unit $unit) {
		$character = $this->getCharacter();
		$settlement = $unit->getSettlement();
		# TODO: Break this function into a separate unitAssignTest and unitSettingsTest. Combining them like this is going to be a problem eventually.
		if (!$character->getUnits()->contains($unit)) {
			if($settlement && (!$this->pm->checkSettlementPermission($settlement, $character, 'units') && $unit->getMarshal() != $character)) {
				if($unit->getSettlement() != $character->getInsideSettlement()) {
					return array("name"=>"unit.manage.name", "description"=>"unavailable.notinside");
				}
				return array("name"=>"unit.manage.name", "description"=>"unavailable.notmarshal");
			}
		} elseif ($unit->getCharacter() != $character) {
			return array("name"=>"unit.manage.name", "description"=>"unavailable.notyourunit");
		}
		return $this->action("unit.manage.name", "maf_unit_manage");
	}

	public function unitRebaseTest($ignored, Unit $unit) {
		$character = $this->getCharacter();
		$settlement = $this->getCharacter()->getInsideSettlement();
		if($unit->getDisbanded()) {
			return array("name"=>"unit.rebase.name", "description"=>"unavailable.unitdisbanded");
		}
		if($unit->getSettlement() && !$this->pm->checkSettlementPermission($unit->getSettlement(), $character, 'units')) {
			return array("name"=>"unit.rebase.name", "description"=>"unavailable.notowner");
		}
		if(!$settlement) {
			return array("name"=>"unit.rebase.name", "description"=>"unavailable.notinside");
		}
		if ($unit->getTravelDays() > 0) {
			return array("name"=>"unit.rebase.name", "description"=>"unavailable.rebasing");
		}
		return $this->action("unit.rebase.name", "maf_unit_rebase");
	}

	public function unitAppointTest($ignored, Unit $unit) {
		$character = $this->getCharacter();
		$settlement = $unit->getSettlement();

		if($settlement && !$this->pm->checkSettlementPermission($settlement, $character, 'units') && $unit->getSettlement() != $character->getInsideSettlement()) {
			return array("name"=>"unit.appoint.name", "description"=>"unavailable.notlord");
		} elseif(!$settlement) {
			return array("name"=>"unit.appoint.name", "description"=>"unavailable.notinside");
		}
		return $this->action("unit.appoint.name", "maf_unit_appoint");
	}

	public function unitSoldiersTest($ignored, Unit $unit) {
		$settlement = $this->getCharacter()->getInsideSettlement();
		$character = $this->getCharacter();

		if ($unit->getTravelDays() > 0) {
			return array("name"=>"unit.soldiers.name", "description"=>"unavailable.rebasing");
		}
		if (
			$unit->getCharacter() === $character
			|| ($unit->getSettlement() && ($unit->getSettlement()->getOwner() === $character || $unit->getSettlement()->getSteward() === $character || $unit->getMarshal() === $character || $this->pm->checkSettlementPermission($unit->getSettlement(), $character, 'recruit')))
		) {
			return $this->action("unit.soldiers", "maf_unit_soldiers");
		} else {
			return array("name"=>"unit.soldiers.name", "description"=>"unavailable.notyourunit");
		}
	}

	public function unitRecruitTest() {
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->recruitActionsGenericTests($settlement)) !== true) {
			return array("name"=>"recruit.troops.name", "description"=>"unavailable.$check");
		}
		$available = $this->milman->findAvailableEquipment($settlement, true);
		if (empty($available)) {
			return array("name"=>"recruit.troops.name", "description"=>"unavailable.notrain");
		}
		return $this->action("recruit.troops", "maf_recruit");
	}

	public function unitCancelTrainingTest($ignored, Unit $unit) {
		$character = $this->getCharacter();
		$settlement = $unit->getSettlement();
		if (($check = $this->recruitActionsGenericTests($settlement)) !== true) {
			return array("name"=>"unit.canceltraining.name", "description"=>"unavailable.$check");
		}
		if (!$character->getUnits()->contains($unit)) {
			if ($unit->getSettlement() != $character->getInsideSettlement()) {
				return array("name"=>"unit.canceltraining.name", "description"=>"unavailable.notinside");
			}
		}
		if ($unit->getTravelDays() > 0) {
			return array("name"=>"unit.canceltraining.name", "description"=>"unavailable.rebasing");
		}
		return $this->action("unit.canceltraining.name", "maf_unit_cancel_training");
	}

	public function unitDisbandTest($ignored, Unit $unit) {
		$character = $this->getCharacter();
		$settlement = $unit->getSettlement();
		if ($settlement) {
			$permission = $this->pm->checkSettlementPermission($settlement, $character, 'units');
			if ($unit->getCharacter()) {
				return array("name"=>"unit.disband.name", "description"=>"unavailable.recallfirst");
			}
			if ($settlement && !$character->getUnits()->contains($unit)) {
				if(!$character->getInsideSettlement() || $settlement != $character->getInsideSettlement()) {
					return array("name"=>"unit.disband.name", "description"=>"unavailable.notinside");
				} elseif($settlement && !$permission) {
					return array("name"=>"unit.disband.name", "description"=>"unavailable.notlord");
				}
			}
		}
		if ($unit->getSoldiers()->count() > 0) {
			return array("name"=>"unit.disband.name", "description"=>"unavailable.hassoldiers");
		}
		if ($unit->getTravelDays() > 0) {
			return array("name"=>"unit.disband.name", "description"=>"unavailable.rebasing");
		}
		return $this->action("unit.disband.name", "maf_unit_disband");
	}

	public function unitReturnTest($ignored, Unit $unit) {
		$character = $this->getCharacter();
		if (!$character->getUnits()->contains($unit)) {
			return array("name"=>"unit.return.name", "description"=>"unavailable.notassigned");
		}
		if (!$unit->getSettlement()) {
			return array("name"=>"unit.return.name", "description"=>"unavailable.nobase");
		}
		if ($character->isInBattle()) {
			return array("name"=>"unit.return.all", "description"=>"unavailable.inbattle2");
		}
		return $this->action("unit.return.name", "maf_unit_return");
	}

	public function unitRecallTest($ignored, Unit $unit) {
		$character = $this->getCharacter();
		$settlement = $unit->getSettlement();
		if (!$settlement) {
			return array("name"=>"unit.recall.name", "description"=>"unavailable.notinside");
		}
		if ($unit->getSettlement() !== $settlement) {
			return array("name"=>"unit.recall.name", "description"=>"unavailable.notyourunit");
		}
		if (!$this->pm->checkSettlementPermission($settlement, $character, 'units') && $unit->getMarshal() != $character) {
			return array("name"=>"unit.recall.name", "description"=>"unavailable.notyours");
		}
		if ($unit->getCharacter()->isInBattle()) {
			return array("name"=>"unit.return.all", "description"=>"unavailable.inbattle2");
		}

		if ($unit->getTravelDays() > 0) {
			return array("name"=>"unit.recall.name", "description"=>"unavailable.rebasing");
		}
		return $this->action("unit.recall.name", "maf_unit_recall");
	}

}
