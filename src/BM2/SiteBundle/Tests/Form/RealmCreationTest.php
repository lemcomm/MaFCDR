<?php

namespace BM2\SiteBundle\Tests\Form;

use BM2\SiteBundle\Form\RealmCreationType;

use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase;


class RealmCreationTest extends TypeTestCase {

	/**
	  * @dataProvider getValidTestData
	  */
	public function testForm($data) {
		$type = new RealmCreationType();
		$form = $this->factory->create($type);

		$form->bind($data);

		$this->assertTrue($form->isSynchronized());

		$view = $form->createView();
		$children = $view->children;

		foreach (array_keys($data) as $key) {
			$this->assertArrayHasKey($key, $children);
		}
	}

	public function getValidTestData() {
		return array(
			array('data' => array(
				'name' => 'test 1',
				'formal_name' => 'first test realm',
				'type' => 1
			)),
			array('data' => array(
				'name' => 'test 2',
				'formal_name' => 'second test realm',
				'type' => 5
			)),
		);
	}
}
