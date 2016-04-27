<?php

/**
 * @guy TestGuy\UserSteps
 */
class RealmBasicsCest {

	public function testRealmLinks(TestGuy $I) {
		$I->am('a registered player');
		$I->wantTo('Test the realm pages');
		$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
		$I->seeCurrentUrlEquals('/en/character/summary');

		$realm_id = $I->grabFromRepository('BM2SiteBundle:Realm', 'id', array('name' => 'Keplerstan'));
		$realm_fullname = $I->grabFromRepository('BM2SiteBundle:Realm', 'formal_name', array('name' => 'Keplerstan'));

		$I->amOnPage('/en/politics/');
		$I->see('Kingdom of Keplerstan');
		$I->seeLink("Hierarchy Tree");
		$I->click("Hierarchy Tree");
		$I->seeCurrentUrlEquals("/en/realm/$realm_id/hierarchy");
		$I->see('Hierarchy');
		$I->see('Keplerstan');
		$I->see('Stanton');

		$I->amOnPage('/en/politics/');
		$I->seeLink("Realm Positions");
		$I->click("Realm Positions");
		$I->seeCurrentUrlEquals("/en/realm/$realm_id/positions");
		$I->see('King / Queen');

		$I->amOnPage('/en/politics/');
		$I->seeLink("Realm Laws");
		$I->click("Realm Laws");
		$I->seeCurrentUrlEquals("/en/realm/$realm_id/laws");
		$I->see('Realm Laws');
		$I->see('estates inheritance');

		$I->amOnPage('/en/politics/');
		$I->seeLink("Elections");
		$I->click("Elections");
		$I->seeCurrentUrlEquals("/en/realm/$realm_id/elections");
		$I->see('Elections allow the members');
	}

	public function testRealmView(TestGuy $I) {
		$I->am('a registered player');
		$I->wantTo('Test the realm details');
		$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
		$I->seeCurrentUrlEquals('/en/character/summary');

		$realm_id = $I->grabFromRepository('BM2SiteBundle:Realm', 'id', array('name' => 'Keplerstan'));
		$realm_fullname = $I->grabFromRepository('BM2SiteBundle:Realm', 'formal_name', array('name' => 'Keplerstan'));
		$log_id = $I->grabFromRepository('BM2SiteBundle:Realm', 'log', array('name' => 'Keplerstan'));

		$I->amOnPage('/en/realm/'.$realm_id.'/view');
		$I->see($realm_fullname);
		$I->see("Realm Details");

		$I->amOnPage('/en/events/log/'.$log_id);
		$I->see("Keplerstan");
		$I->see("Event Journal");
		$I->see("mark as read");
	}

}
