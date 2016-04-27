<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('access a characters events');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('admin', 'admin', 'Alice Kepler');

$I->amGoingTo('access events page');
$I->seeLink("events");
$I->click("events");
$I->seeCurrentUrlEquals('/en/events/');
$I->see('Event Journals');
$I->amGoingTo('Read Events');
$I->seeLink("read");
$I->click("read");
$I->see('Event Journal Of');

/* FIXME: this doesn't work because log is a reserved word probably.
$log = $I->grabFromRepository('BM2SiteBundle:Character', 'log', array('name' => 'Alice Kepler'));
$I->amOnPage('/en/events/log/'.$log->getId());
$I->see('Event Journal Of Alice Kepler');
$I->see('First mention in family chronicle');
*/