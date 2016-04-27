<?php

$I = new TestGuy($scenario);
$I->am('a registered player');
$I->wantTo('login as user');
$I->seeInRepository('BM2SiteBundle:User', array('username' => 'admin'));
$email = $I->grabFromRepository('BM2SiteBundle:User', 'email', array('username' => 'admin'));
$I->amOnPage('/en/login');
$I->fillField("#username", 'admin');
$I->fillField("#password", 'admin');
$I->click("#_submit");
$I->see('admin@mightandfealty.com');
