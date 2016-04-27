<?php

/**
 * @guy WebGuy\UserSteps
 */
class MapDataCest {

	public function testRealmData(WebGuy $I) {
		$I->amOnPage('/en/map/data?type=realms');
		$I->see('FeatureCollection');
		$I->see('Keplerstan');
		$I->cantSee('Stanton');
		$I->see('Emp');

		$I->amOnPage('/en/map/data?type=realms&mode=1');
		$I->see('FeatureCollection');
		$I->cantSee('Keplerstan');
		$I->cantSee('Stanton');
		$I->cantSee('Emp');

		$I->amOnPage('/en/map/data?type=realms&mode=2');
		$I->see('FeatureCollection');
		$I->cantSee('Keplerstan');
		$I->cantSee('Stanton');
		$I->see('Emp');

		$I->amOnPage('/en/map/data?type=realms&mode=3');
		$I->see('FeatureCollection');
		$I->cantSee('Keplerstan');
		$I->see('Stanton');
		$I->cantSee('Emp');

		$I->amOnPage('/en/map/data?type=realms&mode=4');
		$I->see('FeatureCollection');
		$I->cantSee('Keplerstan');
		$I->cantSee('Stanton');
		$I->cantSee('Emp');

		$I->amOnPage('/en/map/data?type=realms&mode=5');
		$I->see('FeatureCollection');
		$I->see('Keplerstan');
		$I->cantSee('Stanton');
		$I->cantSee('Emp');

		$I->amOnPage('/en/map/data?type=realms&mode=6');
		$I->see('FeatureCollection');
		$I->cantSee('Keplerstan');
		$I->cantSee('Stanton');
		$I->cantSee('Emp');

		$I->amOnPage('/en/map/data?type=realms&mode=all');
		$I->see('FeatureCollection');
		$I->see('Keplerstan');
		$I->see('Stanton');
		$I->see('Emp');

		// this behaves a bit strangely, as it does, in fact, include them all (because they all have estates, I think)
		$I->amOnPage('/en/map/data?type=realms&mode=2nd');
		$I->see('FeatureCollection');
		$I->see('Keplerstan');
		$I->see('Stanton');
		$I->see('Emp');

	}

	public function testSettlementData(WebGuy $I) {
		// FIXME: might want to fetch the data for these from the database?
		$I->amOnPage('/en/map/data?type=settlements&bbox=0,0,512000,512000');
		$I->see('FeatureCollection');
		$I->see('Volelbas');
		$I->see('Roduhorn');

		$I->amOnPage('/en/map/data?type=settlements&bbox=0,96000,512000,512000');
		$I->see('FeatureCollection');
		$I->cantSee('Volelbas');
		$I->see('Roduhorn');
	}


}
