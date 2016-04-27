<?php

namespace BM2\SiteBundle\Tests\Form;

use BM2\SiteBundle\Form\HeraldryType;
use BM2\SiteBundle\Entity\Heraldry;

use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase;
use Doctrine\Common\Util\Inflector;


class HeraldryTest extends TypeTestCase {

	/**
     * @dataProvider getValidTestData
     */
	public function testForm($data) {

		$type = new HeraldryType;
		$form = $this->factory->create($type);

		$object = new Heraldry();
		foreach ($data as $key=>$val) {
			$setter = 'set'.Inflector::classify($key);
			$object->$setter($val);
		}

		$form->bind($data);

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
				'name' => 'test crest',
				'shield' => 'badge',
				'shield_colour' => 'rgb(0,0,0)',
			)),
			array('data' => array(
				'name' => 'dark crest',
				'shield' => 'swiss',
				'shield_colour' => 'rgb(0,0,0)',
				'pattern' => 'bend',
				'pattern_colour' => 'rgb(0,0,0)',
				'charge' => 'sword',
				'charge_colour' => 'rgb(0,0,0)',
			)),
		);
	}
}
