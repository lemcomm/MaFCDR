<?php

$newCharacterName = 'Rename Test';


$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('Test a character functions');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('admin', 'admin', 'Bob Kepler');
$I->see('Bob Kepler');


// test viewing of other characters
$other_id = $I->grabFromRepository('BM2SiteBundle:Character', 'id', array('name' => 'Eve'));
$I->amGoingTo('Test Character view Page');
$I->amOnPage('en/character/view/'.$other_id);
$I->see('Eve');


// testing rename
$I->amGoingTo('Rename Character');
$I->amOnPage('/en/character/rename');
$I->see('Rename');
$I->see('New Name');

//functional testing
$I->fillField('#form_name', 'Bobby Kepler');
$I->click('submit');
$I->see('has been renamed');
$I->amOnPage('/en/character/');
$I->see('Status of Bobby Kepler');
