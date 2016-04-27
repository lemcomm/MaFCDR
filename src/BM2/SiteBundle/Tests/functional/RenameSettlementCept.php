<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('rename a settlement');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('admin', 'admin', 'Alice Kepler');

$old_name = $I->grabFromRepository('BM2SiteBundle:Settlement', 'name', array('id' => 1101));

$I->amOnPage('/en/actions/rename');
$I->see('you can change its name');
$I->fillField("#form_name", 'Test Rename');
$I->click("change name");
$I->see('change the name of this place to');
$I->see('Test Rename');

$I->amGoingTo('check that it is not yet applied');
$I->amOnPage('/en/settlement/1101');
$I->see($old_name);

/* 
this only works in acceptance testing because the jquery and/or AJAX isn't resolved in functional testing:

$I->amGoingTo('cancel the rename');
$action_id = $I->grabFromRepository('BM2SiteBundle:Action', 'id', array('type' => 'settlement.rename'));
$I->amOnPage('/en/queue/details/'.$action_id);
$I->see('Rename Settlement');
$I->click("#cancelaction");
$I->see('this action has been cancelled');

$I->amGoingTo('verify that it is gone from the database');
$I->dontSeeInRepository('BM2SiteBundle:Action', 'id', array('id' => $action_id));



---

or maybe this works:

$I->sendAjaxPostRequest('login/verify', array('name' => 'name', 'password' => 'password'));
$I->seeResponseIsJson();
$I->seeResponseContainsJson(['login_failed' => 1]);
//or
$I->grabDataFromJsonResponse('data.login_failed');

*/