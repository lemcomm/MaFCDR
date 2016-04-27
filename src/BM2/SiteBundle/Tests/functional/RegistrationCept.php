<?php

$I = new TestGuy($scenario);
$I->am('a new player');
$I->wantTo('register an account');
$I->amOnPage('/en/');
$I->seeLink('register');
$I->click('register');
$I->see('supply the required information');

$I->fillField("#fos_user_registration_form_email", 'test@mightandfealty.com');
$I->fillField("#fos_user_registration_form_username", 'registration test');
$I->fillField("#fos_user_registration_form_plainPassword_first", 'test');
$I->fillField("#fos_user_registration_form_plainPassword_second", 'test');
$I->click('Register');

$I->seeCurrentUrlEquals('/en/register/check-email');
$I->see('An email has been sent to test@mightandfealty.com');
