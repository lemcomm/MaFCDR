<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\Settlement;

class SettlementTransformer implements DataTransformerInterface {
	private $om;

	public function __construct(ObjectManager $om) {
		$this->om = $om;
	}

	public function transform($settlement) {
		if (null === $settlement) {
			return "";
		}

		return $settlement->getName();
	}

	public function reverseTransform($name) {
		if (!$name) {
			return null;
		}

		$settlement = $this->om->getRepository('BM2SiteBundle:Settlement')->findOneByName($name);

		if (null === $settlement) {
			throw new TransformationFailedException(sprintf(
				'Settlement named "%s" does not exist!',
				$name
			));
		}
		return $settlement;
	}
}