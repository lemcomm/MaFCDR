<?php

namespace BM2\SiteBundle\Tests\Entity;

use Codeception\Util\Stub;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Common\Util\Inflector;

use CrEOF\Spatial\PHP\Types\Geometry\Point;
use CrEOF\Spatial\PHP\Types\Geometry\LineString;
use CrEOF\Spatial\PHP\Types\Geometry\Polygon;


abstract class GenericEntityTest extends \Codeception\TestCase\Test {
	protected $cmf;

	public function _before() {
		$em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->cmf = $em->getMetadataFactory();
	}

	public function getTrace() {
		// this is a fix/workaround/hack for some broken stupidity in codeception
		return array();
	}

	protected function runPropertiesTests($classname) {
		$class = $this->cmf->getMetadataFor($classname);
		$entity = new $classname;

		foreach ($class->fieldMappings as $name=>$field) {
			if (isset($field['id'])) {
				$getter = 'get'.$this->fieldname($name);
				$this->assertNull($entity->$getter(), "id test failed for $name");
			} else switch ($field['type']) {
				case 'string':
				case 'text':
					$this->stringTest($entity, $name);
					break;
				case 'integer':
				case 'smallint':				
				case 'float':
					$this->numberTest($entity, $name);
					break;
				case 'boolean':
					$this->booleanTest($entity, $name);
					break;
				case 'array':
					$this->arrayTest($entity, $name);
					break;
				case 'date':
				case 'datetime':
					$this->datetimeTest($entity, $name);
					break;
				case 'point':
					$this->geometryTest($entity, $name, new Point(2,3));
					break;
				case 'linestring':
					$this->geometryTest($entity, $name, new Linestring(array(new Point(1,1), new Point(3,5))));
					break;
				case 'polygon':
					$this->geometryTest($entity, $name, new Polygon(array(new Linestring(array(new Point(0,0), new Point(0,10), new Point(5,5), new Point(0,0))))));
					break;
				default:
					throw new \Exception("unknown field type ".$field['type']);
			}
		}

		// FIXME: extra-lazy associations don't work here

		foreach ($class->associationMappings as $name=>$association) {
			switch ($association['type']) {
				case ClassMetadata::ONE_TO_ONE:
					$this->toOneAssociationTest($entity, $name, $association['targetEntity']);
					break;
				case ClassMetadata::MANY_TO_MANY:
					$this->toManyAssociationTest($entity, $name, $association['targetEntity']);
					break;
				case ClassMetadata::ONE_TO_MANY:
					$this->toManyAssociationTest($entity, $name, $association['targetEntity']);
					break;
				case ClassMetadata::MANY_TO_ONE:
					$this->toOneAssociationTest($entity, $name, $association['targetEntity']);
					break;
				default:
					throw new \Exception("unknown association type ".$association['type']);
			}
		}
	}

	protected function getset($entity, $field) {
		$setter = 'set'.$this->fieldname($field);
		$getter = 'get'.$this->fieldname($field);
		$this->assertTrue(method_exists($entity, $setter), "$setter doesn't exist in class ".get_class($entity));
		$this->assertTrue(method_exists($entity, $getter), "$getter doesn't exist in class ".get_class($entity));
		return array($setter, $getter);		
	}

	protected function stringTest($entity, $field) {
		list($setter, $getter) = $this->getset($entity, $field);

		$entity->$setter('test');
		$this->assertEquals('test', $entity->$getter(), "string test failed for $field");
	}

	protected function numberTest($entity, $field) {
		list($setter, $getter) = $this->getset($entity, $field);

		$entity->$setter(23);
		$this->assertEquals(23, $entity->$getter(), "number test failed for $field");
	}

	protected function booleanTest($entity, $field) {
		list($setter, $getter) = $this->getset($entity, $field);

		$entity->$setter(true);
		$this->assertTrue($entity->$getter(), "bool test failed for $field");
		$entity->$setter(false);
		$this->assertFalse($entity->$getter(), "bool test failed for $field");
	}

	protected function arrayTest($entity, $field) {
		list($setter, $getter) = $this->getset($entity, $field);

		$data = array(1, 2, 3);
		$entity->$setter($data);
		$this->assertEquals($data, $entity->$getter(), "array test failed for $field");
	}

	protected function datetimeTest($entity, $field) {
		list($setter, $getter) = $this->getset($entity, $field);

		$data = new \DateTime("now");
		$entity->$setter($data);
		$this->assertEquals($data, $entity->$getter(), "datetime test failed for $field");
	}

	protected function geometryTest($entity, $field, $geo) {
		list($setter, $getter) = $this->getset($entity, $field);

		$entity->$setter($geo);
		$this->assertEquals($geo, $entity->$getter(), "geometry test failed for $field");
	}


	protected function toOneAssociationTest($entity, $field, $targetclass) {
		list($setter, $getter) = $this->getset($entity, $field);
		$target = new $targetclass;

		$entity->$setter($target);
		$this->assertEquals($target, $entity->$getter(), "association test failed for $field");
	}

	protected function toManyAssociationTest($entity, $field, $targetclass) {
		$singular = Inflector::singularize($field);
		$add = 'add'.$this->fieldname($singular);
		$remove = 'remove'.$this->fieldname($singular);
		if (!method_exists($entity, $add)) {
			$add = 'add'.$this->fieldname($field);
			$remove = 'remove'.$this->fieldname($field);
		}
		$this->assertTrue(method_exists($entity, $add), "$add doesn't exist in class ".get_class($entity));
		$this->assertTrue(method_exists($entity, $remove), "$remove doesn't exist in class ".get_class($entity));
		$getter = 'get'.$this->fieldname($field);
		$this->assertTrue(method_exists($entity, $getter), "$getter doesn't exist in class ".get_class($entity));
		$one = new $targetclass;
		$two = new $targetclass;

		$data = $entity->$getter();
		$this->assertInstanceOf("Doctrine\Common\Collections\ArrayCollection", $data, "$getter in class ".get_class($entity)." doesn't return an array collection.");

		$entity->$add($one);
		$entity->$add($two);
		$this->assertTrue($entity->$getter()->contains($one), "add test failed for $field");
		$this->assertTrue($entity->$getter()->contains($two), "add test failed for $field");
		$entity->$remove($one);
		$this->assertFalse($entity->$getter()->contains($one), "remove test failed for $field");
		$this->assertTrue($entity->$getter()->contains($two), "remove/retain test failed for $field");
	}

	protected function fieldname($field) {
		return Inflector::classify($field);
	}

}
