<?php

namespace BM2\SiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;


/*
	This is the API for external clients, especially our 3D Map Viewer
	Clients should be well-behaved and especially cache results as much as possible
*/



/**
 * @Route("/api")
 */
class ApiController extends Controller {

	/**
	  * @Route("/mapdata")
	  */
	public function mapdataAction() {
		$response = new JsonResponse;

		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT s.id as id, s.name as name, s.population+s.thralls as population, c.id as owner_id, c.name as owner_name, bio.name as biome, g.center as center, SUM(CASE WHEN b.active = true THEN t.defenses ELSE 0 END) as defenses FROM BM2SiteBundle:Settlement s JOIN s.geo_data g JOIN g.biome bio LEFT JOIN s.owner c LEFT JOIN s.buildings b LEFT JOIN b.type t GROUP BY s.id, c.id, g.center, bio.name');

		$settlements = array();
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
			$settlements[] = array(
				'id'		=> $r['id'],
				'geo'		=> array('x'=>$r['center']->getX(), 'y'=>$r['center']->getY()),
				'n'		=> $r['name'],
				'o'		=> array('id'=>$r['owner_id'], 'n'=>$r['owner_name']),
				'p'		=> $r['population'],
				'd'		=> $def,
				'b'		=> $r['biome']
			);
		}

		$response->setData(array(
			"settlements" => $settlements
		));
		return $response;
	}


	/**
	  * @Route("/manualdata")
	  */
	public function manualdataAction() {
		$response = new JsonResponse;

		$em = $this->getDoctrine()->getManager();

		$all_buildings = $em->getRepository("BM2SiteBundle:BuildingType")->findAll();
		$buildings = array();
		foreach ($all_buildings as $building) {
			$enables = array();
			$requires = array();
			foreach ($building->getEnables() as $e) {
				$enables[] = $e->getId();
			}
			foreach ($building->getRequires() as $e) {
				$requires[] = $e->getId();
			}
			$buildings[] = array(
				'id'		=> $building->getId(),
				'name'	=> $this->get('translator')->trans('building.'.$building->getName(), array(), 'economy'),
				'desc'	=> trim($this->get('translator')->trans('description.'.$building->getName(), array(), 'economy')),
				'icon'	=> $building->getIcon(),
				'enables'	=> $enables,
				'requires'	=> $requires
			);
		}

		$all_features = $em->getRepository("BM2SiteBundle:FeatureType")->findByHidden(false);
		$features = array();
		foreach ($all_features as $feature) {
			$features[] = array(
				'id'		=> $feature->getId(),
				'name'	=> $this->get('translator')->trans('feature.'.$feature->getName(), array(), 'economy'),
				'desc'	=> trim($this->get('translator')->trans('description.'.$feature->getName(), array(), 'economy')),
				'icons'	=> array('ready'=>$feature->getIcon(), 'construction'=>$feature->getIconUnderConstruction()),
				'hours'	=> $feature->getBuildHours()
			);
		}

		$all_entourage = $em->getRepository("BM2SiteBundle:EntourageType")->findAll();
		$entourages = array();
		foreach ($all_entourage as $entourage) {
			$entourages[] = array(
				'id'		=> $entourage->getId(),
				'name'	=> $this->get('translator')->transchoice('npc.'.$entourage->getName(), 1),
				'desc'	=> trim($this->get('translator')->trans('description.'.$entourage->getName())),
				'icon'	=> $entourage->getIcon(),
				'provider'=> $entourage->getProvider()->getId()
			);
		}

		$all_items = $em->getRepository("BM2SiteBundle:EquipmentType")->findAll();
		$items = array();
		foreach ($all_items as $item) {
			$items[] = array(
				'id'		=> $item->getId(),
				'name'	=> $this->get('translator')->transchoice('item.'.$item->getName(), 1),
				'desc'	=> trim($this->get('translator')->trans('description.'.$item->getName())),
				'icon'	=> $item->getIcon(),
				'provider'	=> $item->getProvider()->getId(),
				'trainer'	=> $item->getTrainer()->getId()
			);
		}

		$response->setData(array(
			"buildings" => $buildings,
			"features"	=> $features,
			"entourage"	=> $entourages,
			"equipment"	=> $items
		));
		return $response;
	}

	/**
	  * @Route("/active")
	  */
	public function activeUsersAction() {
		$response = new JsonResponse;
		$cycle = $this->get('appstate')->getCycle()-1;

		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT s.today_users as active_users FROM BM2SiteBundle:StatisticGlobal s WHERE s.cycle = :cycle');
		$query->setParameter('cycle', $cycle);
		$response->setData($query->getArrayResult());

		return $response;
	}

}
