<?php

namespace BM2\SiteBundle\Tests\Form;

use BM2\SiteBundle\Form\UserDataType;
use BM2\SiteBundle\Entity\User;


use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase;


class UserDataTest extends TypeTestCase {

	/**
     * @dataProvider getValidTestData
     */
	public function testForm($data) {

		$user = new User;

		$type = new UserDataType($user);
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
				'username' => 'test user',
				'email' => 'dummy@lemuria.org'
			)),
		);
	}
}
