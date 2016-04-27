<?php

$I = new TestGuy\UserSteps($scenario);
$I->am('a registered player');
$I->wantTo('take a grand tour around everything (just looking)');
$I->amGoingTo('login to a character first');
$I->loginToCharacter('admin', 'admin', 'Alice Kepler');
$I->seeCurrentUrlEquals('/en/character/summary');
$I->seeLink('Alice Kepler');
$I->see('Recent Events');

$I->amOnPage('/en/character/');
$I->seeLink('Background');
$I->seeLink('Rename');
$I->seeLink('Kill');
$I->seeLink('Heraldry');
$I->click('Background');
$I->seeCurrentUrlEquals('/en/character/background');
$I->see('Describes the manner and');

$I->amOnPage('/en/character/');
$I->click('Rename');
$I->seeCurrentUrlEquals('/en/character/rename');
$I->see('change the name of your character');

$I->amOnPage('/en/character/');
$I->click('Heraldry');
$I->seeCurrentUrlEquals('/en/character/crest');
$I->see('Here you can assign');

$I->amOnPage('/en/character/');
$I->click('Kill');
$I->seeCurrentUrlEquals('/en/character/kill');
$I->see('kill your character');

$I->amGoingTo('look at the map');
$I->amOnPage('/en/map/');
$I->see('Map And Travel');

$I->amGoingTo('look at the events');
$I->amOnPage('/en/events/');
$I->see('Event Journals');

$I->amGoingTo('look at the messages');
$I->amOnPage('/en/read/summary');
$I->see('Messages Summary');
$I->see('conversations overview');
$I->seeLink('create a new conversation');
$I->click('create a new conversation');
$I->seeCurrentUrlEquals('/en/write/new_conversation');
$I->see('Topic');
$I->see('create new conversation');

$I->amGoingTo('look at the relations');
$I->amOnPage('/en/politics/relations');
$I->see('Family');
$I->seeLink('Manage Partners');
$I->seeLink('Your Successor');
$I->seeLink('Manage Lists');
$I->click('Manage Partners');
$I->seeCurrentUrlEquals('/en/politics/partners');
$I->see('Relationships with other characters');

$I->amOnPage('/en/politics/relations');
$I->click('Your Successor');
$I->seeCurrentUrlEquals('/en/politics/successor');
$I->see('Death being a part of life');

$I->amOnPage('/en/politics/relations');
$I->click('Manage Lists');
$I->seeCurrentUrlEquals('/en/politics/lists');
$I->see('Defining lists of realms and people');
$I->seeLink('create new list');
$I->click('create new list');
$I->seeCurrentUrlEquals('/en/politics/list/0');
$I->see('Members Of This List');

$I->amGoingTo('look at politics');
$I->amOnPage('/en/politics/');
$I->see('The Kingdom of Keplerstan');
$I->seeLink('Hierarchy Tree');
$I->seeLink('Manage Realm');
$I->seeLink('Abdicate');
$I->seeLink('Realm Positions');
$I->seeLink('Realm Laws');
$I->seeLink('Diplomacy');
$I->seeLink('Manage Lists');

// FIXME: hardcoded realm-id
$I->click('Hierarchy Tree');
$I->seeCurrentUrlEquals('/en/realm/1/hierarchy');
$I->see('Hierarchy');

$I->amOnPage('/en/politics/');
$I->click('Manage Realm');
$I->seeCurrentUrlEquals('/en/realm/1/manage');
$I->see('As the ruler of Keplerstan');

$I->amOnPage('/en/politics/');
$I->click('Abdicate');
$I->seeCurrentUrlEquals('/en/realm/1/abdicate');
$I->see('announce as successor');

$I->amOnPage('/en/politics/');
$I->click('Realm Positions');
$I->seeCurrentUrlEquals('/en/realm/1/positions');
$I->see('Realm Positions');
$I->seeLink('create a new position');
$I->click('create a new position');
$I->seeCurrentUrlEquals('/en/realm/1/position/0');
$I->see('formal description');

$I->amOnPage('/en/politics/');
$I->click('Realm Laws');
$I->seeCurrentUrlEquals('/en/realm/1/laws');
$I->see('Realm Laws');

$I->amOnPage('/en/politics/');
$I->click('Diplomacy');
$I->seeCurrentUrlEquals('/en/realm/1/diplomacy');
$I->see('is a sovereign realm');
$I->seeLink('Relations');
$I->seeLink('Join Realm');
$I->seeLink('Create Subrealm');
$I->click('Relations');
$I->seeCurrentUrlEquals('/en/realm/1/relations');
$I->see('Diplomatic Relations');
$I->seeLink('create new relation');
$I->click('create new relation');
$I->seeCurrentUrlEquals('/en/realm/1/editrelation/0');
$I->see('target realm');

$I->amOnPage('/en/realm/1/diplomacy');
$I->click('Join Realm');
$I->seeCurrentUrlEquals('/en/realm/1/join');
$I->see('Submitting to another realm');

$I->amOnPage('/en/realm/1/diplomacy');
$I->click('Create Subrealm');
$I->seeCurrentUrlEquals('/en/realm/1/subrealm');
$I->see('you can create sub-realms');
