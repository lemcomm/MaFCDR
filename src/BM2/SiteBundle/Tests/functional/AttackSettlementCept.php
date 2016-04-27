<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('attack a settlement');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('user 2', 'user 2', 'Gustav');
$I->seeCurrentUrlEquals('/en/character/summary');
$I->seeLink('Gustav');
$I->see('Recent Events');

$I->amGoingTo('attack');
$I->amOnPage('/en/actions/');
$I->seeLink('Attack Settlement');
$I->click('Attack Settlement');
$I->seeCurrentUrlEquals('/en/war/settlement/attack');
$I->see('Order your soldiers to attack');
$I->click("attack!");
$I->see("are preparing for the attack");

$I->amOnPage('/en/queue/');
$I->seeLink('attack settlement');
