<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('access a character');
$I->login('admin', 'admin');
$I->seeLink("characters");
$I->click("characters");
$I->seeCurrentUrlEquals('/en/account/characters');
$I->see('Alice Kepler');

$char_id = $I->grabFromRepository('BM2SiteBundle:Character', 'id', array('name' => 'Alice Kepler'));
$I->amOnPage('/en/account/play/'.$char_id);
$I->seeCurrentUrlEquals('/en/character/summary');
$I->see('playing');
$I->seeLink('Alice Kepler');
$I->see('Recent Events');
