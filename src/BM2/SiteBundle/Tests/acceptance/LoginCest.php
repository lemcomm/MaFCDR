<?php
use \WebGuy;
use \Codeception\Util\Fixtures;

/**
 * @guy WebGuy\UserSteps
 */

class loginCest {
	private $username;
	private $falseUsername;
	private $userEmail;
	private $password;
	private $character;

	public function _before() {
		$this->username = Fixtures::get('username');
		$this->falseUsername = Fixtures::get('falseUsername');
		$this->userEmail = Fixtures::get('userEmail');
		$this->password = Fixtures::get('password');
		$this->character = Fixtures::get('character');
	}

	public function _after() {

	}


   // tests

	public function loginToCharacter(WebGuy\UserSteps $I) {
		$I->wantTo('Test Login Functionality');
		$I->loginToCharacter($this->username, $this->password, $this->character);
	}

}
