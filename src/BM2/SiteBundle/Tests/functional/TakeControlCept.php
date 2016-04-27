<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('check the actions page');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('user 1', 'user 1', 'Carol Stanis');
$I->seeCurrentUrlEquals('/en/character/summary');

$I->amGoingTo('go to the actions page now');
$I->seeLink('actions');
$I->click('actions');
$I->seeCurrentUrlEquals('/en/actions/');
$I->seeLink('Take Control');
$I->cantSee("Actions Queue");
$I->click('Take Control');

$I->seeCurrentUrlEquals('/en/actions/take');
$I->see("Take Control");
$I->see("This settlement is currently controlled by");
$I->selectOption("#realm_target", "Stanton");
$I->click("take control");

$I->seeCurrentUrlEquals('/en/actions/take');
$I->see("You are now in the process of taking control");

$I->amOnPage('/en/actions');
$I->see('Actions Queue');
$I->see('take settlement');
$I->seeLink('manage queue');
$I->click('manage queue');

$I->seeCurrentUrlEquals('/en/queue/');
$I->see("Actions Queue");
$I->see("priority");
$I->seeLink("take settlement");
$I->click("take settlement");

$I->see("Take Settlement");
$I->see("started");
$I->see("progress");
$I->see("Stanton");
$I->see("cancel action");

// can't test cancel here because that uses AJAX

$I->amGoingTo('validate that the owner sees it');
$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
$I->seeCurrentUrlEquals('/en/character/summary');
$I->see("has initiated actions to take control");
