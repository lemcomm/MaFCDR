<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('Create a new character');
$I->amGoingTo('login to my account first');
$I->login('admin', 'admin');
$I->amOnPage('/en/account/characters');
$I->seeLink("Create Character");
$I->click("Create Character");
$I->seeCurrentUrlEquals('/en/account/newchar');
$I->see('Enter the vital details');

$xavier = $I->grabFromRepository('BM2SiteBundle:Character', 'id', array('name' => 'Xavier Kepler'));
$I->fillField('#charactercreation_name', 'Creation Test');
$I->selectOption("#charactercreation_father", $xavier);
$I->click('create');

$I->seeCurrentUrlEquals('/en/character/background?starting=1');
$I->see('Background');
$I->seeLink("skip this part");
$I->click("skip this part");

// FIXME: hardcoded settlement-id
$I->seeCurrentUrlEquals('/en/character/start');
$I->see('Character Placement');
$I->selectOption("#character_placement_family_estate", 1101);
$I->click("#character_placement_family_submit");

$name = $I->grabFromRepository('BM2SiteBundle:Settlement', 'name', array('id' => 1101));
$I->seeCurrentUrlEquals('/en/character/first');
$I->see('You have decided to begin as an independent noble');
$I->amOnPage('/en/character/');
$I->see('Status Of Creation Test');
$I->see($name);
