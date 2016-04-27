<?php
namespace WebGuy;

class UserSteps extends \WebGuy {
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
		$I->amOnPage('/en/account/characters');
		$session = $I->getSession();
		$page=$session->getPage();
		//$selector = //td[text()='" .$charater."']";
		$el=$page->find('xpath',"//td[text()='$character']");
		$I->see($character,'//td');
		$parentel=$el->getParent();
		$link=$parentel->findlink('Play');
		$link->click();
		$I->see('Status of '.$character);
		//$I->amOnPage('/en/account/play/'.$char_id);
	}

	function loginToCharacter($username, $password, $character) {
		$this->login($username, $password);
		$this->accessCharacter($character);
	}



}
