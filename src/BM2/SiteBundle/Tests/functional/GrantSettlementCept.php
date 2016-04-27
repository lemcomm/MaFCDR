<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('grant a settlement');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('admin', 'admin', 'Alice Kepler');

$I->amOnPage('/en/actions/grant');
$I->see('You can grant a settlement');
$carol_id = $I->grabFromRepository('BM2SiteBundle:Character', 'id', array('name' => 'Carol Stanis'));
$I->selectOption("#form_newowner", $carol_id);
$I->checkOption("#form_withrealm");
$I->click("submit");
$I->see('You will soon be meeting with');
$I->see('Carol Stanis');

$I->amGoingTo('check the queue as well');
$I->amOnPage('/en/queue');
$I->see('grant settlement');

$I->amGoingTo('verify I cannot do it again');
$I->amOnPage('/en/actions/grant');
$I->see('Error');
$I->see('You are already conducting this action');

/* 
this only works in acceptance testing because the jquery and/or AJAX isn't resolved in functional testing:

$I->amGoingTo('cancel the grant');
$action_id = $I->grabFromRepository('BM2SiteBundle:Action', 'id', array('type' => 'settlement.grant'));
$I->amOnPage('/en/queue/details/'.$action_id);
$I->see('Grant Settlement');
$I->see('Carol Stanis');
$I->click("#cancelaction");
$I->see('this action has been cancelled');

$I->amGoingTo('verify that it is gone from the database');
$I->dontSeeInRepository('BM2SiteBundle:Action', 'id', array('id' => $action_id));

*/