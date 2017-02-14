<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Settlement;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


/*
TODO:
refactor to use $this->action() everywhere (with some exceptions where it doesn't work)
*/

class Dispatcher {

	const FREE_ACCOUNT_ESTATE_LIMIT = 3;

	private $character=false;
	private $realm;
	private $settlement;
	private $appstate;
	private $permission_manager;
	private $geography;
	private $military;
	private $interactions;

	// test results to store because they are expensive to calculate
	private $actionableSettlement=false;
	private $actionableRegion=false;
	private $actionableDock=false;
	private $actionableShip=false;

	public function __construct(AppState $appstate, PermissionManager $pm, Geography $geo, Military $military, Interactions $interactions) {
		$this->appstate = $appstate;
		$this->permission_manager = $pm;
		$this->geography = $geo;
		$this->military = $military;
		$this->interactions = $interactions;
	}

	public function getCharacter() {
		if ($this->character) return $this->character;
		return $this->appstate->getCharacter();
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

	public function clear() {
		$this->character=false;
		$this->realm=false;
		$this->actionableSettlement=false;
		$this->actionableDock=false;
		$this->actionableShip=false;
	}

	/*
		this is our main entrance, fetching the character data from the appstate as well as the nearest settlement
		and then applying any (optional) test on the whole thing.
	*/
	public function gateway($test=false, $getSettlement=false, $check_duplicate=true) {
		if (!$this->getCharacter()) {
			throw new AccessDeniedHttpException('error.nocharacter');
		}
		$settlement = null;
		if ($test || $getSettlement) {
			if ($test) {
				$test = $this->$test($check_duplicate);
				if (!isset($test['url'])) {
					throw new AccessDeniedHttpException("messages::unavailable.intro::".$test['description']);
				}
			}
			if ($getSettlement) {
				$settlement = $this->getActionableSettlement();
			}
		}
		if ($getSettlement) {
			return array($this->getCharacter(), $settlement);
		} else {
			return $this->getCharacter();
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
		if ($place = $this->getActionableSettlement()) {
			$actions[] = $this->locationEnterTest(true);
		} else {
			$actions[] = array("name"=>"location.enter.name", "description"=>"unavailable.nosettlement");
		}

		if ($this->getLeaveableSettlementTest()) {
			$actions[] = $this->locationLeaveTest(true);
		} else {
			$actions[] = array("name"=>"location.exit.name", "description"=>"unavailable.inside");
		}

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
		$has = $this->locationInnTest();
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
		$has = $this->locationLendanTowerTest();
		if (isset($has['url'])) {
			$actions[] = $this->action("building.lendantower", "bm2_site_building_lendantower");
		}

		return array("name"=>"building.title", "elements"=>$actions);
	}

	public function locationTavernTest() { return $this->locationHasBuildingTest("Tavern"); }
	public function locationInnTest() { return $this->locationHasBuildingTest("Inn"); }
	public function locationLibraryTest() { return $this->locationHasBuildingTest("Library"); }
	public function locationTempleTest() { return $this->locationHasBuildingTest("Temple"); }
	public function locationBarracksTest() { return $this->locationHasBuildingTest("Barracks"); }
	public function locationLendanTowerTest() { return $this->locationHasBuildingTest("Inn"); } // FIXME: set right

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

		$actions=array();
		$actions[] = $this->controlTakeTest(true);

		if (!$this->getCharacter()->getInsideSettlement()) {
			$actions[] = array("name"=>"control.all", "description"=>"unavailable.notinside");
		} else {
			$actions[] = $this->controlChangeRealmTest(true);
			$actions[] = $this->controlGrantTest(true);
			$actions[] = $this->controlRenameTest(true);
			$actions[] = $this->controlCultureTest(true);
			$actions[] = $this->controlPermissionsTest();
			$actions[] = $this->controlQuestsTest();
		}

		return array("name"=>"control.name", "elements"=>$actions);
	}

	private function controlActionsGenericTests() {
		if (!$place = $this->getActionableSettlement()) {
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
		if ($this->getCharacter()->getActiveSoldiers()->isEmpty()) {
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
		if ($place = $this->getActionableSettlement()) {
			$actions[] = $this->militaryAttackSettlementTest(true);
			$actions[] = $this->militaryDefendSettlementTest(true);
		} else {
			$actions[] = array("name"=>"military.other", "description"=>"unavailable.nosettlement");
		}

		return array("name"=>"military.name", "elements"=>$actions);
	}

	public function economyActions() {
		$place = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->economyActionsGenericTests($place)) !== true) {
			return array("name"=>"economy.name", "elements"=>array(array("name"=>"economy.all", "description"=>"unavailable.$check")));
		}

		$actions=array();
		$actions[] = $this->economyTradeTest();

		if ($this->permission_manager->checkSettlementPermission($place, $this->getCharacter(), 'construct')) {
			$actions[] = $this->economyRoadsTest();
			$actions[] = $this->economyFeaturesTest();
			$actions[] = $this->economyBuildingsTest();
		} else {
			$actions[] = array("name"=>"economy.others", "description"=>"unavailable.notyours");
		}


		return array("name"=>"economy.name", "elements"=>$actions);
	}

	private function economyActionsGenericTests(Settlement $place=null) {
		if (!$place) {
			return 'notinside';
		}
		return $this->veryGenericTests();
	}

	public function personalActions() {
		$actions=array();
		if ($this->getCharacter()->getUser()->getRestricted()) {
			return array("name"=>"recruit.name", "elements"=>array(array("name"=>"recruit.all", "description"=>"unavailable.restricted")));
		}
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"recruit.name", "description"=>"unavailable.npc");
		}
		if (! $place = $this->getCharacter()->getInsideSettlement()) {
			$actions[] = array("name"=>"recruit.all", "description"=>"unavailable.notinside");
		} else {
		if ($this->permission_manager->checkSettlementPermission($place, $this->getCharacter(), 'recruit')) {
				$actions[] = $this->personalEntourageTest();
				$actions[] = $this->personalSoldiersTest();
				$actions[] = $this->personalMilitiaTest();
				$actions[] = $this->personalOffersTest();
			} else {
				$actions[] = array("name"=>"recruit.all", "description"=>"unavailable.notyours");
			}
		}

		$actions[] = $this->personalAssignedSoldiersTest();

		return array("name"=>"recruit.name", "elements"=>$actions);
	}

	private function personalActionsGenericTests(Settlement $place=null, $test='recruit') {
		if ($this->getCharacter()->isNPC()) {
			return 'npc';
		}
		if (!$place) {
			return 'notinside';
		}
		if (!$this->permission_manager->checkSettlementPermission($place, $this->getCharacter(), $test)) {
			return 'notyours';
		}

		return $this->veryGenericTests();
	}

	/* ========== Politics Dispatchers ========== */

	public function RelationsActions() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"relations.name", "intro"=>"relations.intro", "elements"=>array("name"=>"relations.all", "description"=>"unavailable.npc"));
		}

		$actions=array();

		if ($this->getCharacter()->getLiege()) {
			$actions[] = array("name"=>"oath.view.name", "url"=>"bm2_site_politics_hierarchy", "description"=>"oath.view.description", "long"=>"oath.view.longdesc");
		}
		if ($this->getCharacter()->getVassals()) {
			$actions[] = array("name"=>"vassals.view.name", "url"=>"bm2_site_politics_vassals", "description"=>"vassals.view.description", "long"=>"vassals.view.longdesc");			
		}
		$actions[] = $this->hierarchyOathTest();
		$actions[] = $this->hierarchyIndependenceTest();

		return array("name"=>"relations.name", "intro"=>"relations.intro", "elements"=>$actions);
	}

	public function PoliticsActions() {
		$actions=array();
		$actions[] = $this->personalPrisonersTest();
		$actions[] = $this->personalClaimsTest();
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			$actions[] = array("name"=>"politics.all", "description"=>"unavailable.$check");
			return array("name"=>"politics.name", "intro"=>"politics.intro", "elements"=>$actions);
		}

		$actions[] = $this->hierarchyCreateRealmTest();
		foreach ($this->getCharacter()->findRealms() as $realm) {
			$this->setRealm($realm);
			$actions[] = array("title"=>$realm->getFormalName());
			$actions[] = array("name"=>"realm.view.name", "url"=>"bm2_site_realm_hierarchy", "parameters"=>array("realm"=>$realm->getId()), "description"=>"realm.view.description", "long"=>"realm.view.longdesc");
			$actions[] = $this->hierarchyManageRealmTest();
			$actions[] = $this->hierarchyAbdicateTest();
			$actions[] = $this->hierarchyRealmPositionsTest();
			$actions[] = $this->hierarchyRealmLawsTest();
			$actions[] = $this->hierarchyWarTest();
			$actions[] = $this->hierarchyDiplomacyTest();
			$actions[] = $this->hierarchyElectionsTest();
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



	/* ========== Meta Dispatchers ========== */

	public function metaActions() {
		$actions=array();

		if ($this->getCharacter()->isNPC()) {
			$actions[] = $this->metaKillTest();
		} else {
			$actions[] = $this->metaBackgroundTest();
			$actions[] = $this->metaRenameTest();
			$actions[] = $this->metaKillTest();
			if ($this->getCharacter()->getUser()->getCrests()) {
				$actions[] = $this->metaHeraldryTest();
			}
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
		if (!$place = $this->getActionableSettlement()) {
			return array("name"=>"location.enter.name", "description"=>"unavailable.nosettlement");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.enter')) {
			return array("name"=>"location.enter.name", "description"=>"unavailable.already");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"location.enter.name", "description"=>"unavailable.inbattle");
		}

		if ($this->getCharacter()->isPrisoner()) {
			if ($place->getOwner() == $this->getCharacter()) {
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
		if (!$place = $this->getActionableSettlement()) {
			return array("name"=>"location.exit.name", "description"=>"unavailable.nosettlement");
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
		if (!$place = $this->getActionableSettlement()) {
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
		if (!$place = $this->getActionableSettlement()) {
			return array("name"=>"control.take.name", "description"=>"unavailable.nosettlement");
		}
		if ($place->isFortified() && $this->getCharacter()->getInsideSettlement()!=$place) {
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

		if ($this->getCharacter()->isTrial() && $this->getCharacter()->getEstates()->count() >= Dispatcher::FREE_ACCOUNT_ESTATE_LIMIT) {
			return array("name"=>"control.take.name", "description"=>"unavailable.free2");
		}

		if ($place->getOwner() == $this->getCharacter()) {
			// I control this settlement - defend if applicable
			if ($place->getRelatedActions()->exists(
				function($key, $element) { return $element->getType() == 'settlement.take'; }
			)) {
				return $this->action("control.takeX", "bm2_site_actions_take");
			} else {
				return array("name"=>"control.take.name", "description"=>"unavailable.location.yours");
			}
		} elseif ($place->getOwner()) {
			// someone else controls this settlement
			// TODO: different text?
			return $this->action("control.take", "bm2_site_actions_take");
		} else {
			// uncontrolled settlement
			return $this->action("control.take", "bm2_site_actions_take");
		}
	}

	public function controlChangeRealmTest($check_duplicate=false) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.changerealm.name", "description"=>"unavailable.$check");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.changerealm')) {
			return array("name"=>"control.changerealm.name", "description"=>"unavailable.already");
		}
		// FIXME: this still sometimes gives a "you are not inside" message when it shouldn't, I think?
		if ($this->settlement) {
			$place = $this->settlement;
		} else {
			$place = $this->getCharacter()->getInsideSettlement();
		}
		if (!$place) {
			return array("name"=>"control.changerealm.name", "description"=>"unavailable.notsettlement");
		}
		if ($place->getOwner() != $this->getCharacter()) {
			return array("name"=>"control.changerealm.name", "description"=>"unavailable.notyours2");
		}

		$myrealms = $this->getCharacter()->findRealms();
		if ($myrealms->isEmpty()) {
			return array("name"=>"control.changerealm.name", "description"=>"unavailable.norealms");
		}
		return $this->action("control.changerealm", "bm2_site_actions_changerealm", false, array('id'=>$place->getId()));
	}

	public function controlGrantTest($check_duplicate=false) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.grant.name", "description"=>"unavailable.$check");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.grant')) {
			return array("name"=>"control.grant.name", "description"=>"unavailable.already");
		}
		if (!$place = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"control.grant.name", "description"=>"unavailable.nosettlement");
		}
		if ($place->getOwner() != $this->getCharacter()) {
			return array("name"=>"control.grant.name", "description"=>"unavailable.notyours2");
		}
		if (!$this->getActionableCharacters()) {
			return array("name"=>"control.grant.name", "description"=>"unavailable.nobody");
		}
		return $this->action("control.grant", "bm2_site_actions_grant");
	}


	public function controlRenameTest($check_duplicate=false) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.rename.name", "description"=>"unavailable.$check");
		}
		if (!$place = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"control.rename.name", "description"=>"unavailable.nosettlement");
		}
		if ($place->getOwner() == $this->getCharacter()) {
			return $this->action("control.rename", "bm2_site_actions_rename");
		} else {
			return array("name"=>"control.rename.name", "description"=>"unavailable.notyours2");
		}
	}

	public function controlCultureTest($check_duplicate=false) {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.culture.name", "description"=>"unavailable.$check");
		}
		if (!$place = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"control.culture.name", "description"=>"unavailable.nosettlement");
		}
		if ($place->getOwner() == $this->getCharacter()) {
			return $this->action("control.culture", "bm2_site_actions_changeculture");               
		} else {
			return array("name"=>"control.culture.name", "description"=>"unavailable.notyours2");
		}
	}

	public function controlPermissionsTest() {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.permissions.name", "description"=>"unavailable.$check");
		}
		$place = $this->getCharacter()->getInsideSettlement();
		if ($place->getOwner() == $this->getCharacter()) {
			return $this->action("control.permissions", "bm2_site_settlement_permissions", false, array('id'=>$place->getId()));
		} else {
			return array("name"=>"control.permissions.name", "description"=>"unavailable.notyours2");
		}
	}

	public function controlQuestsTest() {
		if (($check = $this->controlActionsGenericTests()) !== true) {
			return array("name"=>"control.quests.name", "description"=>"unavailable.$check");
		}
		$place = $this->getCharacter()->getInsideSettlement();
		if ($place->getOwner() == $this->getCharacter()) {
			return $this->action("control.quests", "bm2_site_settlement_quests", false, array('id'=>$place->getId()));
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
		if ($this->getCharacter()->getActiveSoldiers()->isEmpty()) {
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

	public function militaryDefendSettlementTest($check_duplicate=false) {
		if ($this->getCharacter()->isPrisoner()) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.prisoner");
		}
		if ($check_duplicate && $this->getCharacter()->isDoingAction('settlement.defend')) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.already");
		}
		if ( ! $place = $this->getCharacter()->getInsideSettlement()) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.notinside");
		}
		if ($this->getCharacter()->isDoingAction('settlement.attack')) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.both");
		}
		if ($this->getCharacter()->isDoingAction('military.evade')) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.evading");
		}
		if ($this->getCharacter()->getActiveSoldiers()->isEmpty()) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.nosoldiers");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.settlement.defend.name", "description"=>"unavailable.inbattle");			
		}
		return $this->action("military.settlement.defend", "bm2_site_war_defendsettlement");
	}

	public function militaryAttackSettlementTest($check_duplicate=false) {
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
		if (!$place = $this->getActionableSettlement()) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.nosettlement");
		}
		if ($place->getOwner() == $this->getCharacter()) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.location.yours");
		}
		if (!$place->isDefended()) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.location.nodefenders");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.inbattle");			
		}
		if ($this->getCharacter()->DaysInGame()<2) {
			return array("name"=>"military.settlement.attack.name", "description"=>"unavailable.fresh");
		}
		return $this->action("military.settlement.attack", "bm2_site_war_attacksettlement");
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
		if ($this->getCharacter()->getActiveSoldiers()->isEmpty()) {
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
		if ($this->getCharacter()->getActiveSoldiers()->isEmpty()) {
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
		$place = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->personalActionsGenericTests($place)) !== true) {
			return array("name"=>"recruit.entourage.name", "description"=>"unavailable.$check");
		}

		return $this->action("recruit.entourage", "bm2_site_actions_entourage");
	}

	public function personalSoldiersTest() {
		$place = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->personalActionsGenericTests($place)) !== true) {
			return array("name"=>"recruit.troops.name", "description"=>"unavailable.$check");
		}
		$available = $this->military->findAvailableEquipment($place, true);
		if (empty($available)) {
			return array("name"=>"recruit.troops.name", "description"=>"unavailable.notrain");			
		}

		return $this->action("recruit.troops", "bm2_site_actions_soldiers");
	}

	public function personalMilitiaTest() {
		$place = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->personalActionsGenericTests($place, 'mobilize')) !== true) {
			return array("name"=>"recruit.militia.name", "description"=>"unavailable.$check");
		}
		if ($place->getSoldiers()->isEmpty()) {
			return array("name"=>"recruit.militia.name", "description"=>"unavailable.nomilitia");
		}

		return $this->action("recruit.militia", "bm2_site_settlement_soldiers", false, array('id'=>$place->getID()));
	}

	public function personalOffersTest() {
		$place = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->personalActionsGenericTests($place, 'mobilize')) !== true) {
			return array("name"=>"recruit.offers.name", "description"=>"unavailable.$check");
		}
		if ($place->getOwner() != $this->getCharacter()) {
			return array("name"=>"recruit.offers.name", "description"=>"unavailable.notyours2");
		}
		if ($place->getSoldiers()->isEmpty()) {
			return array("name"=>"recruit.offers.name", "description"=>"unavailable.nooffers");
		}

		return $this->action("recruit.offers", "bm2_site_actions_offers", false, array('id'=>$place->getID()));
	}

	public function personalAssignedSoldiersTest() {
		if ($this->getCharacter()->getSoldiersGiven()->isEmpty()) {
			return array("name"=>"recruit.assigned.name", "description"=>"unavailable.noassigned");
		}
		if ($this->getCharacter()->isInBattle()) {
			return array("name"=>"recruit.assigned.name", "description"=>"unavailable.inbattle");
		}

		return $this->action("recruit.assigned", "bm2_site_actions_assigned");

	}
	/* ========== Economy Actions ========== */

	public function economyTradeTest() {
		$place = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->economyActionsGenericTests($place)) !== true) {
			return array("name"=>"economy.trade.name", "description"=>"unavailable.$check");
		}

		// TODO: need a merchant in your entourage for trade options? or just foreign trade?

		if ($this->permission_manager->checkSettlementPermission($place, $this->getCharacter(), 'trade')) {
			return array("name"=>"economy.trade.name", "url"=>"bm2_site_actions_trade", "description"=>"economy.trade.owner");
		} else {
			if ($this->getCharacter()->getEstates()->isEmpty()) {
				return array("name"=>"economy.trade.name", "description"=>"unavailable.trade.noestate");
			}
			return array("name"=>"economy.trade.name", "url"=>"bm2_site_actions_trade", "description"=>"economy.trade.foreign");
		}
	}

	public function economyRoadsTest() {
		$place = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->economyActionsGenericTests($place)) !== true) {
			return array("name"=>"economy.roads.name", "description"=>"unavailable.$check");
		}
		if ( ! $this->permission_manager->checkSettlementPermission($place, $this->getCharacter(), 'construct')) {
			return array("name"=>"economy.roads.name", "description"=>"unavailable.notyours");
		}

		return $this->action("economy.roads", "bm2_site_construction_roads");
	}

	public function economyFeaturesTest() {
		$place = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->economyActionsGenericTests($place)) !== true) {
			return array("name"=>"economy.features.name", "description"=>"unavailable.$check");
		}
		if ( ! $this->permission_manager->checkSettlementPermission($place, $this->getCharacter(), 'construct')) {
			return array("name"=>"economy.features.name", "description"=>"unavailable.notyours");
		}

		return array("name"=>"economy.features.name", "url"=>"bm2_site_construction_features", "description"=>"economy.features.description");
	}

	public function economyBuildingsTest() {
		$place = $this->getCharacter()->getInsideSettlement();
		if (($check = $this->economyActionsGenericTests($place)) !== true) {
			return array("name"=>"economy.build.name", "description"=>"unavailable.$check");
		}
		if ( ! $this->permission_manager->checkSettlementPermission($place, $this->getCharacter(), 'construct')) {
			return array("name"=>"economy.build.name", "description"=>"unavailable.notyours");
		}

		return array("name"=>"economy.build.name", "url"=>"bm2_site_construction_buildings", "description"=>"economy.build.description");
	}


	/* ========== Political Actions ========== */


	public function hierarchyOathTest() {
		// swear an oath of fealty - only available if we don't lead a realm (if we do, similar actions are under realm management)
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"oath.name", "include"=>"hierarchy", "description"=>"unavailable.npc");
		}
		if ($this->getCharacter()->isRuler()) {
			return array("name"=>"oath.name", "include"=>"hierarchy", "description"=>"unavailable.leader");
		}
		if (!$this->getActionableCharacters()) {
			return array("name"=>"oath.name", "include"=>"hierarchy", "description"=>"unavailable.noothers");
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
		if ($this->getCharacter()->getLiege()) {
			return array("name"=>"realm.new.name", "description"=>"unavailable.vassal");
		}
		if ($this->getCharacter()->isRuler()) {
			return array("name"=>"realm.new.name", "description"=>"unavailable.haverealm");
		}
		list($valid, $estates) = $this->checkVassals($this->getCharacter());
		if (!$valid) {
			return array("name"=>"realm.new.name", "description"=>"unavailable.novassals");
		}
		if ($estates < 2) {
			return array("name"=>"realm.new.name", "description"=>"unavailable.fewestates");
		}
		return array("name"=>"realm.new.name", "url"=>"bm2_site_realm_new", "description"=>"realm.new.description", "long"=>"realm.new.longdesc");
	}

	private function checkVassals(Character $char) {
		$valid = false;
		$estates = $char->getEstates()->count();
		foreach ($char->getVassals() as $vassal) {
			if ($vassal->getUser() != $char->getUser()) {
				$valid=true;
			}
			list($v, $e) = $this->checkVassals($vassal);
			if ($v) {
				$valid = true;
			}
			$estates += $e;
		}
		return array($valid, $estates);		
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

	public function hierarchyIndependenceTest() {
		if (($check = $this->politicsActionsGenericTests()) !== true) {
			return array("name"=>"rogue.name", "description"=>"unavailable.$check");
		}
		// break my oath and become independent
		if (!$this->getCharacter()->getLiege() || $this->getCharacter()->isRuler()) {
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
		if ($this->realm->getEstates()->count() < 2) {
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
		if ($this->realm->getInferiors()->count() > 0) {
			return array("name"=>"diplomacy.restore", "description"=>"unavailable.nosubrealms");
		}
		if ($this->realm->findDeadInferiors()->count() == 0) {
			return array("name"=>"diplomacy.restore", "description"=>"unavailable.tooalive");
		}
		if (!$this->realm->findRulers()->contains($this->getCharacter())) {
			return array("name"=>"diplomacy.restore", "description"=>"unavailable.notleader");
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

	public function metaKillTest() {
		if ($this->getCharacter()->isNPC()) {
			// FIXME: respawn template doesn't exist.
			return array("name"=>"meta.background.name", "description"=>"unavailable.npc");
			return array("name"=>"meta.respawn.name", "url"=>"bm2_site_character_respawn", "description"=>"meta.respawn.description");
		}
		return array("name"=>"meta.kill.name", "url"=>"bm2_site_character_kill", "description"=>"meta.kill.description");
	}

	public function metaHeraldryTest() {
		if ($this->getCharacter()->isNPC()) {
			return array("name"=>"meta.background.name", "description"=>"unavailable.npc");
		}
		return array("name"=>"meta.heraldry.name", "url"=>"bm2_site_character_heraldry", "description"=>"meta.heraldry.description");
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

	public function getActionableRegion() {
		if (is_object($this->actionableRegion) || $this->actionableRegion===null) return $this->actionableRegion;

		$this->actionableRegion = $this->geography->findMyRegion($this->getCharacter());
		return $this->actionableRegion;
	}

	public function getActionableCharacters($match_battle = false) {
		if (!$this->getCharacter()) {
			throw new AccessDeniedHttpException('error.nocharacter');
		}
		if ($place = $this->getCharacter()->getInsideSettlement()) {
			// initially, this was all restricted to characters inside the settlement, but that makes attacks towards the outside, etc. impossible,
			// and since we don't have a "leave settlement" action...
			// FIXME: it should contain both - inside settlement and in action range
			// FIXME: anyway this doesn't work and those outside are excluded
//			return $this->geography->findCharactersInSettlement($place, $this->getCharacter());
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
