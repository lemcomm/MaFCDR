<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\Realm;

class RealmTransformer implements DataTransformerInterface {
	private $om;

	public function __construct(ObjectManager $om) {
		$this->om = $om;
	}

	public function transform($realm) {
		if (null === $realm) {
			return "";
		}

		return $realm->getName();
	}

	public function reverseTransform($name) {
		if (!$name) {
			return null;
		}

		$realm = $this->om->getRepository('BM2SiteBundle:Realm')->findOneByName($name);

		if (null === $realm) {
			throw new TransformationFailedException(sprintf(
				'Realm named "%s" does not exist!',
				$name
			));
		}
		return $realm;
	}
}