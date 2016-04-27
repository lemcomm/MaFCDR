<?php

/**
 * @guy TestGuy\UserSteps
 */
class AbdicateCest {

	public function testAbdication(TestGuy $I) {
		$I->am('a ruler');
		$I->wantTo('abdicate');
		$I->loginToCharacter('user 1', 'user 1', 'Eve');
		$I->seeCurrentUrlEquals('/en/character/summary');

		$realm_id = $I->grabFromRepository('BM2SiteBundle:Realm', 'id', array('name' => 'Emp'));

		$I->amOnPage('/en/realm/'.$realm_id.'/abdicate');
		$I->see('Abdicate');
		$I->see('As the ruler of');

		$I->click("abdicate");

	}

}
