<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\EventLog;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Character;


class EventLogEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\EventLog');
	}

	public function testExtras() {
		$place = new Settlement;
		$place->setName('some place');
		$realm = new Realm;
		$realm->setName('some realm');
		$char = new Character;
		$char->setName('someone');

		$log = new EventLog;
		$this->assertFalse($log->getType());
		$this->assertFalse($log->getSubject());
		$this->assertFalse($log->getName());

		$log = new EventLog;
		$log->setSettlement($place);
		$this->assertEquals('settlement', $log->getType());
		$this->assertEquals($place, $log->getSubject());
		$this->assertEquals('some place', $log->getName());

		$log = new EventLog;
		$log->setRealm($realm);
		$this->assertEquals('realm', $log->getType());
		$this->assertEquals('some realm', $log->getName());

		$log = new EventLog;
		$log->setCharacter($char);
		$this->assertEquals('character', $log->getType());
		$this->assertEquals('someone', $log->getName());
	}
}

