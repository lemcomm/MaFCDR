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
		# Because of the above abuse proofing, this transformer will also accepted character IDs.
		# If we have an ID, we just use it. We confirm that by seeing if what we have qualifies as numerical, per PHP.
		if (is_numeric($input)) {
			$character = $this->om->getRepository('BM2SiteBundle:Character')->findOneBy(array('id'=>$input, 'alive' => TRUE));
		} else {
			# If not, we assume we have $char->getListName(), as these are most list entries, and see if we can strip of anything that's not numeric.
			$id = preg_replace('/(?:[^123456790]*)/', '', $input);
			# Now see if doctrine can find that id....
			if (is_numeric($id)) {
				$character = $this->om->getRepository('BM2SiteBundle:Character')->findOneBy(array('id'=>$input, 'alive' => TRUE));
			} else {
				# Presumably, that wasn't an ID. Assume it's just a name.
				$character = $this->om->getRepository('BM2SiteBundle:Character')->findOneBy(array('name' => $input, 'alive' => TRUE), array('id' => 'ASC'));
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
