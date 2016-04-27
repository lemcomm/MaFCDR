<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('manage my account data');
$I->amGoingTo('login to my account first');
$I->login('admin', 'admin');
$I->seeLink("Edit Data");
$I->click("Edit Data");
$I->seeCurrentUrlEquals('/en/account/data');
$I->see('Personal Data');

$I->fillField("#userdata_display_name", 'Anon Y. Mouse');
$I->click("submit");
$I->seeCurrentUrlEquals('/en/account/');
$I->see('Your personal data has been updated.');

$I->seeLink("Edit Settings");
$I->click("Edit Settings");
$I->seeCurrentUrlEquals('/en/account/settings');
$I->see('E-Mail notifications');

