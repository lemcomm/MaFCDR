<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('access a characters messages');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('admin', 'admin', 'Alice Kepler');

$I->amGoingTo('open Messages page');
$I->seeLink("messages");
$I->click("messages");
$I->seeCurrentUrlEquals('/en/read/summary');
$I->see('Messages Summary');
$I->see('Your Unread Messages');

/* TODO: more */

