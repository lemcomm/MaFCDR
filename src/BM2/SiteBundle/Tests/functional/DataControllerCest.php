<?php

/**
 * @guy TestGuy\UserSteps
 */
class DataControllerCest {

	public function testAutocomplete(TestGuy $I) {
		$I->amOnPage('/en/data/realms');
		$I->see('Keplerstan');

		$I->amOnPage('/en/data/characters');
		$I->see('Alice');

		$I->amOnPage('/en/data/settlements');
		$I->see('Keplerville');

	}

}
