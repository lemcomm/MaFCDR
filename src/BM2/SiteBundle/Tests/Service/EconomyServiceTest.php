<?php

use BM2\SiteBundle\Entity\Building;
use BM2\SiteBundle\Entity\GeoFeature;
use BM2\SiteBundle\Entity\Road;


class EconomyServiceTest extends \Codeception\TestCase\Test {

	protected $em;
	protected $economy;
	private $village;
	private $food;
	private $wood;

	function _before() {
		$this->em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->economy = $this->getModule('Symfony2')->container->get('economy');

		$this->village = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Averick');
		$this->assertNotNull($this->village, 'no village found');
		$this->food = $this->em->getRepository('BM2SiteBundle:ResourceType')->findOneByName("food");
		$this->assertNotNull($this->food, 'resource food not found');
		$this->wood = $this->em->getRepository('BM2SiteBundle:ResourceType')->findOneByName("wood");
		$this->assertNotNull($this->wood, 'resource wood not found');
	}

	public function testResourceProduction() {
		$this->em->clear();
		$this->village = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Averick');
		$this->village->setPopulation(200);
		$this->assertEquals(339, $this->economy->ResourceProduction($this->village, $this->food));
		$this->assertEquals(317, $this->economy->ResourceProduction($this->village, $this->wood));

		$this->em->clear();
		$this->village = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Averick');
		$this->village->setPopulation(500);
		$this->assertEquals(563, $this->economy->ResourceProduction($this->village, $this->food));
		$this->assertEquals(533, $this->economy->ResourceProduction($this->village, $this->wood));
	}

	public function testResourceDemand() {
		$this->em->clear();
		$this->village = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Averick');
		$this->village->setPopulation(200);
		$this->assertEquals(205, $this->economy->ResourceDemand($this->village, $this->food));
		$this->assertEquals(53, $this->economy->ResourceDemand($this->village, $this->wood));

		$this->em->clear();
		$this->village = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Averick');
		$this->village->setPopulation(500);
		$this->assertEquals(505, $this->economy->ResourceDemand($this->village, $this->food));
		$this->assertEquals(136, $this->economy->ResourceDemand($this->village, $this->wood));
	}

	public function testSupply() {
		$this->em->clear();
		$this->village = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Averick');
		$this->village->setPopulation(200);
		$supply = $this->economy->getSupply($this->village);

		$expect = array(
			'food' => 1.0,
			'wood' => 1.0,
			'metal' => 1.0,
			'goods' => 1.0,
			'money' => 0.84089641525371461,
		);
		$resources = $this->em->getRepository('BM2SiteBundle:ResourceType')->findAll();
		foreach ($resources as $r) {
			$this->assertEquals($expect[$r->getName()], $supply[$r->getId()]);
		}

		$this->em->clear();
		$this->village = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Averick');
		$this->village->setPopulation(500);
		$supply = $this->economy->getSupply($this->village);
		$this->assertEquals(500, $this->village->getPopulation());
	}

	// TODO: add trade example data and test that (changing below value)

	public function testTradeCost() {
		$this->assertEquals(0.01, $this->economy->TradeCostBetween($this->village, $this->village));

		$keplerville = $this->em->getRepository('BM2SiteBundle:Settlement')->findOneByName('Keplerville');
		$this->assertNotNull($keplerville, 'keplerville not found');

		$this->assertEquals(0.075887692339299886, $this->economy->TradeCostBetween($keplerville, $this->village));
	}

	public function testResourceAvailable() {
		$this->assertEquals(596, $this->economy->ResourceAvailable($this->village, $this->food));
	}

	// TODO: road construction, feature construction, building construction

	public function testWorkhours() {
		$building = new Building;
		$feature = new GeoFeature;
		$road = new Road;
		$building->setSettlement($this->village);
		$feature->setGeoData($this->village->getGeoData());
		$road->setGeoData($this->village->getGeoData());

		$this->village->setPopulation(500);
		$building->setWorkers(5.0);
		$this->assertEquals(16906, round($this->economy->calculateWorkHours($building, $this->village)));
		$building->setWorkers(10.0);
		$this->assertEquals(32660, round($this->economy->calculateWorkHours($building, $this->village)));
		$this->village->setPopulation(200);
		$this->assertEquals(13677, round($this->economy->calculateWorkHours($building)));

		$this->village->setPopulation(500);
		$feature->setWorkers(5.0);
		$this->assertEquals(15369, round($this->economy->calculateWorkHours($feature, $this->village)));
		$feature->setWorkers(10.0);
		$this->assertEquals(29691, round($this->economy->calculateWorkHours($feature, $this->village)));
		$this->village->setPopulation(200);
		$this->assertEquals(12433, round($this->economy->calculateWorkHours($feature)));

		$this->village->setPopulation(500);
		$road->setWorkers(5.0);
		$this->assertEquals(20559, round($this->economy->calculateWorkHours($road, $this->village)));
		$road->setWorkers(10.0);
		$this->assertEquals(40411, round($this->economy->calculateWorkHours($road, $this->village)));
		$this->village->setPopulation(200);
		$this->assertEquals(16539, round($this->economy->calculateWorkHours($road)));
	}


	public function testCycle() {
		// the below values were gained experimentally
		$this->village->setPopulation(500);
		$this->economy->EconomyCycle($this->village);
		$this->assertEquals(514, $this->village->getPopulation());

		$this->village->setPopulation(10000);
		$this->economy->EconomyCycle($this->village);
		$this->assertEquals(9976, $this->village->getPopulation());

	}


}
