<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GeoData;
use BM2\SiteBundle\Entity\GeoFeature;
use BM2\SiteBundle\Entity\MapPOI;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Settlement;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\ResultSetMapping;


class Geography {

	private $em;
	private $appstate;
	private $biomes_water=false;
	private $biomes_invalid=false;

	private $embark_distance = 200;
	private $road_buffer = 200;

	private $base_speed = 12000; // 12km a day base speed
	private $spotBase = -1;
	private $spotScout = -1;
	private $scout = null;

	const DISTANCE_BATTLE = 20000;
	const DISTANCE_FEATURE = 20000;
	const DISTANCE_DUNGEON = 50000;
	const DISTANCE_MERCENARIES = 120000;

	public $world = array(
		'x_min' => 0,
		'x_max' => 512000,
		'y_min' => 0,
		'y_max' => 512000,
	);

	public function __construct(EntityManager $em, PermissionManager $pm, AppState $appstate) {
		$this->em = $em;
		$this->pm = $pm;
		$this->appstate = $appstate;
	}

	private function getSpotBase() {
		if ($this->spotBase == -1) {
			$this->spotBase = $this->appstate->getGlobal('spot.basedistance');
		}
		return $this->spotBase;
	}

	private function getSpotScout() {
		if ($this->spotScout == -1) {
			$this->spotScout = $this->appstate->getGlobal('spot.scoutmod');
		}
		return $this->spotScout;
	}

	private function getScout() {
		if ($this->scout == null) {
			$this->scout = $this->em->getRepository('BM2SiteBundle:EntourageType')->findOneByName('scout');
		}
		return $this->scout;
	}

	private function water() {
		if (!$this->biomes_water) {
			$this->biomes_water = $this->em->getRepository('BM2SiteBundle:Biome')->findByName(array('water','ocean'));
		}
		return $this->biomes_water;
	}
	private function cantwalk() {
		if (!$this->biomes_invalid) {
			$this->biomes_invalid = $this->em->getRepository('BM2SiteBundle:Biome')->findByName(array('water','ocean','snow'));
		}
		return $this->biomes_invalid;
	}

	public function travelDetails(Character $character, $to=1.0) {
		$query = $this->em->createQuery('SELECT s as place,b.name as biome,ST_Length(ST_Intersection(g.poly,ST_Line_Substring(c.travel, c.progress, :to)) as length from BM2SiteBundle:Settlement s JOIN s.geo_data g JOIN g.biome b, BM2SiteBundle:Character c WHERE c = :me AND ST_Intersects(g.poly, ST_Line_Substring(c.travel, c.progress, :to)) = true');
		$query->setParameters(array('me'=>$character, 'to'=>$to));
		return $query->getResult();
	}

	public function checkContains(GeoData $geo, $point) {
		$query = $this->em->createQuery('SELECT ST_Contains(g.poly, ST_POINT(:x, :y)) FROM BM2SiteBundle:GeoData g WHERE g = :geo');
		$query->setParameters(array('geo'=>$geo, 'x'=>$point->getX(), 'y'=>$point->getY()));
		$query->setMaxResults(1); // FIXME: due to small overlaps in the geometry, we can get multiple rows here. This hack is not the best solution, we should probably order by biome or something
		$results = $query->getSingleResult();
		return array_shift($results);
	}

	public function findRegionPolygon(Settlement $settlement) {
		$query = $this->em->createQuery('SELECT ST_AsText(g.poly) AS poly FROM BM2SiteBundle:GeoData g where g = :g');
		$query->setParameter('g', $settlement->getGeoData());
		$data = $query->getSingleResult();
		return $data['poly'];
	}

	public function findMyRegion(Character $character) {
		$query = $this->em->createQuery('SELECT g FROM BM2SiteBundle:GeoData g, BM2SiteBundle:Character c where c = :me AND ST_Contains(g.poly, c.location) = true AND g.passable = true');
		$query->setParameter('me', $character);
		$query->setMaxResults(1); // FIXME: due to small overlaps in the geometry, we can get multiple rows here. This hack is not the best solution, we should probably order by biome or something
		return $query->getOneOrNullResult();
	}

	public function findRegionFamiliarity(Character $character, GeoData $geo) {
		$fam = $this->em->getRepository('BM2SiteBundle:RegionFamiliarity')->findOneBy(array('character'=>$character, 'geo_data'=>$geo));
		if ($fam) {
			return $fam->getAmount();
		} else {
			return 0;
		}
	}
	public function findRegionFamiliarityLevel(Character $character, GeoData $geo) {
		$familiarity = $this->findRegionFamiliarity($character, $geo);
		if ($familiarity < 10) return 0;
		if ($familiarity < 100) return 1;
		if ($familiarity < 500) return 2;
		if ($familiarity < 2000) return 3;
		return 4;
	}

	public function findRegionsPolygon($settlements) {
		$query = $this->em->createQuery('SELECT ST_AsText(ST_UNION(g.poly)) AS poly FROM BM2SiteBundle:GeoData g JOIN g.settlement s WHERE s IN (:settlements)');
		$query->setParameter('settlements', $settlements->toArray());
		$data = $query->getSingleResult();
		return $data['poly'];
	}

	public function findWatchTowers(Character $character) {
		// FIXME: this and others like it should probably use ST_DWithin (http://postgis.net/docs/manual-2.0/ST_DWithin.html) which is probably faster
		$distance = $this->calculateSpottingDistance($character);
		$query = $this->em->createQuery('SELECT f as feature, ST_AsGeoJSON(f.location) as json, t.name as typename FROM BM2SiteBundle:GeoFeature f JOIN f.type t, BM2SiteBundle:Character c WHERE c = :me AND t.name = :tower AND f.active = true AND ST_Distance(f.location, c.location) <= :maxdistance');
		$query->setParameters(array('me'=>$character, 'tower'=>'tower', 'maxdistance'=>$distance*0.5));
		return $query->getResult();
	}

	public function findRealmPolygon(Realm $realm, $format='text', $with_subs='true') {
		$estate_ids=array();
		foreach ($realm->findTerritory($with_subs) as $estate) {
			$estate_ids[] = $estate->getId();
		}
		if (empty($estate_ids)) {
			return null;
		} else {
			if ($format=='json') { $as='ST_AsGeoJSON'; } else { $as='ST_AsText'; }
			$query = $this->em->createQuery('SELECT '.$as.'(ST_UNION(g.poly)) as poly FROM BM2SiteBundle:GeoData g JOIN g.settlement s WHERE s.id IN (:estates)');
			$query->setParameters(array('estates'=>$estate_ids));
			$data = $query->getSingleResult();
			return $data['poly'];
		}
	}

	public function findRealmDataPolygons(Realm $realm) {
		$realms = $realm->findAllInferiors(true);
		$realm_ids = array();
		foreach ($realms as $r) {
			$realm_ids[] = $r->getId();
		}

		// this is PostGIS specific:
		$rsm = new ResultSetMapping();
		$rsm->addScalarResult('poly', 'poly');
		$rsm->addScalarResult('area', 'area');
		$query = $this->em->createNativeQuery('SELECT ST_AsGeoJSON((a.p).geom) as poly, ST_Area((a.p).geom) as area FROM (SELECT ST_Dump(ST_UNION(g.poly)) as p from geodata g join settlement s on s.geo_data_id=g.id where s.realm_id in (:realms)) as a', $rsm);
		$query->setParameter('realms', $realm_ids);
		return $query->getResult();
	}


	public function calculateRealmArea(Realm $realm) {
		$estate_ids=array();
		foreach ($realm->findTerritory() as $estate) {
			$estate_ids[] = $estate->getId();
		}
		if (empty($estate_ids)) {
			return 0;
		} else {
			$query = $this->em->createQuery('SELECT ST_Area(ST_UNION(g.poly)) as poly FROM BM2SiteBundle:GeoData g JOIN g.settlement s WHERE s.id IN (:estates)');
			$query->setParameters(array('estates'=>$estate_ids));
			return $query->getSingleScalarResult(); // this is in square m
		}
	}

	public function findNearestDock(Character $character) {
		$query = $this->em->createQuery('SELECT f, ST_Distance(f.location, c.location) AS distance FROM BM2SiteBundle:GeoFeature f JOIN f.type t, BM2SiteBundle:Character c WHERE c = :char AND t.name = :type AND f.active = true ORDER BY distance ASC');
		$query->setParameters(array('char'=> $character, 'type'=>'docks'));
		$query->setMaxResults(1);
		$results = $query->getResult();
		if ($results) {
			return $results[0];
		} else {
			return false;
		}
	}

	public function findMyShip(Character $character) {
		$my_ship = $this->em->getRepository('BM2SiteBundle:Ship')->findOneByOwner($character);
		if (!$my_ship) {
			return array('0'=>null,'distance'=>0);
		}
		$query = $this->em->createQuery('SELECT s, ST_Distance(c.location, s.location) AS distance FROM BM2SiteBundle:Ship s JOIN s.owner c WHERE s = :ship');
		$query->setParameters(array('ship'=>$my_ship));
		$query->setMaxResults(1);
		$results = $query->getResult();
		return $results[0];
	}

	public function findEmbarkPoint(Character $character) {
		// find nearest water:
		$query = $this->em->createQuery('SELECT g, ST_Distance(g.poly, c.location) as distance, ST_AsGeoJSON(ST_ClosestPoint(g.poly, c.location)) as coast FROM BM2SiteBundle:GeoData g, BM2SiteBundle:Character c WHERE g.biome in (:water) AND c = :me ORDER BY distance ASC');
		$query->setParameters(array('water'=>$this->water(), 'me'=>$character));
		$query->setMaxResults(1);
		$result = $query->getSingleResult();

		$coast = json_decode($result['coast']);
		if ($result['distance'] == 0) {
			// damn... not sure if this works...
			$x_off = 0; $y_off = 0;
		} else {
			$x = $coast->coordinates[0] - $character->getLocation()->getX();
			$y = $coast->coordinates[1] - $character->getLocation()->getY();
			$x_off = $this->embark_distance * $x/$result['distance'];
			$y_off = $this->embark_distance * $y/$result['distance'];
		}

		$embark = new Point($coast->coordinates[0] + $x_off, $coast->coordinates[1] + $y_off);

		// FIXME: verify that the resulting point really is within water, and extrapolate further if not
		// -- select * from geodata where ST_Contains(poly, ST_Point(:x, :y));

		return $embark;
	}

	public function findLandPoint(Point $point) {
		$query = $this->em->createQuery('SELECT g, ST_Distance(g.poly, ST_Point(:x, :y)) as distance, ST_AsGeoJSON(ST_ClosestPoint(g.poly, ST_Point(:x, :y))) as coast FROM BM2SiteBundle:GeoData g WHERE g.biome not in (:cantwalk) ORDER BY distance ASC');
		$query->setParameters(array('cantwalk'=>$this->cantwalk(), 'x'=>$point->getX(), 'y'=>$point->getY()));
		$query->setMaxResults(1);
		$result = $query->getSingleResult();

		$coast = json_decode($result['coast']);

		if ($result['distance'] == 0) {
			// FIXME: with our current travel code, this is always zero, which also means extrapolation down there will fail...
			$x_off = 0; $y_off = 0;
		} else {
			$x = $coast->coordinates[0] - $point->getX();
			$y = $coast->coordinates[1] - $point->getY();
			$x_off = $this->embark_distance * $x/$result['distance'];
			$y_off = $this->embark_distance * $y/$result['distance'];
		}

		$land = new Point($coast->coordinates[0] + $x_off, $coast->coordinates[1] + $y_off);

		// verify that the resulting point really is valid, and extrapolate further if not
		$query = $this->em->createQuery('SELECT g.passable FROM BM2SiteBundle:GeoData g WHERE ST_Contains(g.poly, ST_Point(:x, :y)) = true');
		$query->setParameters(array('x'=>$land->getX(), 'y'=>$land->getY()));
		$passable = $query->getSingleScalarResult();

		if (!$passable) {
			$land = new Point($coast->coordinates[0] + $x_off*2, $coast->coordinates[1] + $y_off*2);
			$query = $this->em->createQuery('SELECT g.passable FROM BM2SiteBundle:GeoData g WHERE ST_Contains(g.poly, ST_Point(:x, :y)) = true');
			$query->setParameters(array('x'=>$land->getX(), 'y'=>$land->getY()));
			$passable = $query->getSingleScalarResult();
			if (!$passable) {
				// sorry, we have to stop somewhere...
				return array(false, false);
			}
		}

		return array($land, new Point($coast->coordinates[0], $coast->coordinates[1]));
	}

	public function findNearestSettlement(Character $character) {
		$query = $this->em->createQuery('SELECT s, ST_Distance(g.center, c.location) AS distance FROM BM2SiteBundle:Settlement s JOIN s.geo_data g, BM2SiteBundle:Character c WHERE c = :char ORDER BY distance ASC');
		$query->setParameter('char', $character);
		$query->setMaxResults(1);
		return $query->getSingleResult();
	}
	
	public function findNearestPlace(Character $character) {
		$query = $this->em->createQuery('SELECT p, ST_Distance(p.location, c.location) AS distance FROM BM2SiteBundle:Place p JOIN BM2SiteBundle:Character c WHERE c = :char ORDER BY distance ASC');
		$query->setParameter('char', $character);
		$query->setMaxResults(1);
		return $query->getOneOrNullResult();
	}

	public function findNearestSettlementToPoint(Point $point) {
		$query = $this->em->createQuery('SELECT s, ST_Distance(g.center, ST_Point(:x, :y)) AS distance FROM BM2SiteBundle:Settlement s JOIN s.geo_data g ORDER BY distance ASC');
		$query->setParameters(array('x'=>$point->getX(), 'y'=>$point->getY()));
		$query->setMaxResults(1);
		return $query->getSingleResult();
	}

	public function calculateActionDistance(Settlement $settlement) {
		// FIXME: ugly hardcoded crap
		return 15*(10+sqrt($settlement->getFullPopulation()/5));
	}

	public function calculatePlaceActionDistance(Place $place) {
		if ($place->getSettlement()) {
			return $this->calculateActionDistance($place->getSettlement());
		} else {
			return 400;
		}
	}

	public function findSettlementRoads(Settlement $settlement) {
		$query = $this->em->createQuery('SELECT r as road, ST_Length(r.path) as length
			FROM BM2SiteBundle:Road r JOIN r.geo_data g WHERE g = :geo');
		$query->setParameter('geo', $settlement->getGeoData());
		return $query->getResult();
	}

	public function calculateArea(GeoData $geo) {
		$query = $this->em->createQuery('SELECT ST_AREA(g.poly) FROM BM2SiteBundle:GeoData g WHERE g.id=:id');
		$query->setParameter('id', $geo->getId());
		return $query->getSingleScalarResult(); // this is in square m
	}
	public function calculatePopulationDensity(Settlement $settlement) {
		$area = $this->calculateArea($settlement->getGeoData());
		$area /= 3429904; // recalculate to square miles - as hardcoded in GeographyExtension.php (ugly)
		return $settlement->getFullPopulation()/$area;
	}


	public function calculateDistanceBetweenSettlements(Settlement $a, Settlement $b) {
		$query = $this->em->createQuery('SELECT ST_Distance(ga.center, gb.center) AS distance FROM BM2SiteBundle:Settlement a JOIN a.geo_data ga, BM2SiteBundle:Settlement b JOIN b.geo_data gb WHERE a=:a and b=:b');
		$query->setParameters(array('a'=>$a, 'b'=>$b));
		return $query->getSingleScalarResult();
	}

	public function calculateDistanceToSettlement(Character $a, Settlement $b) {
		$query = $this->em->createQuery('SELECT ST_Distance(a.location, gb.center) AS distance FROM BM2SiteBundle:Character a, BM2SiteBundle:Settlement b JOIN b.geo_data gb WHERE a=:a and b=:b');
		$query->setParameters(array('a'=>$a, 'b'=>$b));
		return $query->getSingleScalarResult();
	}

	public function calculateDistanceToPlace(Character $a, Place $b) {
		if ($b->getLocation()) {
			$query = $this->em->createQuery('SELECT ST_Distance(a.location, b.location) AS distance FROM BM2SiteBundle:Character a, BM2SiteBundle:Place b WHERE a=:a and b=:b');
			$query->setParameters(array('a'=>$a, 'b'=>$b));
			return $query->getSingleScalarResult();
		} else {
			return $this->calculateDistanceToSettlement($a, $b->getSettlement());
		}
	}
	
	public function calculateDistanceToCharacter(Character $a, Character $b) {
		$query = $this->em->createQuery('SELECT ST_Distance(a.location, b.location) AS distance FROM BM2SiteBundle:Character a, BM2SiteBundle:Character b WHERE a=:a and b=:b');
		$query->setParameters(array('a'=>$a, 'b'=>$b));
		return $query->getSingleScalarResult();
	}


	public function findCharactersNearMe(Character $character, $maxdistance, $only_outside=false, $exclude_prisoners=true, $match_battle=false, $exclude_slumbering=false) {
		$qb = $this->em->createQueryBuilder();
		$qb->select('c as character, ST_Distance(me.location, c.location) AS distance')
			->from('BM2SiteBundle:Character', 'me')
			->from('BM2SiteBundle:Character', 'c')
			->where('c.alive = true')
			->andWhere('me = :me')
			->andWhere('me != c');
		if ($character->getInsideSettlement()) {
			$qb->andWhere($qb->expr()->orX(
					$qb->expr()->eq('c.inside_settlement', 'me.inside_settlement'),
					$qb->expr()->lt('ST_Distance(me.location, c.location)', ':maxdistance')
				));
		} else {
			$qb->andWhere('ST_Distance(me.location, c.location) < :maxdistance');
		}
		if ($exclude_slumbering) {
			$qb->andWhere('c.slumbering = false');
		}
		if ($only_outside) {
			$qb->andWhere('c.inside_settlement is NULL');
		}
		$qb->setParameters(array('me'=>$character, 'maxdistance'=>$maxdistance));
		if ($exclude_prisoners) {
			$qb->andWhere('c.prisoner_of is NULL');
		}
		if ($match_battle) {
			// TODO: $match_battle -- must be in the same battle, to prevent assign-into-battle abuses
		}

		$query = $qb->getQuery();
		return $query->getResult();
	}

	public function findCharactersInSpotRange(Character $character) {
		return $this->findCharactersNearMe($character, $this->calculateSpottingDistance($character));
	}
	public function findCharactersInActionRange(Character $character, $only_outside=false, $match_battle=false) {
		// FIXME: this should also include characters in the same settlement
		return $this->findCharactersNearMe($character, $this->calculateInteractionDistance($character), $only_outside, true, $match_battle);
	}

	public function findCharactersInArea($geo) {
		$query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Character c, BM2SiteBundle:GeoData g WHERE g.id = :here AND ST_Contains(g.poly, c.location) = true');
		$query->setParameters(array('here'=>$geo));
		return $query->getResult();
	}

	public function findCharactersInSettlement(Settlement $settlement, $except=null) {
		$qb = $this->em->createQueryBuilder();
		$qb->select('c')
			->from('BM2SiteBundle:Character', 'c')
			->where('c.alive = true')
			->andWhere('c.inside_settlement = :here')->setParameter('here', $settlement);
		if ($except != null) {
			if (is_array($except)) {
				$qb->andWhere('c NOT IN (:except)')->setParameter('except', $except);
			} else {
				$qb->andWhere('c != :except')->setParameter('except', $except);
			}
		}
		$query = $qb->getQuery();
		return $query->getResult();
	}
	
	public function findPlacesNearMe(Character $character, $maxdistance) {
		$query = $this->em->createQuery('SELECT p as place, ST_Distance(me.location, p.location) AS distance, ST_Azimuth(me.location, p.location) AS direction FROM BM2SiteBundle:Character me, BM2SiteBundle:Place p WHERE me.id = :me AND ST_Distance(me.location, p.location) < :maxdistance');
		$query->setParameters(array('me'=>$character, 'maxdistance'=>$maxdistance));
		$places = [];
		foreach ($query->getResult() as $result) {
			if($this->pm->checkPlacePermission($result, $character, 'see') OR $result->getVisible() OR $result->getOwner == $character) {
				$places[] = $result;
			}
		}
		return $places;
	}

	public function findPlacesInSpotRange(Character $character) {
		return $this->findPlacesNearMe($character, $this->calculateSpottingDistance($character));
	}
	
	public function findPlacesInActionRange($character) {
		return $this->findPlacesNearMe($character, $this->calculateInteractionDistance($character));
	}

	public function findBattlesNearMe(Character $character, $maxdistance) {
		$query = $this->em->createQuery('SELECT b as battle, ST_Distance(me.location, b.location) AS distance, ST_Azimuth(me.location, b.location) AS direction FROM BM2SiteBundle:Character me, BM2SiteBundle:Battle b WHERE me.id = :me AND ST_Distance(me.location, b.location) < :maxdistance');
		$query->setParameters(array('me'=>$character, 'maxdistance'=>$maxdistance));
		return $query->getResult();
	}

	public function findBattlesInSpotRange(Character $character) {
		return $this->findBattlesNearMe($character, $this->calculateSpottingDistance($character));
	}
	
	public function findBattlesInActionRange($character) {
		return $this->findBattlesNearMe($character, $this->calculateInteractionDistance($character));
	}

	public function findDungeonsNearMe(Character $character, $maxdistance) {
		$qb = $this->em->createQueryBuilder();
		$qb->select('d as dungeon, ST_Distance(me.location, d.location) AS distance, ST_Azimuth(me.location, d.location) AS direction')
			->from('BM2SiteBundle:Character', 'me')
			->from('DungeonBundle:Dungeon', 'd')
			->where('me = :me')
			->andWhere('ST_Distance(me.location, d.location) < :maxdistance')
			->setParameters(array('me'=>$character, 'maxdistance'=>$maxdistance));
		$query = $qb->getQuery();
		return $query->getResult();
	}
	
	public function findDungeonsInSpotRange(Character $character) {
		return $this->findDungeonsNearMe($character, $this->calculateSpottingDistance($character));
	}
	
	public function findDungeonsInActionRange($character) {
		return $this->findDungeonsNearMe($character, $this->calculateInteractionDistance($character));
	}

	public function findFeaturesNearMe(Character $character, $maxdistance=-1) {
		if ($maxdistance==-1) {
			$maxdistance = $this->calculateInteractionDistance($character);
		}
		$query = $this->em->createQuery('SELECT f as feature FROM BM2SiteBundle:GeoFeature f JOIN f.type t, BM2SiteBundle:Character c WHERE c = :me AND t.hidden = false AND ST_DWithin(f.location, c.location, :maxdistance) = true');
		$query->setParameters(array('me'=>$character, 'maxdistance'=>$maxdistance));
		return $query->getResult();
	}

	public function findMercenariesNear(Settlement $settlement, $maxdistance) {
		$query = $this->em->createQuery('SELECT m FROM BM2SiteBundle:Mercenaries m, BM2SiteBundle:Settlement s JOIN s.geo_data g WHERE m.active = true AND s = :here AND ST_Distance(g.center, m.location) < :maxdistance AND m.hired_by IS NULL');
		$query->setParameters(array('here'=>$settlement, 'maxdistance'=>$maxdistance));
		return $query->getResult();
	}



	public function jsonTravelSegments(Character $character) {
		if ($character->getTravel()) {
			// split my existing route into already-completed and future
			if ($character->getProgress() > 0) {
				$query = $this->em->createQuery('SELECT ST_AsGeoJSON(ST_Line_Substring(c.travel, 0.0, c.progress)) as completed, ST_AsGeoJSON(ST_Line_Substring(c.travel, c.progress, 1.0)) as future FROM BM2SiteBundle:Character c where c.id=:me');
				$query->setParameter('me', $character);
				$result = $query->getSingleResult();
				$completed = $result['completed'];
				$future = $result['future'];
			} else {
				$completed = json_encode(null);
				$future = json_encode(array('type'=>'LineString', 'coordinates'=>$character->getTravel()->toArray()));
			}
		} else {
			$completed = json_encode(null);
			$future = json_encode(null);
		}
		return array('completed'=>$completed, 'future'=>$future);
	}

	public function updateSpottingDistance(Character $character) {
		if ($character->isActive()) {
			$qb = $this->em->createQueryBuilder();
			$qb->select('(:base + SQRT(count(DISTINCT e))*:mod + POW(count(DISTINCT s), 0.3333333))*b.spot as spotdistance')
				->from('BM2SiteBundle:GeoData', 'g')
				->join('g.biome', 'b')
				->from('BM2SiteBundle:Character', 'c')
				->leftJoin('c.soldiers', 's', 'WITH', 's.alive=true')
				->leftJoin('c.entourage', 'e', 'WITH', '(e.type = :scout AND e.alive=true)')
				->where($qb->expr()->eq('ST_Contains(g.poly, c.location)', 'true'))
				->andWhere($qb->expr()->eq('c', ':me'))
				->groupBy('c')
				->addGroupBy('b.spot')
				->setParameter('base', $this->getSpotBase())
				->setParameter('mod', $this->getSpotScout())
				->setParameter('scout', $this->getScout())
				->setParameter('me', $character);
			;
			$result = $qb->getQuery()->getSingleResult();
			$spot = $result['spotdistance'];
		} else {
			$spot = 0.0;
		}
		$character->setSpottingDistance(floor($spot));
		return $spot;
	}

	public function calculateSpottingDistance(Character $character, $with_biome=true) {
		$query = $this->databaseSpottingDistance($character, $with_biome);
		$result = $query->getSingleResult();
		return $result['spotdistance'];
	}

	public function databaseSpottingDistance(Character $character=null, $with_biome=true) {
		// the distance at which we see things
		$spotBase = $this->appstate->getGlobal('spot.basedistance');
		$spotScout = $this->appstate->getGlobal('spot.scoutmod');

		$scout = $this->em->getRepository('BM2SiteBundle:EntourageType')->findOneByName('scout');
		// database magic below:
		// spotting distance =
		//					base distance (what the noble has by himself)
		//					+ tiny amount for his soldiers
		//					+ scouts
		//					* biome modifier
		$qb = $this->em->createQueryBuilder();
		if ($with_biome) {
			$qb->select(array('c as spotter', '(:base + SQRT(count(DISTINCT e))*:mod + POW(count(DISTINCT s), 0.3333333))*b.spot as spotdistance'))
				->from('BM2SiteBundle:GeoData', 'g')
				->join('g.biome', 'b');
		} else {
			$qb->select(array('c as spotter', '(:base + SQRT(count(DISTINCT e))*:mod + POW(count(DISTINCT s), 0.3333333)) as spotdistance'));
		}
		$qb->from('BM2SiteBundle:Character', 'c')
			->leftJoin('c.soldiers', 's', 'WITH', 's.alive=true')
			->leftJoin('c.entourage', 'e', 'WITH', '(e.type = :scout AND e.alive=true)')->setParameter('scout', $scout)
			->where($qb->expr()->eq('ST_Contains(g.poly, c.location)', 'true'))
			->groupBy('c')
			->addGroupBy('b.spot')
			->setParameter('base', $spotBase)
			->setParameter('mod', $spotScout)
		;
		if ($character) {
			$qb->andWhere($qb->expr()->eq('c', ':me'))->setParameter('me', $character);
		}
		return $qb->getQuery();
	}


	public function getLocalBiome(Character $character) {
		$query = $this->em->createQuery('SELECT b as biome, ST_DISTANCE(c.location, g.center) as distance FROM BM2SiteBundle:Biome b join b.geo_data g, BM2SiteBundle:Character c WHERE c = :me AND ST_Contains(g.poly, c.location)=true ORDER BY distance ASC');
		$query->setParameter('me', $character);
		$query->setMaxResults(1); // should not be necessary, but we cannot 100% rule out tiny overlaps in the region data
		$result = $query->getSingleResult();
		return $result['biome'];
	}

	public function calculateInteractionDistance(Character $character) {
		// the distance at which we can interact with others
		$actBase = $this->appstate->getGlobal('act.basedistance');
		$actScout = $this->appstate->getGlobal('act.scoutmod');

		$act = $actBase; // base distance a noble on his own has
		$act += sqrt($character->getSoldiers()->count()); // add a tiny amount for his troops

		// add in scouts
		$scouts = $character->getEntourageOfType('scout')->count();
		$act += pow($scouts, 1/3)*$actScout;

		return $act;
	}

	public function checkSettlementOwnerPresent(Settlement $settlement) {
		if (!$owner = $settlement->getOwner()) {
			return false;
		}
		if ($owner->getSlumbering()) {
			return false;
		}
		$query = $this->em->createQuery('SELECT ST_Distance(g.center, c.location) AS distance FROM BM2SiteBundle:Settlement s JOIN s.geo_data g JOIN s.owner c WHERE s = :here');
		$query->setParameter('here', $settlement);
		$distance = $query->getSingleScalarResult();
		$max = $this->calculateActionDistance($settlement);
		if ($distance > $max) {
			return false;
		}

		return true;
	}

	public function locationName($point) {
		$key = 'nowhere';
		$settlement = null;

		// TODO: find geofeatures as well (that can replace the below since geofeatures contains the hidden settlements and a link to geodata)
		//			so we can name for bridges, border crossings, etc.

		$query = $this->em->createQuery('SELECT g FROM BM2SiteBundle:GeoData g WHERE ST_Contains(g.poly, ST_Point(:x,:y)) = true');
		$query->setParameters(array('x'=>$point->getX(), 'y'=>$point->getY()));
		$result = $query->getOneOrNullResult();
		if ($result) {
			$key = 'near';
			$settlement = $result->getSettlement();
		}
		if (!$settlement || !is_object($settlement)) {
			// battle in a place without settlement - maybe desert? sometimes ocean? FIXME?
			$key = 'around';
			$nearest = $this->findNearestSettlementToPoint($point);
			$settlement=array_shift($nearest);
		}

		return array('key'=>$key, 'entity'=>$settlement);
	}


	public function checkTravelSea(Character $character, $invalid) {
		$query = $this->em->createQuery('SELECT ST_AsGeoJSON(ST_Intersection(c.travel, g.poly)) as intersections FROM BM2SiteBundle:Character c, BM2SiteBundle:GeoData g WHERE c = :me AND g.biome not in (:water) AND ST_Intersects(c.travel, g.poly)=true');
		$query->setParameters(array('me'=> $character, 'water'=>$this->water()));
		$result = $query->getResult();
		$crosses_land = false;
		foreach ($result as $data) {
			$crosses_land = true;
			$invalid[] = array('type'=>'feature', 'geometry'=>json_decode($data['intersections']));
		}
		// FIXME: can go into impassable land!

		if ($crosses_land) {
			// hardcoded 100m final distance from land
			$query = $this->em->createQuery('SELECT ST_AsGeoJSON(ST_StartPoint(ST_GeometryN(ST_Intersection(c.travel, ST_Buffer(g.poly, :buffer)),1))) as disembark FROM BM2SiteBundle:Character c, BM2SiteBundle:GeoData g WHERE c = :me AND g.biome not in (:water) AND ST_Intersects(c.travel, g.poly)=true');
			$query->setParameters(array('me'=> $character, 'water'=>$this->water(), 'buffer'=>$this->embark_distance/10));
			$result = $query->getResult();
			$point = json_decode($result[0]['disembark']);

			$invalid[] = array('type'=>'feature', 'geometry'=>$point);
			// FIXME: this breaks because: ST_Line_Substring: "If 'start' and 'end' have the same value this is equivalent to ST_Line_Interpolate_Point."
			$query = $this->em->createQuery('UPDATE BM2SiteBundle:Character c SET c.travel = ST_Line_Substring(c.travel, 0, ST_Line_Locate_Point(c.travel, ST_Point(:x,:y))) WHERE c=:me');
			$query->setParameters(array('me'=>$character, 'x'=>$point->coordinates[0], 'y'=>$point->coordinates[1]));
			$query->execute();
		}
		return array($invalid, $crosses_land);
	}

	public function checkTravelLand(Character $character, $invalid) {
		$query = $this->em->createQuery('SELECT ST_AsGeoJSON(ST_Intersection(c.travel, g.poly)) as intersections FROM BM2SiteBundle:Character c, BM2SiteBundle:GeoData g WHERE c = :me AND g.passable=false AND ST_Intersects(c.travel, g.poly)=true');
		$query->setParameter('me', $character);
		$result = $query->getResult();
		foreach ($result as $data) {
			$invalid[] = array('type'=>'feature', 'geometry'=>json_decode($data['intersections']));
		}
		return $invalid;
	}

	public function checkTravelRivers(Character $character, $invalid) {
		$bridges = array();
		// FIXME: I think this is a bit ugly with AsText and AsGeoJSON... but it works...
		$query = $this->em->createQuery('SELECT r as river, ST_AsText(ST_Intersection(r.course, c.travel)) as intersection, ST_AsGeoJSON(ST_Intersection(r.course, c.travel)) as json  FROM BM2SiteBundle:River r, BM2SiteBundle:Character c WHERE ST_Intersects(r.course, c.travel)=true AND c.id=:me');
		$query->setParameter('me', $character);
		$result = $query->getResult();
		foreach ($result as $data) {
			$river = $data['river'];
			// look for a nearby bridge
			$bridgedistance = $this->em->getRepository('BM2SiteBundle:Setting')->findOneByName('travel.bridgedistance');
			$bridge = $this->em->getRepository('BM2SiteBundle:FeatureType')->findOneByName('bridge');
			$query = $this->em->createQuery('SELECT f FROM BM2SiteBundle:GeoFeature f WHERE ST_Distance(f.location, ST_GeomFromText(:intersection)) < :distance AND f.active=true AND f.type=:bridge');
			$query->setParameters(array('intersection'=>$data['intersection'], 'distance'=>$bridgedistance->getValue(), 'bridge'=>$bridge));
			$query->setMaxResults(1);
			$bridge = $query->getResult();
			if ($bridge) {
				$bridges[] = array('river'=>$river->getName(), 'bridgename'=>$bridge[0]->getName(), 'bridgelocation'=>$bridge[0]->getGeoData()->getSettlement()->getName());
			} else {
				$invalid[] = array('type'=>'feature', 'geometry'=>json_decode($data['json']));
			}
		}
		return array($invalid, $bridges);
	}

	public function checkTravelCliffs(Character $character, $invalid) {
		// FIXME: I think this is a bit ugly with AsText and AsGeoJSON... but it works...
		$query = $this->em->createQuery('SELECT ST_AsText(ST_Intersection(x.path, c.travel)) as intersection, ST_AsGeoJSON(ST_Intersection(x.path, c.travel)) as json  FROM BM2SiteBundle:Cliff x, BM2SiteBundle:Character c WHERE ST_Intersects(x.path, c.travel)=true AND c.id=:me');
		$query->setParameter('me', $character);
		$result = $query->getResult();
		foreach ($result as $data) {
			$invalid[] = array('type'=>'feature', 'geometry'=>json_decode($data['json']));
		}
		return $invalid;
	}

	public function checkTravelRoads(Character $character) {
		$roads = array();
		$query = $this->em->createQuery('SELECT ST_AsGeoJSON(ST_Intersection(c.travel, ST_Buffer(r.path, :buffer))) as intersections FROM BM2SiteBundle:Character c, BM2SiteBundle:Road r WHERE c = :me AND r.quality > 0 AND ST_Intersects(c.travel, ST_Buffer(r.path, :buffer))=true');
		$query->setParameters(array('me'=> $character, 'buffer'=>$this->road_buffer));
		$result = $query->getResult();
		foreach ($result as $data) {
			$roads[] = array('type'=>'feature', 'geometry'=>json_decode($data['intersections']));
		}
		if (empty($roads)) {
			$roads = false;
		} else {
			$roads = array('type'=>'FeatureCollection', 'features'=>$roads);
		}
		return $roads;
	}

	public function updateTravelSpeed(Character $character) {
		$query = $this->em->createQuery('SELECT ST_Length(c.travel) as length FROM BM2SiteBundle:Character c where c.id=:me');
		$query->setParameter('me', $character);
		$length = $query->getSingleScalarResult();
		if ($length <= 0) return false;

		if ($character->getTravelAtSea()) {
			// at sea, our troop size doesn't matter for speed
			$base_speed = $this->base_speed;
		} else {
			// on land, the base speed is modified by the size of our army
			$prisonercount = 0;
			if ($character->getPrisoners()) {
				foreach ($character->getPrisoners() as $prisoner) {
					$prisonercount += $prisoner->getSoldiers()->count() + ($prisoner->getEntourage()->count()/2);
				}
			}
			$men = $character->getSoldiers()->count() + $prisonercount + ($character->getEntourage()->count()/2);
			$base_speed = $this->base_speed / exp(sqrt($men/200));
			if ($prisonercount > 0) {
				$base_speed *= 0.9;
			}
		}
		if ($character->isNPC()) {
			// make bandits slower so they can't run away so much
			$base_speed *= 0.75;
		}

		// modify further if we are near/on a road:
		$query = $this->em->createQuery('SELECT r FROM BM2SiteBundle:Road r,  BM2SiteBundle:Character c WHERE c = :me AND ST_Distance(r.path, c.location) < :buffer AND r.quality > 0 ORDER BY r.quality DESC');
		$query->setParameters(array('me'=> $character, 'buffer'=>$this->road_buffer));
		$query->setMaxResults(1);
		if ($road = $query->getOneOrNullResult()) {
			$road_mod = 1.0 + $road->getQuality()/10; // up to +50%
			$biome_mod = $this->getLocalBiome($character)->getTravel();
			if ($biome_mod < 1.0) {
				$biome_mod = min(1.0, $biome_mod + $road->getQuality()/20); // depending on biome, up to +100%
			}
			$combined_mod = $road_mod * $biome_mod;
			$base_speed *= $combined_mod;
		} else {
			$base_speed *= $this->getLocalBiome($character)->getTravel();
		}

		$speed = $base_speed / $length;
		$character->setSpeed($speed);

		return true;
	}



	public function findRandomPoint() {
		// FIXME: this should run on the database using the passable check and code from here: http://trac.osgeo.org/postgis/wiki/UserWikiRandomPoint
		$iterations = 0;
		while ($iterations < 100) {
			$x = rand($this->world['x_min'], $this->world['x_max']);
			$y = rand($this->world['y_min'], $this->world['y_max']);

			$query = $this->em->createQuery('SELECT g FROM BM2SiteBundle:GeoData g WHERE ST_Contains(g.poly, ST_Point(:x,:y))=true and g.passable=true');
			$query->setParameters(array('x'=>$x, 'y'=>$y));
			$geo = $query->getOneOrNullResult();
			if ($geo) {
				return array($x, $y, $geo);
			}
			$iterations++;
		}
		return array(false,false, false);
	}

	public function findRandomPointInsidePOI(MapPOI $poi) {
		// FIXME: this also should run on the database using the passable check and code from here: http://trac.osgeo.org/postgis/wiki/UserWikiRandomPoint
		$query = $this->em->createQuery('SELECT ST_Extent(p.geom) as extent FROM BM2SiteBundle:MapPOI p WHERE p = :me');
		$query->setParameter('me', $poi);
		$result = $query->getSingleResult();
		// unfortunately, we have to parse this ourselves - BOX(x,y)
		$extent = array();
		preg_match("/BOX\((.*) (.*),(.*) (.*)\)/", $result['extent'], $extent);
		$x_min = $extent[1];
		$y_min = $extent[2];
		$x_max = $extent[3];
		$y_max = $extent[4];

		$iterations = 0;
		while ($iterations < 200) {
			$x = rand($x_min, $x_max);
			$y = rand($y_min, $y_max);

			$query = $this->em->createQuery('SELECT g FROM BM2SiteBundle:GeoData g, BM2SiteBundle:MapPOI p WHERE ST_Contains(g.poly, ST_Point(:x,:y))=true and g.passable=true and ST_Contains(ST_BUFFER(p.geom, :buffer), ST_Point(:x,:y))=true and p = :me');
			$query->setParameters(array('x'=>$x, 'y'=>$y, 'me'=>$poi, 'buffer'=>5000));
			$geo = $query->getOneOrNullResult();
			if ($geo) {
				return array($x, $y, $geo);
			}
			$iterations++;
		}
		return array(false,false, false);
	}

}
