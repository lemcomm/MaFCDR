<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Service\Geography;
use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Character;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/building")
 */
class BuildingController extends Controller {

	/**
	  * @Route("/tavern")
	  * @Template
	  */
	public function tavernAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationTavernTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s JOIN s.geo_data g, BM2SiteBundle:GeoData me WHERE ST_Distance(g.center, me.center) < :maxdistance AND me.id = :me AND s != me');
		$query->setParameters(array('me'=>$settlement->getGeoData()->getId(), 'maxdistance'=>20000));
		$nearby_settlements = $query->getResult();

		$query = $em->createQuery('SELECT DISTINCT c FROM BM2SiteBundle:Character c JOIN c.inside_settlement s JOIN s.geo_data g JOIN c.positions p, BM2SiteBundle:GeoData me WHERE ST_Distance(g.center, me.center) < :maxdistance AND me.id = :me AND s != me AND c.slumbering = false');
		$query->setParameters(array('me'=>$settlement->getGeoData()->getId(), 'maxdistance'=>20000));
		$nearby_people = $query->getResult();

		return array(
			'settlement'=>$settlement,
			'nearby_settlements'=>$nearby_settlements,
			'nearby_people'=>$nearby_people
		);
	}

	/**
	  * @Route("/library")
	  * @Template
	  */
	public function libraryAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationLibraryTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT s FROM BM2SiteBundle:Settlement s ORDER BY s.population+s.thralls DESC');
		$query->setMaxResults(5);
		$top_settlements = $query->getResult();

		$cycle = $this->get('appstate')->getCycle();

		$query = $em->createQuery('SELECT s as stat, r, (s.population + s.area * 10) as size FROM BM2SiteBundle:StatisticRealm s JOIN s.realm r WHERE s.cycle = :cycle ORDER BY size DESC');
		$query->setParameter('cycle', $cycle);
		$query->setMaxResults(5);
		$top_realms = $query->getResult();


		return array(
			'settlement'=>$settlement,
			'top_settlements'=>$top_settlements,
			'top_realms'=>$top_realms
		);
	}

	/**
	  * @Route("/map/{map}")
	  */
	public function mapAction($map) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationLibraryTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		// TODO: there are several better ways to do it, e.g.:
		// http://stackoverflow.com/questions/3697748/fastest-way-to-serve-a-file-using-php
		// also headers and caching: http://stackoverflow.com/questions/1353850/serve-image-with-php-script-vs-direct-loading-image

		$allowed = array('allrealms.png', 'majorrealms.png', '2ndrealms.png', 'allrealms-thumb.png', 'majorrealms-thumb.png', '2ndrealms-thumb.png');
		if (in_array($map, $allowed)) {
			header('Content-type: image/png');
			if (gethostname() == 'Ghost.local') {
				readfile("/Users/Tom/Documents/BM2/$map");
			} else {
				readfile("/var/www/qgis/maps/$map");
			}
		} else {
			echo "invalid map";
		}

		exit;
	}

	/**
	  * @Route("/temple")
	  * @Template
	  */
	public function templeAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationTempleTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$data = array(
			"population"	=> array("label" => "population", "data" => array()),
			"thralls"		=> array("label" => "thralls", "data" => array()),
		);
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT s FROM BM2SiteBundle:StatisticSettlement s WHERE s.settlement = :me ORDER BY s.cycle DESC');
		$query->setMaxResults(600); // TODO: max two in-game years - for now. No idea how much flot.js can handle.
		$query->setParameter('me', $settlement);
		$current_cycle = intval($this->get('appstate')->getGlobal('cycle'));
		foreach ($query->getResult() as $row) {
			$cycle = $row->getCycle() - $current_cycle;

			$data["population"]["data"][] = array($cycle, $row->getPopulation());
			$data["thralls"]["data"][] = array($cycle, $row->getThralls());
		}
		return array('settlement'=>$settlement, 'data'=>$data);
	}

	/**
	  * @Route("/barracks")
	  * @Template
	  */
	public function barracksAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationBarracksTest', true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$data = array(
			"militia"		=> array("label" => "militia", "data" => array()),
		);
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT s FROM BM2SiteBundle:StatisticSettlement s WHERE s.settlement = :me ORDER BY s.cycle DESC');
		$query->setMaxResults(600); // TODO: max two in-game years - for now. No idea how much flot.js can handle.
		$query->setParameter('me', $settlement);
		$current_cycle = intval($this->get('appstate')->getGlobal('cycle'));
		foreach ($query->getResult() as $row) {
			$cycle = $row->getCycle() - $current_cycle;

			$data["militia"]["data"][] 	= array($cycle, $row->getMilitia());
		}
		return array('settlement'=>$settlement, 'data'=>$data);
	}

}
