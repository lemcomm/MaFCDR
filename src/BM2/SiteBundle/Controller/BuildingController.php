<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Service\Geography;
use BM2\SiteBundle\Entity\Action;

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
	  * @Route("/inn")
	  * @Template
	  */
	public function innAction(Request $request) {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationInnTest', true);

		$em = $this->getDoctrine()->getManager();
		$offers = $em->getRepository('BM2SiteBundle:KnightOffer')->findAll();

		$already = $character->isDoingAction('military.hire');

		$mercs = $this->get('geography')->findMercenariesNear($settlement, GEOGRAPHY::DISTANCE_MERCENARIES);
		$form = $this->createFormBuilder()
			->add('merc_id', 'hidden')
			->add('merc_number', 'integer', array(
				'required'=>true,
				'label'=>'mercenaries.hiring.amount',
				'translation_domain' => 'actions'
				))
			->add('submit', 'submit', array('label'=>'mercenaries.hiring.submit', 'translation_domain'=>'actions'))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			if ($already) {
				throw new \Exception("You are already hiring a mercenary unit, wait for this to complete.");
			}
			$my_mercs = $em->getRepository('BM2SiteBundle:Mercenaries')->find($data['merc_id']);
			$amount = $data['merc_number'];

			if ($this->get('npc_manager')->hireMercenaries($character, $my_mercs, $amount)) {
				$act = new Action;
				$complete = new \DateTime("now");
				$time = 2 + round(sqrt($amount)/3);
				$complete->add(new \DateInterval("PT".$time."H"));
				$act->setType('military.hire')
					->setCharacter($character)
					->setComplete($complete)
					->setCanCancel(false)
					->setBlockTravel(true);
				$this->get('action_resolution')->queue($act);
				$em->flush();
				return array('success'=>true);
			} else {
				// TODO: translate
				$form->addError(new FormError("Could not hire these mercenaries - not enough gold maybe?"));
			}
		}

		return array(
			'settlement'=>$settlement,
			'offers' => $offers,
			'mercs' => $mercs,
			'gold' => $character->getGold(),
			'form' => $form->createView(),
			'already' => $already
		);
	}

	/**
	  * @Route("/library")
	  * @Template
	  */
	public function libraryAction() {
		list($character, $settlement) = $this->get('dispatcher')->gateway('locationLibraryTest', true);

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
