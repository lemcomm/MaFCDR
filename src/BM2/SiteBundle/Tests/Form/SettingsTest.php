<?php

namespace BM2\SiteBundle\Tests\Form;

use BM2\SiteBundle\Form\SettingsType;
use BM2\SiteBundle\Entity\User;

use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase;


class SettingsTest extends TypeTestCase {

   /**
     * @dataProvider getValidTestData
     */
	public function testForm($data) {
		$user = new User;
		$languages = array('en'=>'english', 'de'=>'deutsch');
		$type = new SettingsType($user, $languages);
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
				'notifications' => true,
				'language' => 'en',
			)),
			array('data' => array(
				'notifications' => false,
				'language' => 'de',
			)),
		);
	}
}

