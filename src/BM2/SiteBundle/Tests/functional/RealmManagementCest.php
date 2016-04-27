<?php

/**
 * @guy TestGuy\UserSteps
 */
class RealmManagementCest {

	public function testRealmManagement(TestGuy $I) {
		$I->am('a registered player');
		$I->wantTo('Test realm management');
		$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
		$I->seeCurrentUrlEquals('/en/character/summary');

		$realm_id = $I->grabFromRepository('BM2SiteBundle:Realm', 'id', array('name' => 'Keplerstan'));
		$realm_fullname = $I->grabFromRepository('BM2SiteBundle:Realm', 'formal_name', array('name' => 'Keplerstan'));

		$I->amOnPage('/en/politics/');
		$I->seeLink("Manage Realm");
		$I->click("Manage Realm");
		$I->seeCurrentUrlEquals("/en/realm/$realm_id/manage");

		$I->fillField("#realmmanage_language", 'Sindarin');
		$I->click("#realmmanage_submit");
		$I->seeCurrentUrlEquals("/en/realm/$realm_id/manage");
		$I->see('Realm details have been updated.');
		$I->see('Sindarin');

		$I->amOnPage("/en/realm/$realm_id/view");
		$I->see('The Kingdom of Keplerstan');
		$I->see('official language');
		$I->see('Sindarin');
	}


	// TODO: diplomacy, elections, etc, etc.


}
