<?php

/**
 * @guy TestGuy\UserSteps
 */
class SubscriptionCest {

	public function testPageLinks(TestGuy $I) {
		$I->am('a registered player');
		$I->wantTo('Test the subscription handling');
		$I->amGoingTo('login to my account first');
		$I->login('admin', 'admin');
		$I->amOnPage('/en/account/');
		$I->seeLink("Change Subscription Level");
		$I->click("Change Subscription Level");
		$I->seeCurrentUrlEquals('/en/payment/subscription');
		$I->see('your current subscription level');

		$I->amOnPage('/en/account/');
		$I->seeLink("Credits And Gifts");
		$I->click("Credits And Gifts");
		$I->seeCurrentUrlEquals('/en/payment/credits');
		$I->see('Spend Credits');
		$I->see('Subscription');
		$I->seeLink("Change Subscription Level");
		$I->click("Change Subscription Level");
		$I->seeCurrentUrlEquals('/en/payment/subscription');
		$I->see('your current subscription level');
	}


	// TODO: change the level, but that requires some JS evaluation, etc.

}
