<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('recruit some entourage');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('admin', 'admin', 'Alice Kepler');

$I->amOnPage('/en/character/');
$I->see('2 scouts');

$I->amOnPage('/en/actions/');
$I->seeLink('Recruit Entourage');
$I->click('Recruit Entourage');
$I->seeCurrentUrlEquals('/en/actions/entourage');
$I->see('you can hire peasants');

$scout_id = $I->grabFromRepository('BM2SiteBundle:EntourageType', 'id', array('name' => 'scout'));
$I->fillField("#recruitment_recruits_".$scout_id, 2);
$I->click("submit");
$I->see('You have now recruited 2 scouts.');

$I->amOnPage('/en/character/');
$I->see('4 scouts');
