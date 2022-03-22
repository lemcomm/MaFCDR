<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\GeoData;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\River;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Libraries\Perlin;
use CrEOF\Spatial\PHP\Types\Geometry\LineString;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use CrEOF\Spatial\PHP\Types\Geometry\Polygon;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use BM2\SiteBundle\Libraries\MovingAverage;

/**
 * @Route("/game")
 */
class GameController extends Controller {

	private $start_cycle = 2200;
	private $low_moving_average_cycles = 6; // game week
	private $high_moving_average_cycles = 24; // 4 game weeks

	/**
	  * @Route("/")
	  */
	public function indexAction($time_spent=0) {
		$game = $this->get('game_runner');
		$status = array();

		$cycle = $game->getCycle();

		$parts = array(
			'action'=>'Actions',
			'travel'=>'Travel',
			'settlement'=>'Settlements',
			'road'=>'Roads',
			'building'=>'Buildings',
		);

		foreach ($parts as $part=>$name) {
			list($total, $done) = $game->Progress($part);
			if ($total>0) {
				$percent = ($done*100)/$total;
			} else {
				$percent=100;
			}
			$status[]=array('name'=>$name, 'total'=>$total, 'done'=>$done, 'percent'=>$percent);
		}

		return $this->render('Game/status.html.twig', [
			'cycle' => $cycle,
			'status' => $status,
			'time_spent' => $time_spent
		]);
	}

	/**
	  * @Route("/users")
	  */
	public function usersAction() {
		$em = $this->getDoctrine()->getManager();

		$query = $em->createQuery('SELECT u FROM BM2SiteBundle:User u WHERE u.account_level > 0 ORDER BY u.username ASC');
		$users = array();
		foreach ($query->getResult() as $user) {
			if ($user->getActiveCharacters()->count()>0 OR $user->getRetiredCharacters()->count()>0) {
				$users[] = array(
					'name' => $user->getUsername(),
					'level' => $user->getAccountLevel(),
					'credits' => $user->getCredits(),
					'created' => $user->getCreated(),
					'last_login' => $user->getLastLogin(),
					'characters' => $user->getLivingCharacters()->count(),
					'retired' => $user->getRetiredCharacters()->count(),
					'dead' => $user->getDeadCharacters()->count()
				);
			}
		}

		return $this->render('Game/users.html.twig', [
			'users'=>$users
		]);
	}


   /**
     * @Route("/statistics/{start}", requirements={"start"="\d+"}, defaults={"start"=-1})
     */
	public function statisticsAction($start) {
		if ($start == -1) { $start = $this->start_cycle; }
		$em = $this->getDoctrine()->getManager();

		$global = array(
			"users" 					=> array("label" => "registered users", "data" => array(), "yaxis" => 2),
			"active_users" 		=> array("label" => "active users", "data" => array()),
			"ever_paid_users" 	=> array("label" => "users who ever paid anything", "data" => array()),
			"paying_users" 		=> array("label" => "paying users", "data" => array()),
			"characters" 			=> array("label" => "created characters", "data" => array(), "yaxis" => 2),
			"living_characters" 	=> array("label" => "living characters", "data" => array()),
			"active_characters" 	=> array("label" => "active characters", "data" => array()),
			"deceased_characters"=> array("label" => "deceased characters", "data" => array()),

			"realms" 				=> array("label" => "realms", "data" => array()),
			"major_realms" 		=> array("label" => "sovereign realms", "data" => array()),
			"buildings"				=> array("label" => "buildings", "data" => array()),
			"constructions"		=> array("label" => "constructions", "data" => array(), "yaxis" => 2),
			"abandoned"				=> array("label" => "abandoned", "data" => array(), "yaxis" => 2),
			"features"				=> array("label" => "features", "data" => array()),
			"roads"					=> array("label" => "roads", "data" => array()),

			"trades" 				=> array("label" => "trades", "data" => array()),
			"battles" 				=> array("label" => "battles", "data" => array()),
			"battles_avg"			=> array("label" => $this->low_moving_average_cycles." days moving average", "data" => array()),
			"battles_avg2"			=> array("label" => $this->high_moving_average_cycles." days moving average", "data" => array()),

			"soldiers"				=> array("label" => "soldiers", "data" => array()),
			"militia"				=> array("label" => "militia", "data" => array()),
			"recruits"				=> array("label" => "recruits", "data" => array()),
			"offers"					=> array("label" => "offered to knights", "data" => array(), "yaxis" => 2),
			"entourage"				=> array("label" => "entourage", "data" => array()),
			"peasants"				=> array("label" => "peasants", "data" => array()),
			"thralls"				=> array("label" => "thralls", "data" => array()),
			"thralls2"				=> array("label" => "thralls", "data" => array(), "yaxis" => 2),
			"population"			=> array("label" => "total population", "data" => array()),
		);
		$current = null; $total = 0;
		$battles_avg = new MovingAverage($this->low_moving_average_cycles);
		$battles_avg2 = new MovingAverage($this->high_moving_average_cycles);
		$query = $em->createQuery('SELECT s FROM BM2SiteBundle:StatisticGlobal s WHERE s.cycle >= :start ORDER BY s.cycle ASC');
		$query->setParameter('start', $start);
		foreach ($query->getResult() as $row) {
			$current = $row;
			$cycle = $row->getCycle();

			$global["users"]["data"][] = array($cycle, $row->getUsers());
			$global["active_users"]["data"][] = array($cycle, $row->getActiveUsers());
			$global["ever_paid_users"]["data"][] = array($cycle, $row->getEverPaidUsers());
			$global["paying_users"]["data"][] = array($cycle, $row->getPayingUsers());
			$global["characters"]["data"][] = array($cycle, $row->getCharacters());
			$global["living_characters"]["data"][] = array($cycle, $row->getLivingCharacters());
			$global["active_characters"]["data"][] = array($cycle, $row->getActiveCharacters());
			$global["deceased_characters"]["data"][] = array($cycle, $row->getDeceasedCharacters());

			$global["realms"]["data"][] = array($cycle, $row->getRealms());
			$global["major_realms"]["data"][] = array($cycle, $row->getMajorRealms());
			$global["buildings"]["data"][] = array($cycle, $row->getBuildings());
			$global["constructions"]["data"][] = array($cycle, $row->getConstructions());
			$global["abandoned"]["data"][] = array($cycle, $row->getAbandoned());
			$global["features"]["data"][] = array($cycle, $row->getFeatures());
			$global["roads"]["data"][] = array($cycle, $row->getRoads());

			$global["trades"]["data"][] = array($cycle, $row->getTrades());
			$global["battles"]["data"][] = array($cycle, $row->getBattles());
			$battles_avg->addData($row->getBattles());
			$global["battles_avg"]["data"][] = array($cycle-floor($this->low_moving_average_cycles/2), $battles_avg->getAverage());
			$battles_avg2->addData($row->getBattles());
			$global["battles_avg2"]["data"][] = array($cycle-floor($this->high_moving_average_cycles/2), $battles_avg2->getAverage());

			$global["soldiers"]["data"][] = array($cycle, $row->getSoldiers());
			$global["militia"]["data"][] = array($cycle, $row->getMilitia());
			$global["recruits"]["data"][] = array($cycle, $row->getRecruits());
			$global["offers"]["data"][] = array($cycle, $row->getOffers());
			$global["entourage"]["data"][] = array($cycle, $row->getEntourage());
			$global["peasants"]["data"][] = array($cycle, $row->getPeasants());
			$global["thralls"]["data"][] = array($cycle, $row->getThralls());
			$global["thralls2"]["data"][] = array($cycle, $row->getThralls());
			$total = $row->getSoldiers()+$row->getMilitia()+$row->getRecruits()+$row->getEntourage()+$row->getPeasants()+$row->getThralls();
			$global["population"]["data"][] = array($cycle, $total);
		}

		return $this->render('Game/statistics.html.twig', [
			'current'=>$current, 'global'=>$global, 'total'=>$total
		]);
	}

	/**
	  * @Route("/statistics/compare/{what}")
	  */
	public function comparedataAction($what) {
		$em = $this->getDoctrine()->getManager();

		$data = array();
        $avg = 0; $q = '1 = 1';

		switch ($what) {
			case 'area':
				$query = $em->createQuery('SELECT AVG(s.area) FROM BM2SiteBundle:StatisticRealm s');
				$avg = round($query->getSingleScalarResult()/2);
				$q = 's.area > :avg';
				break;
			case 'soldiers':
				$query = $em->createQuery('SELECT AVG(s.soldiers) FROM BM2SiteBundle:StatisticRealm s');
				$avg = round($query->getSingleScalarResult()/2);
				$q = 's.soldiers > :avg';
				break;
		}

		$query = $em->createQuery('SELECT s FROM BM2SiteBundle:StatisticRealm s WHERE s.cycle >= :start AND s.superior IS NULL AND '.$q.' ORDER BY s.cycle ASC');
		$query->setParameters(array('avg'=>$avg, 'start'=>$this->start_cycle));
		foreach ($query->getResult() as $row) {
			$cycle = $row->getCycle();
			$id = $row->getRealm()->getId();

			if (!isset($data[$id])) {
				$data[$id] = array("label"=>$row->getRealm()->getName(), "data"=>array());
			}

			$value = false;
			switch ($what) {
				case 'area':		$value = $row->getArea(); break;
				case 'settlements':	$value = $row->getEstates(); break;
				case 'players':	$value = $row->getPlayers(); break;
				case 'soldiers':	$value = $row->getSoldiers(); break;
			}
			if ($value !== false) {
				$data[$id]["data"][] = array($cycle, $value);
			}
		}

		return $this->render('Game/comparedata.html.twig', [
			'data'=>$data, 'what'=>$what
		]);
	}


	/**
	  * @Route("/statistics/realm/{realm}", requirements={"realm"="\d+"})
	  */
	public function realmdataAction(Realm $realm) {
		$em = $this->getDoctrine()->getManager();

		$data = array(
			"settlements"		=> array("label" => "settlements", "data" => array()),
			"population"	=> array("label" => "population", "data" => array()),
			"soldiers"		=> array("label" => "soldiers", "data" => array()),
			"militia"		=> array("label" => "militia", "data" => array()),
			"area"			=> array("label" => "area", "data" => array()),
			"characters"	=> array("label" => "characters", "data" => array()),
			"players"		=> array("label" => "players", "data" => array()),
		);
		$query = $em->createQuery('SELECT s FROM BM2SiteBundle:StatisticRealm s WHERE s.cycle >= :start AND s.realm = :me ORDER BY s.cycle ASC');
		$query->setParameters(array('me'=>$realm, 'start'=>$this->start_cycle));
		foreach ($query->getResult() as $row) {
			$cycle = $row->getCycle();

			$data["settlements"]["data"][] 	= array($cycle, $row->getEstates());
			$data["population"]["data"][] = array($cycle, $row->getPopulation());
			$data["soldiers"]["data"][] 	= array($cycle, $row->getSoldiers());
			$data["militia"]["data"][] 	= array($cycle, $row->getMilitia());
			$data["area"]["data"][] 		= array($cycle, $row->getArea());
			$data["characters"]["data"][] = array($cycle, $row->getCharacters());
			$data["players"]["data"][] 	= array($cycle, $row->getPlayers());
		}

		return $this->render('Game/realmdata.html.twig', [
			'realm'=>$realm, 'data'=>$data
		]);
	}

	/**
	  * @Route("/statistics/settlement/{settlement}", requirements={"settlement"="\d+"})
	  */
	public function settlementdataAction(Settlement $settlement) {
		$em = $this->getDoctrine()->getManager();

		$data = array(
			"population"	=> array("label" => "population", "data" => array()),
			"peasants"		=> array("label" => "peasants", "data" => array()),
			"thralls"		=> array("label" => "thralls", "data" => array()),
			"militia"		=> array("label" => "militia", "data" => array()),
			"starvation"	=> array("label" => "starvation", "data" => array()),
			"war_fatigue"	=> array("label" => "war_fatigue", "data" => array()),
		);
		$query = $em->createQuery('SELECT s FROM BM2SiteBundle:StatisticSettlement s WHERE s.cycle >= :start AND s.settlement = :me ORDER BY s.cycle ASC');
		$query->setParameters(array('me'=>$settlement, 'start'=>$this->start_cycle));
		foreach ($query->getResult() as $row) {
			$cycle = $row->getCycle();

			$data["population"]["data"][] = array($cycle, $row->getPopulation()+$row->getThralls()+$row->getMilitia());
			$data["peasants"]["data"][] = array($cycle, $row->getPopulation());
			$data["thralls"]["data"][] 	= array($cycle, $row->getThralls());
			$data["militia"]["data"][] 	= array($cycle, $row->getMilitia());
			$data["war_fatigue"]["data"][] 	= array($cycle, $row->getWarFatigue());
		}

		return $this->render('Game/settlementdata.html.twig', [
			'settlement'=>$settlement, 'data'=>$data
		]);
	}

	/**
	  * @Route("/statistics/realms")
	  */
	public function realmstatisticsAction() {
		$em = $this->getDoctrine()->getManager();

		$realms=new ArrayCollection();
		$query = $em->createQuery('SELECT s, r FROM BM2SiteBundle:StatisticRealm s JOIN s.realm r WHERE s.cycle >= :start AND r.superior IS NULL AND r.active = true AND s.cycle = (select MAX(x.cycle) FROM BM2SiteBundle:StatisticRealm x)');
		$query->setParameter('start', $this->start_cycle);
		foreach ($query->getResult() as $result) {
			$data = array(
				'realm' =>		$result->getRealm(),
				'settlements' =>	$result->getEstates(), #TODO: Change this to getSettlements.
				'population'=>	$result->getPopulation(),
				'soldiers'=>	$result->getSoldiers(),
				'militia'=>		$result->getMilitia(),
				'area' =>		$result->getArea(),
				'nobles' =>		$result->getCharacters(),
				'players' => 	$result->getPlayers(),
			);
			$realms->add($data);
		}

		return $this->render('Game/realmstatistics.html.twig', [
			'realms'=>$realms
		]);
	}

	/**
	  * @Route("/statistics/battles")
	  */
	public function battlestatisticsAction() {
		$em = $this->getDoctrine()->getManager();

		$cycle = $this->get('game_runner')->getCycle();

		$data = array(
			"rabble"					=> array("label" => "rabble", "data" => array()),
			"light infantry"		=> array("label" => "light infantry", "data" => array()),
			"medium infantry"		=> array("label" => "medium infantry", "data" => array()),
			"heavy infantry"		=> array("label" => "heavy infantry", "data" => array()),
			"archer"					=> array("label" => "archers", "data" => array()),
			"mounted archer"		=> array("label" => "mounted archers", "data" => array()),
			"cavalry"				=> array("label" => "cavalry", "data" => array()),
			"noble"					=> array("label" => "nobles", "data" => array()),
		);

		$battles = array("label"=>"no. of battles", "data"=>array());

		for ($i=$this->start_cycle;$i<$cycle;$i++) {
			$soldiers = array();
			foreach ($data as $key=>$d) {
				$soldiers[$key] = 0;
			}
			$reports = $em->getRepository('BM2SiteBundle:BattleReport')->findByCycle($i);
			$battles["data"][] = array($i, count($reports));
			foreach ($reports as $report) {
				foreach ($report->getStart() as $group) {
					foreach ($group as $type=>$count) {
						$soldiers[$type] += $count;
					}
				}
			}
			foreach ($soldiers as $type=>$count) {
				$data[$type]["data"][] = array($i, $count);
			}
		}

		return $this->render('Game/battlestatistics.html.twig', [
			'data'=>$data, 'battles'=>$battles
		]);
	}

	/**
	  * @Route("/statistics/troops")
	  */
	public function troopsstatisticsAction() {
		$em = $this->getDoctrine()->getManager();

		$data = array(
			"rabble"					=> array("label" => "rabble", "data" => 0),
			"light infantry"		=> array("label" => "light infantry", "data" => 0),
			"medium infantry"		=> array("label" => "medium infantry", "data" => 0),
			"heavy infantry"		=> array("label" => "heavy infantry", "data" => 0),
			"archer"					=> array("label" => "archers", "data" => 0),
			"armoured archer"		=> array("label" => "armoured archers", "data" => 0),
			"mounted archer"		=> array("label" => "mounted archers", "data" => 0),
			"light cavalry"		=> array("label" => "light cavalry", "data" => 0),
			"heavy cavalry"		=> array("label" => "heavy cavalry", "data" => 0),
		);

		$qb = $em->createQueryBuilder()
			->select(array('count(s) as number', 'w.name as weapon', 'a.name as armour', 'e.name as equipment', 'a.defense as adef', 'e.defense as edef'))
			->from('BM2SiteBundle:Soldier', 's')
			->leftJoin('s.weapon', 'w')
			->leftJoin('s.armour', 'a')
			->leftJoin('s.equipment', 'e')
			->groupBy('w')
			->addGroupBy('a')
			->addGroupBy('e');
		$query = $qb->getQuery();
		$result = $query->getResult();

		foreach ($result as $row) {
			$type = $this->getSoldierType($row);
			$data[$type]["data"]+=$row['number'];
		}

		return $this->render('Game/troopsstatistics.html.twig', [
			'data'=>$data
		]);
	}

	private function getSoldierType($row) {
		if (!$row['weapon'] && !$row['armour'] && !$row['equipment']) return 'rabble';
		$defense = intval($row['adef']) + intval($row['edef']);
		if ($row['equipment'] =='horse' || $row['equipment']=='war horse') {
			if (in_array($row['weapon'], array('crossbow', 'shortbow', 'longbow'))) {
				return 'mounted archer';
			} else {
				if ($defense >= 80) {
					return 'heavy cavalry';
				} else {
					return 'light cavalry';
				}
			}
		}
		if (in_array($row['weapon'], array('crossbow', 'shortbow', 'longbow'))) {
			if ($defense >= 50) {
				return 'armoured archer';
			} else {
				return 'archer';
			}
		}
		if ($row['armour'] && $defense >= 60) {
			return 'heavy infantry';
		}
		if ($row['armour'] && $defense >= 40) {
			return 'medium infantry';
		}
		return 'light infantry';
	}

	/**
	  * @Route("/statistics/roads")
	  * @Template
	  */
	public function roadsstatisticsAction() {
		$em = $this->getDoctrine()->getManager();

		$data = array();
		$query = $em->createQuery('SELECT r.quality as quality, count(r) as amount FROM BM2SiteBundle:Road r GROUP BY r.quality ORDER BY r.quality ASC');
		foreach ($query->getResult() as $row) {
			$level = $this->get('translator')->trans('road.quality.'.$row['quality']);
			$amount = $row['amount'];
			$data[$level] = array("label" => $level, "data" => $amount);
		}

		return $this->render('Game/roadstatistics.html.twig', [
			'data'=>$data
		]);
	}

	/**
	  * @Route("/statistics/resources")
	  */
	public function resourcesdataAction() {
		$em = $this->getDoctrine()->getManager();

		$data = array();
		$resources = $em->getRepository('BM2SiteBundle:ResourceType')->findAll();
		foreach ($resources as $resource) {
			$data[$resource->getName()] = array(
				"supply"=>array("label"=>$resource->getName()." supply", "data"=>array()),
				"demand"=>array("label"=>$resource->getName()." demand", "data"=>array()),
				"trade"=>array("label"=>$resource->getName()." trade", "data"=>array(), "yaxis"=>2)
			);
		}
		$query = $em->createQuery('SELECT s FROM BM2SiteBundle:StatisticResources s WHERE s.cycle >= :start ORDER BY s.cycle ASC');
		$query->setParameters(array('start'=>$this->start_cycle));
		foreach ($query->getResult() as $row) {
			$cycle = $row->getCycle();

			$data[$row->getResource()->getName()]["supply"]["data"][] = array($cycle, $row->getSupply());
			$data[$row->getResource()->getName()]["demand"]["data"][] = array($cycle, $row->getDemand());
			$data[$row->getResource()->getName()]["trade"]["data"][] = array($cycle, $row->getTrade());
		}

		return $this->render('Game/resourcesdata.html.twig', [
			'resources'=>$resources, 'data'=>$data
		]);
	}


    /**
     * @Route("/settlements")
     */
	public function settlementsAction() {
		$em = $this->getDoctrine()->getManager();

		$settlements = $em->getRepository('BM2SiteBundle:Settlement')->findAll();
		$rt = $em->getRepository('BM2SiteBundle:ResourceType')->findAll();

		return $this->render('Game/settlements.html.twig', [
			'settlements' => $settlements,
			'resourcetypes' => $rt,
			'economy' => $this->get('economy')
		]);
	}

   /**
     * @Route("/heraldry")
     */
	public function heraldryAction() {
		$em = $this->getDoctrine()->getManager();

		$crests = $em->getRepository('BM2SiteBundle:Heraldry')->findAll();

		return $this->render('Game/heraldry.html.twig', [
			'crests' => $crests,
		]);
	}

	/**
     * @Route("/techtree")
     */
	public function techtreeAction() {
		$em = $this->getDoctrine()->getManager();

		$query = $em->createQuery('SELECT e from BM2SiteBundle:EquipmentType e');
		$equipment = $query->getResult();

		$query = $em->createQuery('SELECT e from BM2SiteBundle:EntourageType e');
		$entourage = $query->getResult();

		$query = $em->createQuery('SELECT b from BM2SiteBundle:BuildingType b');
		$buildings = $query->getResult();

		$descriptorspec = array(
			0 => array("pipe", "r"),  // stdin
			1 => array("pipe", "w"),  // stdout
			2 => array("pipe", "w") // stderr
		);

		$process = proc_open('dot -Tsvg', $descriptorspec, $pipes, '/tmp', array());

		if (is_resource($process)) {
		$dot = $this->renderView('Game/techtree.dot.twig', array(
			'equipment' => $equipment,
			'entourage' => $entourage,
			'buildings' => $buildings
		));
		echo $dot; exit; // FIXME: the svg generation fails and I don't know why

		fwrite($pipes[0], $dot);
		fclose($pipes[0]);

		$svg = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		$return_value = proc_close($process);
		}

		return $this->render('Game/techtree.html.twig', [
			'svg' => $svg
		]);
	}


	/**
	  * @Route("/diplomacy")
	  */
	public function diplomacyAction() {
		$em = $this->getDoctrine()->getManager();

		$query = $em->createQuery('SELECT r FROM BM2SiteBundle:Realm r WHERE r.superior IS NULL AND r.active = true');
		$realms = $query->getResult();

		$data = array();
		$query = $em->createQuery('SELECT r FROM BM2SiteBundle:RealmRelation r WHERE r.source_realm IN (:realms) AND r.target_realm IN (:realms)');
		$query->setParameter('realms', $realms);
		foreach ($query->getResult() as $row) {
			$data[$row->getSourceRealm()->getId()][$row->getTargetRealm()->getId()] = $row->getStatus();
		}

		return $this->render('Game/diplomacy.html.twig', [
			'realms'=>$realms, 'data'=>$data
		]);
	}


	/**
	  * @Route("/buildings")
	  */
	public function buildingsAction() {
		$em = $this->getDoctrine()->getManager();

		return $this->render('Game/buildings.html.twig', [
			'buildings'	=> $em->getRepository('BM2SiteBundle:BuildingType')->findAll(),
			'resources'	=> $em->getRepository('BM2SiteBundle:ResourceType')->findAll()
		]);
	}


	/* ======================================== Map Generator ======================================== */

   /**
     * @Route("/generate/{scale}", defaults={"seed"=-1})
     * @Route("/generate/{scale}/{seed}")
	  * @codeCoverageIgnore
     */
	public function newgenerateAction($seed, $scale) {
		if ($seed==-1) $seed=rand(0, time());
		$Perlin = new Perlin($seed);

		$jitter=0.48*$scale;
		$image = imagecreatefrompng('/Users/Tom/Documents/BM2/MapMaker/input.png');
		$width = round(imagesx($image)/$scale);
		$height = round(imagesy($image)/$scale);

		$points=array();
		for ($x=0; $x < $width; $x++) {
			for ($y=0; $y < $height; $y++) {
				$xa = $x*$scale + $jitter * $Perlin->random2D($x,$y) + $scale/2;
				$ya = $y*$scale + $jitter * $Perlin->random2D($x*1.5,$y*1.5) + $scale/2;

				$rgb = imagecolorat($image, $xa, $ya);
				$colors = imagecolorsforindex($image, $rgb);
				// we assume greyscale here, so we just get the red channel
				$alt = $colors['red']/255;

				$points[] = array($xa, $ya, $alt);
			}
		}

		return $this->render('Game/generate.html.twig', [
			'seed'=>$seed, 'width_old'=>$width, 'width'=>$width*$scale,  'height_old'=>$height, 'height'=>$height*$scale, 'points'=>$points
		]);
	}


   /**
     * @Route("/make", defaults={"seed"=-1, "width"=20, "height"=20})
     * @Route("/make/{seed}", defaults={"width"=20, "height"=20})
     * @Route("/make/{width}/{height}", defaults={"seed"=-1})
     * @Route("/make/{width}/{height}/{seed}")
	  * @codeCoverageIgnore
     */
	public function generateAction($seed, $width, $height) {
		if ($seed==-1) $seed=rand(0, time());
		$Perlin = new Perlin($seed);

		$scale=20;
		$jitter=0.666*$scale;
		$border=ceil(min($width,$height)*0.2);
		$size = ($width+$height)*$scale;

		$points=array();
		for ($x=0; $x < $width; $x++) {
			for ($y=0; $y < $height; $y++) {
				$xa = $x*$scale + $jitter * $Perlin->random2D($x,$y) + $scale/2;
				$ya = $y*$scale + $jitter * $Perlin->random2D($x*1.5,$y*1.5) + $scale/2;

				$alt = $this->heightmap($Perlin, $border, $size, $xa, $ya);
				if ($x<$border) $alt-=($border-$x)*2/$border; else if ($x>$width-$border) $alt-=($border-($width-$x))*2/$border;
				if ($y<$border) $alt-=($border-$y)*2/$border; else if ($y>$height-$border) $alt-=($border-($height-$y))*2/$border;
				$alt = max(-1,min(1,$alt));

				$points[] = array($xa, $ya, $alt);
			}
		}

		return $this->render('Game/generate.html.twig', [
			'seed'=>$seed, 'width_old'=>$width, 'width'=>$width*$scale,  'height_old'=>$height, 'height'=>$height*$scale, 'points'=>$points
		]);
	}

   /**
     * @Route("/heightmap/{width}/{height}/{seed}")
	  * @codeCoverageIgnore
     */
	public function heightmapAction($seed, $width, $height) {
//		return new Response(); // disable to save time
		$factor = 2;
		$scale = 20/$factor;
		$width = $width/$factor;
		$height = $height/$factor;

		$Perlin = new Perlin($seed);
		$border=ceil(min($width,$height)*0.2);
		$size = ($width+$height)*$scale;

		$image = imagecreatetruecolor($width*$scale, $height*$scale);
		imageantialias($image, true);
		for ($x=0; $x < $width*$scale; $x++) {
			for ($y=0; $y < $height*$scale; $y++) {
				$xb = $x/$scale;
				$yb = $y/$scale;
				$alt = $this->heightmap($Perlin, $border, $size, $x, $y);
				if ($xb<$border) $alt-=($border-$xb)*2/$border; else if ($xb>$width-$border) $alt-=($border-($width-$xb))*2/$border;
				if ($yb<$border) $alt-=($border-$yb)*2/$border; else if ($yb>$height-$border) $alt-=($border-($height-$yb))*2/$border;
				$alt = max(-1,min(1,$alt));

				$col = 255*($alt+1)/2;
				$color = imagecolorallocate($image, $col, $col, $col);
				imagesetpixel($image, $x, $y, $color);
			}
		}

		header('Content-Type: image/png');

		$factor *= 2;
		$finalimage = imagecreatetruecolor($width*$scale*$factor, $height*$scale*$factor);
		imagecopyresized($finalimage, $image, 0, 0, 0, 0, $width*$scale*$factor, $height*$scale*$factor, $width*$scale, $height*$scale);
		imagedestroy($image);
		imagepng($finalimage);
		imagedestroy($finalimage);
		return new Response();
	}


	private function heightmap($Perlin, $border, $size, $x, $y) {
		// the occasional inversion of $x and $y is not a bug - it makes the noise look much better
		$alt = 0.6*$Perlin->noise($x, $y, 0, $size/4)
			+ 0.6*$Perlin->noise($y, $x, 0, $size/8)
			+ max(0,0.4*$Perlin->noise($x, $y, 0, $size/12))
			- abs(0.3*$Perlin->noise($x, $y, 0, $size/16))
			+ abs(0.2*$Perlin->noise($x, $y, 0, $size/24));
		if ($alt>0.4) $alt+=abs(0.2*$Perlin->noise($y, $x, 0, $size/10));
		if ($alt>0.65) $alt+=abs(0.2*$Perlin->noise($y, $x, 0, $size/18));
		if ($alt>1.0) $alt-=abs(0.1*$Perlin->noise($y, $x, 0, $size/20));
		return $alt;
	}

   /**
     * @Route("/process")
	  * @codeCoverageIgnore
     */
	public function processAction(Request $request) {
		// NOTE: PHP configuration directive max_input_vars must be high enough!
		if ($request->isMethod('POST')) {
			$seed = $request->request->get('seed');
			$water = $request->request->get('water');
			$mountains = $request->request->get('mountains');
			$basepoints = $request->request->get('points');
			$voronoi = $request->request->get('voronoi');
			$x = $request->request->get('x');
			$y = $request->request->get('y');
			$scale = $request->request->get('scale');

			$em = $this->getDoctrine()->getManager();
			$i=0;
			// all this testing for last point identity is only necessary due to our rounding
			foreach ($voronoi as $i=>$cell) {
				$points = array(); $last=null;
				foreach ($cell as $point) {
					$newpoint = new Point($this->myRound($point[0])*$scale + $x, $this->myRound($point[1])*$scale + $y);
					if (!$last || $newpoint->getX() != $last->getX() || $newpoint->getY() != $last->getY()) {
						$points[] = $newpoint;
						$last = $newpoint;
					}
				}
				if ($last->getX() != $points[0]->getX() || $last->getY() != $points[0]->getY()) {
					$points[] = $points[0]; // close ring
				}
				$polygon = new Polygon(array(new LineString($points)));
				$center = new Point($this->myRound($basepoints[$i][0])*$scale + $x, $this->myRound($basepoints[$i][1])*$scale + $y);
				$alt = $basepoints[$i][2];
				if ($alt > $water) {
					$alt = ($alt-$water)/(1-$water);
					$alt = round($alt*2500); // max mountain height: 2500m
				} else {
					$alt = ($alt-$water)/($water);
					$alt = round($alt*1000); // max water depth: -1000m
				}

				$data = new GeoData();
				$data->setPoly($polygon);
				$data->setCenter($center);
				$data->setAltitude($alt);
				$data->setCoast(false)->setLake(false)->setRiver(false)->setHumidity(0)->setPassable(true);
				if ($alt<-500) {
					$biome="dummy-ocean";
				} else if ($alt>2500*$mountains) {
					$biome="dummy-mountain";
				} else {
					$biome="dummy";
				}
				$data->setBiome($biome); // FIXME: this needs to use the biome table - if we even use this code anymore...
				$em->persist($data);

				if (($i++ % 100) == 0) {
					$em->flush();
					echo ".";
				}
			}
			$em->flush();
			echo "\n$i inserted";
		}
		return new Response();
	}

	private function myRound($number) {
		// this is mostly to eliminate clipping artifacts, resulting in coordinates such as 0.5*10^-14
		return round(10000*$number)/10000;
	}

	/**
	 * @Route("/jitter", defaults={"seed"=-1})
	 * @Route("/jitter/{seed}")
	 * @codeCoverageIgnore
	 */
	/* FIXME: this should be re-written as a console command */
	public function jitterAction($seed) {
		if ($seed==-1) $seed=rand(0, time());
		$Perlin = new Perlin($seed);
		$em = $this->getDoctrine()->getManager();

		$query = $em->createQuery('SELECT ST_Extent(e.geom) as extent FROM BM2SiteBundle:TopoEdge e');
		$result = $query->getSingleResult();
		// unfortunately, we have to parse this ourselves - BOX(x,y)
		$extent = array();
		preg_match("/BOX\((.*) (.*),(.*) (.*)\)/", $result['extent'], $extent);
		$min_x = $extent[1];
		$min_y = $extent[2];
		$max_x = $extent[3];
		$max_y = $extent[4];

		$query = $em->createQuery('SELECT max(ST_Length(e.geom)) FROM BM2SiteBundle:TopoEdge e');
		$maxlength = $query->getSingleScalarResult();

		$query = $em->createQuery('SELECT e as edge, ST_Length(e.geom) as length FROM BM2SiteBundle:TopoEdge e');
		$iterableResult = $query->iterate();

		$c=1; $batchsize=100;
		while ($row = $iterableResult->next()) {
			$edge = $row[0]['edge'];

			if (isset($row[0]['length'])) {
				$length = $row[0]['length'];
			} else {
				$data = array_pop($row);
				$length=$data["length"];
			}

			// no inserts at the edge of the map
			$points = $edge->getGeom()->getPoints();
			if ($points[0]->getX() <= $min_x || $points[0]->getX() >= $max_x
				|| $points[0]->getY() <= $min_y || $points[0]->getY() >= $max_y
				|| $points[1]->getX() <= $min_x || $points[1]->getX() >= $max_x
				|| $points[1]->getY() <= $min_y || $points[1]->getY() >= $max_y) {
				$inserts = 0;
			} else {
				// max number of splits, scaled by length of edge
				// FIXME: somehow this should be made scale-independent without a fixed number!
				$inserts = floor(sqrt($length*$maxlength*50) / $maxlength);
			}

			if ($inserts>0) {
				for ($i=0;$i<$inserts;$i++) {
					$points = $this->jitterLine($points, $Perlin, $i);
				}

				$edge->setGeom(new LineString($points));
			}

			if (($c++ % $batchsize) == 0) {
				$em->flush();
			}
		}
		$em->flush();

		return $this->render('Game/jitter.html.twig');
	}


	/**
	 * @codeCoverageIgnore
	 */
	private function jitterLine($points, $Perlin, $level) {
		$jitter = 0.5/($level*1.5+2); // must be < 0.5
		if ($level<=0) $jitter=0; // this makes the mid-point stay on the actual voronoi cell line
		$newpoints = array();
		$prev=false;

		foreach ($points as $pt) {
			if ($prev) {
				$x = ($pt->getX() + $prev->getX())/2;
				$lx = abs($pt->getX() - $prev->getX());
				$y = ($pt->getY() + $prev->getY())/2;
				$ly = abs($pt->getY() - $prev->getY());

				// finally, the actual jittering
				$x_jitter = $Perlin->random2D($x,$y);
				$b = $Perlin->random2D($x*5,$y*5);
				$c = $Perlin->random2D($x*30,$y*30);
				if (abs($b) > abs($x_jitter)) $x_jitter=$b;
				if (abs($c) > abs($x_jitter)) $x_jitter=$c;

				$y_jitter = $Perlin->random2D($y,$x);
				$b = $Perlin->random2D($y*5,$x*5);
				$c = $Perlin->random2D($y*30,$x*30);
				if (abs($b) > abs($y_jitter)) $y_jitter=$b;
				if (abs($c) > abs($y_jitter)) $y_jitter=$c;

				$x += $jitter * $x_jitter * $ly; // this is correct, multiply by length of other side creates perpendicular elipses
				$y += $jitter * $y_jitter * $lx;

				$newpoints[] = new Point($this->myRound($x), $this->myRound($y));
			}
			$newpoints[] = $pt;
			$prev = $pt;
		}

		return $newpoints;
	}

}
