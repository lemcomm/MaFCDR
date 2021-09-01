<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Conversation;
use BM2\SiteBundle\Entity\House;
use BM2\SiteBundle\Entity\Message;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Unit;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


/*
TODO:
refactor to use $this->action() everywhere (with some exceptions where it doesn't work)
*/

class Dispatcher {

	const FREE_ACCOUNT_ESTATE_LIMIT = 3;

	private $character;
	private $realm;
	private $house;
	private $settlement;
	private $appstate;
	private $permission_manager;
	private $geography;
	private $milman;
	private $interactions;

	// test results to store because they are expensive to calculate
	private $actionableSettlement=false;
	private $actionablePlace=false;
	private $actionableRegion=false;
	private $actionableDock=false;
	private $actionableShip=false;
	private $actionableHouses=false;

	public function __construct(AppState $appstate, PermissionManager $pm, Geography $geo, MilitaryManager $milman, Interactions $interactions) {
		$this->appstate = $appstate;
		$this->permission_manager = $pm;
		$this->geography = $geo;
		$this->milman = $milman;
		$this->interactions = $interactions;
	}

	public function getCharacter() {
		if ($this->character) {
			$result = $this->character;
		} else {
			$result = $this->appstate->getCharacter();
		}
		if ($result instanceof Character) {
			#Set the character's house, if it exists.
			if ($result->getHouse()) {
				$this->setHouse($result->getHouse());
			}
		}
		return $result;
	}

	public function setCharacter(Character $character) {
		$this->clear();
		$this->character = $character;
	}
	public function setRealm(Realm $realm) {
		$this->realm = $realm;
	}
	public function setSettlement(Settlement $settlement) {
		$this->settlement = $settlement;
	}
	public function setHouse(House $house) {
		$this->house = $house;
	}

	public function clear() {
		$this->character=false;
		$this->realm=false;
		$this->actionableSettlement=false;
		$this->actionablePlace=false;
		$this->actionableDock=false;
		$this->actionableShip=false;
		$this->actionableHouses=false;
	}

	/*
		this is our main entrance, fetching the character data from the appstate as well as the nearest settlement
		and then applying any (optional) test on the whole thing.
	*/
	public function gateway($test=false, $getSettlement=false, $check_duplicate=true, $getPlace=false, $option=null) {
		$character = $this->getCharacter();
		if (!$character || ! $character instanceof Character) {
			/* Yes, if it's not a character, we return it. We check this on the other side again, and redirect if it's not a character.
			Would it make more sense to just redirect here? Probably. Symfony doesn't work that way though.
			Services, like Dispatcher, do logic, not interaction. Redirection, though, is distinctly interactive.
			When Dispatcher calls AppState to get the character, it adds a flash message explaining why it's not returning a character.
			That flash will then generate on the route the calling Controller will redirect to, explaining to the user what's going on.*/
			if ($getSettlement) {
				if (!$getPlace) {
					return array($character, null); #Most common first.
				} else {
					return array($character, null, null);
				}
			} else {
				return $character;
			}
		}
		$settlement = null;
		$place = null;
		if ($test) {
			$test = $this->$test($check_duplicate, $option);
			if (!isset($test['url'])) {
				throw new AccessDeniedHttpException("messages::unavailable.intro::".$test['description']);
			}
		}
		if ($getSettlement) {
			$settlement = $this->getActionableSettlement();
			if ($getPlace) {
				$place = $this->geography->findNearestActionablePlace($character);
				return array($character, $settlement, $place);
			} else {
				return array($character, $settlement);
			}
		} else {
			if ($getPlace) {
				return [$character, $place];
			}
			return $character;
		}
	}

	private function veryGenericTests() {
		if ($this->getCharacter()->getUser()->getRestricted()) {
			return 'restricted';
		}
		if ($this->getCharacter()->isNPC()) {
			return 'npc';
		}
		return true;
	}


	/* ========== Local Action Dispatchers ========== */

	public function interActions() {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"location.title", "elements"=>array(array("name"=>"location.all", "description"=>"unavailable.$check")));
		}

		$actions=array();

		if ($this->getLeaveableSettlement()) {
			$actions[] = $this->locationLeaveTest(true);
		} else if ($settlement = $this->getActionableSettlement()) {
			$actions[] = $this->locationEnterTest(true);
		} else {
			$actions[] = array("name"=>"location.enter.name", "description"=>"unavailable.nosettlement");
		}

		if ($this->getLeaveablePlace()) {
			$actions[] = $this->placeLeaveTest(true);
		}
		$actions[] = $this->placeListTest();
		$actions[] = $this->placeCreateTest();

		$actions[] = $this->locationQuestsTest();
		$actions[] = $this->locationEmbarkTest();

		// these actions are hidden if not available
		$has = $this->locationGiveShipTest();
		if (isset($has['url'])) {
			$actions[] = $has;
		}

		$actions[] = $this->locationGiveGoldTest();
		$has = $this->locationGiveArtifactTest();
		if (isset($has['url'])) {
			$actions[] = $has;
		}

		$has = $this->personalSurrenderTest();
		if (isset($has['url'])) {
			$actions[] = $has;
		}
		$has = $this->personalEscapeTest();
		if (isset($has['url'])) {
			$actions[] = $has;
		}

		$spy = $this->nearbySpyTest(true);
		if (isset($spy['url'])) {
			$actions[] = $spy;
		}
		$has = $this->locationDungeonsTest();
		if (isset($has['url'])) {
			$actions[] = $has;
		} else {
			$has = $this->personalPartyTest();
			if (isset($has['url'])) {
				$actions[] = $has;
			} else {
				$has = $this->personalDungeoncardsTest();
				if (isset($has['url'])) {
					$actions[] = $has;
				}
			}
		}

		$actions[] = $this->locationMarkersTest();

		return array("name"=>"location.title", "elements"=>$actions);
	}

	private function interActionsGenericTests() {
		if ($this->getCharacter()->getUser()->getRestricted()) {
			return 'restricted';
		}
		return true;
	}

	/* ========== Building Action Dispatchers ========== */

	public function buildingActions() {
		if (($check = $this->veryGenericTests()) !== true) {
			return array("name"=>"building.title", "elements"=>array(array("name"=>"building.all", "description"=>"unavailable.$check")));
		}

		$actions=array();
		$has = $this->locationTavernTest();
		if (isset($has['url'])) {
			$actions[] = $has;
		}
		$has = $this->locationLibraryTest();
		if (isset($has['url'])) {
			$actions[] = $has;
		}
		$has = $this->locationTempleTest();
		if (isset($has['url'])) {
			$actions[] = $has;
		}
		$has = $this->locationBarracksTest();
		if (isset($has['url'])) {
			$actions[] = $has;
		}

		return array("name"=>"building.title", "elements"=>$actions);
	}

	public function locationTavernTest() { return $this->locationHasBuildingTest("Tavern"); }
	public function locationLibraryTest() { return $this->locationHasBuildingTest("Library"); }
	public function locationTempleTest() { return $this->locationHasBuildingTest("Temple"); }
	public function locationBarracksTest() { return $this->locationHasBuildingTest("Barracks"); }

	public function locationHasBuildingTest($name) {
		$lname = strtolower($name);
		if (($check = $this->veryGenericTests()) !== true) {
			return array("name"=>"building.$lname.name", "description"=>"unavailable.$check");
		}
		if (!$this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"building.$lname.name", "description"=>"unavailable.notinside");
		}
		if (!$this->getCharacter()->getInsideSettlement()->hasBuildingNamed($name)) {
			return array("name"=>"building.$lname.name", "description"=>"unavailable.building.$lname");
		}

		return $this->action("building.$lname", "bm2_site_building_$lname");
	}



	public function controlActions() {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.name", "elements"=>array(array("name"=>"control.all", "description"=>"unavailable.$check")));
		}
		$char = $this->getCharacter();
		$settlement = $char->getInsideSettlement();
		$actions=array();

		if (!$settlement) {
			$actions[] = array("name"=>"control.all", "description"=>"unavailable.notinside");
		} else {
			$actions[] = $this->controlTakeTest(true);
			if ($settlement->getOccupant() == $char) {
				$actions[] = $this->controlOccupationEndTest(true);
				$actions[] = $this->controlChangeOccupantTest(true);
				$actions[] = $this->controlChangeOccupierTest(true);
			}
			$actions[] = $this->controlChangeRealmTest(true, $settlement);
			$actions[] = $this->controlSettlementDescriptionTest(null, $settlement);
			$actions[] = $this->controlGrantTest(true);
			$actions[] = $this->controlRenameTest(true);
			$actions[] = $this->controlCultureTest(true);
			$actions[] = $this->controlStewardTest(true);
			$actions[] = $this->controlPermissionsTest(null, $settlement);
			$actions[] = $this->controlQuestsTest(null, $settlement);
		}

		return array("name"=>"control.name", "elements"=>$actions);
	}

	private function controlActionsGenericTests() {
		if (!$settlement = $this->getActionableSettlement()) {
			return 'notinside';
		}
		return $this->veryGenericTests();
	}


	public function militaryActions() {
		$actions=array();
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.name", "elements"=>array(array("name"=>"military.all", "description"=>"unavailable.prisoner")));
		}

		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.name", "elements"=>array(
				$this->militaryDisengageTest(true),
				$this->militaryEvadeTest(true),
				array("name"=>"military.all", "description"=>"unavailable.inbattle")
			));
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			return array("name"=>"military.name", "elements"=>array(array("name"=>"military.all", "description"=>"unavailable.nosoldiers")));
		}
		if ($this->getCharacter()->getUser()->getRestricted()) {
			return array("name"=>"military.name", "elements"=>array(array("name"=>"military.all", "description"=>"unavailable.restricted")));
		}
		$actions[] = $this->militaryAttackNoblesTest();
		$actions[] = $this->militaryAidTest();
		$actions[] = $this->militaryJoinBattleTest();
		$actions[] = $this->militaryBlockTest();
		$actions[] = $this->militaryEvadeTest(true);

		$actions[] = $this->militaryDamageFeatureTest(true);
		$actions[] = $this->militaryLootSettlementTest(true);
		if ($settlement = $this->getActionableSettlement()) {
			$actions[] = $this->militaryDefendSettlementTest();
			if (!$settlement->getSiege()) {
				$actions[] = $this->militarySiegeSettlementTest();
			} else {
				$actions[] = $this->militarySiegeJoinSiegeTest();
			}
		} else {
			$actions[] = array("name"=>"military.other", "description"=>"unavailable.nosettlement");
		}

		return array("name"=>"military.name", "elements"=>$actions);
	}

	public function siegeActions() {
		$actions=array();
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.name", "elements"=>array(array("name"=>"military.all", "description"=>"unavailable.prisoner")));
		}

		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.name", "elements"=>array(
				$this->militaryDisengageTest(true),
				$this->militaryEvadeTest(true),
				array("name"=>"military.all", "description"=>"unavailable.inbattle")
			));
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			return array("name"=>"military.name", "elements"=>array(array("name"=>"military.all", "description"=>"unavailable.nosoldiers")));
		}
		if ($this->getCharacter()->getUser()->getRestricted()) {
			return array("name"=>"military.name", "elements"=>array(array("name"=>"military.all", "description"=>"unavailable.restricted")));
		}
		if ($settlement = $this->getActionableSettlement()) {
			if (!$siege = $settlement->getSiege()) {
				$actions[] = $this->militarySiegeSettlementTest();
			} else {
				$actions[] = $this->militarySiegeLeadershipTest(null, $siege);
				$actions[] = $this->militarySiegeAssumeTest(null, $siege);
				$actions[] = $this->militarySiegeBuildTest(null, $siege);
				$actions[] = $this->militarySiegeAssaultTest(null, $siege);
				$actions[] = $this->militarySiegeDisbandTest(null, $siege);
				$actions[] = $this->militarySiegeLeaveTest(null, $siege);
				#$actions[] = $this->militarySiegeAttackTest(null, $siege);
				#$actions[] = $this->militarySiegeJoinAttackTest(null, $siege);
			}
		}

		$actions[] = $this->militaryLootSettlementTest(true);

		return array("name"=>"military.siege.name", "elements"=>$actions);
	}

	public function economyActions() {
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->economyActionsGenericTests($settlement)) !== true) {
			return array("name"=>"economy.name", "elements"=>array(array("name"=>"economy.all", "description"=>"unavailable.$check")));
		}

		$actions=array();
		$actions[] = $this->economyTradeTest();

		if ($this->permission_manager->checkSettlementPermission($settlement, $this->getCharacter(), 'construct')) {
			$actions[] = $this->economyRoadsTest();
			$actions[] = $this->economyFeaturesTest();
			$actions[] = $this->economyBuildingsTest();
		} else {
			$actions[] = array("name"=>"economy.others", "description"=>"unavailable.notyours");
		}


		return array("name"=>"economy.name", "elements"=>$actions);
	}

	private function economyActionsGenericTests(Settlement $settlement=null) {
		if (!$settlement) {
			return 'notinside';
		}
		return $this->veryGenericTests();
	}

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
			if ($settlement->getOccupier()) {
				$occupied = true;
			} else {
				$occupied = false;
			}
			if ($this->permission_manager->checkSettlementPermission($settlement, $this->getCharacter(), 'recruit', false, $occupied)) {
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

	public function personalActions() {
		$actions=array();

		if ($this->getCharacter()->isNPC()) {
			$actions[] = $this->metaKillTest();
		} else {
			$actions[] = $this->personalRequestsManageTest();
			$actions[] = $this->personalRequestSoldierFoodTest();
			if ($this->getCharacter()->getUser()->getCrests()) {
				$actions[] = $this->metaHeraldryTest();
			}
		}
		return array("name"=>"personal.name", "elements"=>$actions);
	}

	private function recruitActionsGenericTests(Settlement $settlement=null, $test='recruit') {
		if ($this->getCharacter()->isNPC()) {
			return 'npc';
		}
		if (!$settlement) {
			return 'notinside';
		}
		if (!$this->permission_manager->checkSettlementPermission($settlement, $this->getCharacter(), $test)) {
			if ($test == 'recruit' && $this->permission_manager->checkSettlementPermission($settlement, $this->getCharacter(), 'units')) {
				return $this->veryGenericTests();
			} else {
				return 'notyours';
			}
		}

		return $this->veryGenericTests();
	}

	/* ========== Politics Dispatchers ========== */

	public function RelationsActions() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"relations.name", "intro"=>"relations.intro", "elements"=>array("name"=>"relations.all", "description"=>"unavailable.npc"));
		}

		$actions=array();

		if ($this->getCharacter()->findAllegiance()) {
			$actions[] = array("name"=>"oath.view.name", "url"=>"bm2_site_politics_hierarchy", "description"=>"oath.view.description", "long"=>"oath.view.longdesc");
		}
		if ($this->getCharacter()->findVassals()) {
			$actions[] = array("name"=>"vassals.view.name", "url"=>"bm2_site_politics_vassals", "description"=>"vassals.view.description", "long"=>"vassals.view.longdesc");
		}
		$actions[] = $this->hierarchyOathTest();
		$actions[] = $this->hierarchyIndependenceTest();

		return array("name"=>"relations.name", "intro"=>"relations.intro", "elements"=>$actions);
	}

	public function PoliticsActions() {
		$actions=array();
		$actions[] = $this->personalRelationsTest();
		$actions[] = $this->personalPrisonersTest();
		$actions[] = $this->personalClaimsTest();
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			$actions[] = array("name"=>"politics.all", "description"=>"unavailable.$check");
			return array("name"=>"politics.name", "intro"=>"politics.intro", "elements"=>$actions);
		}

		$actions[] = $this->hierarchyCreateRealmTest();
		$actions[] = $this->houseCreateHouseTest();
		$house = $this->house;
		if ($house) {
			$actions[] = array("title"=>$house->getName());
			$actions[] = array("name"=>"house.view.name", "url"=>"maf_house", "parameters"=>array("id"=>$this->house->getId()), "description"=>"house.view.description", "long"=>"house.view.longdesc");
			if (!$house->getActive()) {
				$actions[] = $this->houseManageReviveTest();
			} elseif ($house->getHead() == $this->getCharacter()) {
				$actions[] = $this->houseManageHouseTest();
				$actions[] = $this->houseManageRelocateTest();
				$actions[] = $this->houseManageApplicantsTest();
				$actions[] = $this->houseManageDisownTest();
				$actions[] = $this->houseManageSuccessorTest();
				if ($house->getSuperior()) {
					$actions[] = $this->houseManageUncadetTest();
				}
				$actions[] = $this->houseNewPlayerInfoTest();
				$actions[] = $this->houseSpawnToggleTest();
			}
		}

		return array("name"=>"politics.name", "intro"=>"politics.intro", "elements"=>$actions);
	}

	public function politicsRealmsActions() {
		$actions=array();
		$actions[] = $this->personalRelationsTest();
		$actions[] = $this->personalPrisonersTest();
		$actions[] = $this->personalClaimsTest();
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			$actions[] = array("name"=>"politics.all", "description"=>"unavailable.$check");
			return array("name"=>"politics.name", "intro"=>"politics.intro", "elements"=>$actions);
		}

		$actions[] = $this->hierarchyCreateRealmTest();
		$actions[] = $this->houseCreateHouseTest();
		foreach ($this->getCharacter()->findRealms() as $realm) {
			$this->setRealm($realm);
			$actions[] = array("title"=>$realm->getFormalName());
			$actions[] = array("name"=>"realm.view.name", "url"=>"bm2_site_realm_hierarchy", "parameters"=>array("realm"=>$realm->getId()), "description"=>"realm.view.description", "long"=>"realm.view.longdesc");
			$actions[] = $this->hierarchyElectionsTest();
			if ($realm->findRulers()->contains($this->getCharacter())) {
				# NOTE: We'll have to rework this later when othe positions can manage a realm.
				$actions[] = $this->hierarchyManageRealmTest();
				$actions[] = $this->hierarchyManageDescriptionTest();
				$actions[] = $this->hierarchySelectCapitalTest();
				$actions[] = $this->hierarchyNewPlayerInfoTest();
				$actions[] = $this->hierarchyRealmSpawnsTest();
				$actions[] = $this->hierarchyAbdicateTest();
				$actions[] = $this->hierarchyRealmPositionsTest();
				$actions[] = $this->hierarchyRealmLawsTest();
				$actions[] = $this->hierarchyWarTest();
				$actions[] = $this->hierarchyDiplomacyTest();
				$actions[] = $this->hierarchyAbolishRealmTest();
			}
		}

		return array("name"=>"politics.name", "intro"=>"politics.intro", "elements"=>$actions);
	}


	private function politicsActionsGenericTests() {
		return $this->veryGenericTests();
	}


	public function DiplomacyActions() {
		$actions=array();

		$actions[] = $this->diplomacyRelationsTest();
		$actions[] = $this->diplomacyHierarchyTest();
		$actions[] = $this->diplomacySubrealmTest();
		$actions[] = $this->diplomacyBreakHierarchyTest();
		$actions[] = $this->diplomacyRestoreTest();

		return array("name"=>"diplomacy", "elements"=>$actions);
	}

	public function InheritanceActions() {
		$actions=array();

		$actions[] = $this->inheritanceSuccessorTest();

		return array("name"=>"inheritance", "elements"=>$actions);
	}

	/* ========== Place Dispatchers ========= */

	public function PlacesActions() {
		$actions=array();
		if (($check = $this->placesActionsGenericTests()) !== true) {
			$actions[] = array("name"=>"place.all", "description"=>"unavailable.$check");
			return array("name"=>"place.name", "intro"=>"politics.intro", "elements"=>$actions);
		}
		$actions[] = $this->placeCreateTest();

		foreach ($this->geo->findPlacesInActionRange($this->getCharacter()) as $place) {
			$this->setPlace($place);
			$actions[] = array("title"=>$place->getFormalName());
			$actions[] = array("name"=>"place.view.name", "url"=>"bm2_site_place_view", "parameters"=>array("id"=>$place->getId()), "description"=>"place.view.description", "long"=>"place.view.longdesc");
			$actions[] = $this->placeManageTest();
			$actions[] = $this->placeEnterTest();
		}

		return array("name"=>"place.name", "intro"=>"place.intro", "elements"=>$actions);
	}

	private function placeActionsGenericTests(Place $place=null) {
		if ($this->getCharacter()->getUser()->getRestricted()) {
			return 'restricted';
		}
		if ($this->getCharacter()->isNPC()) {
			return 'npc';
		}

		return $this->veryGenericTests();
	}

	/* ========== Meta Dispatchers ========== */

	public function metaActions() {
		$actions=array();

		if ($this->getCharacter()->isNPC()) {
			# $actions[] = $this->metaUnitSettingsTest();
			$actions[] = $this->metaKillTest();
		} else {
			# $actions[] = $this->metaUnitSettingsTest();
			$actions[] = $this->metaBackgroundTest();
			if ($this->getCharacter()->getUser()->getCrests()) {
				$actions[] = $this->metaHeraldryTest();
			}
			$actions[] = $this->metaLoadoutTest();
			$actions[] = $this->metaSettingsTest();
			$actions[] = $this->metaRenameTest();
			$actions[] = $this->metaRetireTest();
			$actions[] = $this->metaKillTest();
		}

		return array("name"=>"meta.name", "elements"=>$actions);
	}


	/* ========== Interaction Actions ========== */

	public function locationMarkersTest($check_duplicate=false) {
		$myrealms = $this->getCharacter()->findRealms();
		if ($myrealms->isEmpty()) {
			return array("name"=>"location.marker.name", "description"=>"unavailable.norealms");
		}
		return $this->action("location.marker", "bm2_setmarker");
	}

	public function locationEnterTest($check_duplicate=false) {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"location.enter.name", "description"=>"unavailable.$check");
		}
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"location.enter.name", "description"=>"unavailable.npc");
		}
		if ($this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"location.enter.name", "description"=>"unavailable.inside");
		}
		$settlement = $this->getActionableSettlement();
		if (!$settlement) {
			return array("name"=>"location.enter.name", "description"=>"unavailable.nosettlement");
		}
		if ($settlement->getSiege() && $settlement->getSiege()->getEncircled()) {
			return array("name"=>"location.enter.name", "description"=>"unavailable.besieged");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.enter')) {
			return array("name"=>"location.enter.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"location.enter.name", "description"=>"unavailable.inbattle");
		}
		if ($settlement->getOccupier()) {
			$occupied = true;
		} else {
			$occupied = false;
		}
		if ($settlement->isFortified() && !$this->permission_manager->checkSettlementPermission($settlement, $this->getCharacter(), 'visit', false, $occupied)) {
			return array("name"=>"location.enter.name", "description"=>"unavailable.nopermission");
		}

		if ($this->getCharacter()->isPrisoner()) {
			if ($settlement->getOwner() == $this->getCharacter()) {
				# Delierately no stewards.
				return array("name"=>"location.enter.name", "url"=>"bm2_site_actions_enter", "description"=>"location.enter.description2");
			} else {
				return array("name"=>"location.enter.name", "description"=>"unavailable.enter.notyours");
			}
		} else {
			return $this->action("location.enter", "bm2_site_actions_enter");
		}

	}

	public function locationLeaveTest($check_duplicate=false) {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"location.exit.name", "description"=>"unavailable.$check");
		}
		if (!$this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"location.exit.name", "description"=>"unavailable.outside");
		}
		if (!$settlement = $this->getActionableSettlement()) {
			return array("name"=>"location.exit.name", "description"=>"unavailable.nosettlement");
		}
		if ($settlement->getSiege() && $settlement->getSiege()->getEncircled()) {
			return array("name"=>"location.exit.name", "description"=>"unavailable.besieged");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.exit')) {
			return array("name"=>"location.exit.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"location.exit.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"location.exit.name", "description"=>"unavailable.prisoner");
		} else {
			return $this->action("location.exit", "bm2_site_actions_exit");
		}
	}

	public function locationQuestsTest() {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"location.quests.name", "description"=>"unavailable.$check");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"location.quests.name", "description"=>"unavailable.prisoner");
		}
		if (!$geo = $this->getActionableRegion()) {
			return array("name"=>"location.quests.name", "description"=>"unavailable.noregion");
		}
		$settlement = $geo->getSettlement();

		return array("name"=>"location.quests.name", "url"=>"bm2_site_quests_localquests", "description"=>"location.quests.description", "long"=>"location.quests.longdesc");
	}

	public function locationEmbarkTest() {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"location.embark.name", "description"=>"unavailable.$check");
		}
		if ($this->getCharacter()->getTravelAtSea() == true) {
			return array("name"=>"location.embark.name", "description"=>"unavailable.atsea");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"location.embark.name", "description"=>"unavailable.prisoner");
		}
		$dock = $this->getActionableDock();
		if ($dock) {
			if ( $this->permission_manager->checkSettlementPermission($dock->getGeoData()->getSettlement(), $this->getCharacter(), 'docks')) {
				return array("name"=>"location.embark.name", "url"=>"bm2_site_actions_embark", "description"=>"location.embark.description", "long"=>"location.embark.longdesc");
			}
		}

		// no dock, check for ship
		$ship = $this->getActionableShip();
		if ($ship) {
			return array("name"=>"location.embark.name", "url"=>"bm2_site_actions_embark", "description"=>"location.embark.description2", "long"=>"location.embark.longdesc2");
		}

		if ($dock) {
			return array("name"=>"location.embark.name", "description"=>"unavailable.notyours");
		} else {
			return array("name"=>"location.embark.name", "description"=>"unavailable.nodock");
		}
	}

	public function locationGiveGoldTest() {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"location.givegold.name", "description"=>"unavailable.$check");
		}
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"location.givegold.name", "description"=>"unavailable.npc");
		}
		if (!$this->getActionableCharacters()) {
			return array("name"=>"location.givegold.name", "description"=>"unavailable.nobody");
		}
		return array("name"=>"location.givegold.name", "url"=>"bm2_site_actions_givegold", "description"=>"location.givegold.description");
	}

	public function locationGiveArtifactTest() {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"location.giveartifact.name", "description"=>"unavailable.$check");
		}
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"location.giveartifact.name", "description"=>"unavailable.npc");
		}
		if (!$this->getActionableCharacters()) {
			return array("name"=>"location.giveartifact.name", "description"=>"unavailable.nobody");
		}
		if ($this->getCharacter()->getArtifacts()->isEmpty()) {
			return array("name"=>"location.giveartifact.name", "description"=>"unavailable.noartifacts");
		}
		return array("name"=>"location.giveartifact.name", "url"=>"bm2_site_artifacts_give", "description"=>"location.giveartifact.description");
	}

	public function locationGiveShipTest() {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"location.giveship.name", "description"=>"unavailable.$check");
		}
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"location.giveship.name", "description"=>"unavailable.npc");
		}
		$ship = $this->getActionableShip();
		if (!$ship) {
			return array("name"=>"location.giveship.name", "description"=>"unavailable.noship");
		}
		return array("name"=>"location.giveship.name", "url"=>"bm2_site_actions_giveship", "description"=>"location.giveship.description", "long"=>"location.giveship.longdesc");
	}

	public function locationDungeonsTest() {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"location.dungeons.name", "description"=>"unavailable.$check");
		}
		if ($this->getCharacter()->getDungeoneer() && $this->getCharacter()->getDungeoneer()->getParty()) {
			return array("name"=>"location.dungeons.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"location.dungeons.name", "description"=>"unavailable.npc");
		}
		if ($this->getCharacter()->getTravelAtSea() == true) {
			return array("name"=>"location.dungeons.name", "description"=>"unavailable.atsea");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"location.dungeons.name", "description"=>"unavailable.prisoner");
		}
		if ($this->getCharacter()->isDoingAction('dungeon.explore')) {
			return array("name"=>"location.dungeons.name", "description"=>"unavailable.already");
		}
		$dungeons = $this->geography->findDungeonsInActionRange($this->getCharacter());
		if (!$dungeons) {
			return array("name"=>"location.dungeons.name", "description"=>"unavailable.nodungeons");
		}
		return $this->action("location.dungeons", "bm2_dungeons");
	}

	public function locationVisitHousesTest() {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"location.houses.name", "description"=>"unavailable.$check");
		}
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"location.houses.name", "description"=>"unavailable.npc");
		}
		$houses = $this->getActionableHouses();
		if (!$houses) {
			return array("name"=>"location.houses.name", "description"=>"unavaibable.nohouses");
		}
		return array("name"=>"location.houses.name", "url"=>"maf_house_nearby", "description"=>"location.houses.description");
	}

	public function personalPartyTest() {
		if (!$this->getCharacter()->getDungeoneer() || !$this->getCharacter()->getDungeoneer()->getParty()) {
			return array("name"=>"personal.party.name", "description"=>"unavailable.noparty");
		}
		return $this->action("personal.party", "dungeons_party");
	}

	public function personalDungeoncardsTest() {
		if (!$this->getCharacter()->getDungeoneer()) {
			return array("name"=>"personal.party.name", "description"=>"unavailable.nocards");
		}
		return $this->action("personal.dungeoncards", "dungeons_cards");
	}


	public function nearbySpyTest($check_duplicate=false) {
		if (!$settlement = $this->getActionableSettlement()) {
			return array("name"=>"nearby.spy.name", "description"=>"unavailable.nosettlement");
		}
		if ($this->getCharacter()->getAvailableEntourageOfType("spy")->count() <= 0) {
			return array("name"=>"nearby.spy.name", "description"=>"unavailable.nospies");
		}
		return array("name"=>"nearby.spy.name", "url"=>"bm2_site_actions_spy", "description"=>"nearby.spy.description");
	}


	/* ========== Control Actions ========== */

	public function controlTakeTest($check_duplicate=false, $check_regroup=true) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.take.name", "description"=>"unavailable.$check");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"control.take.name", "description"=>"unavailable.prisoner");
		}
		if (!$settlement = $this->getActionableSettlement()) {
			return array("name"=>"control.take.name", "description"=>"unavailable.nosettlement");
		}
		if ($settlement->isFortified() && $this->getCharacter()->getInsideSettlement()!=$settlement) {
			return array("name"=>"control.take.name", "description"=>"unavailable.location.fortified");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.take')) {
			return array("name"=>"control.take.name", "description"=>"unavailable.already");
		}
		if ($check_regroup && $this->getCharacter()->isDoingAction('military.regroup')) {
			return array("name"=>"control.take.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"control.take.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->getActions()->exists(
			function($key, $element) { return ($element->getType() == 'support' && $element->getSupportedAction() && $element->getSupportedAction()->getType() == 'settlement.take'); }
		)) {
			return array("name"=>"control.take.name", "description"=>"unavailable.supporting");
		}

		if ($this->getCharacter()->isTrial() && $this->getCharacter()->getOwnedSettlements()->count() >= Dispatcher::FREE_ACCOUNT_ESTATE_LIMIT) {
			return array("name"=>"control.take.name", "description"=>"unavailable.free2");
		}

		if ($settlement->getOwner() == $this->getCharacter()) {
			// I control this settlement - defend if applicable
			if ($settlement->getRelatedActions()->exists(
				function($key, $element) { return $element->getType() == 'settlement.take'; }
			)) {
				return $this->action("control.takeX", "bm2_site_actions_take");
			} else {
				return array("name"=>"control.take.name", "description"=>"unavailable.location.yours");
			}
		} elseif ($settlement->getOwner()) {
			// someone else controls this settlement
			// TODO: different text?
			return $this->action("control.take", "bm2_site_actions_take");
		} else {
			// uncontrolled settlement
			return $this->action("control.take", "bm2_site_actions_take");
		}
	}

	/* Only implemented for testing. Starting an occupation requires a siege.
	public function controlOccupationStartTest($check_duplicate=false, $check_regroup=true) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.occupationstart.name", "description"=>"unavailable.$check");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"control.occupationstart.name", "description"=>"unavailable.prisoner");
		}
		if (!$settlement = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"control.occupationstart.name", "description"=>"unavailable.notinside");
		}
		if ($settlement->isFortified() && $this->getCharacter()->getInsideSettlement()!=$settlement) {
			return array("name"=>"control.occupationstart.name", "description"=>"unavailable.location.fortified");
		}
		if ($check_regroup && $this->getCharacter()->isDoingAction('military.regroup')) {
			return array("name"=>"control.occupationstart.name", "description"=>"unavailable.regrouping");
		}
		if ($settlement->getOwner() == $this->getCharacter()) {
			return array("name"=>"control.occupationstart.name", "description"=>"unavailable.location.yours");
		}
		if ($settlement->getOccupant()) {
			return array("name"=>"control.occupationstart.name", "description"=>"unavailable.occupied");
		}
		return $this->action("control.occupationstart", "maf_settlement_occupation_start");
	}
	*/

	public function controlOccupationEndTest($check_duplicate=false, $check_regroup=true) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.occupationend.name", "description"=>"unavailable.$check");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"control.occupationend.name", "description"=>"unavailable.prisoner");
		}
		if (!$settlement = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"control.occupationend.name", "description"=>"unavailable.notinside");
		}
		if ($settlement->isFortified() && $this->getCharacter()->getInsideSettlement()!=$settlement) {
			return array("name"=>"control.occupationend.name", "description"=>"unavailable.location.fortified");
		}
		if ($check_regroup && $this->getCharacter()->isDoingAction('military.regroup')) {
			return array("name"=>"control.occupationend.name", "description"=>"unavailable.regrouping");
		}
		if (!$settlement->getOccupant()) {
			return array("name"=>"control.occupationend.name", "description"=>"unavailable.notoccupied");
		}
		if ($settlement->getOccupant() != $this->getCharacter()) {
			return array("name"=>"control.occupationend.name", "description"=>"unavailable.notyours");
		}
		return $this->action("control.occupationend", "maf_settlement_occupation_end");
	}

	public function controlChangeRealmTest($check_duplicate=false, $settlement) {
		if (($check = $this->veryGenericTests()) !== true) {
			return array("name"=>"control.changerealm.name", "description"=>"unavailable.$check");
		}
		if (!$settlement) {
			return array("name"=>"control.changerealm.name", "description"=>"unavailable.notsettlement");
		}
		if ($settlement->getOccupier()) {
			return array("name"=>"control.changerealm.name", "description"=>"unavailable.occupied");
		}
		if ($settlement->getOwner() != $this->getCharacter()) {
			return array("name"=>"control.changerealm.name", "description"=>"unavailable.notyours2");
		}

		$myrealms = $this->getCharacter()->findRealms();
		if ($myrealms->isEmpty()) {
			return array("name"=>"control.changerealm.name", "description"=>"unavailable.norealms");
		}
		return $this->action("control.changerealm", "bm2_site_actions_changerealm", false, array('id'=>$settlement->getId()));
	}

	public function controlChangeOccupierTest($check_duplicate=false) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.changeoccupier.name", "description"=>"unavailable.$check");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.changerealm')) {
			return array("name"=>"control.changeoccupier.name", "description"=>"unavailable.already");
		}
		// FIXME: this still sometimes gives a "you are not inside" message when it shouldn't, I think?
		if ($this->settlement) {
			$settlement = $this->settlement;
		} else {
			$settlement = $this->getCharacter()->getInsideSettlement();
		}
		if (!$settlement) {
			return array("name"=>"control.changeoccupier.name", "description"=>"unavailable.notsettlement");
		}
		if (!$settlement->getOccupier()) {
			return array("name"=>"control.changeoccupier.name", "description"=>"unavailable.notoccupied");
		}
		if ($settlement->getOccupant() != $this->getCharacter()) {
			return array("name"=>"control.changeoccupier.name", "description"=>"unavailable.notyours2");
		}

		$myrealms = $this->getCharacter()->findRealms();
		if ($myrealms->isEmpty()) {
			return array("name"=>"control.changeoccupier.name", "description"=>"unavailable.norealms");
		}
		return $this->action("control.changeoccupier", "maf_settlement_occupier", false, array('id'=>$settlement->getId()));
	}

	public function controlGrantTest($check_duplicate=false) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.grant.name", "description"=>"unavailable.$check");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.grant')) {
			return array("name"=>"control.grant.name", "description"=>"unavailable.already");
		}
		if (!$settlement = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"control.grant.name", "description"=>"unavailable.nosettlement");
		}
		if ($settlement->getOccupier()) {
			return array("name"=>"control.grant.name", "description"=>"unavailable.occupied");
		}
		if ($settlement->getOwner() != $this->getCharacter()) {
			return array("name"=>"control.grant.name", "description"=>"unavailable.notyours2");
		}
		if (!$this->getActionableCharacters()) {
			return array("name"=>"control.grant.name", "description"=>"unavailable.nobody");
		}
		return $this->action("control.grant", "bm2_site_actions_grant");
	}

	public function controlStewardTest($check_duplicate=false) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.steward.name", "description"=>"unavailable.$check");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.grant')) {
			return array("name"=>"control.steward.name", "description"=>"unavailable.already");
		}
		if (!$settlement = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"control.steward.name", "description"=>"unavailable.nosettlement");
		}
		if ($settlement->getOccupier()) {
			return array("name"=>"control.steward.name", "description"=>"unavailable.occupied");
		}
		if ($settlement->getOwner() != $this->getCharacter()) {
			return array("name"=>"control.steward.name", "description"=>"unavailable.notyours2");
		}
		if (!$this->getActionableCharacters()) {
			return array("name"=>"control.steward.name", "description"=>"unavailable.nobody");
		}
		return $this->action("control.steward", "maf_actions_steward");
	}

	public function controlAbandonTest($check_duplicate=false, $settlement) {
		if (($check = $this->veryGenericTests()) !== true) {
			return array("name"=>"control.abandon.name", "description"=>"unavailable.$check");
		}
		if ($settlement->getOwner() != $this->getCharacter()) {
			return array("name"=>"control.abandon.name", "description"=>"unavailable.notyours2");
		}
		return $this->action("control.abandon", "bm2_site_settlement_abandon");
	}

	public function controlChangeOccupantTest($check_duplicate=false) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.changeoccupant.name", "description"=>"unavailable.$check");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.occupant')) {
			return array("name"=>"control.changeoccupant.name", "description"=>"unavailable.already");
		}
		if (!$settlement = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"control.changeoccupant.name", "description"=>"unavailable.nosettlement");
		}
		if (!$settlement->getOccupier()) {
			return array("name"=>"control.changeoccupant.name", "description"=>"unavailable.notoccupied");
		}
		if ($settlement->getOccupant() != $this->getCharacter()) {
			return array("name"=>"control.changeoccupant.name", "description"=>"unavailable.notyours2");
		}
		if (!$this->getActionableCharacters()) {
			return array("name"=>"control.changeoccupant.name", "description"=>"unavailable.nobody");
		}
		return $this->action("control.changeoccupant", "maf_settlement_occupant");
	}

	public function controlRenameTest($check_duplicate=false) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.rename.name", "description"=>"unavailable.$check");
		}
		if (!$settlement = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"control.rename.name", "description"=>"unavailable.nosettlement");
		}
		if ($settlement->getOccupier()) {
			return array("name"=>"control.rename.name", "description"=>"unavailable.occupied");
		}
		$char = $this->getCharacter();
		if ($settlement->getOwner() == $char || $settlement->getSteward() == $char) {
			return $this->action("control.rename", "bm2_site_actions_rename");
		} else {
			return array("name"=>"control.rename.name", "description"=>"unavailable.notyours2");
		}
	}


	public function controlSettlementDescriptionTest($check_duplicate=false, $settlement) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.description.settlement.name", "description"=>"unavailable.$check");
		}
		if (!$settlement = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"control.description.settlement.name", "description"=>"unavailable.nosettlement");
		}
		if ($settlement->getOccupier()) {
			return array("name"=>"control.description.settlement.name", "description"=>"unavailable.occupied");
		}
		$char = $this->getCharacter();
		if ($settlement->getOwner() == $char || $settlement->getSteward() == $char) {
			return $this->action("control.description.settlement", "bm2_site_settlement_description", false, array('id'=>$settlement->getId()));
		} else {
			return array("name"=>"control.description.settlement.name", "description"=>"unavailable.notyours2");
		}
	}

	public function controlCultureTest($check_duplicate=false) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.culture.name", "description"=>"unavailable.$check");
		}
		if (!$settlement = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"control.culture.name", "description"=>"unavailable.nosettlement");
		}
		$char = $this->getCharacter();
		if ($settlement->getOwner() == $char || $settlement->getSteward() == $char) {
			return $this->action("control.culture", "bm2_site_actions_changeculture");
		} else {
			return array("name"=>"control.culture.name", "description"=>"unavailable.notyours2");
		}
	}

	public function controlPermissionsTest($ignored, $settlement) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.permissions.name", "description"=>"unavailable.$check");
		}
		$char = $this->getCharacter();
		if (($settlement->getOwner() == $char || $settlement->getSteward() == $char) || $settlement->getOccupant() == $char) {
			return $this->action("control.permissions", "bm2_site_settlement_permissions", false, array('id'=>$settlement->getId()));
		} else {
			return array("name"=>"control.permissions.name", "description"=>"unavailable.notyours2");
		}
	}

	public function controlQuestsTest($ignored, $settlement) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.quests.name", "description"=>"unavailable.$check");
		}
		if ($settlement->getOccupier()) {
			return array("name"=>"control.quests.name", "description"=>"unavailable.occupied");
		}
		$char = $this->getCharacter();
		if ($settlement->getOwner() == $char || $settlement->getSteward() == $char) {
			return $this->action("control.quests", "bm2_site_settlement_quests", false, array('id'=>$settlement->getId()));
		} else {
			return array("name"=>"control.quests.name", "description"=>"unavailable.notyours2");
		}
	}


	/* ========== Military Actions ========== */

	public function militaryDisengageTest($check_duplicate=false) {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"military.disengage.name", "description"=>"unavailable.npc");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.disengage.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('military.disengage')) {
			return array("name"=>"military.disengage.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->isDoingAction('settlement.attack') && $this->getCharacter()->isDoingAction('control.take')) {
			return array("name"=>"military.disengage.name", "description"=>"unavailable.attacking");
		}
		if (!$this->getCharacter()->isInBattle()) {
			return array("name"=>"military.disengage.name", "description"=>"unavailable.nobattle");
		}
		if (count($this->getCharacter()->findForcedBattles()) <= 0) {
			return array("name"=>"military.disengage.name", "description"=>"unavailable.nobattle2");
		}
		return $this->action("military.disengage", "bm2_site_war_disengage", true);
	}

	public function militaryEvadeTest($check_duplicate=false) {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"military.evade.name", "description"=>"unavailable.npc");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.evade.name", "description"=>"unavailable.prisoner");
		}
		if ($this->getCharacter()->isDoingAction('settlement.defend')) {
			return array("name"=>"military.evade.name", "description"=>"unavailable.defending");
		}
		if ($this->getCharacter()->isDoingAction('settlement.attack') && $this->getCharacter()->isDoingAction('control.take')) {
			return array("name"=>"military.evade.name", "description"=>"unavailable.attacking");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			return array("name"=>"military.evade.name", "description"=>"unavailable.regrouping");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.evade.name", "description"=>"unavailable.already");
		}
		return $this->action("military.evade", "bm2_site_war_evade", true);
	}


	public function militaryBlockTest($check_duplicate=false) {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.block.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('military.block')) {
			return array("name"=>"military.block.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			return array("name"=>"military.block.name", "description"=>"unavailable.nosoldiers");
		}
		if ($this->getCharacter()->isDoingAction('settlement.attack')) {
			return array("name"=>"military.block.name", "description"=>"unavailable.attacking");
		}
		if ($this->getCharacter()->isDoingAction('settlement.defend')) {
			return array("name"=>"military.block.name", "description"=>"unavailable.defending");
		}
		if ( $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"military.block.name", "description"=>"unavailable.inside");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.block.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.block.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			return array("name"=>"military.block.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.block", "bm2_site_war_block", true);
	}

	/*public function militaryAttackSettlementTest($check_duplicate=false) {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.attack')) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->isDoingAction('settlement.defend')) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.both");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->getActiveSoldiers()->isEmpty()) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.nosoldiers");
		}
		if (!$settlement = $this->getActionableSettlement()) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.nosettlement");
		}
		if ($settlement->getOwner() == $this->getCharacter()) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.location.yours");
		}
		if (!$settlement->isDefended()) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.location.nodefenders");
		}
		if ($this->getCharacter()->getInsideSettlement() != $settlement) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.mustsiege");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.settlement.attack", "bm2_site_war_attacksettlement");
	}

	public function militaryAttackPlaceTest($check_duplicate=false) {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.attack')) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->isDoingAction('settlement.defend')) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.both");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->getActiveSoldiers()->isEmpty()) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.nosoldiers");
		}
		if (!$place = $this->getActionablePlace()) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.nosettlement");
		}
		if ($place->getOwner() == $this->getCharacter()) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.location.yours");
		}
		if (!$place->isDefended()) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.location.nodefenders");
		}
		if ($this->getCharacter()->getInsidePlace() != $place) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.mustsiege");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			return array("name"=>"military.place.attack.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.place.attack", "bm2_site_war_attacksettlement");
	}*/

	public function militaryDefendSettlementTest($check_duplicate=false) {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.defend')) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.already");
		}
		if ( ! $estate = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.notinside");
		}
		if ($this->getCharacter()->isDoingAction('settlement.attack')) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.both");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.nosoldiers");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.inbattle");
		}
		return $this->action("military.settlement.defend", "bm2_site_war_defendsettlement");
	}

	public function militaryDefendPlaceTest($check_duplicate=false) {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.place.defend.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('place.defend')) {
			return array("name"=>"military.place.defend.name", "description"=>"unavailable.already");
		}
		if ( ! $estate = $this->getCharacter()->getInsidePlace()) {
			return array("name"=>"military.place.defend.name", "description"=>"unavailable.notinside");
		}
		if ($this->getCharacter()->isDoingAction('settlement.attack')) {
			return array("name"=>"military.place.defend.name", "description"=>"unavailable.both");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.place.defend.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			return array("name"=>"military.place.defend.name", "description"=>"unavailable.nosoldiers");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.place.defend.name", "description"=>"unavailable.inbattle");
		}
		return $this->action("military.place.defend", "bm2_site_war_defendplace");
	}

	public function militarySiegeSettlementTest() {
		# Grants you access to the page in which you can start a siege.
		$settlement = $this->getActionableSettlement();
		if ($this->getCharacter()->isPrisoner()) {
			# Prisoners can't attack.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.prisoner");
		}
		if ($this->getCharacter()->isDoingAction('military.siege')) {
			# Already doing.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->getInsideSettlement()) {
			# Already inside.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.inside");
		}
		if (!$settlement) {
			# Can't attack nothing or empty places.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.nosiegable");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			# Busy regrouping.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			# Busy avoiding battle.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			# The guards laugh at your "siege".
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.nosoldiers");
		}
		if ($settlement->getOwner() == $this->getCharacter() || $settlement->getOccupier() == $this->getCharacter()) {
			# No need to siege your own settlement.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.location.yours");
		}
		if ($this->getCharacter()->isInBattle()) {
			# Busy fighting for life.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			# Too new.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.siege.start", "bm2_site_war_siege", false, array('action'=>'start'));
	}

	public function militarySiegePlaceTest($ignored, $place) {
		# Grants you access to the page in which you can start a siege.
		if ($this->getCharacter()->isPrisoner()) {
			# Prisoners can't attack.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.prisoner");
		}
		if ($this->getCharacter()->isDoingAction('military.siege')) {
			# Already doing.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->getInsidePlace()) {
			# Already inside.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.inside");
		}
		if (!$place || ($place && !$place->isDefended())) {
			# Can't attack nothing or empty places.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.notdefended");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			# Busy regrouping.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			# Busy avoiding battle.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			# The guards laugh at your "siege".
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.nosoldiers");
		}
		if ($place->getOwner() == $this->getCharacter() || $place->getOccupier() == $this->getCharacter()) {
			# No need to siege your own settlement.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.location.yours");
		}
		if ($this->getCharacter()->isInBattle()) {
			# Busy fighting for life.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			# Too new.
			return array("name"=>"military.siege.start.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.siege.start", "maf_war_siege_place", false, array('place'=>$place->getId(), 'action'=>'start'));
	}

	public function militarySiegeLeadershipTest($check_duplicate=false, $siege) {
		# Controls access to siege change of leadership page.
		if (!$siege) {
			# No siege.
			return array("name"=>"military.siege.leadership.name", "description"=>"unavailable.nosiege");
		}
		if ($this->getCharacter()->isPrisoner()) {
			# Prisoners can't attack.
			return array("name"=>"military.siege.leadership.name", "description"=>"unavailable.prisoner");
		}
		$inSiege = FALSE;
		$isLeader = FALSE;
		$isAttacker = FALSE;
		$isDefender = FALSE;
		$attLeader = FALSE;
		$defLeader = FALSE;
		foreach ($siege->getGroups() as $group) {
			if ($group->getCharacters()->contains($this->getCharacter())) {
				$inSiege = TRUE;
				if ($group->isAttacker()) {
					$isAttacker = TRUE;
					if ($group->getLeader()) {
						$attLeader = TRUE;
					}
				} else {
					$isDefender = TRUE;
					if ($group->getLeader()) {
						$defLeader = TRUE;
					}
				}
				if ($group->getLeader() == $this->getCharacter()) {
					$isLeader = TRUE;
				}
			}
		}
		if (!$isLeader) {
			# Isn't leader.
			return array("name"=>"military.siege.leadership.name", "description"=>"unavailable.isleader");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			# The guards laugh at your "siege".
			return array("name"=>"military.siege.leadership.name", "description"=>"unavailable.nosoldiers");
		}
		if ($this->getCharacter()->isInBattle()) {
			# Busy fighting for life.
			return array("name"=>"military.siege.leadership.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			# Too new.
			return array("name"=>"military.siege.leadership.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.siege.leadership", "bm2_site_war_siege", false, array('action'=>'leadership'));
	}

	public function militarySiegeAssumeTest($check_duplicate=false, $siege) {
		# Controls access to siege assume leadership page.
		# Normally, only defenders will have this issue, but just in case, we let attackers assume command as well if the opportunity presents itself.
		if (!$siege) {
			# No siege.
			return array("name"=>"military.siege.assume.name", "description"=>"unavailable.nosiege");
		}
		if ($this->getCharacter()->isPrisoner()) {
			# Prisoners can't attack.
			return array("name"=>"military.siege.assume.name", "description"=>"unavailable.prisoner");
		}
		$inSiege = FALSE;
		$isLeader = FALSE;
		$isAttacker = FALSE;
		$isDefender = FALSE;
		$attLeader = FALSE;
		$defLeader = FALSE;
		foreach ($siege->getGroups() as $group) {
			if ($group->getCharacters()->contains($this->getCharacter())) {
				$inSiege = TRUE;
				if ($group->isAttacker() && $isAttacker == FALSE) {
					$isAttacker = TRUE;
					if ($group->getLeader()) {
						$attLeader = TRUE; # Attackers already have leader
					}
				} else if ($isDefender == FALSE) {
					$isDefender = TRUE;
					if ($group->getLeader()) {
						$defLeader = TRUE; # Defenders already have leader
					}
				}
				if ($group->getLeader() == $this->getCharacter() && $isLeader == FALSE) {
					$isLeader = TRUE; # We are a leader!
				}
			}
		}
		if ($isLeader) {
			# Already leader.
			return array("name"=>"military.siege.assume.name", "description"=>"unavailable.isleader");
		} else if ($isAttacker && $attLeader) {
			# Already have leader.
			return array("name"=>"military.siege.assume.name", "description"=>"unavailable.haveleader");
		} else if ($isDefender && $defLeader) {
			# Already have leader.
			return array("name"=>"military.siege.assume.name", "description"=>"unavailable.haveleader");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			# The guards laugh at your "siege".
			return array("name"=>"military.siege.assume.name", "description"=>"unavailable.nosoldiers");
		}
		if ($this->getCharacter()->isInBattle()) {
			# Busy fighting for life.
			return array("name"=>"military.siege.assume.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			# Too new.
			return array("name"=>"military.siege.assume.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.siege.assume", "bm2_site_war_siege", false, array('action'=>'assume'));
	}

	public function militarySiegeBuildTest($check_duplicate=false) {
		# Controls access to page for building siege equipment.
		# TODO: Implement this.
		return array("name"=>"military.siege.build.name", "description"=>"unavailable.notimplemented");
		/*$settlement = $this->getActionableSettlement();
		if ($this->getCharacter()->isPrisoner()) {
			# Prisoners can't attack.
			return array("name"=>"military.settlement.siege.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.siege')) {
			# Already doing.
			return array("name"=>"military.settlement.siege.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->getInsideSettlement()) {
			# Already inside.
			return array("name"=>"military.settlement.siege.name", "description"=>"unavailable.inside");
		}
		if (!$settlement) {
			# Can't attack nothing.
			return array("name"=>"military.settlement.siege.name", "description"=>"unavailable.nosettlement");
		}
		if (!$settlement->getSiege()) {
			# No siege.
			return array("name"=>"military.settlement.siege.name", "description"=>"unavailable.nosiege");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			# Busy regrouping.
			return array("name"=>"military.settlement.siege.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			# Busy avoiding battle.
			return array("name"=>"military.settlement.siege.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			# The guards laugh at your "siege".
			return array("name"=>"military.settlement.siege.name", "description"=>"unavailable.nosoldiers");
		}
		if ($settlement->getOwner() == $this->getCharacter()) {
			# No need to siege your own settlement.
			return array("name"=>"military.settlement.siege.name", "description"=>"unavailable.location.yours");
		}
		if ($this->getCharacter()->isInBattle()) {
			# Busy fighting for life.
			return array("name"=>"military.settlement.siege.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			# Too new.
			return array("name"=>"military.settlement.siege.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.settlement.siege", "bm2_site_war_siege", false, array('action'=>'build'));*/
	}

	public function militarySiegeAssaultTest($check_duplicate=false, $siege) {
		# Controls access to the siege page for calling assaults and sorties.
		if (!$siege) {
			# No siege.
			return array("name"=>"military.siege.assault.name", "description"=>"unavailable.nosiege");
		}
		if ($this->getCharacter()->isPrisoner()) {
			# Prisoners can't attack.
			return array("name"=>"military.siege.assault.name", "description"=>"unavailable.prisoner");
		}
		if ($this->getCharacter()->isDoingAction('military.battle')) {
			# Already doing.
			return array("name"=>"military.siege.assault.name", "description"=>"unavailable.inbattle");
		}
		$inSiege = FALSE;
		$isLeader = FALSE;
		foreach ($siege->getGroups() as $group) {
			if ($group->getCharacters()->contains($this->getCharacter())) {
				$inSiege = TRUE;
				if ($group->getLeader() == $this->getCharacter()) {
					$isLeader = TRUE;
				}
			}
		}
		if (!$inSiege) {
			return array("name"=>"military.siege.assault.name", "description"=>"unavailable.notinsiege");
		}
		if (!$isLeader) {
			return array("name"=>"military.siege.assault.name", "description"=>"unavailable.notleader");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			# Busy regrouping.
			return array("name"=>"military.siege.assault.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			# Busy avoiding battle.
			return array("name"=>"military.siege.assault.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			# The guards laugh at your "siege".
			return array("name"=>"military.siege.assault.name", "description"=>"unavailable.nosoldiers");
		}
		if ($this->getCharacter()->isInBattle()) {
			# Busy fighting for life.
			return array("name"=>"military.siege.assault.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			# Too new.
			return array("name"=>"military.siege.assault.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.siege.assault", "bm2_site_war_siege", false, array('action'=>'assault'));
	}

	public function militarySiegeDisbandTest($check_duplicate=false, $siege) {
		if (!$siege) {
			# No siege.
			return array("name"=>"military.siege.disband.name", "description"=>"unavailable.nosiege");
		}
		if ($this->getCharacter()->isPrisoner()) {
			# Prisoners can't attack.
			return array("name"=>"military.siege.disband.name", "description"=>"unavailable.prisoner");
		}
		$isLeader = FALSE;
		foreach ($siege->getGroups() as $group) {
			if ($group->getCharacters()->contains($this->getCharacter())) {
				$inSiege = TRUE;
				if ($siege->getAttacker()->getLeader() == $this->getCharacter()) {
					$isLeader = TRUE;
				}
			}
		}
		if (!$inSiege) {
			return array("name"=>"military.siege.disband.name", "description"=>"unavailable.notinsiege");
		}
		if (!$isLeader) {
			# Can't cancel a siege you didn't start.
			return array("name"=>"military.siege.disband.name", "description"=>"unavailable.notbesieger");
		}
		if ($this->getCharacter()->isInBattle()) {
			# Busy fighting for life.
			return array("name"=>"military.siege.disband.name", "description"=>"unavailable.inbattle");
		}
		return $this->action("military.siege.disband", "bm2_site_war_siege", false, array('action'=>'disband'));
	}

	public function militarySiegeLeaveTest($check_duplicate=false, $siege) {
		# Controls access to the leave siege menu.
		if (!$siege) {
			# No siege.
			return array("name"=>"military.siege.leave.name", "description"=>"unavailable.nosiege");
		}
		if ($siege->getAttacker()->getLeader() == $this->getCharacter()) {
			return array("name"=>"military.siege.leave.name", "description"=>"unavailable.areleader");
		}
		$inSiege = FALSE;
		foreach ($siege->getGroups() as $group) {
			if ($group->getCharacters()->contains($this->getCharacter())) {
				$inSiege = TRUE;
				break;
			}
		}
		if (!$inSiege) {
			return array("name"=>"military.siege.leave.name", "description"=>"unavailable.notinsiege");
		}
		return $this->action("military.siege.leave", "bm2_site_war_siege", false, array('action'=>'leave'));
	}

	public function militarySiegeGeneralTest($check_duplicate=false, $siege) {
		# Controls access to the siege action selection menu.
		if (!$siege) {
			# No siege.
			return array("name"=>"military.siege.general.name", "description"=>"unavailable.nosiege");
		}
		if ($this->getCharacter()->isPrisoner()) {
			# Prisoners can't attack.
			return array("name"=>"military.siege.general.name", "description"=>"unavailable.prisoner");
		}
		$inSiege = FALSE;
		foreach ($siege->getGroups() as $group) {
			if ($group->getCharacters()->contains($this->getCharacter())) {
				$inSiege = TRUE;
			}
		}
		if (!$inSiege) {
			# Not in the siege.
			return array("name"=>"military.siege.leave.name", "description"=>"unavailable.notinsiege");
		}
		return $this->action("military.siege.leave", "bm2_site_war_siege", false, array('action'=>'leave'));
	}

	/* TODO: Add suicide runs, maybe?
	public function militarySiegeAttackTest($check_duplicate=false) {
		# Controls access to the suicide run menu for sieges.
		$settlement = $this->getActionableSettlement();
		$place = $this->getActionablePlace();
		if ($this->getCharacter()->isPrisoner()) {
			# Prisoners can't attack.
			return array("name"=>"military.siege.attack.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('military.battle')) {
			# Already doing.
			return array("name"=>"military.siege.attack.name", "description"=>"unavailable.already");
		}
		if (!$settlement && !$place) {
			# Can't attack nothing.
			return array("name"=>"military.siege.attack.name", "description"=>"unavailable.nosettlement");
		}
		if (((!$settlement || $settlement && !$settlement->getSiege())) && (!$place || ($place && !$place->getSiege()))) {
			# No siege.
			return array("name"=>"military.siege.attack.name", "description"=>"unavailable.nosiege");
		}
		if ($settlement && $settlement->getSiege()) {
			$siege = $settlement->getSiege();
		} elseif ($place && $place->getSettlement()) {
			$siege = $place->getSiege();
		}
		$inSiege = FALSE;
		foreach ($siege->getGroups() as $group) {
			if ($group->getCharacters()->contains($this->getCharacter())) {
				$inSiege = TRUE;
			}
		}
		if (!$inSiege) {
			return array("name"=>"military.siege.attack.name", "description"=>"unavailable.notinsiege");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			# Busy regrouping.
			return array("name"=>"military.siege.attack.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			# Busy avoiding battle.
			return array("name"=>"military.siege.attack.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			# The guards laugh at your "siege".
			return array("name"=>"military.siege.attack.name", "description"=>"unavailable.nosoldiers");
		}
		if ($this->getCharacter()->isInBattle()) {
			# Busy fighting for life.
			return array("name"=>"military.siege.attack.name", "description"=>"unavailable.inbattle");
		}
		return $this->action("military.siege.attack", "bm2_site_war_siege", false, array('action'=>'attack'));
	}

	public function militarySiegeJoinAttackTest($check_duplicate=false) {
		# Controls access to the option to join someone elses suicide run in a siege.
		$settlement = $this->getActionableSettlement();
		$place = $this->getActionablePlace();
		if ($this->getCharacter()->isPrisoner()) {
			# Prisoners can't attack.
			return array("name"=>"military.siege.joinattack.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('military.battle')) {
			# Already doing.
			return array("name"=>"military.siege.joinattack.name", "description"=>"unavailable.already");
		}
		if (!$settlement && !$place) {
			# Can't attack nothing.
			return array("name"=>"military.siege.joinattack.name", "description"=>"unavailable.nosettlement");
		}
		if (((!$settlement || $settlement && !$settlement->getSiege())) && (!$place || ($place && !$place->getSiege()))) {
			# No siege.
			return array("name"=>"military.siege.joinattack.name", "description"=>"unavailable.nosiege");
		}
		if ($settlement && $settlement->getSiege()) {
			$siege = $settlement->getSiege();
		} elseif ($place && $place->getSettlement()) {
			$siege = $place->getSiege();
		}
		$inSiege = FALSE;
		if ($siege->getCharacters()->contains($this->getCharacter())) {
			$inSiege = TRUE;
		}
		if ($siege->getBattles()->isEmpty()) {
			return array("name"=>"military.battles.join.name", "description"=>"unavailable.nobattles");
		}
		if (!$inSiege) {
			return array("name"=>"military.siege.joinattack.name", "description"=>"unavailable.notinsiege");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			# Busy regrouping.
			return array("name"=>"military.siege.joinattack.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			# Busy avoiding battle.
			return array("name"=>"military.siege.joinattack.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			# The guards laugh at your "siege".
			return array("name"=>"military.siege.joinattack.name", "description"=>"unavailable.nosoldiers");
		}
		if ($this->getCharacter()->isInBattle()) {
			# Busy fighting for life.
			return array("name"=>"military.siege.joinattack.name", "description"=>"unavailable.inbattle");
		}
		return $this->action("military.siege.joinattack", "bm2_site_war_siege", false, array('action'=>'joinattack'));
	}
	*/

	public function militarySiegeJoinSiegeTest($check_duplicate=false, $siege) {
		# Controls access to the ability to join an ongoing siege.
		if (!$siege) {
			# No siege.
			return array("name"=>"military.siege.join.name", "description"=>"unavailable.nosiege");
		}
		if ($this->getCharacter()->isPrisoner()) {
			# Prisoners can't attack.
			return array("name"=>"military.siege.join.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.siege')) {
			# Already doing.
			return array("name"=>"military.siege.join.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			# Busy regrouping.
			return array("name"=>"military.siege.join.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			# Busy avoiding battle.
			return array("name"=>"military.siege.join.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			# The guards laugh at your "siege".
			return array("name"=>"military.siege.join.name", "description"=>"unavailable.nosoldiers");
		}
		if ($this->getCharacter()->isInBattle()) {
			# Busy fighting for life.
			return array("name"=>"military.siege.join.name", "description"=>"unavailable.inbattle");
		}
		return $this->action("military.siege.join", "bm2_site_war_siege", false, array('action'=>'joinsiege'));
	}

	public function militaryDamageFeatureTest($check_duplicate=false) {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.damage.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('military.damage')) {
			return array("name"=>"military.damage.name", "description"=>"unavailable.already");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('military.loot')) {
			return array("name"=>"military.damage.name", "description"=>"unavailable.similar");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.damage.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			return array("name"=>"military.damage.name", "description"=>"unavailable.nosoldiers");
		}
		if (!$this->geography->findFeaturesNearMe($this->getCharacter())) {
			return array("name"=>"military.damage.name", "description"=>"unavailable.nofeatures");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.damage.name", "description"=>"unavailable.inbattle");
		}
		return $this->action("military.damage", "bm2_site_war_damage", true);
	}

	public function militaryLootSettlementTest($check_duplicate=false) {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.settlement.loot.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.loot')) {
			return array("name"=>"military.settlement.loot.name", "description"=>"unavailable.already");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('military.damage')) {
			return array("name"=>"military.settlement.loot.name", "description"=>"unavailable.similar");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			return array("name"=>"military.settlement.loot.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.settlement.loot.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->hasNoSoldiers()) {
			return array("name"=>"military.settlement.loot.name", "description"=>"unavailable.nosoldiers");
		}
		if (!$this->getActionableRegion()) {
			return array("name"=>"military.settlement.loot.name", "description"=>"unavailable.noregion");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.settlement.loot.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			return array("name"=>"military.settlement.loot.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.settlement.loot", "bm2_site_war_lootsettlement");
	}

	public function militaryAttackNoblesTest($check_duplicate=false) {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.battles.initiate.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('military.battle')) {
			return array("name"=>"military.battles.initiate.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			return array("name"=>"military.battles.initiate.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.battles.initiate.name", "description"=>"unavailable.evading");
		}
		if (!$this->getActionableCharacters()) {
			return array("name"=>"military.battles.initiate.name", "description"=>"unavailable.nobody");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.battles.initiate.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			return array("name"=>"military.battles.initiate.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.battles.initiate", "bm2_site_war_attackothers");
	}

	public function militaryAidTest() {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.aid.name", "description"=>"unavailable.prisoner");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.aid.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.aid.name", "description"=>"unavailable.evading");
		}
		return $this->action("military.aid", "bm2_site_war_aid");
	}

	public function militaryJoinBattleTest() {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.battles.join.name", "description"=>"unavailable.prisoner");
		}
		if (!$this->geography->findBattlesInActionRange($this->getCharacter())) {
			return array("name"=>"military.battles.join.name", "description"=>"unavailable.nobattles");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.battles.join.name", "description"=>"unavailable.inbattle");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			return array("name"=>"military.battles.join.name", "description"=>"unavailable.regrouping");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.battles.join.name", "description"=>"unavailable.evading");
		}
		return $this->action("military.battles.join", "bm2_site_war_battlejoin");
	}

	/* ========== Personal Actions ========== */

	public function personalRelationsTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"relations", "description"=>"unavailable.npc");
		}

		return $this->action("relations", "bm2_relations");

	}
	public function personalPrisonersTest() {
		if ( $this->getCharacter()->getPrisoners()->count() == 0) {
			return array("name"=>"diplomacy.prisoners.name", "description"=>"unavailable.noprisoners");
		}

		return $this->action("diplomacy.prisoners", "bm2_site_politics_prisoners");

	}
	public function personalClaimsTest() {
		if ( $this->getCharacter()->getSettlementClaims()->count() == 0) {
			return array("name"=>"diplomacy.claims.name", "description"=>"unavailable.noclaims");
		}

		return $this->action("diplomacy.claims", "bm2_site_politics_claims");

	}


	public function personalSurrenderTest() {
		return array("name"=>"surrender.name", "description"=>"disabled because of abuse");
		if ($this->getCharacter()->getPrisonerOf()) {
			return array("name"=>"surrender.name", "description"=>"unavailable.prisoner");
		}
		if (!$this->getActionableCharacters()) {
			return array("name"=>"surrender.name", "description"=>"unavailable.nobody");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"surrender.name", "description"=>"unavailable.inbattle");
		}
		return $this->action("surrender", "bm2_site_character_surrender");
	}

	public function personalEscapeTest() {
		if ( $this->getCharacter()->getPrisonerOf() == false) {
			return array("name"=>"escape.name", "description"=>"unavailable.notprisoner");
		}
		if ($this->getCharacter()->isDoingAction('character.escape')) {
			return array("name"=>"escape.name", "description"=>"unavailable.already");
		}

		return $this->action("escape", "bm2_site_character_escape");
	}

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

	public function personalRequestsManageTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"personal.requests.name", "description"=>"unavailable.npc");
		}

		return $this->action("personal.requests", "bm2_gamerequest_manage");
	}

	public function personalRequestSoldierFoodTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"personal.soldierfood.name", "description"=>"unavailable.npc");
		}

		return $this->action("personal.soldierfood", "bm2_gamerequest_soldierfood");

	}
	/* ========== Economy Actions ========== */

	public function economyTradeTest() {
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->economyActionsGenericTests($settlement)) !== true) {
			return array("name"=>"economy.trade.name", "description"=>"unavailable.$check");
		}

		// TODO: need a merchant in your entourage for trade options? or just foreign trade?
		if ($settlement->getOccupier()) {
			$occupied = true;
		} else {
			$occupied = false;
		}
		if ($this->permission_manager->checkSettlementPermission($settlement, $this->getCharacter(), 'trade', false, $occupied)) {
			return array("name"=>"economy.trade.name", "url"=>"bm2_site_actions_trade", "description"=>"economy.trade.owner");
		} else {
			if ($this->getCharacter()->getOwnedSettlements()->isEmpty()) {
				return array("name"=>"economy.trade.name", "description"=>"unavailable.trade.noestate");
			}
			return array("name"=>"economy.trade.name", "url"=>"bm2_site_actions_trade", "description"=>"economy.trade.foreign");
		}
	}

	public function economyRoadsTest() {
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->economyActionsGenericTests($settlement)) !== true) {
			return array("name"=>"economy.roads.name", "description"=>"unavailable.$check");
		}
		if ($settlement->getOccupier()) {
			$occupied = true;
		} else {
			$occupied = false;
		}
		if ( ! $this->permission_manager->checkSettlementPermission($settlement, $this->getCharacter(), 'construct', false, $occupied)) {
			return array("name"=>"economy.roads.name", "description"=>"unavailable.notyours");
		}

		return $this->action("economy.roads", "bm2_site_construction_roads");
	}

	public function economyFeaturesTest() {
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->economyActionsGenericTests($settlement)) !== true) {
			return array("name"=>"economy.features.name", "description"=>"unavailable.$check");
		}
		if ($settlement->getOccupier()) {
			$occupied = true;
		} else {
			$occupied = false;
		}
		if ( ! $this->permission_manager->checkSettlementPermission($settlement, $this->getCharacter(), 'construct', false, $occupied)) {
			return array("name"=>"economy.features.name", "description"=>"unavailable.notyours");
		}

		return array("name"=>"economy.features.name", "url"=>"bm2_site_construction_features", "description"=>"economy.features.description");
	}

	public function economyBuildingsTest() {
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->economyActionsGenericTests($settlement)) !== true) {
			return array("name"=>"economy.build.name", "description"=>"unavailable.$check");
		}
		if ($settlement->getOccupier()) {
			$occupied = true;
		} else {
			$occupied = false;
		}
		if ( ! $this->permission_manager->checkSettlementPermission($settlement, $this->getCharacter(), 'construct', false, $occupied)) {
			return array("name"=>"economy.build.name", "description"=>"unavailable.notyours");
		}

		return array("name"=>"economy.build.name", "url"=>"bm2_site_construction_buildings", "description"=>"economy.build.description");
	}

	/* ========== Place Actions ============== */

	public function placeListTest() {
		if ($this->getCharacter() && $this->geography->findPlacesInActionRange($this->getCharacter())) {
			return $this->action("place.list", "maf_place_actionable");
		} else {
			return array("name"=>"place.actionable.name", "description"=>"unavailable.noplace");
		}
	}

	public function placeCreateTest() {
		$character = $this->getCharacter();
		if ($check = $this->placeActionsGenericTests() !== true) {
			return array("name"=>"place.new.name", "description"=>'unavailable.'.$check);
		}
		if ($character->getUser()->getFreePlaces() < 1) {
			return array("name"=>"place.new.name", "description"=>"unavailable.nofreeplaces");
		}
		# If not inside a settlement, check that we've enough separation (500m)
		$settlement = $character->getInsideSettlement();
		if (!$settlement) {
			if (!$this->geography->checkPlacePlacement($character)) {
				return array("name"=>"place.new.name", "description"=>"unavailable.toocrowded");
			}
		}
		if ($settlement && $settlement->getOccupier()) {
				$occupied = true;
		} elseif ($this->geography->findMyRegion($character)->getSettlement() && $this->geography->findMyRegion($character)->getSettlement()->getOccupier()) {
			$occupied = true;
		} else {
			$occupied = false;
		}
		if ($occupied) {
			return array("name"=>"place.new.name", "description"=>"unavailable.occupied");
		}
		if (($character->getInsideSettlement() && !$this->permission_manager->checkSettlementPermission($character->getInsideSettlement(), $character, 'placeinside')) || (!$character->getInsideSettlement() && !$this->permission_manager->checkSettlementPermission($this->geography->findMyRegion($character)->getSettlement(), $character, 'placeoutside'))) {
			# It's a long line, but basically, are we inside a settlement with permission, or outside a settlement with permission. If neither, we don't get access :)
			return array("name"=>"place.new.name", "description"=>"unavailable.nopermission");
		}
		return array("name"=>"place.new.name", "url"=>"maf_place_new", "description"=>"place.new.description", "long"=>"place.new.longdesc");
	}

	public function placeManageTest($ignored, Place $place) {
		if (($check = $this->placeActionsGenericTests()) !== true) {
			return array("name"=>"place.manage.name", "description"=>"unavailable.$check");
		}
		if ($place->getOccupier()) {
			$occupied = true;
		} else {
			$occupied = false;
		}
		if (!$this->permission_manager->checkPlacePermission($place, $this->getCharacter(), 'manage', false, $occupied)) {
			return array("name"=>"place.manage.name", "description"=>"unavailable.notmanager");
		} else {
			return $this->action("place.manage", "maf_place_manage", true,
				array('place'=>$place->getId()),
				array("%name%"=>$place->getName(), "%formalname%"=>$place->getFormalName())
			);
		}
	}

	public function placeTransferTest($ignored, Place $place) {
		if (($check = $this->placeActionsGenericTests()) !== true) {
			return array("name"=>"place.manage.name", "description"=>"unavailable.$check");
		}
		if ($place->getOwner() !== $this->getCharacter()) {
			return ["name"=>"place.transfer.name", "description"=>"unavailable.notowner"];
		}
		return [
			$this->action("place.transfer", "maf_place_transfer", true,
				['place'=>$place->getId()],
				['%name%'=>$place->getName(), '%formalname%'=>$place->getFormalName()]
			)
		];
	}

	public function placeNewPlayerInfoTest($ignored, $place) {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"place.newplayer.name", "description"=>"unavailable.$check");
		}
		if ($place->getOccupant()) {
			return array("name"=>"place.newplayer.name", "description"=>"unavailable.occupied");
		}
		if ($place->getOwner() != $this->getCharacter()) {
			return array("name"=>"place.newplayer.name", "description"=>"unavailable.notowner");
		}
		if (!$place->getType()->getSpawnable()) {
			return array("name"=>"place.newplayer.name", "description"=>"unavailable.notspawnable");
		} else {
			return $this->action("place.newplayer", "maf_place_newplayer", true,
				array('place'=>$place->getId()),
				array("%name%"=>$place->getName(), "%formalname%"=>$place->getFormalName())
			);
		}
	}

	public function placePermissionsTest($ignored, Place $place) {
		if (($check = $this->placeActionsGenericTests()) !== true) {
			return array("name"=>"place.permissions.name", "description"=>"unavailable.$check");
		}
		if ($place->getOwner() != $this->getCharacter() || $place->getOccupant() != $this->getCharacter()) {
			return array("name"=>"place.permissions.name", "description"=>"unavailable.notowner");
		}
		return $this->action("place.permissions", "maf_place_permissions", true,
				array('place'=>$place->getId()),
				array("%name%"=>$place->getName(), "%formalname%"=>$place->getFormalName())
			);
	}

	public function placeAllowRealmUseTest($ignored, Place $place) {
		if (($check = $this->placeActionsGenericTests()) !== true) {
			return array("name"=>"place.allowuse.name", "description"=>"unavailable.$check");
		}
		if ($place->getOwner() != $this->getCharacter()) {
			return array("name"=>"place.allowuse.name", "description"=>"unavailable.notowner");
		}
		if ($place->getType()->getName() != 'embassy' && $place->getType()->getSpawnable()) {
			return array("name"=>"place.allowuse.name", "description"=>"unavailable.notrealmuseable");
		}
		return $this->action("place.allowuse", "maf_place_allowuse", true,
				array('place'=>$place->getId()),
				array("%name%"=>$place->getName(), "%formalname%"=>$place->getFormalName())
			);
	}

	public function placeManageRulersTest($ignored, Place $place) {
		if (($check = $this->placeActionsGenericTests()) !== true) {
			return array("name"=>"place.spawn.name", "description"=>"unavailable.$check");
		}
		$character = $this->getCharacter();
		$settlement = $place->getSettlement();
		if (!$settlement) {
			$settlement = $place->getGeoFeature()->getGeoData()->getSettlement();
		}
		if (!$place->getType()->getSpawnable()) {
			return array("name"=>"place.spawn.name", "description"=>"unavailable.notspawnable");
		}
		if (
			(!$place->getRealm() && $settlement->getOwner() != $character) ||
			!$place->getRealm()->findRulers()->contains($character)
		) {
			return array("name"=>"place.spawn.name", "description"=>"unavailable.notowner");
		}

		return $this->action("place.spawn", "maf_place_spawn", true,
				array('place'=>$place->getId()),
				array("%name%"=>$place->getName(), "%formalname%"=>$place->getFormalName())
			);
	}

	public function placeManageEmbassyTest($ignored, Place $place) {
		if (($check = $this->placeActionsGenericTests()) !== true) {
			return array("name"=>"place.spawn.name", "description"=>"unavailable.$check");
		}
		$character = $this->getCharacter();
		$settlement = $place->getSettlement();
		if (!$settlement) {
			$settlement = $place->getGeoFeature()->getGeoData()->getSettlement();
		}
		if ($place->getType()->getName() != 'embassy') {
			return array("name"=>"place.embassy.name", "description"=>"unavailable.wrongplacetype");
		}
		if (
			$place->getAmbassador() == $character ||
			(!$place->getAmbassador() && $place->getOwningRealm() && $place->getOwningRealm()->findRulers()->contains($character)) ||
			(!$place->getAmbassador() && !$place->getOwningRealm() && $place->getHostingRealm() && $place->getHostingRealm()->findRulers()->conntains($character)) ||
			(!$place->getAmbassador() && !$place->getOwningRealm() && !$place->getHostingRealm() && $place->getOwner() == $character)
		) {
			return $this->action("place.embassy", "maf_place_embassy", true,
					array('place'=>$place->getId()),
					array("%name%"=>$place->getName(), "%formalname%"=>$place->getFormalName())
				);
		} else {
			return array("name"=>"place.embassy.name", "description"=>"unavailable.notowner");
		}
	}

	public function placeEnterTest($check_duplicate=false, Place $place) {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"place.enter.name", "description"=>"unavailable.$check");
		}
		if ($place->getOccupier()) {
			$occupied = true;
		} else {
			$occupied = false;
		}
		if (!$this->permission_manager->checkPlacePermission($place, $this->getCharacter(), 'visit', false, $occupied)) {
			return array("name"=>"place.enter.name", "desciprtion"=>"unavailable.noaccess");
		}
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"place.enter.name", "description"=>"unavailable.npc");
		}
		if ($place != $this->getActionablePlace() && $this->getCharacter()->getInsideSettlement() != $place->getSettlement()) {
			return array("name"=>"place.enter.name", "description"=>"unavailable.noplace");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('place.enter')) {
			return array("name"=>"place.enter.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"place.enter.name", "description"=>"unavailable.inbattle");
		}

		if ($this->getCharacter()->isPrisoner()) {
			if ($place->getOwner() == $this->getCharacter()) { # FIXME: Wut?
				return array("name"=>"place.enter.name", "url"=>"bm2_site_actions_enter", "description"=>"place.enter.description2");
			} else {
				return array("name"=>"place.enter.name", "description"=>"unavailable.enter.notyours");
			}
		} else {
			return $this->action("place.enter", "maf_place_enter", false, array('id'=>$place->getId()));
		}
	}

	public function placeLeaveTest($check_duplicate=false) {
		if (($check = $this->interActionsGenericTests()) !== true) {
			return array("name"=>"place.exit.name",
				     "description"=>"unavailable.$check"
				    );
		}
		if (!$this->getCharacter()->getInsidePlace()) {
			return array("name"=>"place.exit.name",
				     "description"=>"unavailable.outsideplace"
				    );
		}
		if ($this->getCharacter()->getInsidePlace()->getSiege()) {
			return array("name"=>"location.exit.name", "description"=>"unavailable.besieged");
		}
		if (!$place = $this->getActionablePlace()) {
			return array("name"=>"place.exit.name",
				     "description"=>"unavailable.noplace"
				    );
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('place.exit')) {
			return array("name"=>"place.exit.name",
				     "description"=>"unavailable.already"
				    );
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"place.exit.name",
				     "description"=>"unavailable.inbattle"
				    );
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"place.exit.name",
				     "description"=>"unavailable.prisoner"
				    );
		} else {
			return $this->action("place.exit",
					     "maf_place_exit"
					    );
		}
	}

	public function placeOccupationStartTest($check_duplicate=false, $place) {
		if (!$place) {
			return array("name"=>"place.occupationstart.name", "description"=>"unavailable.noplace");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"place.occupationstart.name", "description"=>"unavailable.prisoner");
		}
		if (!$place = $this->getCharacter()->getInsidePlace()) {
			return array("name"=>"place.occupationstart.name", "description"=>"unavailable.notinside");
		}
		if ($place->isFortified() && $this->getCharacter()->getInsidePlace()!=$place) {
			return array("name"=>"place.occupationstart.name", "description"=>"unavailable.location.fortified");
		}
		if ($this->getCharacter()->isDoingAction('military.regroup')) {
			return array("name"=>"place.occupationstart.name", "description"=>"unavailable.regrouping");
		}
		if ($place->getOwner() == $this->getCharacter()) {
			return array("name"=>"place.occupationstart.name", "description"=>"unavailable.location.yours");
		}
		if ($place->getOccupant()) {
			return array("name"=>"place.occupationstart.name", "description"=>"unavailable.occupied");
		}
		return $this->action("place.occupationstart", "maf_place_occupation_start");
	}

	public function placeOccupationEndTest($check_duplicate=false, $place) {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"place.occupationend.name", "description"=>"unavailable.prisoner");
		}
		if (!$place = $this->getCharacter()->getInsidePlace()) {
			return array("name"=>"place.occupationend.name", "description"=>"unavailable.notinside");
		}
		if ($place->isFortified() && $this->getCharacter()->getInsidePlace()!=$place) {
			return array("name"=>"place.occupationend.name", "description"=>"unavailable.location.fortified");
		}
		if ($check_regroup && $this->getCharacter()->isDoingAction('military.regroup')) {
			return array("name"=>"place.occupationend.name", "description"=>"unavailable.regrouping");
		}
		if (!$place->getOccupant()) {
			return array("name"=>"place.occupationend.name", "description"=>"unavailable.notoccupied");
		}
		if ($place->getOccupant() != $this->getCharacter()) {
			return array("name"=>"place.occupationend.name", "description"=>"unavailable.notyours");
		}
		return $this->action("place.occupationend", "maf_settlement_occupation_end");
	}

	public function placeChangeOccupierTest($check_duplicate=false, $place) {
		if (!$place) {
			return array("name"=>"place.changeoccupier.name", "description"=>"unavailable.notsettlement");
		}
		if (!$place->getOccupier()) {
			return array("name"=>"place.changeoccupier.name", "description"=>"unavailable.notoccupied");
		}
		if ($place->getOccupant() != $this->getCharacter()) {
			return array("name"=>"place.changeoccupier.name", "description"=>"unavailable.notyours2");
		}

		$myrealms = $this->getCharacter()->findRealms();
		if ($myrealms->isEmpty()) {
			return array("name"=>"place.changeoccupier.name", "description"=>"unavailable.norealms");
		}
		return $this->action("place.changeoccupier", "maf_settlement_occupier", false, array('id'=>$settlement->getId()));
	}

	public function placeChangeOccupantTest($check_duplicate=false, $place) {
		if (!$place = $this->getCharacter()->getInsidePlace()) {
			return array("name"=>"place.changeoccupant.name", "description"=>"unavailable.nosettlement");
		}
		if (!$place->getOccupier()) {
			return array("name"=>"place.changeoccupant.name", "description"=>"unavailable.notoccupied");
		}
		if ($place->getOccupant() != $this->getCharacter()) {
			return array("name"=>"place.changeoccupant.name", "description"=>"unavailable.notyours2");
		}
		if (!$this->getActionableCharacters()) {
			return array("name"=>"place.changeoccupant.name", "description"=>"unavailable.nobody");
		}
		return $this->action("place.changeoccupant", "maf_settlement_occupant");
	}

	/* ========== Unit Actions ========== */

	public function unitNewTest() {
		$character = $this->getCharacter();
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->recruitActionsGenericTests($settlement)) !== true) {
			return array("name"=>"unit.new.name", "description"=>"unavailable.$check");
		}
		if (!$this->permission_manager->checkSettlementPermission($settlement, $character, 'units')) {
			return array("name"=>"unit.new.name", "description"=>"unavailable.notyours2");
		}

		return $this->action("unit.new", "maf_unit_new");
	}

	public function unitManageTest($ignored, Unit $unit) {
		$character = $this->getCharacter();
		$settlement = $unit->getSettlement();
		if (!$character->getUnits()->contains($unit)) {
			if($settlement && (!$this->permission_manager->checkSettlementPermission($settlement, $character, 'units') && $unit->getMarshal() != $character)) {
				if($unit->getSettlement() != $character->getInsideSettlement()) {
					return array("name"=>"unit.manage.name", "description"=>"unavailable.notinside");
				}
				return array("name"=>"unit.manage.name", "description"=>"unavailable.notmarshal");
			}
		} elseif ($unit->getCharacter() != $character) {
			return array("name"=>"unit.new.name", "description"=>"unavailable.notyourunit");
		}
		return $this->action("unit.manage.name", "maf_unit_manage");
	}

	public function unitRebaseTest($ignored, Unit $unit) {
		$character = $this->getCharacter();
		$settlement = $this->getCharacter()->getInsideSettlement();
		if($unit->getSettlement() && !$this->permission_manager->checkSettlementPermission($unit->getSettlement(), $character, 'units')) {
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
		if($settlement && !$this->permission_manager->checkSettlementPermission($settlement, $character, 'units') && $unit->getSettlement() != $character->getInsideSettlement()) {
			return array("name"=>"unit.appoint.name", "description"=>"unavailable.notlord");
		} elseif(!$settlement) {
			return array("name"=>"unit.appoint.name", "description"=>"unavailable.notinside");
		}
		return $this->action("unit.appoint.name", "maf_unit_appoint");
	}

	public function unitSoldiersTest($ignored, Unit $unit) {
		$settlement = $this->getCharacter()->getInsideSettlement();
		$character = $this->getCharacter();
		if (
			$unit->getCharacter() == $character || (
				$unit->getSettlement() && (
					$unit->getSettlement()->getOwner() == $character || $unit->getSettlement()->getSteward() == $character
				)
			) || ($unit->getMarshal() == $character) || $this->permission_manager->checkSettlementPermission($settlement, $character, 'recruit')) {
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
		$permission = $this->permission_manager->checkSettlementPermission($settlement, $character, 'units');
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
		$settlement = $this->getCharacter()->getInsideSettlement();
		if (!$character->getUnits()->contains($unit)) {
			return array("name"=>"unit.return.name", "description"=>"unavailable.notassigned");
		}
		if (!$unit->getSettlement()) {
			return array("name"=>"unit.return.name", "description"=>"unavailable.nobase");
		}
		if (!$this->permission_manager->checkSettlementPermission($unit->getSettlement(), $character, 'units')) {
			return array("name"=>"unit.return.name", "description"=>"unavailable.notyourunit");
		}
		return $this->action("unit.return.name", "maf_unit_return");
	}

	public function unitRecallTest($ignored, Unit $unit) {
		$character = $this->getCharacter();
		$settlement = $unit->getSettlement();
		if (!$character->getUnits()->contains($unit)) {
			if($settlement && (!$this->permission_manager->checkSettlementPermission($settlement, $character, 'units') || $unit->getMarshal() != $character)) {
				if($unit->getSettlement() != $character->getInsideSettlement()) {
					return array("name"=>"unit.recall.name", "description"=>"unavailable.notinside");
				}
				return array("name"=>"unit.recall.name", "description"=>"unavailable.notlord");
			}
		} elseif ($unit->getCharacter() != $character) {
			return array("name"=>"unit.recall.name", "description"=>"unavailable.notyourunit");
		}

		if ($unit->getTravelDays() > 0) {
			return array("name"=>"unit.recall.name", "description"=>"unavailable.rebasing");
		}
		if ($unit->getTravelDays() == 0 && !$unit->getCharacter())
		return $this->action("unit.recall.name", "maf_unit_recall");
	}

	/* ========== Political Actions ========== */

	public function hierarchyOathTest() {
		// swear an oath of fealty - only available if we don't lead a realm (if we do, similar actions are under realm management)
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"oath.name", "include"=>"hierarchy", "description"=>"unavailable.npc");
		}
		if (!$this->getActionableCharacters()) {
			return array("name"=>"oath.name", "include"=>"hierarchy", "description"=>"unavailable.noothers");
		}

		return array("name"=>"oath.name", "url"=>"maf_politics_oath_offer", "include"=>"hierarchy");
	}

	public function hierarchyOfferOathTest() {
		// swear an oath of fealty - only available if we don't lead a realm (if we do, similar actions are under realm management)
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"oath.name", "include"=>"hierarchy", "description"=>"unavailable.npc");
		}

		return array("name"=>"oath.name", "url"=>"bm2_site_politics_oath", "include"=>"hierarchy");
	}

	public function hierarchyCreateRealmTest() {
		if ($this->getCharacter()->isTrial()) {
			return array("name"=>"realm.new.name", "description"=>"unavailable.free");
		}
		if ($check = $this->politicsActionsGenericTests() !== true) {
			return array("name"=>"realm.new.name", "description"=>'unavailable.'.$check);
		}
		// create a new realm - only available if we are independent and don't yet have a realm
		if ($this->getCharacter()->findLiege()) {
			return array("name"=>"realm.new.name", "description"=>"unavailable.vassal");
		}
		if ($this->getCharacter()->isRuler()) {
			return array("name"=>"realm.new.name", "description"=>"unavailable.haverealm");
		}
		list($valid, $settlements) = $this->checkVassals($this->getCharacter());
		if (!$valid) {
			return array("name"=>"realm.new.name", "description"=>"unavailable.novassals");
		}
		if ($settlements < 2) {
			return array("name"=>"realm.new.name", "description"=>"unavailable.fewestates");
		}
		return array("name"=>"realm.new.name", "url"=>"bm2_site_realm_new", "description"=>"realm.new.description", "long"=>"realm.new.longdesc");
	}

	private function checkVassals(Character $char) {
		$valid = false;
		$settlements = $char->getOwnedSettlements()->count();
		foreach ($char->findVassals() as $vassal) {
			if ($vassal->getUser() != $char->getUser()) {
				$valid=true;
			}
		}
		return array($valid, $settlements);
	}

	public function hierarchyManageRealmTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"realm.manage.name", "description"=>"unavailable.$check");
		}
		if (!$this->realm->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"realm.manage.name", "description"=>"unavailable.notleader");
		} else {
			return $this->action("realm.manage", "bm2_site_realm_manage", true,
				array('realm'=>$this->realm->getId()),
				array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
			);
		}
	}

	public function hierarchyNewPlayerInfoTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"realm.newplayer.name", "description"=>"unavailable.$check");
		}
		if (!$this->realm->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"realm.newplayer.name", "description"=>"unavailable.notleader");
		} else {
			return $this->action("realm.newplayer", "bm2_site_realm_newplayer", true,
				array('realm'=>$this->realm->getId()),
				array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
			);
		}
	}

	public function hierarchyManageDescriptionTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"realm.description.name", "description"=>"unavailable.$check");
		}
		if (!$this->realm->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"realm.description.name", "description"=>"unavailable.notleader");
		} else {
			return $this->action("realm.description", "bm2_site_realm_description", true,
				array('realm'=>$this->realm->getId()),
				array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
			);
		}
	}

	public function hierarchyAbolishRealmTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"realm.abolish.name", "description"=>"unavailable.$check");
		}
		if (!$this->realm->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"realm.abolish.name", "description"=>"unavailable.notleader");
		} else {
			return $this->action("realm.abolish", "bm2_site_realm_abolish", true,
				array('realm'=>$this->realm->getId()),
				array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
			);
		}
	}

	public function hierarchyAbdicateTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"realm.abdicate.name", "description"=>"unavailable.$check");
		}
		if (!$this->realm->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"realm.abdicate.name", "description"=>"unavailable.notleader");
		} else {
			return $this->action("realm.abdicate", "bm2_site_realm_abdicate", true,
				array('realm'=>$this->realm->getId()),
				array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
			);
		}
	}

	public function hierarchyRealmLawsTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"realm.laws.name", "description"=>"unavailable.$check");
		}
		if (!$this->realm->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"realm.laws.name", "description"=>"unavailable.notleader");
		} else {
			return $this->action("realm.laws", "bm2_site_realm_laws", true,
				array('realm'=>$this->realm->getId()),
				array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
			);
		}
	}

	public function hierarchyRealmSpawnsTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"realm.spawns.name", "description"=>"unavailable.$check");
		}
		if (!$this->realm->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"realm.spawns.name", "description"=>"unavailable.notleader");
		} else {
			return $this->action("realm.spawns", "maf_realm_spawn", true,
				array('realm'=>$this->realm->getId()),
				array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
			);
		}
	}

	public function hierarchyRealmPositionsTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"realm.positions.name", "description"=>"unavailable.$check");
		}
		if (!$this->realm->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"realm.positions.name", "description"=>"unavailable.notleader");
		} else {
			return $this->action("realm.positions", "bm2_site_realm_positions", true,
				array('realm'=>$this->realm->getId()),
				array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
			);
		}
	}

	public function hierarchyWarTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"war.name", "description"=>"unavailable.$check");
		}
		if ( ! $this->permission_manager->checkRealmPermission($this->realm, $this->getCharacter(), 'diplomacy')) {
			return array("name"=>"war.name", "description"=>"unavailable.notdiplomat");
		} else {
			return $this->action("war", "bm2_site_war_declare", true,
				array('realm'=>$this->realm->getId()),
				array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
			);
		}
	}

	public function hierarchyDiplomacyTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"diplomacy.name", "description"=>"unavailable.$check");
		}
		if ( ! $this->permission_manager->checkRealmPermission($this->realm, $this->getCharacter(), 'diplomacy')) {
			return array("name"=>"diplomacy.name", "description"=>"unavailable.notdiplomat");
		} else {
			return $this->action("diplomacy", "bm2_site_realm_diplomacy", true,
				array('realm'=>$this->realm->getId()),
				array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
			);
		}
	}

	public function hierarchyElectionsTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"elections.name", "description"=>"unavailable.$check");
		}
		if (!$this->getCharacter()->findRealms()->contains($this->realm)) {
			return array("name"=>"elections.name", "description"=>"unavailable.notmember");
		}

		return $this->action("elections", "bm2_site_realm_elections", false,
			array('realm'=>$this->realm->getId()),
			array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
		);
	}

	public function hierarchySelectCapitalTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"realm.capital.name1", "description"=>"unavailable.$check");
		}
		if (!$this->realm->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"realm.capital.name1", "description"=>"unavailable.notleader");
		}

		return $this->action("realm.capital", "bm2_site_realm_capital", false,
			array('realm'=>$this->realm->getId()),
			array("%name%"=>$this->realm->getName(), "%formalname%"=>$this->realm->getFormalName())
		);
	}

	public function hierarchyIndependenceTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"rogue.name", "description"=>"unavailable.$check");
		}
		// break my oath and become independent
		if (!$this->getCharacter()->findAllegiance()) {
			return array("name"=>"rogue.name", "description"=>"unavailable.notvassal");
		}
		return $this->action("rogue", "bm2_site_politics_breakoath", true);
	}

	public function diplomacyRelationsTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"diplomacy.relations", "description"=>"unavailable.$check");
		}
		if ( ! $this->permission_manager->checkRealmPermission($this->realm, $this->getCharacter(), 'diplomacy')) {
			return array("name"=>"diplomacy.relations", "description"=>"unavailable.notdiplomat");
		}
		return $this->action("diplomacy.relations", "bm2_site_realm_relations", false, array('realm'=>$this->realm->getId()));
	}

	public function diplomacyHierarchyTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"diplomacy.change.name", "description"=>"unavailable.$check");
		}
		if ($this->realm->getSuperior()) {
			$name = "diplomacy.change.name";
			$desc = "diplomacy.change.description";
		} else {
			$name = "diplomacy.join.name";
			$desc = "diplomacy.join.description";
		}
		if ( ! $this->permission_manager->checkRealmPermission($this->realm, $this->getCharacter(), 'diplomacy')) {
			return array("name"=>$name, "description"=>"unavailable.notdiplomat");
		}
		return array("name"=>$name, "url"=>"bm2_site_realm_join", "parameters"=>array('realm'=>$this->realm->getId()), "description"=>$desc);
	}

	public function diplomacySubrealmTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"diplomacy.subrealm", "description"=>"unavailable.$check");
		}
		if ($this->realm->getType()<=1) {
			return array("name"=>"diplomacy.subrealm", "description"=>"unavailable.toolow");
		}
		if ($this->realm->getSettlements()->count() < 2) {
			return array("name"=>"diplomacy.subrealm", "description"=>"unavailable.toosmall");
		}
		if (!$this->realm->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"diplomacy.subrealm", "description"=>"unavailable.notleader");
		}
		return $this->action("diplomacy.subrealm", "bm2_site_realm_subrealm", true, array('realm'=>$this->realm->getId()));
	}

	public function diplomacyRestoreTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"diplomacy.restore", "description"=>"unavailable.$check");
		}
		if ($this->realm->getActive() != FALSE) {
			return array("name"=>"diplomacy.restore", "description"=>"unavailable.tooalive");
		}
		if (!$this->realm->getSuperior()->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"diplomacy.restore", "description"=>"unavailable.notsuperruler");
		}
		return $this->action("diplomacy.restore", "bm2_site_realm_restore", true, array('realm'=>$this->realm->getId()));
	}

	public function diplomacyBreakHierarchyTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"diplomacy.break", "description"=>"unavailable.$check");
		}
		if (!$this->realm->getSuperior()) {
			return array("name"=>"rogue.name", "description"=>"unavailable.nosuperior");
		}
		return $this->action("diplomacy.break", "bm2_site_realm_break", false, array('realm'=>$this->realm->getId()));
	}


	public function inheritanceSuccessorTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"successor.name", "description"=>"unavailable.npc");
		}
		return array("name"=>"successor.name", "url"=>"bm2_site_politics_successor", "description"=>"successor.description");
	}

	public function partnershipsTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"partner.name", "description"=>"unavailable.npc");
		}
		return array("name"=>"partners.name", "url"=>"bm2_site_politics_partners", "description"=>"");
	}

	/* ========== House Actions ========== */

	public function houseCreateHouseTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.new.name", "description"=>"unavailable.$check");
		}
		$character = $this->getCharacter();
		if ($character->getHouse()) {
			return array("name"=>"house.new.name", "description"=>"unavailable.havehouse");
		}
		if (!$character->getInsidePlace()) {
			return array("name"=>"house.new.name", "description"=>"unavailable.outsideplace");
		}
		if ($character->getInsidePlace() && $character->getInsidePlace()->getType()->getName() != "home") {
			return array("name"=>"house.new.name", "description"=>"unavailable.wrongplacetype");
		}
		if ($character->getInsidePlace() && $character->getInsidePlace()->getOwner() !== $character) {
			#TODO: Rework this for permissions when we add House permissions (if we do).
			return array("name"=>"house.manage.relocate.name", "description"=>"unavailable.notyours2");
		}
		return array("name"=>"house.new.name", "url"=>"maf_house_create", "description"=>"house.new.description", "long"=>"house.new.longdesc");
	}

	public function houseManageReviveTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.manage.revive.name", "description"=>"unavailable.$check");
		}
		if ($this->house && $this->house->getActive()) {
			return array("name"=>"house.manage.revive.name", "description"=>"unavailable.isactive");
		} else {
			return $this->action("house.manage.revive", "maf_house_revive", true,
				array('house'=>$this->house->getId()),
				array("%name%"=>$this->house->getName())
			);
		}
	}

	public function houseManageHouseTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.manage.house.name", "description"=>"unavailable.$check");
		}
		if ($this->house && $this->house->getHead() != $this->getCharacter()) {
			return array("name"=>"house.manage.house.name", "description"=>"unavailable.nothead");
		} else {
			return $this->action("house.manage.house", "maf_house_manage", true,
				array('house'=>$this->house->getId()),
				array("%name%"=>$this->house->getName())
			);
		}
	}

	public function houseJoinHouseTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.join.house.name", "description"=>"unavailable.$check");
		}
		if ($this->house) {
			return array("name"=>"house.join.house.name", "description"=>"unavailable.alreadyinhouse");
		}
		$character = $this->getCharacter();
		if (!$character->getInsideSettlement() AND !$character->getInsidePlace()) {
			return array("name"=>"house.new.name", "description"=>"unavailable.outsideall");
		}
		if (($character->getInsideSettlement() && $character->getInsideSettlement()->getHousesPresent()->isEmpty()) OR (!$character->getInsideSettlement() && $character->getInsidePlace() && !$character->getInsidePlace()->getHouse())) {
			return array("name"=>"house.join.name", "description"=>"unavailable.housenothere");
		} else {
			return $this->action("house.join.house", "maf_house_join", true);
		}
	}

	public function houseManageRelocateTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.manage.relocate.name", "description"=>"unavailable.$check");
		}
		if (!$this->house) {
			return array("name"=>"house.manage.relocate.name", "description"=>"unavailable.nohouse");
		}
		if ($this->house->getHead() != $this->getCharacter()) {
			return array("name"=>"house.manage.relocate.name", "description"=>"unavailable.nothead");
		}
		$character = $this->getCharacter();
		if (!$character->getInsidePlace()) {
			return array("name"=>"house.manage.relocate.name", "description"=>"unavailable.outsideplace");
		}
		if ($character->getInsidePlace() && $character->getInsidePlace()->getType()->getName() != "home") {
			return array("name"=>"house.manage.relocate.name", "description"=>"unavailable.wrongplacetype");
		}
		if ($character->getInsidePlace() && $character->getInsidePlace()->getOwner() != $this->getCharacter()) {
			#TODO: Rework this for permissions when we add House permissions (if we do).
			return array("name"=>"house.manage.relocate.name", "description"=>"unavailable.notyours2");
		}
		if ($character->getInsidePlace() == $this->house->getHome()) {
			return array("name"=>"house.manage.relocate.name", "description"=>"unavailable.househere");
		} else {
			return $this->action("house.manage.relocate", "maf_house_relocate", true,
				array('house'=>$this->house->getId()),
				array("%name%"=>$this->house->getName())
			);
		}
	}

	public function houseManageApplicantsTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.manage.applicants.name", "description"=>"unavailable.$check");
		}
		if (!$this->house) {
			return array("name"=>"house.manage.applicants.name", "description"=>"unavailable.nohouse");
		}
		if ($this->house->getHead() != $this->getCharacter()) {
			return array("name"=>"house.manage.applicants.name", "description"=>"unavailable.nothead");
		} else {
			return $this->action("house.manage.applicants", "maf_house_applicants", true,
				array('house'=>$this->house->getId()),
				array("%name%"=>$this->house->getName())
			);
		}
	}

	public function houseManageDisownTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.manage.disown.name", "description"=>"unavailable.$check");
		}
		if (!$this->house) {
			return array("name"=>"house.manage.disown.name", "description"=>"unavailable.nohouse");
		}
		if ($this->house->getHead() != $this->getCharacter()) {
			return array("name"=>"house.manage.disown.name", "description"=>"unavailable.nothead");
		} else {
			return $this->action("house.manage.disown", "maf_house_disown", true,
				array('house'=>$this->house->getId()),
				array("%name%"=>$this->house->getName())
			);
		}
	}

	public function houseManageSuccessorTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.manage.successor.name", "description"=>"unavailable.$check");
		}
		if (!$this->house) {
			return array("name"=>"house.manage.successor.name", "description"=>"unavailable.nohouse");
		}
		if ($this->house->getHead() != $this->getCharacter()) {
			return array("name"=>"house.manage.successor.name", "description"=>"unavailable.nothead");
		} else {
			return $this->action("house.manage.successor", "maf_house_successor", true,
				array('house'=>$this->house->getId()),
				array("%name%"=>$this->house->getName())
			);
		}
	}

	public function houseManageCadetTest($ignored, House $target) {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.cadet.name", "description"=>"unavailable.$check");
		}
		if (!$this->house) {
			return array("name"=>"house.cadet.name", "description"=>"unavailable.nohouse");
		}
		$char = $this->getCharacter();
		if ($this->house->getHead() != $char) {
			return array("name"=>"house.cadet.name", "description"=>"unavailable.nothead");
		}
		if ($this->house->getSuperior()) {
			return array("name"=>"house.cadet.name", "description"=>"unavailable.hassuperiorhouse");
		}

		$success = $this->action("house.cadet", "maf_house_cadetship", true,
			array('house'=>$this->house->getId()),
			array("%name%"=>$this->house->getName())
		);
		if (
			($target->getHome() && $char->getInsidePlace() == $target->getInsidePlace()) ||
			($char->getInsideSettlement() == $target->getInsideSettlement())
		) {
			return $success;
		} else {
			$nearby = $this->geography->findCharactersInActionRange($char);
			foreach ($nearby as $other) {
				if ($other[0] == $char) {
					return $success;
				}
			}
			return array("name"=>"house.cadet.name", "description"=>"unavailable.housenotnearby");
		}
	}

	public function houseManageUncadetTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.uncadet.name", "description"=>"unavailable.$check");
		}
		if (!$this->house) {
			return array("name"=>"house.uncadet.name", "description"=>"unavailable.nohouse");
		}
		if ($this->house->getHead() != $this->getCharacter()) {
			return array("name"=>"house.uncadet.name", "description"=>"unavailable.nothead");
		}
		if (!$this->house->getSuperior()) {
			return array("name"=>"house.uncadet.name", "description"=>"unavailable.nosuperiorhouse");
		} else {
			return $this->action("house.uncadet", "maf_house_uncadet", true,
				array('house'=>$this->house->getId()),
				array("%name%"=>$this->house->getName())
			);
		}
	}

	public function houseNewPlayerInfoTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.newplayer.name", "description"=>"unavailable.$check");
		}
		if (!$this->house) {
			return array("name"=>"house.newplayer.name", "description"=>"unavailable.nohouse");
		}
		if (!$this->house->getHome()) {
			return array("name"=>"house.newplayer.name", "description"=>"unavailable.nohome");
		}
		if ($this->house && $this->house->getHead() != $this->getCharacter()) {
			return array("name"=>"house.newplayer.name", "description"=>"unavailable.nothead");
		} else {
			return $this->action("house.newplayer", "maf_house_newplayer", true,
				array('house'=>$this->house->getId()),
				array("%name%"=>$this->house->getName())
			);
		}
	}

	public function houseSpawnToggleTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"house.spawntoggle.name", "description"=>"unavailable.$check");
		}
		if (!$this->house) {
			return array("name"=>"house.spawntoggle.name", "description"=>"unavailable.nohouse");
		}
		if (!$this->house->getHome()) {
			return array("name"=>"house.spawntoggle.name", "description"=>"unavailable.nohome");
		}
		if ($this->house && $this->house->getHead() != $this->getCharacter()) {
			return array("name"=>"house.spawntoggle.name", "description"=>"unavailable.nothead");
		} else {
			return $this->action("house.spawntoggle", "maf_house_spawn_toggle", true,
				array('house'=>$this->house->getId()),
				array("%name%"=>$this->house->getName())
			);
		}
	}

	/* ========== Meta Actions ========== */

	public function metaBackgroundTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"meta.background.name", "description"=>"unavailable.npc");
		}
		return array("name"=>"meta.background.name", "url"=>"bm2_site_character_background", "description"=>"meta.background.description");
	}

	public function metaRenameTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"meta.background.name", "description"=>"unavailable.npc");
		}
		return array("name"=>"meta.rename.name", "url"=>"bm2_site_character_rename", "description"=>"meta.rename.description");
	}

	public function metaLoadoutTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"meta.loadout.name", "description"=>"unavailable.npc");
		}
		return array("name"=>"meta.loadout.name", "url"=>"maf_character_loadout", "description"=>"meta.loadout.description");
	}

	public function metaSettingsTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"meta.background.name", "description"=>"unavailable.npc");
		}
		return array("name"=>"meta.settings.name", "url"=>"bm2_site_character_settings", "description"=>"meta.settings.description");
	}

	public function metaRetireTest() {
		if ($this->getCharacter()->isNPC()) {
			// FIXME: respawn template doesn't exist.
			return array("name"=>"meta.retire.name", "description"=>"unavailable.npc");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"meta.retire.name", "description"=>"unavailable.prisonershort");
		}
		return array("name"=>"meta.retire.name", "url"=>"bm2_site_character_retire", "description"=>"meta.retire.description");
	}

	public function metaKillTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"meta.kill.name", "description"=>"unavailable.npc");
		}
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"meta.kill.name", "description"=>"unavailable.prisonershort");
		}
		return array("name"=>"meta.kill.name", "url"=>"bm2_site_character_kill", "description"=>"meta.kill.description");
	}

	public function metaHeraldryTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"meta.background.name", "description"=>"unavailable.npc");
		}
		return array("name"=>"meta.heraldry.name", "url"=>"bm2_site_character_heraldry", "description"=>"meta.heraldry.description");
	}

	/*public function metaUnitSettingsTest() {
		# Not even sure there's a reason to have this here besides standardization. --Andrew
		return array("name"=>"meta.unitsettings.name", "url"=>"bm2_site_character_unitsettings", "description"=>"meta.unitsettings.description");
	}*/

	/* ========== Conversation Tests ========== */

	public function conversationListTest() {
		return ["name"=>"conv.list.name", "url"=>"maf_convs", "description"=>"conv.list.description"];
	}

	public function conversationSummaryTest() {
		return ["name"=>"conv.summary.name", "url"=>"maf_conv_summary", "description"=>"conv.summary.description"];
	}

	public function conversationRecentTest() {
		return ["name"=>"conv.recent.name", "url"=>"maf_conv_recent", "description"=>"conv.recent.description"];
	}

	public function conversationUnreadTest() {
		return ["name"=>"conv.unread.name", "url"=>"maf_conv_unread", "description"=>"conv.unread.description"];
	}

	public function conversationContactsTest() {
		return ["name"=>"conv.contacts.name", "url"=>"maf_conv_contacts", "description"=>"conv.unrcontactsead.description"];
	}

	public function conversationNewTest() {
		return ["name"=>"conv.new.name", "url"=>"maf_conv_new", "description"=>"conv.new.description"];
	}

	public function conversationLocalTest($ignored, Conversation $conv=null) {
		if ($conv && $conv->getLocalFor() != $this->getCharacter()) {
			return ["name"=>"conv.local.name", "description"=>"unavailable.conv.nopermission"];
		}
		return ["name"=>"conv.local.name", "url"=>"maf_conv_local", "description"=>"conv.new.description"];
	}

	public function conversationLocalRemoveTest($ignored, Message $msg) {
		if ($msg->getConversation()->getLocalFor() != $this->getCharacter()) {
			return ["name"=>"conv.localremove.name", "description"=>"unavailable.conv.nopermission"];
		}
		return ["name"=>"conv.localremove.name", "url"=>"maf_conv_local_remove", "description"=>"conv.localremove.description"];
	}

	public function conversationSingleTest($ignored, Conversation $conv) {
		if ($conv->findCharPermissions($this->getCharacter())->isEmpty()) {
			return ["name"=>"conv.read.name", "description"=>"unavailable.conv.nopermission"];
		}
		return ["name"=>"conv.read.name", "url"=>"maf_conv_read", "description"=>"conv.read.description"];
	}

	public function conversationManageTest($ignored, Conversation $conv) {
		if ($conv->getLocalFor()) {
			return ["name"=>"conv.manage.name", "description"=>"unavailable.conv.islocal"];
		}
		if ($conv->findCharPermissions($this->getCharacter())->isEmpty()) {
			return ["name"=>"conv.manage.name", "description"=>"unavailable.conv.nopermission"];
		}
		return ["name"=>"conv.manage.name", "url"=>"maf_conv_participants", "description"=>"conv.manage.description"];
	}

	public function conversationChangeTest($ignored, Conversation $conv) {
		if ($conv->getLocalFor()) {
			return ["name"=>"conv.change.name", "description"=>"unavailable.conv.islocal"];
		}
		if ($conv->findCharPermissions($this->getCharacter())->isEmpty()) {
			return ["name"=>"conv.change.name", "description"=>"unavailable.conv.nopermission"];
		}
		if ($conv->getRealm()) {
			return ["name"=>"conv.change.name", "description"=>"unavailable.conv.ismanaged"];
		}
		$perm = $conv->findActiveCharPermission($this->getCharacter());
		if (!$perm->getManager() AND !$perm->getOwner()) {
			return ["name"=>"conv.change.name", "description"=>"unavailable.conv.notmanager"];
		}
		return ["name"=>"conv.change.name", "url"=>"maf_conv_participants", "description"=>"conv.change.description"];
	}

	public function conversationLeaveTest($ignored, Conversation $conv) {
		if ($conv->getLocalFor()) {
			return ["name"=>"conv.leave.name", "description"=>"unavailable.conv.islocal"];
		}
		if ($conv->getRealm()) {
			return ["name"=>"conv.leave.name", "description"=>"unavailable.conv.ismanaged"];
		}
		if ($conv->findCharPermissions($this->getCharacter())->isEmpty()) {
			return ["name"=>"conv.leave.name", "description"=>"unavailable.conv.nopermission"];
		}
		$perm = $conv->findActiveCharPermission($this->getCharacter());
		if (!$perm) {
			return ["name"=>"conv.leave.name", "description"=>"unavailable.conv.notactive"];
		}
		return ["name"=>"conv.leave.name", "url"=>"maf_conv_leave", "description"=>"conv.leave.description"];
	}

	public function conversationRemoveTest($ignored, Conversation $conv) {
		if ($conv->getLocalFor()) {
			return ["name"=>"conv.remove.name", "description"=>"unavailable.conv.islocal"];
		}
		if ($conv->getRealm()) {
			return ["name"=>"conv.remove.name", "description"=>"unavailable.conv.ismanaged"];
		}
		if ($conv->findCharPermissions($this->getCharacter())->isEmpty()) {
			return ["name"=>"conv.remove.name", "description"=>"unavailable.conv.nopermission"];
		}
		return ["name"=>"conv.remove.name", "url"=>"maf_conv_leave", "description"=>"conv.remove.description"];
	}

	public function conversationAddTest($ignored, Conversation $conv) {
		if ($conv->findCharPermissions($this->getCharacter())->isEmpty()) {
			return ["name"=>"conv.add.name", "description"=>"unavailable.conv.nopermission"];
		}
		if ($conv->getRealm()) {
			return ["name"=>"conv.add.name", "description"=>"unavailable.conv.ismanaged"];
		}
		$perm = $conv->findActiveCharPermission($this->getCharacter());
		if (!$perm->getManager() AND !$perm->getOwner()) {
			return ["name"=>"conv.add.name", "description"=>"unavailable.conv.notmanager"];
		}
		return ["name"=>"conv.add.name", "url"=>"maf_conv_read", "description"=>"conv.change.description"];
	}

	public function conversationReplyTest($ignored, Conversation $conv) {
		if ($conv->findCharPermissions($this->getCharacter())->isEmpty() && $conv->getLocalFor() != $this->getCharacter()) {
			return ["name"=>"conv.reply.name", "description"=>"unavailable.conv.nopermission"];
		}
		return ["name"=>"conv.reply.name", "url"=>"maf_conv_change", "description"=>"conv.reply.description"];
	}

	public function conversationLocalReplyTest() {
		return ["name"=>"conv.localreply.name", "url"=>"maf_conv_local_reply", "description"=>"conv.localreply.description"];
	}

	/* ========== various tests and helpers ========== */

	public function getActionableSettlement() {
		if (is_object($this->actionableSettlement) || $this->actionableSettlement===null) return $this->actionableSettlement;

		$this->actionableSettlement=null;
		if ($this->getCharacter()) {
			if ($this->getCharacter()->getInsideSettlement()) {
				$this->actionableSettlement = $this->getCharacter()->getInsideSettlement();
			} else if ($location=$this->getCharacter()->getLocation()) {
				$nearest = $this->geography->findNearestSettlement($this->getCharacter());
				$settlement=array_shift($nearest);
				if ($nearest['distance'] < $this->geography->calculateActionDistance($settlement)) {
					$this->actionableSettlement=$settlement;
				}
			}
		}
		return $this->actionableSettlement;
	}

	public function getLeaveableSettlement() {
		if ($this->getCharacter()->getInsideSettlement()) {
			return $this->getCharacter()->getInsideSettlement();
		}
	}

	public function getActionablePlace() {
		if (is_object($this->actionablePlace) || $this->actionablePlace===null) return $this->actionablePlace;

		$this->actionablePlace=null;
		if ($this->getCharacter()) {
			if ($this->getCharacter()->getInsidePlace()) {
				$this->actionablePlace = $this->getCharacter()->getInsidePlace();
			} else if ($location=$this->getCharacter()->getLocation()) {
				$nearest = $this->geography->findNearestPlace($this->getCharacter());
				if ($nearest) {
					$place=array_shift($nearest);
					if ($nearest['distance'] < $this->geography->calculatePlaceActionDistance($place)) {
						$this->actionablePlace=$place;
					}
				}
			}
		}
		return $this->actionablePlace;
	}

	public function getLeaveablePlace() {
		if ($this->getCharacter() && $this->getCharacter()->getInsidePlace()) {
			return $this->getCharacter()->getInsidePlace();
		} else {
			return false;
		}
	}

	public function getActionableRegion() {
		if (is_object($this->actionableRegion) || $this->actionableRegion===null) return $this->actionableRegion;

		$this->actionableRegion = $this->geography->findMyRegion($this->getCharacter());
		return $this->actionableRegion;
	}

	public function getActionableCharacters($match_battle = false) {
		if (!$this->getCharacter()) {
			throw new AccessDeniedHttpException('error.nocharacter');
		}
		if ($settlement = $this->getCharacter()->getInsideSettlement()) {
			// initially, this was all restricted to characters inside the settlement, but that makes attacks towards the outside, etc. impossible,
			// and since we don't have a "leave settlement" action...
			// FIXME: it should contain both - inside settlement and in action range
			// FIXME: anyway this doesn't work and those outside are excluded
//			return $this->geography->findCharactersInSettlement($settlement, $this->getCharacter());
			return $this->geography->findCharactersInActionRange($this->getCharacter(), false, $match_battle);
		} else {
			return $this->geography->findCharactersInActionRange($this->getCharacter(), true, $match_battle);
		}
	}

	public function getActionableDock() {
		if (is_object($this->actionableDock) || $this->actionableDock===null) return $this->actionableDock;

		$this->actionableDock=null;
		if ($this->getCharacter() && $location=$this->getCharacter()->getLocation()) {
			$nearest = $this->geography->findNearestDock($this->getCharacter());
			if (!$nearest) {
				return null;
			}
			$dock=array_shift($nearest);
			if ($nearest['distance'] < $this->geography->calculateInteractionDistance($this->getCharacter())) {
				$this->actionableDock=$dock;
			}
		}
		return $this->actionableDock;
	}

	public function getActionableShip() {
		if (is_object($this->actionableShip) || $this->actionableShip===null) return $this->actionableShip;
		$this->actionableShip=null;
		if ($this->getCharacter() && $location=$this->getCharacter()->getLocation()) {
			$nearest = $this->geography->findMyShip($this->getCharacter());
			$ship=array_shift($nearest);
			if ($ship && $nearest['distance'] < $this->geography->calculateInteractionDistance($this->getCharacter())) {
				$this->actionableShip=$ship;
			}
		}
		return $this->actionableShip;
	}

	public function getActionableHouses() {
		if (is_object($this->actionableHouses) || $this->actionableHouses===null) return $this->actionableHouses;
		$this->actionableHouses=null;

		if ($this->getCharacter() && $this->getCharacter()->getInsideSettlement()) {
			$this->actionableHouses = $this->getCharacter()->getInsideSettlement()->getHousesPresent();
		} else {
			# Code for being outside settlement will go here and interact with Places.
		}
		return $this->actionableHouses;
	}



	private function action($trans, $url, $with_long=false, $parameters=null, $transkeys=null) {
		$data = array(
			"name"			=> $trans.'.name',
			"url"				=> $url,
			"description"	=> $trans.'.description'
		);
		if ($with_long) {
			$data['long'] = $trans.'.longdesc';
		}
		if ($parameters!=null) {
			$data['parameters'] = $parameters;
		}
		if ($transkeys!=null) {
			$data['transkeys'] = $transkeys;
		}
		return $data;
	}

}
