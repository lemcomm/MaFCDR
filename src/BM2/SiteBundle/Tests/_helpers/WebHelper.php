<?php
namespace Codeception\Module;

// here you can define custom functions for WebGuy 

class WebHelper extends \Codeception\Module {

	public function getSession() {	
		$session = $this->getModule('PhpBrowser')->session;
		return $session;
	}

}
