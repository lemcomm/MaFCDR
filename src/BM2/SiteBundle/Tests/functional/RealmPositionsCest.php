<?php

/**
 * @guy TestGuy\UserSteps
 */
class RealmPositionsCest {

	public function testPositions(TestGuy $I) {
		$I->am('a ruler');
		$I->wantTo('manage positions');
		$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
		$I->seeCurrentUrlEquals('/en/character/summary');

		$realm_id = $I->grabFromRepository('BM2SiteBundle:Realm', 'id', array('name' => 'Keplerstan'));

		$I->amGoingTo('create a new position');
		$I->amOnPage('/en/realm/'.$realm_id.'/positions');
		$I->see('Realm Positions');
		$I->seeLink('create a new position');
		$I->click('create a new position');

		$I->seeCurrentUrlEquals('/en/realm/'.$realm_id.'/position/0');
		$I->see('Realm Positions');
		$I->fillField('#realmposition_name', "Test Position");
		$I->fillField('#realmposition_description', "This is a test position for unit testing.");
		$I->checkOption('#realmposition_elected');
		$I->click("edit position");

		$I->seeCurrentUrlEquals('/en/realm/'.$realm_id.'/positions');
		$I->see("Test Position");


		$pos_id = $I->grabFromRepository('BM2SiteBundle:RealmPosition', 'id', array('name' => 'Test Position'));

/*
	FIXME: the shit below doesn't work because...


		$I->amGoingTo('appoint someone to a position');
		$I->amOnPage('/en/realm/'.$realm_id.'/officials/'.$pos_id);
		$I->see("Test Position");
		$I->see("You can appoint and discharge");

		$a = $I->grabFromRepository('BM2SiteBundle:Character', 'id', array('name' => 'Alice Kepler'));
		$b = $I->grabFromRepository('BM2SiteBundle:Character', 'id', array('name' => 'David Stanis'));

...this here confuses codeception. neither of these methods work, but why ???

		$I->checkOption('Alice Kepler');
		$I->checkOption('#realmposition_candidates_'.$b);

		$I->click("announce changes");
		$I->seeCurrentUrlEquals('/en/realm/'.$realm_id.'/positions');
		$I->see("David Stanis");

		$I->amGoingTo('check my log shows it');
		$log_id = $I->grabFromRepository('BM2SiteBundle:Character', 'log_id', array('name' => 'Alice Kepler'));
		$I->amOnPage('/en/events/log/'.$log_id);
		$I->seeLink("Alice Kepler");
		$I->see("Appointed");
		$I->seeLink("Test Position");
		$I->click("Test Position");
*/

//		$I->seeCurrentUrlEquals('/en/realm/viewposition/'.$pos_id);
		$I->amOnPage('/en/realm/viewposition/'.$pos_id);
		$I->see("formal description");
		$I->see("This is a test position for unit testing.");
	}

}
