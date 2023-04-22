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
		# This originally just returned the name, but we need to proof this against people with duplicate names. This returns "Name (ID: #)".
		return $character->getListName();
	}

	public function reverseTransform($input) {
		if (!$input) {
			return null;
		}
		# First strip it of all non-numeric characters and see if we can find a character.
		$id = preg_replace('/(?:[^1234567890]*)/', '', $input);
		if ($id) {
			$character = $this->om->getRepository('BM2SiteBundle:Character')->findOneBy(array('id'=>$id, 'alive' => TRUE));
		} else {
			# Presumably, that wasn't an ID. Assume it's just a name. Strip out parantheses and numbers.
			$name = trim(preg_replace('/(?:[123456790()]*)/', '', $input));
			$character = $this->om->getRepository('BM2SiteBundle:Character')->findOneBy(array('name' => $name, 'alive' => TRUE), array('id' => 'ASC'));
			if (!$character) {
				$name = preg_replace('/(<\/i>)+/', '', preg_replace('/(<i>)+/', '', $name));
				$character = $this->om->getRepository('BM2SiteBundle:Character')->findOneBy(array('known_as' => $name, 'alive' => TRUE), array('id' => 'ASC'));
			}
		}

		if (!$character) {
			# There's a few ways this could happen. No matching name, malformed input (someone messing with the preformatted ones), or no matching ID.
			return null;
		}
		/*
		if (null === $character) {
			throw new TransformationFailedException(sprintf(
				'Character named "%s" does not exist!',
				$name
			));
		}
		*/

		return $character;
	}
}
