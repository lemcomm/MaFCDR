<?php
$I = new WebGuy($scenario);
$I->wantTo('load the homepage');
$I->amOnPage('/en');
$I->see('by Tom Vogt');
$I->seeLink('register');
