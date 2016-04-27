<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('test the API interface');


$I->amGoingTo('check my RSS info link');
$user_id = $I->grabFromRepository('BM2SiteBundle:User', 'id', array('username' => 'admin'));
$character_id = $I->grabFromRepository('BM2SiteBundle:Character', 'id', array('name' => 'Alice Kepler'));
$app_key = $I->grabFromRepository('BM2SiteBundle:User', 'app_key', array('id' => $user_id));

$I->amOnPage("/en/app/rss/$app_key/$user_id/$character_id");
$I->see('Alice Kepler');
$I->see('Event Log Summary');
