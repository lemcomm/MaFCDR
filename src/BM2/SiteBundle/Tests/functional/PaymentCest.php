<?php

/**
 * @guy TestGuy\UserSteps
 */
class PaymentCest {

	public function testPaymentLinks(TestGuy $I) {
		$I->am('a registered player');
		$I->wantTo('Test the payment handling');
		$I->amGoingTo('login to my account first');
		$I->login('admin', 'admin');
		$I->amOnPage('/en/account/');
		$I->seeLink("Get More Credits");
		$I->click("Get More Credits");
		$I->seeCurrentUrlEquals('/en/payment/');
		$I->see('Account Balance');
		$I->see('Buying Credits');
	}


	// TODO: test payment handling, but on test the real payment options are disabled :-(

}
