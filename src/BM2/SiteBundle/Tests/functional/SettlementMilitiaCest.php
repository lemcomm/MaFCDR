<?php

/**
 * @guy TestGuy\UserSteps
 */
class SettlementMilitiaCest {

	public function testSoldiers(TestGuy $I) {
		$I->am('a lord');
		$I->wantTo('view my settlement soldiers');
		$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
		$I->seeCurrentUrlEquals('/en/character/summary');

		$estate_id = $I->grabFromRepository('BM2SiteBundle:Settlement', 'id', array('name' => 'Thabeholan'));

		$I->amGoingTo('check the soldiers page');
		$I->amOnPage('/en/settlement/'.$estate_id.'/soldiers');
		$I->see('Here you can manage your soldiers');
		$I->see('Thabeholan');
	}

	public function testTraining(TestGuy $I) {
		$I->am('a lord');
		$I->wantTo('view my settlement soldiers');
		$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
		$I->seeCurrentUrlEquals('/en/character/summary');

		$I->amGoingTo('check the soldiers page');
		$I->amOnPage('/en/actions/soldiers');
		$I->see('you can recruit peasants');
	}

}
