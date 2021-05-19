<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\MapMarker;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Service\Geography;
use BM2\SiteBundle\Form\SetMarkerType;

use CrEOF\Spatial\PHP\Types\Geometry\Point;
use CrEOF\Spatial\PHP\Types\Geometry\LineString;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/map")
 */
class MapController extends Controller {

	private $valid_markers = array('waypoint', 'enemy');

   /**
     * @Route("/", name="bm2_map")
     */
	public function indexAction() {
		$character = $this->get('appstate')->getCharacter(false);
		if ($character instanceof Character) {
			if ($character->getTravel()) {
				$travel = $this->get('geography')->jsonTravelSegments($character);
				$details = $this->get('geography')->travelDetails($character);
				$roads = json_encode($this->get('geography')->checkTravelRoads($character));
			} else {
				$travel = null;
				$details = null;
				$roads = null;
			}

			return $this->render('Map/map-openlayers.html.twig', [
				'actdistance'		=>	$this->get('geography')->calculateInteractionDistance($character),
				'spotdistance'		=>	$this->get('geography')->calculateSpottingDistance($character),
				'travel'				=> $travel,
				'traveldetails'	=> $details,
				'travelroads'		=> $roads,
			]);
		} else {

			return $this->render('Info/map-openlayers.html.twig', [
			]);
		}
	}

	/**
	  * @Route("/setmarker", name="bm2_setmarker")
	  */
	public function markerAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$my_realms = $character->findRealms();
		if (!$my_realms) {
			return new JsonResponse("no realms, no markers");
		}

		$em = $this->getDoctrine()->getManager();
		$my_markers = new ArrayCollection($em->getRepository('BM2SiteBundle:MapMarker')->findByOwner($character));

		if (count($my_markers) >= 10) {
			$limit = true;
		} else {
			$limit = false;
		}

		$form = $this->createForm(new SetMarkerType($my_realms));
		$form->handleRequest($request);
		if ($form->isValid() && !$limit) {
			$data = $form->getData();

			$marker = new MapMarker;
			$marker->setName($data['name']);
			$marker->setType($data['type']);
			$marker->setLocation(new Point($data['new_location_x'], $data['new_location_y']));
			$marker->setPlaced(intval($this->get('appstate')->getGlobal('cycle')));
			$marker->setOwner($character);
			$marker->setRealm($data['realm']);
			$em->persist($marker);
			$em->flush();
			$my_markers->add($marker);
			if (count($my_markers) >= 10) { $limit = true; }
		}

		return $this->render('Map/marker.html.twig', array('mymarkers'=>$my_markers, 'limit'=>$limit, 'form'=>$form->createView()));
	}

	/**
	  * @Route("/removemarker/{marker}", name="bm2_removemarker", requirements={"marker"="\d+"})
	  */
	public function removemarkerAction(MapMarker $marker) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if ($marker->getOwner() == $character) {
			$em = $this->getDoctrine()->getManager();
			$em->remove($marker);
			$em->flush();
			return new Response("success");
		}
		return new Response("failed");
	}



	/**
	  * @Route("/data", defaults={"_format"="json"})
	  */
	public function dataAction(Request $request) {
		$type = $request->query->get('type');
		$bbox_raw = $request->query->get('bbox');
		if ($bbox_raw) {
			$bb = explode(",", $bbox_raw);
			$lowleft=array($bb[0], $bb[1]);
			$upright=array($bb[2], $bb[3]);
		} else {
			$lowleft=array(0,0);
			$upright=array(1, 1);
		}
		$mode = $request->query->get('mode');
		switch ($type) {
			case 'polygons':		return $this->jsonData($this->dataPolygons($mode, $lowleft, $upright));
			case 'settlements':	return $this->jsonData($this->dataSettlements($mode, $lowleft, $upright));
			case 'poi':				return $this->jsonData($this->dataPOI($mode, $lowleft, $upright));
			case 'markers':		return $this->jsonData($this->dataMarkers($mode));
			case 'roads':			return $this->jsonData($this->dataRoads($mode, $lowleft, $upright));
			case 'features':		return $this->jsonData($this->dataFeatures($mode, $lowleft, $upright));
			case 'characters':	return $this->jsonData($this->dataCharacters($mode));
			case 'towers':			return $this->jsonData($this->dataTowers($mode));
			case 'realms':			return $this->jsonData($this->dataRealms($mode));
			case 'cultures':		return $this->jsonData($this->dataCultures($mode, $lowleft, $upright));
			case 'offers':			return $this->jsonData($this->dataOffers($mode, $lowleft, $upright));
			case 'trades':
				if ($request->query->get('secret')=='91c72c604637ec525591efac687690660d67d974') {
					return $this->jsonData($this->dataTrades($mode, $request->query->get('resource')));
				}
		}
		return new Response("invalid request");
	}

	private function jsonData($features) {
		$response = new JsonResponse;
		$response->setData(array(
			'type' => 'FeatureCollection',
			'features' => $features
		));
		return $response;
	}



	private function dataPolygons($mode, $lowleft, $upright) {
		$features = array();
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT g.id, b.name, g.humidity, ST_AsGeoJSON(g.poly) AS geopoly FROM BM2SiteBundle:GeoData g JOIN g.biome b WHERE g.passable=true AND ST_Contains(ST_MakeBox2D(ST_Point(:ax,:ay), ST_Point(:bx,:by)), g.poly) = true');
		$query->setParameters(array('ax'=>$lowleft[0], 'ay'=>$lowleft[1], 'bx'=>$upright[0], 'by'=>$upright[1]));
		$iterableResult = $query->iterate();
		while (($row = $iterableResult->next()) !== false) {
			$r = array_shift($row);

			$features[] = array(
				'type' => 'Feature',
				'properties' => array(
					'biome' => $r['biome'],
					'humidity' => $r['humidity']
					),
				'geometry' => json_decode($r['geopoly'])
			);
			$em->clear();
		}
		return $features;
	}

	private function dataPOI($mode, $lowleft, $upright) {
		$features = array();
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT p.id, p.name, ST_AsGeoJSON(p.geom) as geometry FROM BM2SiteBundle:MapPOI p WHERE ST_Contains(ST_MakeBox2D(ST_Point(:ax,:ay), ST_Point(:bx,:by)), p.geom) = true');
		$query->setParameters(array('ax'=>$lowleft[0], 'ay'=>$lowleft[1], 'bx'=>$upright[0], 'by'=>$upright[1]));
		$iterableResult = $query->iterate();
		while (($row = $iterableResult->next()) !== false) {
			$r = array_shift($row);

			$features[] = array(
				'type' => 'Feature',
				'properties' => array(
					'name' => $r['name']
					),
				'geometry' => json_decode($r['geometry'])
			);
			$em->clear();
		}
		return $features;
	}

	private function dataMarkers($mode) {
		$features = array();
		$character = $this->get('appstate')->getCharacter(false);
		if ($character) {
			$my_realms = $character->findRealms();
			if ($my_realms) {
				$em = $this->getDoctrine()->getManager();
				$realms = array();
				foreach ($my_realms as $realm) {
					$realms[] = $realm->getId();
				}
				$query = $em->createQuery('SELECT m.id, m.name, m.type, ST_AsGeoJSON(m.location) as location FROM BM2SiteBundle:MapMarker m WHERE (m.realm IN (:realms) OR m.owner = :me)');
				$query->setParameters(array('realms'=>$realms, 'me'=>$character));
				foreach ($query->getResult() as $row) {
					$features[] = array(
						'type' => 'Feature',
						'properties' => array(
							'id' => $row['id'],
							'name' => $row['name'],
							'type' => $row['type']
							),
						'geometry' => json_decode($row['location'])
					);
					$em->clear();
				}
			}
		}
		return $features;
	}

	private function dataCharacters($mode) {
		$features = array();
		$character = $this->get('appstate')->getCharacter(false);
		if ($character && $character->getLocation()) {
			$em = $this->getDoctrine()->getManager();

			$targets = array();
			$query = $em->createQuery('SELECT s.location, s.current, t.id, t.name, u.id as family FROM BM2SiteBundle:SpotEvent s JOIN s.target t JOIN t.user u LEFT JOIN s.tower w LEFT JOIN w.geo_data g LEFT JOIN g.settlement x WHERE (s.spotter = :me OR (s.spotter IS NULL AND x.owner = :me)) ORDER BY s.target, s.ts DESC');
			$query->setParameter('me', $character);
			foreach ($query->getArrayResult() as $row) {
				$id = $row['id'];
				if (!isset($targets[$id])) {
					$targets[$id] = array('name'=>$row['name'], 'current'=>false, 'family'=>$row['family'], 'line'=>new LineString(array()));
				}
				if ($row['current']) {
					$targets[$id]['current'] = true;
				}
				if (! in_array($row['location'], $targets[$id]['line']->getPoints())) {
					$targets[$id]['line']->addPoint($row['location']);
				}
			}

			foreach ($targets as $id => $target) {
				if (count($target['line']->getPoints()) > 1) {
					$features[] = array(
						'type' => 'Feature',
						'properties' => array(
							'id' => $id,
							'name' => $target['name']
							),
						'geometry' => array('type'=>'LineString', 'coordinates'=>$target['line']->toArray())
					);
				}
				// FIXME: if this is not current, it should show in a different colour or faded, or something!
				$features[] = array(
					'type' => 'Feature',
					'properties' => array(
						'id' => $id,
						'name' => $target['name'],
						'current' => $target['current'],
						'family' => ($target['family'] == $character->getUser()->getId())
						),
					'geometry' => array('type'=>'Point', 'coordinates'=>$target['line']->getPoint(0)->toArray())
				);
			}
		}
		return $features;
	}

/*
	private function dataCharacters($mode) {
		$features = array();
		$character = $this->get('appstate')->getCharacter(false);
		if ($character && $character->getLocation()) {
			$em = $this->getDoctrine()->getManager();

			$qb = $em->createQueryBuilder();
			$qb->select('c.id, c.name, u.id as userid, ST_AsGeoJSON(c.location) as geometry')
				->from('BM2SiteBundle:Character', 'c')
				->join('c.user', 'u')
				->from('BM2SiteBundle:Character', 'me');
			$towers = $this->get('geography')->findWatchTowers($character);
			if (!empty($towers)) {
				$qb->from('BM2SiteBundle:GeoFeature', 'f');
				$qb->where('ST_Distance(c.location, me.location) < :spotting OR ST_Distance(c.location, f.location) < :towerspot');
				$qb->andWhere('f in (:towers)');
				$qb->setParameter('towers', $towers)
					->setParameter('towerspot', $this->get('appstate')->getGlobal('spot.towerdistance', 2500));
			} else {
				$qb->where('ST_Distance(c.location, me.location) < :spotting');
			}
			$qb->andWhere('me = :me')
				->andWhere('c != :me')
				->andWhere('c.prisoner_of IS NULL');
			$qb->setParameter('spotting', $this->get('geography')->calculateSpottingDistance($character))
				->setParameter('me', $character);
			$query = $qb->getQuery();

			foreach ($query->getArrayResult() as $r) {
				$features[] = array(
					'type' => 'Feature',
					'properties' => array(
						'id' => $r['id'],
						'name' => $r['name'],
						'family' => ($r['userid']==$character->getUser()->getId()),
						),
					'geometry' => json_decode($r['geometry'])
				);
			}
		}
		return $features;
	}
*/

	private function dataTowers($mode) {
		$features = array();
		$character = $this->get('appstate')->getCharacter(false);
		if ($character && $character->getLocation()) {
			$towers = $this->get('geography')->findWatchTowers($character);
			foreach ($towers as $towerdata) {
				$tower = $towerdata['feature'];
				$features[] = array(
					'type' => 'Feature',
					'properties' => array(
						'id' => $tower->getId(),
						'type' => $towerdata['typename']
						),
					'geometry' => json_decode($towerdata['json'])
				);
			}
		}
		return $features;
	}

	private function dataCultures($mode, $lowleft, $upright) {
		$features = array();
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT g.id, c.name as culture, c.colour_hex as colour, ST_AsGeoJSON(g.poly) AS geopoly FROM BM2SiteBundle:GeoData g JOIN g.settlement s JOIN s.culture c WHERE g.passable=true AND ST_Contains(ST_MakeBox2D(ST_Point(:ax,:ay), ST_Point(:bx,:by)), g.poly) = true');
		$query->setParameters(array('ax'=>$lowleft[0], 'ay'=>$lowleft[1], 'bx'=>$upright[0], 'by'=>$upright[1]));
		$iterableResult = $query->iterate();
		while (($row = $iterableResult->next()) !== false) {
			$r = array_shift($row);

			$features[] = array(
				'type' => 'Feature',
				'properties' => array(
//					'culture' => $r['culture'],
					'colour_hex' => $r['colour']
					),
				'geometry' => json_decode($r['geopoly'])
			);
			$em->clear();
		}
		return $features;
	}

	private function dataOffers($mode, $lowleft, $upright) {
		$features = array();
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT o.id, s.id as settlement, count(t) as amount, ST_AsGeoJSON(g.center) as location FROM BM2SiteBundle:KnightOffer o JOIN o.settlement s JOIN s.geo_data g JOIN o.soldiers t WHERE ST_Contains(ST_MakeBox2D(ST_Point(:ax,:ay), ST_Point(:bx,:by)), g.center) = true GROUP BY o.id, s.id, g.center');
		$query->setParameters(array('ax'=>$lowleft[0], 'ay'=>$lowleft[1], 'bx'=>$upright[0], 'by'=>$upright[1]));
		$result = $query->getResult();
		foreach ($result as $row) {
			$features[] = array(
				'type' => 'Feature',
				'properties' => array(
					'id' => $row['id'],
					'place' => $row['settlement'],
					'amount' => $row['amount']
					),
				'geometry' => json_decode($row['location'])
			);
		}
		return $features;
	}

	private function dataSettlements($mode, $lowleft, $upright) {
		$features = array();
		$em = $this->getDoctrine()->getManager();
		if ($mode=='start') {
			$query = $em->createQuery('SELECT s.id, s.name, c.id as owner_id, s.population+s.thralls as population, ST_AsGeoJson(g.center) as center FROM BM2SiteBundle:Settlement s JOIN s.geo_data g LEFT JOIN s.owner c WHERE s.owner IS NOT NULL AND s.allow_spawn=true AND ST_Contains(ST_MakeBox2D(ST_Point(:ax,:ay), ST_Point(:bx,:by)), g.center) = true');
			$query->setParameters(array('ax'=>$lowleft[0], 'ay'=>$lowleft[1], 'bx'=>$upright[0], 'by'=>$upright[1]));
		} else {
			$query = $em->createQuery('SELECT s.id, s.name, c.id as owner_id, s.population+s.thralls as population, ST_AsGeoJson(g.center) as center, SUM(CASE WHEN b.active = true THEN t.defenses ELSE 0 END) as defenses FROM BM2SiteBundle:Settlement s JOIN s.geo_data g LEFT JOIN s.owner c LEFT JOIN s.buildings b LEFT JOIN b.type t WHERE ST_Contains(ST_MakeBox2D(ST_Point(:ax,:ay), ST_Point(:bx,:by)), g.center) = true GROUP BY s.id, c.id, g.center');
			$query->setParameters(array('ax'=>$lowleft[0], 'ay'=>$lowleft[1], 'bx'=>$upright[0], 'by'=>$upright[1]));
		}
		foreach ($query->getResult() as $r) {
			$def = 0;
			if (isset($r['defenses'])) {
				if ($r['defenses']>200) {
					$def = 4;
				} else if ($r['defenses']>100) {
					$def = 3;
				} else if ($r['defenses']>50) {
					$def = 2;
				} else if ($r['defenses']>20) {
					$def = 1;
				}
			}
			$features[] = array(
				'type' => 'Feature',
				'properties' => array(
					'id' => $r['id'],
					'name' => $r['name'],
					'owned' => $r['owner_id']?true:false,
					'population' => $r['population'],
					'defenses' => $def
					),
				'geometry' => json_decode($r['center'])
			);
		}
		return $features;
	}

	private function dataRoads($mode, $lowleft, $upright) {
		$features = array();
		$character = $this->get('appstate')->getCharacter(false);
		$em = $this->getDoctrine()->getManager();

		if ($character && $character->getLocation()) {
			$seen = array(0); // use 0 value to prevent empty arrays, which would break the query one below
			$query = $em->createQuery('SELECT r.id, r.quality, ST_AsGeoJSON(r.path) as roadpath FROM BM2SiteBundle:Road r, BM2SiteBundle:Character me WHERE me = :me AND ST_Distance(r.path, me.location) < :maxdistance');
			$query->setParameters(array('me'=>$character, 'maxdistance'=>Geography::DISTANCE_FEATURE));
			foreach ($query->getResult() as $r) {
				$seen[]=$r['id'];
				$features[] = array(
					'type' => 'Feature',
					'properties' => array(
						'quality' => $r['quality']
						),
					'geometry' => json_decode($r['roadpath'])
				);
			}

			$query = $em->createQuery('SELECT k.amount, r.id, r.quality, ST_AsGeoJSON(r.path) as roadpath FROM BM2SiteBundle:RegionFamiliarity k JOIN k.geo_data g JOIN g.roads r JOIN k.character me WHERE me = :me AND r.id NOT IN (:seen)');
			$query->setParameters(array('me'=>$character, 'seen'=>$seen));
			foreach ($query->getResult() as $r) {
				$features[] = array(
					'type' => 'Feature',
					'properties' => array(
						'quality' => $r['quality']
						),
					'geometry' => json_decode($r['roadpath'])
				);
			}
		}
		return $features;
	}


	private function dataFeatures($mode, $lowleft, $upright) {
		$features = array();
		$character = $this->get('appstate')->getCharacter(false);
		$em = $this->getDoctrine()->getManager();

		if ($character && $character->getLocation()) {
			$seen = array(0); // use 0 value to prevent empty arrays, which would break the query one below
			$query = $em->createQuery('SELECT f.id, f.name, t.name as type, f.active, ST_AsGeoJSON(f.location) as location FROM BM2SiteBundle:GeoFeature f JOIN f.type t, BM2SiteBundle:Character me WHERE me = :me AND t.hidden=false AND ST_Distance(f.location, me.location) < :maxdistance');
			$query->setParameters(array('me'=>$character, 'maxdistance'=>Geography::DISTANCE_FEATURE));
			foreach ($query->getResult() as $r) {
				$seen[]=$r['id'];
				$features[] = array(
					'type' => 'Feature',
//					'id' => 'feature_'.$r['id'],
					'properties' => array(
						'type' => $r['type'],
						'name' => $r['name'],
						'active' => $r['active']
						),
					'geometry' => json_decode($r['location'])
				);
			}

			$query = $em->createQuery('SELECT k.amount, f.id, f.name, t.name as type, f.active, ST_AsGeoJSON(f.location) as location FROM BM2SiteBundle:RegionFamiliarity k JOIN k.geo_data g JOIN g.features f JOIN f.type t JOIN k.character me WHERE me = :me AND t.hidden=false AND f.id NOT IN (:seen)');
			$query->setParameters(array('me'=>$character, 'seen'=>$seen));
			foreach ($query->getResult() as $r) {
				$features[] = array(
					'type' => 'Feature',
//					'id' => 'feature_'.$r['id'],
					'properties' => array(
						'type' => $r['type'],
						'name' => $r['name'],
						'active' => $r['active']
						),
					'geometry' => json_decode($r['location'])
				);
			}

			// mix in battles
			$query = $em->createQuery('SELECT b.id, ST_AsGeoJSON(b.location) as location FROM BM2SiteBundle:Battle b, BM2SiteBundle:Character me WHERE me = :me AND ST_Distance(b.location, me.location) < :maxdistance');
			$query->setParameters(array('me'=>$character, 'maxdistance'=>Geography::DISTANCE_BATTLE));
			foreach ($query->getResult() as $b) {
				$features[] = array(
					'type' => 'Feature',
//					'id' => 'battle_'.$b['id'],
					'properties' => array(
						'type' => 'battle',
						'name' => null,
						'active' => true,
						),
					'geometry' => json_decode($b['location'])
				);
				$em->clear();
			}

			// mix in ships
			$query = $em->createQuery('SELECT s.id, ST_AsGeoJSON(s.location) as location FROM BM2SiteBundle:Ship s, BM2SiteBundle:Character me WHERE me = :me AND (ST_Distance(s.location, me.location) < :maxdistance OR s.owner = :me)');
			$query->setParameters(array('me'=>$character, 'maxdistance'=>Geography::DISTANCE_FEATURE));
			$iterableResult = $query->iterate();
			while (($row = $iterableResult->next()) !== false) {
				$s = array_shift($row);

				$features[] = array(
					'type' => 'Feature',
//					'id' => 'ship_'.$s['id'],
					'properties' => array(
						'type' => 'ship',
						'name' => 'ship',
						'active' => true,
						),
					'geometry' => json_decode($s['location'])
				);
				$em->clear();
			}

			// mix in dungeons
			$query = $em->createQuery('SELECT d.id, d.area as area, ST_AsGeoJSON(d.location) as location FROM DungeonBundle:Dungeon d, BM2SiteBundle:Character me WHERE me = :me AND ST_Distance(d.location, me.location) < :maxdistance');
			$query->setParameters(array('me'=>$character, 'maxdistance'=>$this->get('geography')->calculateSpottingDistance($character)));
			$iterableResult = $query->iterate();
			while (($row = $iterableResult->next()) !== false) {
				$d = array_shift($row);

				$features[] = array(
					'type' => 'Dungeon',
//					'id' => 'dungeon_'.$d['id'],
					'properties' => array(
						'type' => $d['area'],
						'name' => $this->get('translator')->trans('area.'.$d['area'], array(), "dungeons"),
						'active' => true,
						),
					'geometry' => json_decode($d['location'])
				);
				$em->clear();
			}

			// mix in places
			$results = $this->get('geography')->findPlacesInSpotRange($character);
			if ($results != null) {
				foreach ($results as $p) {
					if ($p->getLocation()) {
						$features[] = array(
							'type' => 'Place',
							'properties' => array(
								'type' => $p->getType()->getName(),
								'name' => $p->getName(),
								'active' => true,
								),
							'geometry' => json_decode($p->getLocation())
						);
					}
				}
			}
		}
		return $features;
	}

	private function dataRealms($mode) {
		$features = array();
		$em = $this->getDoctrine()->getManager();
		$id=1;
		switch ($mode) {
			case "all":
				$realms = $em->getRepository('BM2SiteBundle:Realm')->findAll();
				break;
			case '2nd':
				$query = $em->createQuery('SELECT r FROM BM2SiteBundle:Realm r JOIN r.superior s WHERE s.superior IS NULL');
				$realms = $query->getResult();
				break;
			case '1': case '2': case '3': case '4': case '5': case '6': case '7':
				$realms = $em->getRepository('BM2SiteBundle:Realm')->findByType($mode);
				break;
			default:
				$realms = $em->getRepository('BM2SiteBundle:Realm')->findBySuperior(null);
		}
		foreach ($realms as $realm) {
			$data = $this->get('geography')->findRealmDataPolygons($realm);
			foreach ($data as $row) {
				$geo = json_decode($row['poly']);
				$settlements = $row['area'] / 64072607; // this is a hack - area divided by average area
				$features[] = array(
							'type' => 'Feature',
//							'id' => $id++,
							'properties' => array(
								'name' => $realm->getName(),
								'colour_hex' => $realm->getColourHex(),
								'colour_rgb' => $realm->getColourRgb(),
								'settlements' => $settlements
								),
							'geometry' => $geo
						);
			}
		}

		if ($mode=="2nd") {
			// add in those territories that are direct parts
			$realms = $em->getRepository('BM2SiteBundle:Realm')->findBy(array('superior'=>null));
			foreach ($realms as $realm) {
				$data = $this->get('geography')->findRealmDataPolygons($realm);
				foreach ($data as $row) {
					$geo = json_decode($row['poly']);
					$settlements = $row['area'] / 64072607; // this is a hack - area divided by average area
					$features[] = array(
								'type' => 'Feature',
//								'id' => $id++,
								'properties' => array(
									'name' => $realm->getName(),
									'colour_hex' => $realm->getColourHex(),
									'colour_rgb' => $realm->getColourRgb(),
									'settlements' => $settlements
									),
								'geometry' => $geo
							);
				}
			}
		}

		return $features;
	}

    // FIXME: this is not used anymore ?
	private function realmdataArray($realm, $settlements, $with_subs) {
		$data = json_decode($this->get('geography')->findRealmDataPolygons($realm));
		var_dump($data);


		return array(
			'type' => 'Feature',
//			'id' => $realm->getId(),
			'properties' => array(
				'name' => $realm->getName(),
				'colour_hex' => $realm->getColourHex(),
				'colour_rgb' => $realm->getColourRgb(),
				'settlements' => $settlements
				),
			'geometry' => json_decode($this->get('geography')->findRealmPolygon($realm, 'json', $with_subs))
		);
	}


	private function dataTrades($mode, $resource=null) {
		$features = array();
		$em = $this->getDoctrine()->getManager();
		if ($resource) {
			$resource = $em->getRepository('BM2SiteBundle:ResourceType')->findOneByName($resource);
			$query = $em->createQuery('SELECT t.id, t.amount, r.name, ST_AsGeoJSON(ST_MakeLine(aa.center, bb.center)) as geometry FROM BM2SiteBundle:Trade t JOIN t.resource_type r JOIN t.source a JOIN a.geo_data aa JOIN t.destination b JOIN b.geo_data bb WHERE r = :resource');
			$query->setParameters('resource', $resource);
		} else {
			$query = $em->createQuery('SELECT t.id, t.amount, r.name, ST_AsGeoJSON(ST_MakeLine(aa.center, bb.center)) as geometry FROM BM2SiteBundle:Trade t JOIN t.resource_type r JOIN t.source a JOIN a.geo_data aa JOIN t.destination b JOIN b.geo_data bb');
		}
		$iterableResult = $query->iterate();
		while (($row = $iterableResult->next()) !== false) {
			$r = array_shift($row);

			$features[] = array(
				'type' => 'Feature',
//				'id' => $r['id'],
				'properties' => array(
					'resource' => $r['name'],
					'amount' => $r['amount']
					),
				'geometry' => json_decode($r['geometry'])
			);
			$em->clear();
		}

		return $features;
	}


	/**
     * @Route("/details/settlement/{id}", requirements={"id"="\d+"})
     */
	public function detailsSettlementAction($id) {
		$em = $this->getDoctrine()->getManager();
		$settlement = $em->getRepository('BM2SiteBundle:Settlement')->find($id);

		return $this->render('Map/detailsSettlement.html.twig', [
			'settlement'=>$settlement
		]);
	}

	/**
     * @Route("/details/offerslist/{id}", requirements={"id"="\d+"})
     */
	public function detailsOfferslistAction(Settlement $id) {
		$em = $this->getDoctrine()->getManager();
		$offers = $em->getRepository('BM2SiteBundle:KnightOffer')->findBySettlement($id);

		return $this->render('Map/detailsOfferslist.html.twig', [
			'offers'=>$offers
		]);
	}

	/**
     * @Route("/details/character/{id}", requirements={"id"="\d+"})
     */
	public function detailsCharacterAction($id) {
		$em = $this->getDoctrine()->getManager();
		// TODO: verify distance
		$char = $em->getRepository('BM2SiteBundle:Character')->find($id);

		$realms = $char->findRealms();
		$ultimates = new ArrayCollection;
		foreach ($realms as $r) {
			$ult = $r->findUltimate();
			if (!$ultimates->contains($ult)) {
				$ultimates->add($ult);
			}
		}

		return $this->render('Map/detailsCharacter.html.twig', [
			'char'=>$char,
			'realms'=>$realms,
			'ultimates'=>$ultimates
		]);
	}

   /**
     * @Route("/details/marker/{id}", requirements={"id"="\d+"})
     */
	public function detailsMarkerAction($id) {
		$em = $this->getDoctrine()->getManager();
		$marker = $em->getRepository('BM2SiteBundle:MapMarker')->find($id);

		// TODO: check if we are allowed to see this marker

		return $this->render('Map/detailsMarker.html.twig', [
			'marker'=>$marker
		]);
	}

}
