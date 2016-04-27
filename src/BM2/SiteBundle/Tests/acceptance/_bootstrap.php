<?php
// Here you can initialize variables that will for your tests

use \Codeception\Util\Fixtures;

\Codeception\Util\Autoload::registerSuffix('Page', __DIR__.DIRECTORY_SEPARATOR.'_pages');
\Codeception\Util\Autoload::registerSuffix('Steps', __DIR__.DIRECTORY_SEPARATOR.'_steps');


$username = 'admin';
$falseUsername = 'badusername';
$userEmail = 'user@email.com';
$password = 'admin';
$character = 'Alice Kepler';


Fixtures::add('username',$username);
Fixtures::add('falseUsername',$falseUsername);
Fixtures::add('userEmail',$userEmail);
Fixtures::add('password',$password);
Fixtures::add('character',$character);

