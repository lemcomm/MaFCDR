<?php

namespace BM2\SiteBundle\Tests\Entity;


use BM2\SiteBundle\Entity\Realm;


class RealmEntityTest extends GenericEntityTest {

	public function testBasics() {
		$this->runPropertiesTests('BM2\SiteBundle\Entity\Realm');
	}

	public function testHierarchy() {
		$test = new Realm;
		$test->setName('Test Realm');
		$test->setFormalName('The Holy Test Realm Empire');
		$test->setType(6);

		$barony = new Realm;
		$barony->setName("Test Barony")->setFormalName("The Barony of Test");
		$barony->setType(1);

		$barony->setSuperior($test);
		$test->addInferior($barony);

		$this->assertContains($barony, $test->getInferiors());
		$this->assertEquals($test, $barony->findUltimate());
		$this->assertTrue($test->findAllInferiors(false)->contains($barony));
		$this->assertFalse($test->findAllInferiors(false)->contains($test));
		$this->assertTrue($test->findAllInferiors(true)->contains($test));
		$test->removeInferior($barony);
		$this->assertEquals($test, $barony->getSuperior());
		$this->assertNotContains($barony, $test->getInferiors());
	}

}

