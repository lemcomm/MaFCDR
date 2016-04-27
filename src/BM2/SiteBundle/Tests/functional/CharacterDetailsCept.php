<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('Check out character details');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
$I->seeCurrentUrlEquals('/en/character/summary');

$me = $I->grabFromRepository('BM2SiteBundle:Character', 'id', array('name' => 'Alice Kepler'));
$I->amOnPage('/en/character/view/'.$me);
$I->see("Alice Kepler");
$I->seeLink("Keplerstan");
$I->see("Hierarchy");
$I->see("Estate");

$I->seeLink("Family Tree");
$I->click("Family Tree");
$I->seeCurrentUrlEquals('/en/character/family/'.$me);
$I->see("Family Tree");
