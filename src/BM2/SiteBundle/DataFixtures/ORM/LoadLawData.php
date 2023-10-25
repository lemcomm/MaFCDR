<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\LawType;


class LoadLawData extends AbstractFixture implements OrderedFixtureInterface {

	private $laws  = [
		'assoc' => [
			'freeform'    		=> ['allow_multiple'=>true],
			'assocVisibility'    	=> ['allow_multiple'=>false],
			'rankVisibility'    	=> ['allow_multiple'=>false],
			'assocInheritance'    	=> ['allow_multiple'=>false],
		],
		'realm' => [
			'freeform'		=> ['allow_multiple'=>true],
			'slumberingAccess'	=> ['allow_multiple'=>false],
			'settlementInheritance'	=> ['allow_multiple'=>false],
			'placeInheritance'	=> ['allow_multiple'=>false],
			'slumberingClaims'	=> ['allow_multiple'=>false],
		#	'subrealmAutonomy'	=> ['allow_multiple'=>false],
		#	'subrealmReclassing'	=> ['allow_multiple'=>false],
		#	'subrealmSubcreate'	=> ['allow_multiple'=>false],
			'taxesFood'		=> ['allow_multiple'=>false],
			'taxesWood'		=> ['allow_multiple'=>false],
			'taxesMetal'		=> ['allow_multiple'=>false],
			'taxesWealth'		=> ['allow_multiple'=>false],
			'realmPlaceMembership'	=> ['allow_multiple'=>false],
			'realmFaith'		=> ['allow_multiple'=>true],
		]
	];

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 1;
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		foreach ($this->laws as $class=>$members) {
			foreach ($members as $name=>$data) {
				$law = $manager->getRepository('BM2SiteBundle:LawType')->findOneBy(array('name'=>$name, 'category'=>$class));
				if (!$law) {
					$law = new LawType();
					$manager->persist($law);
				}
				$law->setName($name);
				$law->setCategory($class);
				$law->setAllowMultiple($data['allow_multiple']);
			}
		}
		$manager->flush();
	}
}
