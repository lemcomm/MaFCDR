<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Doctrine\Common\Persistence\ObjectManager;


class CharacterTransformer implements DataTransformerInterface {
	private $om;

	public function __construct(ObjectManager $om) {
		$this->om = $om;
	}

	public function transform($character) {
		if (null === $character) {
			return "";
		}

		return $character->getName();
	}

	public function reverseTransform($name) {
		if (!$name) {
			return null;
		}
		
		$character = $this->om->getRepository('BM2SiteBundle:Character')->findOneBy(array('name' => $name, 'alive' => TRUE), array('id' => 'ASC'));
		
		if (!$character) {
			return null;
		}
		
		if (null === $character) {
			throw new TransformationFailedException(sprintf(
				'Character named "%s" does not exist!',
				$name
			));
		}

		return $character;
	}
}
