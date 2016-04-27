<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\NewsEdition;

class NewsEditionEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\NewsEdition');
	}

	public function testOthers() {
		$ed = new NewsEdition;
		$ed->setPublished(true);
		$this->assertTrue($ed->isPublished());
		$ed->setPublished(false);
		$this->assertFalse($ed->isPublished());
	}

}

