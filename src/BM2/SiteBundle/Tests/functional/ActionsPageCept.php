<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('check the actions page');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
$I->seeCurrentUrlEquals('/en/character/summary');
$I->see('Recent Events');

$I->amGoingTo('go to the actions page now');
$I->seeLink('actions');
$I->click('actions');
$I->seeCurrentUrlEquals('/en/actions/');
$I->see('Local Interactions');
$I->see('Military Actions');
$I->see('Local Control Actions');
$I->see('Settlement Economy Actions');
$I->see('Recruitment Actions');
