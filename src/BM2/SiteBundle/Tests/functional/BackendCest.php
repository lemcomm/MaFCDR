<?php

/**
 * @guy TestGuy\UserSteps
 */
class BackendCest {

	public function testACL(TestGuy $I) {
		$I->am('a regular user');
		$I->wantTo('Test the ACL on the backend pages');
		$I->amGoingTo('login to my account first');
		$I->login('user 1', 'user 1');

		$I->amOnPage('/en/game');
		$I->see('Access Denied');

		$I->amOnPage('/en/game/statistics');
		$I->see('Access Denied');
	}

	public function testAdminPages(TestGuy $I) {
		$I->am('an administrator');
		$I->wantTo('Test the admin pages');
		$I->amGoingTo('login to my account first');
		$I->login('admin', 'admin');

		$I->amOnPage('/en/game');
		$I->see('Current Cycle');
		$I->see('Settlements');
	}

	public function testStatisticsPages(TestGuy $I) {
		$I->am('an administrator');
		$I->wantTo('Test the statistics pages');
		$I->amGoingTo('login to my account first');
		$I->login('admin', 'admin');

		$I->amOnPage('/en/game/statistics');
		$I->see('Statistics');
		$I->see('registered users');

		$I->amOnPage('/en/game/statistics/troops');
		$I->see('Troops Statistics');
		$I->see('rabble');

		$I->amOnPage('/en/game/statistics/battles');
		$I->see('Battle Statistics');

		$I->amOnPage('/en/game/statistics/realms');
		$I->see('Realm Statistics');
		$I->seeLink('Keplerstan');
		$I->click('Keplerstan');

		$I->see('Statistics for');
		$I->see('Keplerstan');
		$I->see('estates');
	}


}
