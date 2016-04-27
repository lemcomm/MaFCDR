<?php

/**
 * @guy TestGuy\UserSteps
 */
class RelationsCest {

	public function testLinks(TestGuy $I) {
		$I->am('a registered player');
		$I->wantTo('Test the relations pages');
		$I->loginToCharacter('user 1', 'user 1', 'David Stanis');
		$I->seeCurrentUrlEquals('/en/character/summary');

		$I->amOnPage('/en/politics/relations');
		$I->see('Family');
		$I->see('Inheritance');
		$I->seeLink("Oath Tree");
		$I->seeLink("Go Rogue");
		$I->seeLink("Manage Partners");
		$I->seeLink("Your Successor");
		$I->seeLink("Manage Lists");

		$I->click("Oath Tree");
		$I->seeCurrentUrlEquals('/en/politics/hierarchy');
		$I->see("Hierarchy");
		$I->see("Alice Kepler");

		$I->amOnPage('/en/politics/relations');
		$I->click("Go Rogue");
		$I->seeCurrentUrlEquals('/en/politics/breakoath');
		$I->see("Go Rogue");
		$I->see("Current Oath");

		$I->amOnPage('/en/politics/relations');
		$I->click("Manage Partners");
		$I->seeCurrentUrlEquals('/en/politics/partners');
		$I->see("New Relationships");
		$I->see("no potential partners within your action range");

		$I->amOnPage('/en/politics/relations');
		$I->click("Your Successor");
		$I->seeCurrentUrlEquals('/en/politics/successor');
		$I->see("Your Successor");
		$I->see("Assign A New Successor");

		$I->amOnPage('/en/politics/relations');
		$I->click("Manage Lists");
		$I->seeCurrentUrlEquals('/en/politics/lists');
		$I->see("Manage Lists");
		$I->seeLink("create new list");
		$I->click("create new list");

		$I->seeCurrentUrlEquals('/en/politics/list/0');
		$I->see("Manage Lists");
		$I->see("Members Of This List");
	}


	public function testPartners(TestGuy $I) {
		$I->am('a registered player');
		$I->wantTo('Test partners management');
		$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
		$I->seeCurrentUrlEquals('/en/character/summary');

		$I->amOnPage('/en/politics/partners');
		$I->cantSee("Existing Relationships");
		$I->selectOption("#partnership_type", "marriage");
		$I->selectOption("#partnership_partner", "Bob Kepler");
		$I->checkOption("#partnership_public");
		$I->checkOption("#partnership_sex");
		$I->click("propose");

		$I->seeCurrentUrlEquals('/en/politics/partners');
		$I->see("Existing Relationships");
		$I->see("your proposed");
	}

	public function testSuccessor(TestGuy $I) {
		$I->am('a registered player');
		$I->wantTo('Test successor management');
		$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
		$I->seeCurrentUrlEquals('/en/character/summary');

		$id = $I->grabFromRepository('BM2SiteBundle:Character', 'id', array('name' => 'Carol Stanis'));

		$I->amOnPage('/en/politics/successor');
		$I->selectOption("#character_target", "Carol Stanis");
		$I->click("set successor");
		$I->seeCurrentUrlEquals('/en/politics/successor');
		$I->see("You have now chosen");
		$I->see("Carol Stanis");
	}

}
