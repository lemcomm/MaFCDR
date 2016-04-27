<?php

$I = new TestGuy($scenario);
$I->wantTo('check all the info pages');

$I->amGoingTo('check the buildings');
$I->amOnPage('/en/info/buildingtypes');
$I->see('Table Of Contents');
$I->seeLink("Carpenter");
$I->click("Carpenter");
$I->see('wood workings');
$I->seeLink("shield");

$I->amGoingTo('check the features');
$I->amOnPage('/en/info/featuretypes');
$I->see('Table Of Contents');
$I->seeLink("Watchtower");
$I->click("Watchtower");
$I->see('The watch tower provides an elevated spot');

$I->amGoingTo('check the entourages');
$I->amOnPage('/en/info/entouragetypes');
$I->see('Table Of Contents');
$I->seeLink("scholar");
$I->click("scholar");
$I->see('reading and writing');
$I->seeLink("University");

$I->amGoingTo('check the equipment');
$I->amOnPage('/en/info/equipmenttypes');
$I->see('Table Of Contents');
$I->seeLink("chainmail");
$I->click("chainmail");
$I->see('metal rings providing impressive armor');
$I->seeLink("Garrison");


$I->amGoingTo('check for failures');
$I->amOnPage('/en/info/buildingtype/0');
$I->see('no such building type');
$I->amOnPage('/en/info/featuretype/0');
$I->see('no such feature type');
$I->amOnPage('/en/info/entouragetype/0');
$I->see('no such entourage type');
$I->amOnPage('/en/info/equipmenttype/0');
$I->see('no such equipment type');
