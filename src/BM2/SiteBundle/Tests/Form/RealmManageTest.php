<?php

namespace BM2\SiteBundle\Tests\Form;

use BM2\SiteBundle\Form\RealmManageType;
use BM2\SiteBundle\Entity\Realm;

use Symfony\Component\Form\Test\TypeTestCase;
use Doctrine\Common\Util\Inflector;


class RealmManageTest extends TypeTestCase {

	/**
     * @dataProvider getValidTestData
     */
	public function testForm($data) {

		$type = new RealmManageType(1,5);
		$form = $this->factory->create($type);

		$object = new Realm();
		foreach ($data as $key=>$val) {
			$setter = 'set'.Inflector::classify($key);
			$object->$setter($val);
		}

		$form->submit($data);

		$this->assertTrue($form->isSynchronized());
		$this->assertEquals($object, $form->getData());

		$view = $form->createView();
		$children = $view->children;

		foreach (array_keys($data) as $key) {
			$this->assertArrayHasKey($key, $children);
		}
	}

	public function getValidTestData() {
		return array(
			array('data' => array(
				'name' => 'testing realm',
				'formal_name' => 'the almight realm of testing',
				'colour_hex' => '#000000',
				'colour_rgb' => '0,0,0',
				'type' => 2
			)),
		);
	}
}
