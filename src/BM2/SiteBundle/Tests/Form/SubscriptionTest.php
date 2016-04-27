<?php

namespace BM2\SiteBundle\Tests\Form;

use BM2\SiteBundle\Form\SubscriptionType;
use BM2\SiteBundle\Entity\User;

use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase;


class SubscriptionTest extends TypeTestCase {

   /**
     * @dataProvider getValidTestData
     */
	public function testForm($data) {
		$levels = array(
			10 =>	array('name' => 'casual',		'characters' =>    4, 'fee' => 400, 'selectable' => true),
			20 =>	array('name' => 'basic',		'characters' =>   10, 'fee' => 500, 'selectable' => true),
		);

		$type = new SubscriptionType($levels, 10);
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
				'level' => 10,
			)),
			array('data' => array(
				'level' => 20,
			)),
		);
	}
}
