<?php

/**
 * @guy TestGuy\UserSteps
 */
class ViewPagesCest {

	public function testRedirect(TestGuy $I) {
		$I->amGoingTo('test the redirect first');
		$I->amOnPage('/');
		$I->seeCurrentUrlEquals('/en/');
		$I->amOnPage('/de/');
		$I->seeCurrentUrlEquals('/de/');
		$I->amOnPage('/xx/');
		$I->seeCurrentUrlEquals('/en/');
		$I->amOnPage('/en/thispagedoesnotexist');
		$I->see('Page not found');
	}

	public function testHomepage(TestGuy $I) {
		$I->amGoingTo('start at the front page');
		$I->amOnPage('/en/');
		$I->seeCurrentUrlEquals('/en/');
		$I->seeLink("about");
		$I->click("about");
		$I->seeCurrentUrlEquals('/en/about');
		$I->see('About Might &amp; Fealty');
		$I->see('Philosophy');
	}

	public function testManual(TestGuy $I) {
    $I->amGoingTo('check the manual');
    $I->amOnPage('/en/');
		$I->seeLink("manual");
		$I->click("manual");
		$I->seeCurrentUrlEquals('/en/manual');
		$I->see('Introduction');
		$I->see('Table Of Contents');

		$I->seeLink("battles");
		$I->click("battles");
		$I->see('Battle Preparations');

		$I->seeLink("buildings");
		$I->click("buildings");
		$I->see('list of all building types');

		$I->seeLink("Weaponsmith");
		$I->click("Weaponsmith");
		$I->see('specialised craftsman forging weapons');

		$I->seeLink("broadsword");
		$I->click("broadsword");
		$I->see('sturdy sword designed more');
	}

	public function testFooters(TestGuy $I) {
    $I->amGoingTo('check the footer links');
    $I->amOnPage('/en/');
		$I->seeLink("terms");
		$I->click("terms");
		$I->seeCurrentUrlEquals('/en/terms');
		$I->see('Terms of Service');
		$I->see('view content without registering');

		$I->seeLink("credits");
		$I->click("credits");
		$I->seeCurrentUrlEquals('/en/credits');
		$I->see('The equipment images were made');

		$I->seeLink("contact");
		$I->click("contact");
		$I->seeCurrentUrlEquals('/en/contact');
		$I->see('forum.mightandfealty.com');
	}

	public function testFiction(TestGuy $I) {
    $I->amGoingTo('check the fiction section');
    $I->amOnPage('/en/');
		$I->seeLink("fiction");
		$I->click("fiction");
		$I->seeCurrentUrlEquals('/en/fiction');
		$I->see('Might &amp; Fealty Fiction');
		$I->seeLink("The Ancient Geas");
		$I->click("The Ancient Geas");
		$I->see('The enemy is deploying near the marsh');
	}


}
