<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('check the politics page');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
$I->seeCurrentUrlEquals('/en/character/summary');
$I->seeLink('Alice Kepler');
$I->see('Recent Events');

$I->amGoingTo('go to the politics page now');
$I->seeLink('politics');
$I->click('politics');
$I->seeCurrentUrlEquals('/en/politics/');
$I->see('The Kingdom of Keplerstan');
$I->see('Manage Realm');
$I->see('Realm Laws');
$I->see('Realm Laws');
