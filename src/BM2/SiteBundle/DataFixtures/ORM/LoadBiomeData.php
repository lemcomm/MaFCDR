<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\Biome;


class LoadBiomeData extends AbstractFixture implements OrderedFixtureInterface {

	private $biomes = array(
		'grass'			=> array('spot' => 1.00, 'travel' => 1.00, 'roads' => 1.00, 'features' => 1.00),
		'thin grass'	=> array('spot' => 1.00, 'travel' => 1.00, 'roads' => 1.00, 'features' => 1.00),
		'scrub'			=> array('spot' => 0.80, 'travel' => 0.95, 'roads' => 1.00, 'features' => 1.00),
		'thin scrub'	=> array('spot' => 0.90, 'travel' => 0.95, 'roads' => 1.00, 'features' => 1.00),
		'desert'			=> array('spot' => 1.10, 'travel' => 0.90, 'roads' => 1.10, 'features' => 1.00),
		'marsh'			=> array('spot' => 0.80, 'travel' => 0.65, 'roads' => 1.40, 'features' => 1.20),
		'forest'			=> array('spot' => 0.60, 'travel' => 0.80, 'roads' => 1.10, 'features' => 1.10),
		'dense forest'	=> array('spot' => 0.40, 'travel' => 0.75, 'roads' => 1.25, 'features' => 1.20),
		'rock'			=> array('spot' => 0.75, 'travel' => 0.60, 'roads' => 1.60, 'features' => 1.30),
		'snow'			=> array('spot' => 0.75, 'travel' => 0.50, 'roads' => 2.00, 'features' => 1.50),
		'water'			=> array('spot' => 1.20, 'travel' => 1.50, 'roads' => 1.00, 'features' => 1.00),
		'ocean'			=> array('spot' => 1.20, 'travel' => 1.50, 'roads' => 1.00, 'features' => 1.00),
	);

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 1; // or anywhere, really
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		foreach ($this->biomes as $name=>$data) {
			$type = new Biome;
			$type->setName($name);
			$type->setSpot($data['spot']);
			$type->setTravel($data['travel']);
			$type->setRoadConstruction($data['roads']);
			$type->setFeatureConstruction($data['features']);
			$manager->persist($type);
		}
		$manager->flush();
	}
}
