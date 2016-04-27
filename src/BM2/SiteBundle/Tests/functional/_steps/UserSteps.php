<?php
namespace TestGuy;

class UserSteps extends \TestGuy {

	function login($username, $password) {
		$I = $this;
		$I->amOnPage('/en/login');
		$I->fillField("#username", $username);
		$I->fillField("#password", $password);
		$I->click("#_submit");
	}

	function accessCharacter($character) {
		// assumes logged-in or will fail
		$I = $this;
		$char_id = $I->grabFromRepository('BM2SiteBundle:Character', 'id', array('name' => $character));
		$I->amOnPage('/en/account/play/'.$char_id);
	}

	function loginToCharacter($username, $password, $character) {
		$this->login($username, $password);
		$this->accessCharacter($character);
	}

}