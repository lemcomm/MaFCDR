<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Building;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GeoFeature;
use BM2\SiteBundle\Entity\Road;
use BM2\SiteBundle\Form\BuildingconstructionType;
use BM2\SiteBundle\Form\FeatureconstructionType;
use BM2\SiteBundle\Form\RoadconstructionType;
use CrEOF\Geo\WKB\Parser as BinaryParser;
use CrEOF\Spatial\PHP\Types\Geometry\LineString;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;


/**
 * @Route("/build")
 */
class ConstructionController extends Controller {

	// FIXME: dispatcher uses permission system, but we need to check again to get the reserve values

   /**
     * @Route("/roads")
     */
	public function roadsAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('economyRoadsTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		// FIXME: This was a hack for alpha, where we didn't pre-populate the geofeatures - probably I can remove it?
/*
		if (!$settlement->getGeoMarker()) {
			$marker = new GeoFeature;
			$hidden = $em->getRepository('BM2SiteBundle:FeatureType')->findOneByName('settlement');
			if (!$hidden) {
				throw new \Exception('required hidden feature type not found');
			}
			$marker->setName($settlement->getName());
			$marker->setLocation($settlement->getGeoData()->getCenter());
			$marker->setWorkers(0)->setCondition(0)->setActive(true);
			$marker->setType($hidden);
			$marker->setGeoData($settlement->getGeoData());
			$em->persist($marker);
			$settlement->setGeoMarker($marker);
			$em->flush();
		}
*/

		$roadsdata = $this->get('geography')->findSettlementRoads($settlement);
		foreach ($roadsdata as $key=>$data) {
			$mod = $settlement->getGeoData()->getBiome()->getRoadConstruction();
			$roadsdata[$key]['required'] = $this->get('economy')->RoadHoursRequired($data['road'], $data['length'], $mod);
		}
		$form = $this->createForm(new RoadconstructionType($settlement, $roadsdata));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$existing = $data['existing'];
			$new = $data['new'];
			$totalworkers=0;

			if ($existing) {
				foreach ($existing as $id=>$amount) {
					if ($amount>0) { $totalworkers+=$amount; }
				}
				if ($settlement->getAvailableWorkforcePercent() + $settlement->getRoadWorkersPercent() - $totalworkers < 0.0) {
					// bail out - can't assign more than 100%
					$form->addError(new FormError("economy.toomany"));
				} else {
					foreach ($existing as $id=>$amount) {
						// we are also setting 0 values here because they might currently be > 0
						$road = $em->getRepository('BM2SiteBundle:Road')->find($id);
						if ($road->getQuality()>=5) {
							// max road level: 5
							$amount = 0.0;
						}
						$road->setWorkers(max(0,floatval($amount)));
					}
					$em->flush();
				}
			}

			if ($new && floatval($new['workers'])>0 && $new['from'] && $new['to']) {
				if ($new['from']==$new['to']) {
					$form->addError(new FormError("road.same"));
				} else {
					// verify that we don't already have this identical road :-)
					$exists = false;
					foreach ($roadsdata as $data) {
						$pts = array($data['road']->getWaypoints()->first(), $data['road']->getWaypoints()->last());
						if (in_array($new['from'], $pts) && in_array($new['to'], $pts)) {
							$exists = true;
						}
					}
					if ($exists) {
						$form->addError(new FormError("road.exists"));
						return $this->redirect($request->getUri());
					} else {
						$from = $new['from']->getLocation();
						$to = $new['to']->getLocation();
						if (!$from || !$to) {
							$form->addError(new FormError("road.invalid"));
							return $this->redirect($request->getUri());
						}

						$a = abs($from->getX()-$to->getX());
						$b = abs($from->getY()-$to->getY());
						$length = sqrt($a*$a + $b*$b);
						$jitter = max(2,round(sqrt($length/100))); // ensure at least 2 points or it won't be a linestring
						$points = array();
						$points[] = $from;
						$geom = ''; // FIXME: there must be a better way to do this!
						$xdiff = (($to->getX() - $from->getX()) / ($jitter+1));
						$ydiff = (($to->getY() - $from->getY()) / ($jitter+1));
						for ($i=1;$i<=$jitter;$i++) {
							$x = $from->getX() + $i * $xdiff;
							$y = $from->getY() + $i * $ydiff;
							// jitter - max 25% deviation - TODO: this should depend on biome type...
							// TODO: maybe use Perlin noise here so the same road will always jitter in the same way?
							$x += $ydiff * rand(-25, 25)/100;
							$y += $xdiff * rand(-25, 25)/100;
							$points[] = new Point($x, $y);
							if ($geom!='') {
								$geom.=', ';
							}
							$geom.=$x." ".$y;
						}
						$points[] = $to;
						$path = new LineString($points);

						// test if we cross any impassable terrain, or a cliff
						// FIXME: this sometimes results in an error, with $gemo being only 1 point - why?
						$query = $em->createQuery('SELECT ST_Length(ST_Intersection(g.poly, ST_GeomFromText(:path))) as blocked FROM BM2SiteBundle:GeoData g WHERE g.passable = false AND ST_Intersects(g.poly, ST_GeomFromText(:path))=true');
						$query->setParameter('path', 'LINESTRING('.$geom.')');
						$invalid = $query->getOneOrNullResult();
						if ($invalid && $invalid['blocked']> 5.0) { // small tolerance because otherwise it would sometimes trigger when connecting to docks
							$form->addError(new FormError("road.invalid"));
							return $this->redirect($request->getUri());
						} else {
							$road = new Road;
							$road->setQuality(0)->setCondition(0);
							$road->setWorkers(max(0,floatval($new['workers'])));
							$road->setGeoData($settlement->getGeoData());
							$road->addWaypoint($new['from']);
							$road->addWaypoint($new['to']);
							$road->setPath($path);
							// TODO: check for rivers, only go there if we go to a bridge (and never through, even if to a bridge!)
							$em->persist($road);

							$em->flush();
							return $this->redirect($request->getUri());
						}
					}
				}
			}

		}
		return $this->render('Construction/roads.html.twig', [
			'settlement'=>$settlement,
			'roadsdata'=>$roadsdata,
			'regionpoly'=>$this->get('geography')->findRegionPolygon($settlement),
			'buildingworkers'=>$settlement->getBuildingWorkersPercent(),
			'featureworkers'=>$settlement->getFeatureWorkersPercent(),
			'otherworkers'=>1.0-$settlement->getAvailableWorkforcePercent()+$settlement->getRoadWorkersPercent(),
			'form'=>$form->createView()
		]);
	}

   /**
     * @Route("/features")
     */
	public function featuresAction(Request $request) {
		// TODO: add a way top remove / demolish features
		list($character, $settlement) = $this->get('dispatcher')->gateway('economyFeaturesTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		list($features, $active, $building, $workhours) = $this->featureData($settlement);
		$form = $this->createForm(new FeatureconstructionType($features, $settlement->getGeoData()->getRiver(), $settlement->getGeoData()->getCoast()));

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$existing = $data['existing'];
			$new = $data['new'];
			$totalworkers = 0;

			$em = $this->getDoctrine()->getManager();

			if ($existing) {
				foreach ($existing as $id=>$amount) {
					if ($amount>0) { $totalworkers+=$amount; }
				}
				if ($settlement->getAvailableWorkforcePercent() + $settlement->getFeatureWorkersPercent() - $totalworkers < 0.0) {
					$form->addError(new FormError("economy.toomany"));
				} else {
					foreach ($existing as $id=>$value) {
						$feature = $em->getRepository('BM2SiteBundle:GeoFeature')->find($id);
						if ($feature->getActive()) {
							$feature->setName($value);
						} else {
							// we are also setting 0 values here because they might currently be > 0
							$feature->setWorkers(max(0,floatval($value)));
						}
					}
				}
			} // end existing features

			if ($new['type']) {
				$valid = false;
				if ($new['workers']<=0) {
					$form->addError(new FormError("feature.needworkers"));
				} else {
					if ($settlement->getAvailableWorkforcePercent() + $settlement->getFeatureWorkersPercent(true) < 0.0) {
						$form->addError(new FormError("feature.toomany"));
					} else {
						if (!$new['location_x'] || !$new['location_y']) {
							$form->addError(new FormError("feature.location"));
						} else {
							switch ($new['type']->getName()) {
								case 'docks':
									if ($settlement->getGeoData()->getCoast()==false) {
										$form->addError(new FormError("features.nocoast"));
									} else {
										$location = $this->buildDocks($new);
										$valid=true;
									}
									break;
								case 'bridge':
									if ($settlement->getGeoData()->getRiver()==false) {
										$form->addError(new FormError("features.noriver"));
									} else {
										$location = $this->buildBridge($new);
										$valid=true;
									}
									break;
								case 'borderpost':
									// TODO: don't allow at rivers - players should use bridges there
									$location = $this->buildBorder($new, $settlement->getGeoData());
									$valid=true;
									break;
								default:
									// check if location is within our settlement polygon
									$location = new Point($new['location_x'], $new['location_y']);

									$within = $this->get('geography')->checkContains($settlement->getGeoData(), $location);
									if ($within) {
										$valid = true;
									} else {
										// TODO: maybe snap it, like we do with rivers above?
										$form->addError(new FormError("feature.outside"));
									}
							}
						}
					}
				}
				if ($valid) {
					$feature = new GeoFeature;
					$feature->setType($new['type']);
					$feature->setLocation($location);
					$feature->setGeoData($settlement->getGeoData());
					$feature->setWorkers($new['workers']);
					$feature->setName($new['name']);
					$feature->setActive(false)->setCondition(-$new['type']->getBuildHours());
					$em->persist($feature);
					$settlement->getGeoData()->addFeature($feature);
				}
			} // end new feature

			$em->flush();
			list($features, $active, $building, $workhours) = $this->featureData($settlement);
			$form = $this->createForm(new FeatureconstructionType($features, $settlement->getGeoData()->getRiver(), $settlement->getGeoData()->getCoast()));
		}
		return $this->render('Construction/features.html.twig', [
			'settlement'=>$settlement,
			'regionpoly'=>$this->get('geography')->findRegionPolygon($settlement),
			'features'=>$features,
			'workhours'=>$workhours,
			'active'=>$active,
			'building'=>$building,
			'roadworkers'=>$settlement->getRoadWorkersPercent(),
			'buildingworkers'=>$settlement->getBuildingWorkersPercent(),
			'otherworkers'=>1.0-$settlement->getAvailableWorkforcePercent()+$settlement->getFeatureWorkersPercent(),
			'form'=>$form->createView()
		]);
	}


	private function featureData($settlement) {
		$features = $settlement->getGeoData()->getFeatures();
		$active=0; $building=0; $workhours=array();
		foreach ($features as $feature) {
			if ($feature->getType()->getHidden()==false) {
				if ($feature->getActive()) { $active++; } else { $building++; }
				$workhours[$feature->getId()] = $this->get('economy')->calculateWorkHours($feature, $settlement);
			}
		}
		return array($features, $active, $building, $workhours);
	}

	private function buildDocks($new) {
		// find point to build the docks
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT ST_ClosestPoint(o.poly, ST_POINT(:x,:y)), ST_Distance(o.poly, ST_POINT(:x,:y)) AS distance FROM BM2SiteBundle:GeoData o JOIN o.biome b WHERE b.name = :ocean ORDER BY distance ASC');
		$query->setParameters(array('ocean'=>'ocean', 'x'=>$new['location_x'], 'y'=>$new['location_y']));
		$query->setMaxResults(1);
		$result = $query->getSingleResult();
		$parser = new BinaryParser(array_shift($result));
		$p = $parser->parse();
		$location = new Point($p['value'][0], $p['value'][1]);

		return $location;
	}

	private function buildBridge($new) {
		// find point to build the bridge
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT ST_ClosestPoint(r.course, ST_POINT(:x,:y)), ST_Distance(r.course, ST_POINT(:x,:y)) AS distance FROM BM2SiteBundle:River r ORDER BY distance ASC');
		$query->setParameters(array('x'=>$new['location_x'], 'y'=>$new['location_y']));
		$query->setMaxResults(1);
		$result = $query->getSingleResult();
		$parser = new BinaryParser(array_shift($result));
		$p = $parser->parse();
		$location = new Point($p['value'][0], $p['value'][1]);
		return $location;
	}

	private function buildBorder($new, $geo) {
		// snap to nearest border
		return $this->nearestBorderPoint($new, $geo);
	}

	private function nearestBorderPoint($new, $geo) {
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT ST_ClosestPoint(ST_Boundary(g.poly), ST_POINT(:x,:y)) FROM BM2SiteBundle:GeoData g WHERE g = :geo');
		$query->setParameters(array('geo'=>$geo, 'x'=>$new['location_x'], 'y'=>$new['location_y']));
		$result = $query->getSingleResult();
		$parser = new BinaryParser(array_shift($result));
		$p = $parser->parse();
		$location = new Point($p['value'][0], $p['value'][1]);
		return $location;
	}

   /**
     * @Route("/buildings")
     */
	public function buildingsAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('economyBuildingsTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		$available=array();
		$unavailable=array();
		$all = $em->getRepository('BM2SiteBundle:BuildingType')->findAll();
		foreach ($all as $type) {
			if ($settlement->hasBuilding($type, true) OR !in_array('city',$type->getBuiltIn())) continue; # Already have it? Not buildable here? Move along.
			$data = $this->checkBuildability($settlement, $type);

			if ($data['buildable']) {
				$available[]=$data;
			} else {
				$unavailable[]=$data;
			}
		}

		// TODO: also check prerequisites so you cannot abandon buildings that are required for other buildings you have or are constructing (no abandoning palisades once you've built wood walls, etc.)

		$form = $this->createForm(new BuildingconstructionType($settlement->getBuildings(), $available));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$totalworkers=0;

			foreach ($data['existing'] as $id=>$amount) {
				if ($amount>0) { $totalworkers+=$amount; }
			}
			foreach ($data['available'] as $id=>$amount) {
				if ($amount>0) { $totalworkers+=$amount; }
			}
			if ($settlement->getAvailableWorkforcePercent() + $settlement->getBuildingWorkersPercent() - $totalworkers < 0.0) {
				// bail out - not enough people left to work
				$form->addError(new FormError("economy.toomany"));
			} else {
				foreach ($data['existing'] as $id=>$amount) {
					// we are also setting 0 values here because they might currently be > 0
					$building = $em->getRepository('BM2SiteBundle:Building')->find($id);
					if ($building->getType()->getMinPopulation() * 0.5 > $settlement->getFullPopulation()) {
						// unsustainable
						$amount = 0;
					}
					$building->setWorkers(max(0,floatval($amount)));
				}

				foreach ($data['available'] as $id=>$amount) {
					if ($amount>0) {
						$buildingtype = $em->getRepository('BM2SiteBundle:BuildingType')->find($id);
						$building = new Building;
						$building->setType($buildingtype);
						$building->setSettlement($settlement);
						$building->startConstruction(max(0,floatval($amount)));
						$building->setResupply(0)->setCurrentSpeed(1.0)->setFocus(0);
						$em->persist($building);
					}
				}

				$em->flush();
				return $this->redirect($request->getUri());
			}
		}
		return $this->render('Construction/buildings.html.twig', [
			'settlement'=>$settlement,
			'buildings'=>$settlement->getBuildings(),
			'available'=>$available,
			'unavailable'=>$unavailable,
			'roadworkers'=>$settlement->getRoadWorkersPercent(),
			'featureworkers'=>$settlement->getFeatureWorkersPercent(),
			'otherworkers'=>1.0-$settlement->getAvailableWorkforcePercent()+$settlement->getBuildingWorkersPercent(),
			'form'=>$form->createView()
		]);
	}

	private function checkBuildability($settlement, $type) {
		// TODO: filter out already existing ones
		$data = array('id'=>$type->getId(), 'name'=>$type->getName(), 'buildhours'=>$type->getBuildHours());

		if ($type->getMinPopulation() > $settlement->getFullPopulation()) {
			return array_merge($data,array(
				'buildable' => false,
				'reason' => 'population',
				'value' => $type->getMinPopulation()
			));
		}

		foreach ($settlement->getBuildings() as $old) {
			if ($old->getType()==$type) {
				return array_merge($data,array(
					'buildable' => false,
					'reason' => 'already',
				));
			}
		}

		// special conditions - these are hardcoded because they can be complex
		if ($this->get('economy')->checkSpecialConditions($settlement, $type->getName()) != true) {
			return array_merge($data,array('buildable' => false, 'reason' => 'conditions'));
		}

		$need=array();
		foreach ($type->getRequires() as $required) {
			$have=false;
			foreach ($settlement->getBuildings() as $old) {
				if ($old->getType()==$required && $old->getActive()) {
					$have=true;
					continue;
				}
			}
			if (!$have) {
				$need[]=$required->getName();
			}
		}
		if (!empty($need)) {
			return array_merge($data,array(
				'buildable' => false,
				'reason' => 'prerequisite',
				'value' => implode(', ', $need)
			));
		}

		return array_merge($data,array(
			'buildable' => true
		));
	}

	/**
	  * @Route("/abandonbuilding/{building}")
	  * @Method({"POST"})
	  */
	public function abandonbuildingAction(Building $building, Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('economyBuildingsTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$building->abandon();
		$this->getDoctrine()->getManager()->flush();

		return new RedirectResponse($this->container->get('router')->generate('bm2_site_construction_buildings'));
	}

	/**
	  * @Route("/focus")
	  * @Method({"POST"})
	  */
	public function focusAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('economyBuildingsTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if (!$request->request->has("building") || !$request->request->has("focus")) {
			throw new \Exception("invalid request");
		}
		$id = $request->request->get("building");
		$focus = intval($request->request->get("focus"));

		$focus = max(0, min(3,$focus));

		$em = $this->getDoctrine()->getManager();
		$building = $em->getRepository('BM2SiteBundle:Building')->find($id);
		if (!$building) {
			throw $this->createNotFoundException("building $id not found");
		}
		if ($building->getSettlement() != $settlement) {
			throw new \Exception("invalid building");
		}

		$building->setFocus($focus);

		$response = array(
			"focus" => $focus,
			"base" => round($building->getCurrentSpeed()*100),
			"final" => round($building->getCurrentSpeed()*100*pow(1.5, $focus)),
			"workers" => $building->getEmployees()
		);

		$em->flush();

		return $this->render('Construction/buildingrow.html.twig', [
			'build'=>$building
		]);
	}

}
