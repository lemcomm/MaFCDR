<?php

namespace BM2\SiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class DataController extends Controller {

	private $acceptedTypes = ['application/json', 'application/text'];
	private $securedRoutes = ['playerStatus'];
	private $highSecurityRoutes = ['user'];
	private $start;

	function __construct() {
		$this->securedRoutes += $this->highSecurityRoutes; #High security routes are always secured routes.
		$this->start = microtime(true);
	}

	/**
	  * @Route("/data/characters/dead")
	  */
	public function charactersDeadAction(Request $request) {
		$reqType = $this->validateRequest($request, 'chars-dead');
		if ($reqType instanceof Response) {
			return $reqType;
		}
		$term = $request->query->get("name");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT c.id, c.name as value FROM BM2SiteBundle:Character c WHERE c.alive=false AND LOWER(c.name) LIKE :term ORDER BY c.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = [];
		$result['data'] = $query->getArrayResult();

		return $this->outputHandler($reqType, $result);
	}

	/**
	  * @Route("/data/characters/active")
	  */
	public function charactersActiveAction(Request $request) {
		$reqType = $this->validateRequest($request, 'chars-active');
		if ($reqType instanceof Response) {
			return $reqType;
		}
		$term = $request->query->get("name");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT c.id, c.name as value FROM BM2SiteBundle:Character c WHERE c.alive=true AND c.retired = false AND c.slumbering=false AND LOWER(c.name) LIKE :term ORDER BY c.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = [];
		$result['data'] = $query->getArrayResult();

		return $this->outputHandler($reqType, $result);
	}

	/**
	  * @Route("/data/characters/living")
	  */
	public function charactersLivingAction(Request $request) {
		$reqType = $this->validateRequest($request, 'chars-living');
		if ($reqType instanceof Response) {
			return $reqType;
		}
		$term = $request->query->get("name");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT c.id, c.name as value FROM BM2SiteBundle:Character c WHERE c.alive=true AND LOWER(c.name) LIKE :term ORDER BY c.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = [];
		$result['data'] = $query->getArrayResult();

		return $this->outputHandler($reqType, $result);
	}

	/**
	  * @Route("/data/realms")
	  */
	public function realmsAction(Request $request) {
		$reqType = $this->validateRequest($request, 'realms');
		if ($reqType instanceof Response) {
			return $reqType;
		}
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT r.id, r.name as value FROM BM2SiteBundle:Realm r WHERE LOWER(r.name) LIKE :term OR LOWER(r.formal_name) LIKE :term ORDER BY r.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = [];
		$result['data'] = $query->getArrayResult();

		return $this->outputHandler($reqType, $result);
	}

	/**
	  * @Route("/data/settlements")
	  */
	public function settlementsAction(Request $request) {
		$reqType = $this->validateRequest($request, 'settlements');
		if ($reqType instanceof Response) {
			return $reqType;
		}
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT s.id, s.name as value, ST_X(g.center) as x, ST_Y(g.center) as y, r.name as label FROM BM2SiteBundle:Settlement s JOIN s.geo_data g LEFT JOIN s.realm r WHERE LOWER(s.name) LIKE :term ORDER BY s.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = [];
		$result['data'] = $query->getArrayResult();

		return $this->outputHandler($reqType, $result);
	}

	/**
	  * @Route("/data/assocs")
	  */
	public function assocsAction(Request $request) {
		$reqType = $this->validateRequest($request, 'assocs');
		if ($reqType instanceof Response) {
			return $reqType;
		}
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT a.id, a.name as value FROM BM2SiteBundle:Association a WHERE LOWER(a.name) LIKE :term OR LOWER(a.formal_name) LIKE :term ORDER BY a.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = [];
		$result['data'] = $query->getArrayResult();

		return $this->outputHandler($reqType, $result);
	}

	/**
	  * @Route("/data/deities")
	  */
	public function deitiesAction(Request $request) {
		$reqType = $this->validateRequest($request, 'assocs');
		if ($reqType instanceof Response) {
			return $reqType;
		}
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT d.id, d.name FROM BM2SiteBundle:Deity d WHERE LOWER(d.name) LIKE :term ORDER BY d.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = [];
		$result['data'] = $query->getArrayResult();

		return $this->outputHandler($reqType, $result);
	}

	/**
	  * @Route("/data/places")
	  */
	public function placesAction(Request $request) {
		$reqType = $this->validateRequest($request, 'places');
		if ($reqType instanceof Response) {
			return $reqType;
		}
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT p.id, p.name as value FROM BM2SiteBundle:Place p WHERE LOWER(p.name) LIKE :term OR LOWER(p.formal_name) LIKE :term ORDER BY p.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = [];
		$result['data'] = $query->getArrayResult();

		return $this->outputHandler($reqType, $result);
	}

	/**
	  * @Route("/data/houses")
	  */
	public function housesAction(Request $request) {
		$reqType = $this->validateRequest($request, 'houses');
		if ($reqType instanceof Response) {
			return $reqType;
		}
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT h.id, h.name as value FROM BM2SiteBundle:House h WHERE LOWER(h.name) LIKE :term ORDER BY h.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = [];
		$result['data'] = $query->getArrayResult();

		return $this->outputHandler($reqType, $result);
	}

	/**
	  * @Route("/data/buildings")
	  */
	public function buildingsAction(Request $request) {
		$reqType = $this->validateRequest($request, 'houses');
		if ($reqType instanceof Response) {
			return $reqType;
		}
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT b.id, b.name, b.icon, b.min_population, b.auto_population, b.per_people, b.defenses, b.special_conditions, b.built_in FROM BM2SiteBundle:BuildingType b WHERE LOWER(b.name) LIKE :term ORDER BY b.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = [];
		$result['data'] = $query->getArrayResult();

		return $this->outputHandler($reqType, $result);
	}

	/**
	  * @Route("/api/active")
	  * @Route("/data/active")
	  */
	public function activeUsersAction(Request $request) {
		$reqType = $this->validateRequest($request, 'houses');
		if ($reqType instanceof Response) {
			return $reqType;
		}
		$cycle = $this->get('appstate')->getCycle()-1;

		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT s.today_users as active_users FROM BM2SiteBundle:StatisticGlobal s WHERE s.cycle = :cycle');
		$query->setParameter('cycle', $cycle);
		$result['data'] = $query->getArrayResult()[0];

		return $this->outputHandler($reqType, $result);
	}


	/**
	  * @Route("/api/manualdata")
  	  * @Route("/data/manual")
	  */
	public function manualdataAction(Request $request) {
		$reqType = $this->validateRequest($request, 'houses');
		if ($reqType instanceof Response) {
			return $reqType;
		}

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
		$result['data']['buildings'] = $buildings;
		$result['data']['features'] = $features;
		$result['data']['entourage'] = $entourage;
		$result['data']['equipment'] = $items;
		return $this->outputHandler($reqType, $result);
	}

	/**
	  * @Route("/api/mapdata")
  	  * @Route("/data/map")
	  */
	public function mapdataAction(Request $request) {
		$reqType = $this->validateRequest($request, 'houses');
		if ($reqType instanceof Response) {
			return $reqType;
		}

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
				'd'		=> $def,
				'b'		=> $r['biome']
			);
		}
		$result['data']['settlements'] = $settlements;
		return $this->outputHandler($reqType, $result);
	}

	#
	# VALIDATOR FUNCTIONS
	#

	private function validateRequest($request, $type, $user=false) {
		if (in_array($type, $this->highSecurityRoutes)) {
			$level = 'GM';
			$check = 'secured';
		} else {
			$level = 'user';
			if (in_array($type, $this->securedRoutes)) {
				$check = 'secured';
			} else {
				$check = 'unsecured';
			}
		}
		$content = $this->validateAccept($request);
		if ($content instanceof Response) {
			return $content; #fail out if it's already a bad request. This also ensures that $content below passed to HTTPError is actually a type it can use.
		}
		if ($check === 'secured') {
			$valid = $this->validateToken($request->headers->get('Authorization'), $user, $level);
			if ($valid !== true) {
				# Token validation returned error, send it to the error handler for parsing into something humans can use.
				return $this->HTTPError($valid, $content);
			}
		}
		# Successful validation!
		return $content;
	}

	private function validateToken($token, $user, $level = 'user') {
		if ($token) {
			$arr = explode(' ', $token);
			if ($arr[0] !== 'Bearer') {
				return ['authorization'=>$arr];
			}
			if ($level == 'user') {
				$user = $this->main->getRepository(User::class)->findOneBy(['id'=>$user]);
				if (!$user) {
					return ['authorization'=>'user/token mismatch'];
				}
				foreach ($user->getKeys() as $key) {
					if ($key->getToken() === $token) {
						return true;
					}
				}
				return ['authorization'=>'user/token mismatch'];
			}
			if ($level != 'user') {
				$user = $this->main->getRepository(User::class)->findOneBy(['id'=>$user]);
				if (!$user || !$user->hasRole('ROLE_OLYMPUS')) {
					return ['authorization'=>'insufficient privileges'];
				}
				foreach ($user->getKeys() as $key) {
					if ($key->getToken() === $token) {
						return true;
					}
				}
				return ['authorization'=>'insufficient privileges'];
			}
		} else {
			return ['authorization'=>'no token'];
		}
		return true;
	}

	private function HTTPError($data, $type = ['content-type'=>'text/html']) {
		if (is_array($data)) {
			if (array_key_exists('accept', $data)) {
				$text = 'Invalid or missing accept header declaration sent with API request.';
				$http = Response::HTTP_BAD_REQUEST;
			}
			if (array_key_exists('authorization', $data) && $data['authorization'] == 'no token') {
				$text = 'You are required to provide a bearer authorization token for this request in the HTTP headers. One was not found.';
				$http = Response::HTTP_UNAUTHORIZED;
			}
			if (array_key_exists('authorization', $data) && $data['authorization'] == 'user/token mismatch') {
				$text = 'Invalid access token provided for user request. Please confirm you are submitting the correct token in the correct format as part of the request header authorization field.';
				$http = Response::HTTP_UNAUTHORIZED;
			}
			if (array_key_exists('authorization', $data) && $data['authorization'] == 'insufficient privileges') {
				$text = 'The account you have authenticated with does not have privileges for this resource.';
				$http = Response::HTTP_FORBIDDEN;
			}
			if (array_key_exists('authorization', $data) && $data['authorization'] == 'invalid token type') {
				$text = 'Authorization token must be a Bearer token.';
				$http = Response::HTTP_UNAUTHORIZED;
			}
		} else {
			if ($data=='404') {
				$text = 'Bad API route call. Please refer to '.$this->generateUrl('DataHelp', [], UrlGeneratorInterface::ABSOLUTE_URL).' for more information on available data routes.';
				$http = Response::HTTP_NOT_FOUND;
			}
		}
		return $this->outputHandler($type, [
			'result' => 'error',
			'error' => $text,
		]);
	}

	private function validateAccept($request) {
		return  'application/json';
		if ($content = $request->headers->get('accept')) {
			if (str_contains($content, 'application/json')) {
				return  'application/json';
			}
		}
		return $this->HTTPError(['accept'=>'invalid/missing']);
	}

	#
	# PRINTER FUNCTIONS
	#

	private function outputHandler($type, $data) {
		# Applies MetaData.
		$data['license'] = 'All Rights Reserved Iungard Systems, LLC';
		$spent = microtime(true)-$this->start;
		$time = new \DateTime("now");
		$data['metadata'] = [
			'system' => 'Might & Fealty API',
			'api-version' => '1.1.0.0',
			'api-version' => '2022-12-17',
			'game-version' => $this->get('appstate')->getGlobal('game-version'),
			'game-updated' => $this->get('appstate')->getGlobal('game-updated'),
			'timestamp' => $time->format('Y-m-d H:i:s'),
			'timing' => $spent
		];
		return $this->JSONParser($data);
	}

	private function JSONParser($data) {
		# Convert data array to a JSON format and render response.
		$headers = ['content-type'=>'application/json'];
		$http = Response::HTTP_BAD_REQUEST;
		if (array_key_exists('result', $data)) {
			if ($data['result'] === 'error') {
				$http = Response::HTTP_BAD_REQUEST;
			}
		} else {
			$http = Response::HTTP_OK;
		}
		$json = json_encode($data);
		return new Response(
			$json,
			$http,
			$headers
		);
	}


}
