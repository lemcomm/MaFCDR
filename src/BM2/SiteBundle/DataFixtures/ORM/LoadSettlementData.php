<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Collections\ArrayCollection;

use BM2\SiteBundle\Entity\SettlementType;


class LoadSettlementData extends AbstractFixture implements OrderedFixtureInterface {

	private $settlementtypes = array(
		'city'		=> array('subtype' => 'normal'),
		'village'	=> array('subtype' => 'normal', 'upgrades' => 'signpost', 'enabled' => false),
		'port'		=> array('subtype' => 'normal', 'upgrades' => 'docks', 'enabled' => true),
		'fort'		=> array('subtype' => 'normal', 'upgrades' => 'fort', 'enabled' => true),
		'tournament'	=> array('subtype' => 'normal', 'upgrades' => 'tournament', 'enabled' => true),
		'parade'	=> array('subtype' => 'normal', 'upgrades' => 'parade', 'enabled' => true),
		'graveyard'	=> array('subtype' => 'normal', 'upgrades' => 'graveyard', 'enabled' => true),
		'temple'	=> array('subtype' => 'normal', 'upgrades' => 'temple', 'enabled' => true)
	);
	
	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 5; // Requires featuredata
	}

	
	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		foreach ($this->settlementtypes as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:SettlementType')->findOneByName($name);
			if (!$type) {
				$type = new SettlementType;
				$manager->persist($type);
			}
			$type->setName($name);
			$type->setSubtype($data['subtype']);
			$type->setReplaces($data['upgrades']);
			$type->setEnabled($data['enabled']);
			$manager->persist($type);
			$this->addReference('settlementtype: '.strtolower($name), $type);            
		}
		$manager->flush();
	}
