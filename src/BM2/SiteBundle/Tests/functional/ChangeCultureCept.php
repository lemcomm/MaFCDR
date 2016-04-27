<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('change the culture of a settlement');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('admin', 'admin', 'Alice Kepler');

// FIXME: hardcoded settlement ID - ugly
$I->amGoingTo('verify it is at the default right now');
$I->amOnPage('/en/settlement/1101');
$I->see('culture: northern european');

$I->amGoingTo('check if the action is available');
$I->amOnPage('/en/actions/');
$I->seeLink('Change Culture');
$I->click("Change Culture");
$I->seeCurrentUrlEquals('/en/actions/changeculture');

$I->amGoingTo('change the culture now');
$culture_id = $I->grabFromRepository('BM2SiteBundle:Culture', 'id', array('name' => 'european.central'));
$I->selectOption("#culture_culture", $culture_id);
$I->click("change culture");
$I->see('You have now declared');

// FIXME: hardcoded settlement ID - ugly
$I->amGoingTo('verify it worked');
$I->amOnPage('/en/settlement/1101');
$I->see('culture: central european');
