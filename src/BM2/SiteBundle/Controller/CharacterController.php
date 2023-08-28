<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\CharacterRating;
use BM2\SiteBundle\Entity\CharacterRatingVote;
use BM2\SiteBundle\Entity\House;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Spawn;
use BM2\SiteBundle\Entity\Unit;
use BM2\SiteBundle\Entity\UnitSettings;

use BM2\SiteBundle\Form\AssocSelectType;
use BM2\SiteBundle\Form\CharacterBackgroundType;
use BM2\SiteBundle\Form\CharacterLoadoutType;
use BM2\SiteBundle\Form\CharacterPlacementType;
use BM2\SiteBundle\Form\CharacterRatingType;
use BM2\SiteBundle\Form\CharacterSettingsType;
use BM2\SiteBundle\Form\EntourageManageType;
use BM2\SiteBundle\Form\InteractionType;
use BM2\SiteBundle\Form\UnitSettingsType;

use BM2\SiteBundle\Service\CharacterManager;
use BM2\SiteBundle\Service\Geography;
use BM2\SiteBundle\Service\History;

use CrEOF\Spatial\PHP\Types\Geometry\LineString;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/character")
 */
class CharacterController extends Controller {


	private function getSpottings(Character $character) {
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT e FROM BM2SiteBundle:SpotEvent e JOIN e.target c LEFT JOIN e.tower t LEFT JOIN t.geo_data g LEFT JOIN g.settlement s WHERE e.current = true AND (e.spotter = :me OR (e.spotter IS NULL AND s.owner = :me)) ORDER BY c.id,e.id,s.id');
		$query->setParameter('me', $character);
		$spottings = array();
		foreach ($query->getResult() as $spotevent) {
			$id = $spotevent->getTarget()->getId();
			if ($id !== $character->getId()) {
				if (!isset($spottings[$id])) {
					$spottings[$id] = array('target'=>$spotevent->getTarget(), 'details'=>false, 'events'=>array());
				}
				// TODO: figure out if we can see details or not - by distance between spotter or watchtower?
				$spottings[$id]['events'][] = $spotevent;
			}
		}
		return $spottings;
	}


	/**
	  * @Route("/", name="bm2_character")
	  */
	public function indexAction() {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if ($location=$character->getLocation()) {
			$geo = $this->get('geography');
			$nearest = $geo->findNearestSettlement($character);
			$settlement=array_shift($nearest);
			$location = $settlement->getGeoData();
		} else {
			return $this->redirectToRoute('maf_character_start');
		}
		return $this->render('Character/character.html.twig', [
			'location' => $location,
			'familiarity' => $geo->findRegionFamiliarityLevel($character, $location),
			'spot' => $geo->calculateSpottingDistance($character),
			'act' => $geo->calculateInteractionDistance($character),
			'settlement' => $settlement,
			'nearest' => $nearest,
			'others' => $this->get('geography')->findCharactersInSpotRange($character),
			'spottings' => $this->getSpottings($character),
			'entourage' => $character->getActiveEntourageByType(),
			'units' => $character->getUnits(),
			'dead_entourage' => $character->getDeadEntourage()->count(),
		]);

	}

	/**
	  * @Route("/summary", name="bm2_recent")
	  */
	public function summaryAction() {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if (!$character->getLocation()) {
			return $this->redirectToRoute('maf_character_start');
		}

		$em = $this->getDoctrine()->getManager();

		# TODO: This should really be somewhere else, like at the end of battles.
		foreach ($character->getUnits() as $unit) {
			foreach ($unit->getSoldiers() as $soldier) {
				$soldier->setRouted(false);
			}
		}
		$em->flush();
		return $this->render('Character/summary.html.twig', [
			'events' => $this->get('character_manager')->findEvents($character),
			'unread' => $this->get('conversation_manager')->getUnreadConvPermissions($character),
			'others' => $this->get('geography')->findCharactersInSpotRange($character),
			'spottings' => $this->getSpottings($character),
			'battles' => $this->get('geography')->findBattlesNearMe($character, Geography::DISTANCE_BATTLE),
			'dungeons' => $this->get('geography')->findDungeonsNearMe($character, Geography::DISTANCE_DUNGEON),
			'spotrange' => $this->get('geography')->calculateSpottingDistance($character),
			'actrange' => $this->get('geography')->calculateInteractionDistance($character),
			'requests' => $this->get('game_request_manager')->findAllManageableRequests($character),
			'duels' => $character->findAnswerableDuels()
		]);
	}

	/**
	  * @Route("/scouting", name="bm2_scouting")
	  */
	public function scoutingAction() {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		// FIXME: this needs to be reworked !
		$spotted = array();
		$others = $this->get('geography')->findCharactersInSpotRange($character);

		foreach ($others as $other) {
			$char = $other['character'];

			$realms = $char->findRealms();
			$ultimates = new ArrayCollection;
			foreach ($realms as $r) {
				$ult = $r->findUltimate();
				if (!$ultimates->contains($ult)) {
					$ultimates->add($ult);
				}
			}
			$soldiers = 0;
			Foreach ($char->getUnits() as $unit) {
				$soldiers += $unit->getActiveSoldiers()->count();
			}

			$spotted[] = array(
				'char' => $char,
				'distance' => $other['distance'],
				'realms' => $realms,
				'ultimates' => $ultimates,
				'entourage' => $char->getLivingEntourage()->count(),
				'soldiers' => $soldiers,
			);
		}

		return $this->render('Character/scouting.html.twig', [
			'spotted'=>$spotted
		]);
	}

	/**
	  * @Route("/estates", name="bm2_estates")
	  */
	public function estatesAction() {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		$settlements = array();
		foreach ($character->findControlledSettlements() as $settlement) {
			// FIXME: better: some trend analysis
			$query = $em->createQuery('SELECT s.population as pop FROM BM2SiteBundle:StatisticSettlement s WHERE s.settlement = :here ORDER BY s.cycle DESC');
			$query->setParameter('here', $settlement);
			$query->setMaxResults(3);
			$data = $query->getArrayResult();
			if (isset($data[2])) {
				$popchange = $data[0]['pop'] - $data[2]['pop'];
			} else {
				$popchange = 0;
			}
			if ($settlement->getOwner()) {
				$owner = ['id' => $settlement->getOwner()->getId(), 'name' => $settlement->getOwner()->getName()];
			} else {
				$owner = false;
			}
			if ($settlement->getRealm()) {
				$r = $settlement->getRealm();
				$u = $settlement->getRealm()->findUltimate();
				$realm = array('id'=>$r->getId(), 'name'=>$r->getName());
				$ultimate = array('id'=>$u->getId(), 'name'=>$u->getName());
			} else {
				$realm = null; $ultimate = null;
			}
			$build = array();
			foreach ($settlement->getBuildings()->filter(
				function($entry) {
					return ($entry->getActive()==false && $entry->getWorkers()>0);
				}) as $building) {
				$build[] = array('id'=>$building->getType()->getId(), 'name'=>$building->getType()->getName());
			}
			$militia = 0;
			$recruits = 0;
			foreach ($settlement->getUnits() as $unit) {
				if ($unit->isLocal()) {
					$militia += $unit->getActiveSoldiers()->count();
					$recruits += $unit->getRecruits()->count();
				}
			}
			if ($settlement->getOccupant()) {
				$occupant = ['id' => $settlement->getOccupant()->getId(), 'name' => $settlement->getOccupant()->getName()];
			} else {
				$occupant = false;
			}
			if ($settlement->getOccupier()) {
				$occupier = ['id' => $settlement->getOccupier()->getId(), 'name' => $settlement->getOccupier()->getName()];
			} else {
				$occupier = false;
			}

			$settlements[] = array(
				'id' => $settlement->getId(),
				'owner' => $owner,
				'name' => $settlement->getName(),
				'pop' => $settlement->getFullPopulation(),
				'peasants' => $settlement->getPopulation(),
				'thralls' => $settlement->getThralls(),
				'size' => $settlement->getSize(),
				'occupier' => $occupier,
				'occupant' => $occupant,
				'popchange' => $popchange,
				'militia' => $militia,
				'recruits' => $recruits,
				'realm' => $realm,
				'ultimate' => $ultimate,
				'build' => $build,
			);
		}

		$poly = $this->get('geography')->findRegionsPolygon($character->getOwnedSettlements());
		return $this->render('Character/estates.html.twig', [
	   		'settlements'=>$settlements,
			'poly'=>$poly
		]);
	}

	/**
	  * @Route("/start", name="maf_character_start")
	  */
	public function startAction(Request $request) {
		$character = $this->get('appstate')->getCharacter(true, false, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$now = new \DateTime('now');
		$user = $character->getUser();
		$em = $this->getDoctrine()->getManager();
		$canSpawn = $this->get('user_manager')->checkIfUserCanSpawnCharacters($user, true);
		$em->flush();
		if (!$canSpawn) {
			$this->addFlash('error', $this->get('translator')->trans('newcharacter.overspawn', array('%date%'=>$user->getNextSpawnTime()->format('Y-m-d H:i:s')), 'messages'));
			return $this->redirectToRoute('bm2_characters');
		}
		if ($character->getLocation()) {
			return $this->redirectToRoute('bm2_character');
		}
		if ($request->query->get('logic') == 'retired') {
			$retiree = true;
		} else {
			$retiree = false;
		}
		# Make sure this character can return from retirement. This function will throw an exception if the given character has not been retired for a week.
		$this->get('character_manager')->checkReturnability($character);

		switch(rand(0,7)) {
			case 0:
				$query = $em->createQuery('SELECT s, r FROM BM2SiteBundle:Spawn s JOIN s.realm r WHERE r.active = true AND s.active = true ORDER BY r.id DESC');
				break;
			case 1:
				$query = $em->createQuery('SELECT s, r FROM BM2SiteBundle:Spawn s JOIN s.realm r WHERE r.active = true AND s.active = true ORDER BY r.id ASC');
				break;
			case 2:
				$query = $em->createQuery('SELECT s, r FROM BM2SiteBundle:Spawn s JOIN s.realm r WHERE r.active = true AND s.active = true ORDER BY r.name DESC');
				break;
			case 3:
				$query = $em->createQuery('SELECT s, r FROM BM2SiteBundle:Spawn s JOIN s.realm r WHERE r.active = true AND s.active = true ORDER BY r.name ASC');
				break;
			case 4:
				$query = $em->createQuery('SELECT s, r FROM BM2SiteBundle:Spawn s JOIN s.realm r WHERE r.active = true AND s.active = true ORDER BY r.formal_name DESC');
				break;
			case 5:
				$query = $em->createQuery('SELECT s, r FROM BM2SiteBundle:Spawn s JOIN s.realm r WHERE r.active = true AND s.active = true ORDER BY r.formal_name ASC');
				break;
			case 6:
				$query = $em->createQuery('SELECT s, r FROM BM2SiteBundle:Spawn s JOIN s.realm r WHERE r.active = true AND s.active = true ORDER BY r.superior DESC');
				break;
			case 7:
				$query = $em->createQuery('SELECT s, r FROM BM2SiteBundle:Spawn s JOIN s.realm r WHERE r.active = true AND s.active = true ORDER BY r.superior ASC');
				break;
		}
		$result = $query->getResult();
		$realms = new ArrayCollection();
		$houses = new ArrayCollection();
		$myHouse = null;
		foreach ($result as $spawn) {
			if (!$realms->contains($spawn->getRealm())) {
				if ($spawn->getRealm()->getSpawnDescription() && $spawn->getPlace()->getDescription() && $spawn->getPlace()->getSpawnDescription()) {
					$realms->add($spawn->getRealm());
				}
			}
		}
		if ($character->getHouse() && $character->getHouse()->getHome()) {
			$myHouse = $character->getHouse();
		} else {
			switch(rand(0,5)) {
				case 0:
					$query = $em->createQuery('SELECT s, h FROM BM2SiteBundle:Spawn s JOIN s.house h WHERE h.active = true AND s.active = true ORDER BY h.id DESC');
					break;
				case 1:
					$query = $em->createQuery('SELECT s, h FROM BM2SiteBundle:Spawn s JOIN s.house h WHERE h.active = true AND s.active = true ORDER BY h.id ASC');
					break;
				case 2:
					$query = $em->createQuery('SELECT s, h FROM BM2SiteBundle:Spawn s JOIN s.house h WHERE h.active = true AND s.active = true ORDER BY h.name DESC');
					break;
				case 3:
					$query = $em->createQuery('SELECT s, h FROM BM2SiteBundle:Spawn s JOIN s.house h WHERE h.active = true AND s.active = true ORDER BY h.name ASC');
					break;
				case 4:
					$query = $em->createQuery('SELECT s, h FROM BM2SiteBundle:Spawn s JOIN s.house h WHERE h.active = true AND s.active = true ORDER BY h.superior DESC');
					break;
				case 5:
					$query = $em->createQuery('SELECT s, h FROM BM2SiteBundle:Spawn s JOIN s.house h WHERE h.active = true AND s.active = true ORDER BY h.superior ASC');
					break;
			}
			$result = $query->getResult();
			foreach ($result as $spawn) {
				if (!$houses->contains($spawn->getHouse())) {
					if ($spawn->getHouse()->getSpawnDescription() && $spawn->getPlace()->getDescription() && $spawn->getPlace()->getSpawnDescription()) {
						$houses->add($spawn->getHouse());
					}
				}
			}
		}

		return $this->render('Character/start.html.twig', [
			'realms'=>$realms, 'houses'=>$houses, 'myhouse'=>$myHouse, 'retiree'=>$retiree
		]);

	}

	/**
	  * @Route("/spawn/r{realm}", requirements={"realm"="\d+"}, name="maf_spawn_realm")
	  * @Route("/spawn/h{house}", requirements={"house"="\d+"}, name="maf_spawn_house")
	  * @Route("/spawn/myhouse", name="maf_spawn_myhouse")
	  */
	  public function spawnAction(Realm $realm = null, House $house = null) {
		$character = $this->get('appstate')->getCharacter(true, false, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		if ($character->getLocation()) {
			return $this->redirectToRoute('bm2_character');
		}
		$user = $character->getUser();
		$em = $this->getDoctrine()->getManager();
		$canSpawn = $this->get('user_manager')->checkIfUserCanSpawnCharacters($user, true);
		$em->flush();
		if (!$canSpawn) {
			$this->addFlash('error', $this->get('translator')->trans('newcharacter.overspawn', array('%date%'=>$user->getNextSpawnTime()->format('Y-m-d H:i:s')), 'messages'));
			return $this->redirectToRoute('bm2_characters');
		}

		$spawns = new ArrayCollection();
		$myHouse = null;
		if ($realm) {
			foreach ($realm->getSpawns() as $spawn) {
				if ($spawn->getActive() && $spawn->getPlace()->getSpawnDescription() && $spawn->getPlace()->getDescription()) {
					$spawns->add($spawn);
				}
			}
		}
		if ($house && $house->getHome() && $house->getHome()->getSpawnDescription() && $house->getHome()->getDescription()) {
			$spawns->add($house->getSpawn());
		}
		if (!$house && !$realm) {
			$myHouse = $character->getHouse();
		}

		return $this->render('Character/spawn.html.twig', [
			'realm'=>$realm, 'house'=>$house, 'spawns'=>$spawns, 'myHouse'=>$myHouse
		]);
	}

	/**
	  * @Route("/spawnin/home", name="maf_spawn_home")
	  * @Route("/spawnin/s{spawn}", requirements={"spawn"="\d+"}, name="maf_spawn_in")
	  */
	public function firstAction(Spawn $spawn = null) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$convMan = $this->get('conversation_manager');
		$em = $this->getDoctrine()->getManager();

		$house = null;
		$realm = null;
		$conv = null;
		$supConv = null;
		if (!$character->getLocation()) {
			$user = $character->getUser();
			$canSpawn = $this->get('user_manager')->checkIfUserCanSpawnCharacters($user, true);
			$em->flush();
			if (!$canSpawn) {
				$this->addFlash('error', $this->get('translator')->trans('newcharacter.overspawn', array('%date%'=>$user->getNextSpawnTime()->format('Y-m-d H:i:s')), 'messages'));
				return $this->redirectToRoute('bm2_characters');
			}
			if ($spawn) {
				if (!$spawn->getActive()) {
					$this->addFlash('error', $this->get('translator')->trans('newcharacter.spawnnotactive', [], 'messages'));
					return $this->redirectToRoute('bm2_characters');
				}
				$place = $spawn->getPlace();
				if ($spawn->getRealm()) {
					$realm = $spawn->getRealm();
					$character->setRealm($realm);
					$this->get('history')->logEvent(
						$realm,
						'event.realm.arrival',
						array('%link-character%'=>$character->getId(), '%link-place%'=>$place->getId()),
						History::MEDIUM, false, 15
					);
					if ($realm->getSuperior()) {
						$this->get('history')->logEvent(
							$realm->findUltimate(),
							'event.subrealm.arrival',
							array('%link-character%'=>$character->getId(), '%link-realm%'=>$realm->getId()),
							History::MEDIUM, false, 15
						);
					}
				} else {
					$house = $spawn->getHouse();
					$character->setHouse($house);
					$this->get('history')->logEvent(
						$house,
						'event.house.arrival',
						array('%link-character%'=>$character->getId(), '%link-place%'=>$place->getId()),
						History::MEDIUM, false, 15
					);
				}
			} else {
				$house = $character->getHouse();
				$place = $house->getPlace();
				$spawn = $place->getSpawn();
				if (!$spawn->getActive()) {
					$this->addFlash('error', $this->get('translator')->trans('newcharacter.spawnnotactive', [], 'messages'));
					return $this->redirectToRoute('bm2_characters');
				}
			}
			# new character spawn in.
			if ($place->getLocation()) {
				$character->setLocation($place->getLocation());
				$settlement = null;
			} else {
				$settlement = $place->getSettlement();
				$character->setLocation($settlement->getGeoMarker()->getLocation());
				$character->setInsideSettlement($settlement);
			}
			if ($character->getRetired()) {
				$character->setRetired(false);
			}
			$character->setInsidePlace($place);
			if ($character->getList() != 1) {
				# Resets this on formerly retired characters.
				$character->setList(1);
			}
			list($conv, $supConv) = $convMan->sendNewCharacterMsg($realm, $house, $place, $character);
			# $conv should always be a Conversation, while supConv will be if realm is not Ultimate--otherwise null.
			# Both instances of Converstion.

			$this->get('history')->logEvent(
				$character,
				'event.character.start2',
				array('%link-place%'=>$place->getId()),
				History::HIGH,	true
			);
			$this->get('history')->logEvent(
				$place,
				'event.place.start',
				array('%link-character%'=>$character->getId()),
				History::MEDIUM, false, 15
			);
			$this->get('history')->visitLog($place, $character);
			if ($settlement) {
				$this->get('history')->logEvent(
					$settlement,
					'event.place.charstart',
					array('%link-character%'=>$character->getId(), '%link-place%'=>$place->getId()),
					History::MEDIUM, false, 15
				);
				$this->get('history')->visitLog($settlement, $character);
			}
			$em->flush();
			$this->get('user_manager')->calculateCharacterSpawnLimit($user, true); #This can return the date but we don't need it.
			$em->flush();
		} else {
			$place = $spawn->getPlace();
			$realm = $character->findPrimaryRealm();
			if ($realm) {
				if ($realm->getSuperior()) {
					$supConv = $em->getRepository('BM2SiteBundle:Conversation')->findOneBy(['realm'=>$realm->getSuperior(), 'system'=>'announcements']);
				} else {
					$supConv = null;
				}
				$conv = $em->getRepository('BM2SiteBundle:Conversation')->findOneBy(['realm'=>$realm, 'system'=>'announcements']);
			} elseif ($character->getHouse()) {
				$house = $character->getHouse();
				$conv = $em->getRepository('BM2SiteBundle:Conversation')->findOneBy(['house'=>$house, 'system'=>'announcements']);
				$supConv = null;
			}
		}

		return $this->render('Character/first.html.twig', [
			'unread' => $this->get('conversation_manager')->getUnreadConvPermissions($character),
			'house' => $house,
			'realm' => $realm,
			'place' => $place,
			'conv' => $conv,
			'supConv' => $supConv
		]);
	}


	/**
	  * @Route("/view/{id}", requirements={"id"="\d+"}, name="bm2_site_character_view")
	  */
	public function viewAction(Character $id) {
		$char = $id;
		$character = $this->get('appstate')->getCharacter(FALSE, TRUE, TRUE);
		$banned = false;
		if ($character instanceof Character) {
			$details = $this->get('interactions')->characterViewDetails($character, $char);
		} else {
			$details = array('spot' => false, 'spy' => false);
		}
		if ($details['spot']) {
			$entourage = $char->getActiveEntourageByType();
			$soldiers = [];
			foreach ($char->getUnits() as $unit) {
				foreach ($unit->getActiveSoldiersByType() as $key=>$type) {
					if (array_key_exists($key, $soldiers)) {
						$soldiers[$key] += $type;
					} else {
						$soldiers[$key] = $type;
					}
				}
			}
		} else {
			$entourage = null;
			$soldiers = null;
		}
		if ($char->getUser()) {
			if ($char->getUser()->hasRole('ROLE_BANNED_MULTI') || $char->getUser()->hasRole('ROLE_BANNED_TOS')) {
				$banned = true;
			}
		}
		$relationship = false;
		if ($character instanceof Character && $character->getPartnerships() && $char->getPartnerships()) {
			foreach ($character->getPartnerships() as $partnership) {
				if (!$partnership->getEndDate() && $partnership->getOtherPartner($character) == $char) {
					$relationship = true;
				}
			}
		}
		return $this->render('Character/view.html.twig', [
			'char'		=> $char,
			'details'	=> $details,
			'relationship'	=> $relationship,
			'entourage'	=> $entourage,
			'soldiers'	=> $soldiers,
			'banned'	=> $banned,
		]);
	}

	/**
	  * @Route("/reputation/{id}", requirements={"id"="\d+"})
	  */
	public function reputationAction($id) {
		$em = $this->getDoctrine()->getManager();
		$char = $em->getRepository('BM2SiteBundle:Character')->find($id);
		if (!$char) {
			throw $this->createNotFoundException('error.notfound.character');
		}

		list($respect, $honor, $trust, $data) = $this->get('character_manager')->Reputation($char, $this->getUser());

		usort($data, function($a, $b){
			if ($a['value'] < $b['value']) return 1;
			if ($a['value'] > $b['value']) return -1;
			return 0;
		});

		if (! $my_rating = $em->getRepository('BM2SiteBundle:CharacterRating')->findOneBy(array('character'=>$char, 'given_by_user'=>$this->getUser()))) {
			$my_rating = new CharacterRating;
			$my_rating->setCharacter($char);
		}
		$form = $this->createForm(new CharacterRatingType, $my_rating);
		return $this->render('Character/reputation.html.twig', [
			'char'		=> $char,
			'ratings'	=> $data,
			'respect'	=> $respect,
			'honor'		=> $honor,
			'trust'		=> $trust,
			'form'		=> $form->createView()
		]);
	}

	/**
	  * @Route("/rate")
	  */
	public function rateAction(Request $request) {
		$form = $this->createForm(new CharacterRatingType);
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$id = $data->getCharacter()->getId();
			$em = $this->getDoctrine()->getManager();
			$my_rating = $em->getRepository('BM2SiteBundle:CharacterRating')->findOneBy(array('character'=>$data->getCharacter(), 'given_by_user'=>$this->getUser()));
			if ($my_rating) {
				// TODO: if we've changed it substantially, we should clear out the votes!
				// FIXME: This is a bit ugly. Can we not use the existing $data object?
				$my_rating->setContent(substr($data->getContent(),0,250));
				$my_rating->setHonor($data->getHonor());
				$my_rating->setTrust($data->getTrust());
				$my_rating->setRespect($data->getRespect());
				$my_rating->setLastChange(new \DateTime("now"));
			} else {
				// new rating
				$data->setGivenByUser($this->getUser());
				$data->setContent(substr($data->getContent(),0,250));
				$data->setLastChange(new \DateTime("now"));
				$em->persist($data);
			}
			$em->flush();
		}

		if ($id) {
			return $this->redirectToRoute('bm2_site_character_view', array('id'=>$id));
		} else {
			return $this->redirectToRoute('bm2_recent');
		}
	}

	/**
	  * @Route("/vote")
	  * @Method("post")
	  */
	public function voteAction(Request $request) {
		if ($request->request->has("id") &&  $request->request->has("vote")) {
			$em = $this->getDoctrine()->getManager();
			$rating = $em->getRepository('BM2SiteBundle:CharacterRating')->find($request->request->get("id"));
			if (!$rating) return new Response("rating not found");
			$char = $em->getRepository('BM2SiteBundle:Character')->find($rating->getCharacter());
			if ($char->getUser() == $this->getUser()) return new Response("can't vote on ratings for your own characters");
			$my_vote = $em->getRepository('BM2SiteBundle:CharacterRatingVote')->findOneBy(array('rating'=>$rating, 'user'=>$this->getUser()));
			if (!$my_vote) {
				$my_vote = new CharacterRatingVote;
				$my_vote->setRating($rating);
				$my_vote->setUser($this->getUser());
				$em->persist($my_vote);
				$rating->addVote($my_vote);
			}
			if ($request->request->get("vote")<0) {
				$my_vote->setValue(-1);
			} else {
				$my_vote->setValue(1);
			}
			$em->flush();
			return new Response("done");
		}
		return new Response("bad request");
	}

	/**
	  * @Route("/family/{id}", requirements={"id"="\d+"})
	  */
	public function familyAction($id) {
		$em = $this->getDoctrine()->getManager();
		$char = $em->getRepository('BM2SiteBundle:Character')->find($id);

		$characters = array($id=>$char);
		$characters = $this->addRelatives($characters, $char);

		$descriptorspec = array(
			0 => array("pipe", "r"),  // stdin
			1 => array("pipe", "w"),  // stdout
			2 => array("pipe", "w") // stderr
		);

		$process = proc_open('dot -Tsvg', $descriptorspec, $pipes, '/tmp', array());

		if (is_resource($process)) {
			$dot = $this->renderView('Account/familytree.dot.twig', array('characters'=>$characters));

			fwrite($pipes[0], $dot);
			fclose($pipes[0]);

			$svg = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			$return_value = proc_close($process);
		}

		return $this->render('Account/familytree.html.twig', [
			'svg' => $svg
		]);
	}

	private function addRelatives($characters, Character $char) {
		foreach ($char->getParents() as $parent) {
			if (!isset($characters[$parent->getId()])) {
				$characters[$parent->getId()] = $parent;
				$characters = $this->addRelatives($characters, $parent);
			}
		}
		foreach ($char->getChildren() as $child) {
			if (!isset($characters[$child->getId()])) {
				$characters[$child->getId()] = $child;
				$characters = $this->addRelatives($characters, $child);
			}
		}
		foreach ($char->getPartnerships() as $rel) {
			if ($rel->getActive() && $rel->getPublic() && $rel->getType()=="marriage") {
				$other = $rel->getOtherPartner($char);
				if (!isset($characters[$other->getId()])) {
					$characters[$other->getId()] = $other;
					// not sure if we want the below - maybe make it an option?
					// $characters = $this->addRelatives($characters, $other);
				}
			}
		}
		return $characters;
	}


	/**
	  * @Route("/background")
	  */
	public function backgroundAction(Request $request) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		if ($request->query->get('starting')) {
			$starting = true;
		} else {
			$starting = false;
		}

		// dynamically create when needed
		if (!$character->getBackground()) {
			$this->get('character_manager')->newBackground($character);
		}
		$form = $this->createForm(new CharacterBackgroundType($character->getAlive()), $character->getBackground());
		$form->handleRequest($request);
		if ($form->isValid()) {
			// FIXME: this causes the (valid markdown) like "> and &" to be converted - maybe strip-tags is better?;
			// FIXME: need to apply this here - maybe data transformers or something?
			// htmlspecialchars($data['subject'], ENT_NOQUOTES);

			$em->flush();
			if ($starting) {
				if ($character->isAlive()) {
					if ($character->getLocation()) {
						return $this->redirectToRoute('bm2_play', array('id'=>$character->getId()));
					} else {
						return $this->redirectToRoute('maf_character_start');
					}
				} else {
					return $this->redirectToRoute('bm2_characters');
				}
			} else {
				$this->addFlash('notice', $this->get('translator')->trans('meta.background.updated', array(), 'actions'));
			}
		}

		return $this->render('Character/background.html.twig', [
			'form' => $form->createView(),
			'starting' => $starting
		]);
	}

	/**
	  * @Route("/rename")
	  */
	public function renameAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createFormBuilder()
			->add('name', 'text', array(
				'required'=>true,
				'label'=>'meta.rename.newname',
				'translation_domain' => 'actions',
				'data' => $character->getPureName()
				))
			->add('knownas', 'text', array(
				'required'=>false,
				'label'=>'meta.rename.knownas',
				'translation_domain' => 'actions',
				'data' => $character->getKnownAs()
				))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			// TODO: validation ?
			$data = $form->getData();
			$newname=$data['name'];
			$oldname = $character->getPureName();

			if ($newname != $oldname) {
				$character->setName($newname);
				$this->get('history')->logEvent(
					$character,
					'event.character.renamed',
					array('%oldname%'=>$oldname, '%newname%'=>$newname),
					History::MEDIUM,
					true
				);
			}

			$new_knownas = $data['knownas'];
			$old_knownas = $character->getKnownAs();
			if ($new_knownas != $old_knownas) {
				$character->setKnownAs($new_knownas);
				if ($new_knownas) {
					$this->get('history')->logEvent(
						$character,
						'event.character.knownas1',
						array('%newname%'=>$new_knownas),
						History::MEDIUM,
						true
					);
				} else {
					$this->get('history')->logEvent(
						$character,
						'event.character.knownas2',
						array('%oldname%'=>$old_knownas),
						History::MEDIUM,
						true
					);
				}
			}

			$em = $this->getDoctrine()->getManager();
			$em->flush();

			return $this->render('Character/rename.html.twig', [
				'result'=>array('success'=>true),
				'newname'=>$newname
			]);

			return array();
		}

		return $this->render('Character/rename.html.twig', [
			'form' => $form->createView(),
		]);
	}

	/**
	  * @Route("/settings")
	  */
	public function settingsAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		$form = $this->createForm(new CharacterSettingsType(), $character);
		$form->handleRequest($request);

		if ($form->isValid()) {
			$data = $form->getData();
			$em->flush();


			$this->addFlash('notice', $this->get('translator')->trans('update.success', array(), 'settings'));

			return $this->redirectToRoute('bm2_recent');
		}


		return $this->render('Character/settings.html.twig', [
			'form' => $form->createView(),
		]);
	}

	/**
	  * @Route("/loadout", name="maf_character_loadout")
	  */
	public function loadoutAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$opt['wpns'] = $em->getRepository('BM2SiteBundle:EquipmentType')->findBy(['type'=>'weapon']);
		$opt['arms'] = $em->getRepository('BM2SiteBundle:EquipmentType')->findBy(['type'=>'armour']);
		$opt['othr'] = $em->getRepository('BM2SiteBundle:EquipmentType')->findBy(['type'=>'equipment']);
		$opt['mnts'] = $em->getRepository('BM2SiteBundle:EquipmentType')->findBy(['type'=>'mount']);

		$form = $this->createForm(new CharacterLoadoutType($opt), $character);
		$form->handleRequest($request);

		if ($form->isValid()) {
			$data = $form->getData();
			$em->flush();


			$this->addFlash('notice', $this->get('translator')->trans('loadout.success', array(), 'settings'));

			return $this->redirectToRoute('bm2_recent');
		}

		return $this->render('Character/loadout.html.twig', [
			'form'=>$form->createView(),
		]);
	}

	/**
	  * @Route("/faith", name="maf_character_faith")
	  */
	public function faithAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$opts = new ArrayCollection();
		foreach($character->findAssociations() as $assoc) {
			if ($assoc->getFaithname() && $assoc->getFollowerName()) {
				$opts->add($assoc);
			}
		}

		$form = $this->createForm(new AssocSelectType($opts, 'faith', $character));
		$form->handleRequest($request);

		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			$character->setFaith($data['target']);
			$this->getDoctrine()->getManager()->flush();
			if ($data['target']) {
				$this->addFlash('notice', $this->get('translator')->trans('assoc.route.faith.success', array("%faith%"=>$data['target']->getFaithName()), 'orgs'));
			} else {
				$this->addFlash('notice', $this->get('translator')->trans('assoc.route.faith.success2', array(), 'orgs'));
			}

			return $this->redirectToRoute('bm2_recent');
		}

		return $this->render('Character/faith.html.twig', [
			'form'=>$form->createView(),
		]);
	}

	/**
	  * @Route("/kill")
	  */
	public function killAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('metaKillTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$form = $this->createFormBuilder()
			->add('death', 'textarea', array(
				'required'=>false,
				'label'=>'meta.background.death.desc',
				'translation_domain'=>'actions'
				))
			->add('sure', 'checkbox', array(
				'required'=>true,
				'label'=>'meta.kill.sure',
				'translation_domain' => 'actions'
				))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			$fail = false;
			$id = $character->getId();
			$data = $form->getData();
			$mm = $this->get('military_manager');
			$em = $this->getDoctrine()->getManager();
			if ($data['sure'] != true) {
				$fail = true;
			}
			if (!$fail) {
				// TODO: if killed while prisoner of someone, some consequences? we might simply have that one count as the killer here (for killers rights)
				// TODO: we should somehow store that it was a suicide, to catch various exploits
				$reclaimed = array();
				foreach ($character->getUnits() as $unit) {
					$mm->returnUnitHome($unit, 'suicide', $character);
				}
				$em->flush();
				if ($data['death']) {
					// dynamically create when needed
					if (!$character->getBackground()) {
						$this->get('character_manager')->newBackground($character);
					}
					$character->getBackground()->setDeath($data['death']);
					$em->flush();
				}
				$this->get('character_manager')->kill($character, null, false, 'death', true);
				foreach ($reclaimed as $rec) {
					$this->get('history')->logEvent(
						$rec['liege'],
						'event.character.deathreclaim',
						array('%link-character%'=>$character->getId(), '%amount%'=>$rec['number']),
						History::MEDIUM
					);
				}
				$em->flush();
				$this->addFlash('notice', $this->get('translator')->trans('meta.kill.success', array(), 'actions'));
				return $this->redirectToRoute('bm2_characters');
			}
		}

		return $this->render('Character/kill.html.twig', [
			'form' => $form->createView(),
		]);
	}

   /**
     * @Route("/retire")
     */
	public function retireAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('metaRetireTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$form = $this->createFormBuilder()
			->add('retirement', 'textarea', array(
				'required'=>false,
				'label'=>'meta.background.retirement.desc',
				'translation_domain'=>'actions'
				))
			->add('sure', 'checkbox', array(
				'required'=>true,
				'label'=>'meta.retire.sure',
				'translation_domain' => 'actions'
				))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			$fail = false;
			$id = $character->getId();
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();
			$mm = $this->get('military_manager');
			if ($data['sure'] != true) {
				$fail = true;
			}
			if (!$fail) {
				if ($data['retirement']) {
					// dynamically create when needed
					if (!$character->getBackground()) {
						$this->get('character_manager')->newBackground($character);
					}
					$character->getBackground()->setRetirement($data['retirement']);
					$em->flush();
				}
				$this->get('character_manager')->retire($character, true);
				$this->addFlash('notice', $this->get('translator')->trans('meta.retire.success', array(), 'actions'));
				return $this->redirectToRoute('bm2_characters');
			}
		}

		return $this->render('Character/retire.html.twig', [
			'form' => $form->createView(),
		]);
	}

	/**
	  * @Route("/surrender")
	  */
	public function surrenderAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('personalSurrenderTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new InteractionType('surrender', $this->get('geography')->calculateInteractionDistance($character), $character));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();

			$this->get('character_manager')->imprison($character, $data['target']);

			$this->get('history')->logEvent(
				$character,
				'event.character.surrenderto',
				array('%link-character%'=>$data['target']->getId()),
				History::HIGH, true
			);
			$this->get('history')->logEvent(
				$data['target'],
				'event.character.surrender',
				array('%link-character%'=>$character->getId()),
				History::HIGH, true
			);
			$em->flush();
			return array('success'=>true, 'target'=>$data['target']);
		}

		return $this->render('Character/surrender.html.twig', [
			'form'=>$form->createView(),
			'gold'=>$character->getGold()
		]);
	}


	/**
	  * @Route("/escape")
	  */
	public function escapeAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('personalEscapeTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if ($character->getPrisonerOf()->getSlumbering() == false && $character->getPrisonerOf()->isAlive() == true) {
			$captor_active = true;
		} else {
			$captor_active = false;
		}

		$form = $this->createFormBuilder()
			->add('submit', 'submit', array('label'=>'escape.submit', 'translation_domain' => 'actions'))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {

			if ($captor_active) { $hours = 16; } else { $hours = 4; }

			$act = new Action;
			$act->setType('character.escape')->setCharacter($character);
			$complete = new \DateTime("now");
			$complete->add(new \DateInterval("PT".$hours."H"));
			$act->setComplete($complete);
			$act->setBlockTravel(false);
			$result = $this->get('action_manager')->queue($act);

			return $this->render('Character/escape.html.twig', [
				'queued'=>true,
				'hours'=>$hours
			]);
		}

		return $this->render('Character/escape.html.twig', [
			'captor_active' => $captor_active,
			'form'=>$form->createView()
		]);
	}


	/**
	  * @Route("/crest")
	  */
	public function heraldryAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('metaHeraldryTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$available = array();

		# Get all crests for the current user.
		foreach ($character->getUser()->getCrests() as $crest) {
			$available[] = $crest->getId();
		}

                # Check for parents having different crests.
                foreach ($character->getParents() as $parent) {
                        if ($parent->getCrest()) {
                                $parentcrest = $parent->getCrest()->getId();
                                if (!in_array($parentcrest, $available)) {
                                        $available[] = $parentcrest;
                                }
                        }
                }

                # Check for partners having different crests.
                foreach ($character->getPartnerships() as $partnership) {
                        if ($partnership->getPartnerMayUseCrest()==TRUE) {
                                foreach ($partnership->getPartners() as $partners) {
                                        if ($partners->getCrest()) {
                                                $partnercrest = $partners->getCrest()->getId();
                                                if (!in_array($partnercrest, $available)) {
                                                        $available[] = $partnercrest;
                                                }
                                        }
                                }
                        }
                }

		if (empty($available)) {
			return $this->render('Character/heraldry.html.twig', [
				'nocrests'=>true
			]);
		}
		$form = $this->createFormBuilder()
			->add('crest', 'entity', array(
				'required' => false,
				'empty_value'=>'form.choose',
				'class'=>'BM2SiteBundle:Heraldry', 'property'=>'id', 'query_builder'=>function(EntityRepository $er) use ($available) {
					return $er->createQueryBuilder('c')->where('c.id IN (:avail)')->setParameter('avail', $available);
				}
			))->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$crest = $data['crest'];
			$character->setCrest($crest);
			$em = $this->getDoctrine()->getManager();
			$em->flush();
			return $this->redirectToRoute('bm2_character');
		}

		return $this->render('Character/heraldry.html.twig', [
			'form'=>$form->createView()
		]);
	}


	/**
	  * @Route("/entourage")
	  */
	public function entourageAction(Request $request) {
		# TODO: We call AppState and Dispatcher? Can't we combine this into a single call and return it as a list somehow? -- Andrew 20181210
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$others = $this->get('dispatcher')->getActionableCharacters();
		$em = $this->getDoctrine()->getManager();

		$form = $this->createForm(new EntourageManageType($character->getEntourage(), $others));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			$settlement = $this->get('dispatcher')->getActionableSettlement();
			$this->get('military_manager')->manageEntourage($character->getEntourage(), $data, $settlement, $character);

			$em->flush();
			$this->get('appstate')->setSessionData($character); // update, because maybe we changed our entourage count
			return $this->redirect($request->getUri());
		}

		$resupply = array();
		$total_food = 0;
		foreach ($character->getEntourage() as $entourage) {
			if ($entourage->getType()->getName() == 'follower') {
				if ($entourage->getEquipment()) {
					if (!isset($resupply[$entourage->getEquipment()->getId()])) {
						$resupply[$entourage->getEquipment()->getId()] = array('equipment'=>$entourage->getEquipment(), 'amount'=>0);
					}
					$resupply[$entourage->getEquipment()->getId()]['amount'] += floor($entourage->getSupply()/$entourage->getEquipment()->getResupplyCost());
				} else {
					$total_food += $entourage->getSupply();
				}
			}
		}

		$soldiers = $em->createQuery('SELECT count(s) FROM BM2SiteBundle:Soldier s WHERE s.character = :me and s.alive = true')->setParameter('me', $character)->getSingleScalarResult();
		$entourage = $character->getEntourage()->count();
		$men = $soldiers + $entourage;
		if ($men > 0) {
			$food_days = round($total_food / $men);
		} else {
			$food_days = 0;
		}

		return $this->render('Character/entourage.html.twig', [
			'entourage' => $character->getEntourage(),
			'form' => $form->createView(),
			'food_days' => $food_days,
			'can_resupply' => $character->getInsideSettlement()?$this->get('permission_manager')->checkSettlementPermission($character->getInsideSettlement(), $character, 'resupply'):false,
			'resupply' => $resupply
		]);
	}

   /**
     * @Route("/set_travel", defaults={"_format"="json"})
     */
	public function setTravelAction(Request $request) {
		if ($request->isMethod('POST') && $request->request->has("route")) {
			$character = $this->get('appstate')->getCharacter();
			if (! $character instanceof Character) {
				return $this->redirectToRoute($character);
			}
			if ($character->isPrisoner()) {
				// prisoners cannot travel on their own
				$resp = new JsonResponse();
				$resp->setData(array('turns'=>0, 'prisoner'=>true));
				return $resp;
			}
			if ($character->getUser()->getRestricted()) {
				$resp = new JsonResponse();
				$resp->setData(array('turns'=>0, 'restricted'=>true));
				return $resp;
			}
			$em = $this->getDoctrine()->getManager();
			$points = $request->request->get('route');
			$enter = $request->request->get('enter');
			if ($enter===true or $enter == "true") { $enter = true; } else { $enter = false; }

            /* FIXME: not used - what did I intend it for?
			$travel = $this->get('geography')->jsonTravelSegments($character);
            */
			if ($character->getTravel()) {
				$old = array(
					'route' => $character->getTravel(),
					'progress' => $character->getProgress(),
					'speed' => $character->getSpeed(),
					'enter' => $character->getTravelEnter()
				);
			} else {
				$old = false;
			}

			// make sure we always start at our current location
			$start = $character->getLocation();
			if ( abs($start->getX() - floatval($points[0][0])) > 0.00001 || abs($start->getY() - floatval($points[0][1])) > 0.00001 ) { // sadly, can't use a simple compare here because we would be comparing strings with floats
				array_unshift($points, array($start->getX(), $start->getY()));
			}
			$world = $this->get('geography')->world;
			foreach ($points as $point) {
				if ( $point[0] < $world['x_min']
					|| $point[0] > $world['x_max']
					|| $point[1] < $world['y_min']
					|| $point[1] > $world['y_max']) {
					// outside world boundaries
					$resp = new JsonResponse();
					$resp->setData(array('turns'=>0, 'leftworld'=>true));
					return $resp;
				}
			}

			// validate that we have at least 2 points
			if (count($points) < 2) {
				$resp = new JsonResponse();
				$resp->setData(array('turns'=>0, 'pointerror'=>true));
				return $resp;
			}

			$route = new LineString($points);
//			$route->setSrid(4326);
			$character->setTravel($route)->setProgress(0)->setTravelEnter($enter);
			$em->flush($character); // I think DQL operates on the database directly, so we need to flush first

			$can_travel = true;
			$invalid=array();
			$bridges=array();
			$roads=array();
			$disembark=false;

			if ($character->getTravelAtSea()) {
				// sea travel - disembark when we hit land
				list($invalid, $disembark) = $this->get('geography')->checkTravelSea($character, $invalid);
			} else {
				// land travel - may not cross water, oceans, impassable mountains
				$invalid = $this->get('geography')->checkTravelLand($character, $invalid);

				list($invalid, $bridges) = $this->get('geography')->checkTravelRivers($character, $invalid);
				$invalid = $this->get('geography')->checkTravelCliffs($character, $invalid);

				$roads = $this->get('geography')->checkTravelRoads($character);

				if (!empty($invalid)) {
					$can_travel = false;
				}
			}

			$turns=0;
			if ($can_travel) {
				if ($this->get('geography')->updateTravelSpeed($character)) {
					$turns = 1/$character->getSpeed();
					if ($character->getTravelAtSea()) {
						$character->setTravelDisembark($disembark);
						$character->setTravelEnter(false); // we never directly enter a settlement - TODO: why not?
					}
				} else {
					// restore old travel data
					$character->setTravel($old['route']);
					$character->setProgress($old['progress']);
					$character->setSpeed($old['speed']);
				}
			} else {
				if ($old) {
					// restore old travel data
					$character->setTravel($old['route']);
					$character->setProgress($old['progress']);
					$character->setSpeed($old['speed']);
				} else {
					$character->setTravel(null);
					$character->setProgress(null);
					$character->setSpeed(null);
				}
			}
			$em->flush();

			if (!empty($invalid)) {
				$invalid = array('type'=>'FeatureCollection', 'features'=>$invalid);
			}
			$result = array('turns'=>$turns, 'bridges'=>$bridges, 'roads'=>$roads, 'invalid'=>$invalid, 'disembark'=>$disembark);
		} else {
			$result = false;
		}
		$resp = new JsonResponse();
		$resp->setData($result);
		return $resp;
	}

	/**
	  * @Route("/clear_travel")
	  */
	public function clearTravelAction() {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$character->setTravel(null)->setProgress(null)->setSpeed(null)->setTravelEnter(false)->setTravelDisembark(false);
		$this->getDoctrine()->getManager()->flush();
		return new Response();
	}


   /**
     * @Route("/battlereport/{id}", name="bm2_battlereport", requirements={"id"="\d+"})
     */
	public function viewBattleReportAction($id) {
		$character = $this->get('appstate')->getCharacter(true,true,true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		$report = $em->getRepository('BM2SiteBundle:BattleReport')->find($id);
		if (!$report) {
			throw $this->createNotFoundException('error.notfound.battlereport');
		}

		$check = false;
		if (!$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
			$check = $report->checkForObserver($character);
			if (!$check) {
				$query = $em->createQuery('SELECT p FROM BM2SiteBundle:BattleParticipant p WHERE p.battle_report = :br AND p.character = :me');
				$query->setParameters(array('br'=>$report, 'me'=>$character));
				$check = $query->getOneOrNullResult();
				if (!$check) {
					$query = $em->createQuery('SELECT p FROM BM2SiteBundle:BattleReportCharacter p JOIN p.group_report g WHERE p.character = :me AND g.battle_report = :br');
					$query->setParameters(array('br'=>$report, 'me'=>$character));
					$check = $query->getOneOrNullResult();
					if (!$check) {
						$check = false;
					} else {
						$check = true; # standardize variable.
					}
				} else {
					$check = true; # standardize variable.
				}
			} else {
				$check = true;
			}
		} else {
			$check = true;
		}

		if ($loc = $report->getLocationName()) {
			if ($report->getPlace()) {
				$location = array('key' => $loc['key'], 'entity'=>$em->getRepository("BM2SiteBundle:Place")->find($loc['id']));
			} else {
				$location = array('key' => $loc['key'], 'entity'=>$em->getRepository("BM2SiteBundle:Settlement")->find($loc['id']));
			}
		} else {
			$location = array('key'=>'battle.location.nowhere');
		}


		// get entity references
		if ($report->getStart()) {
			$start = array();
			foreach ($report->getStart() as $i=>$group) {
				$start[$i]=array();
				foreach ($group as $id=>$amount) {
					$start[$i][] = array('type'=>$id, 'amount'=>$amount);
				}
			}

			$survivors = array();
			$nobles = array();
			$finish = $report->getFinish();
			$survivors_data = $finish['survivors'];
			$nobles_data = $finish['nobles'];
			foreach ($survivors_data as $i=>$group) {
				$survivors[$i]=array();
				foreach ($group as $id=>$amount) {
					$survivors[$i][] = array('type'=>$id, 'amount'=>$amount);
				}
			}
			foreach ($nobles_data as $i=>$group) {
				$nobles[$i]=array();
				foreach ($group as $id=>$fate) {
					$char = $em->getRepository('BM2SiteBundle:Character')->find($id);
					$nobles[$i][] = array('character'=>$char, 'fate'=>$fate);
				}
			}

			return $this->render('Character/viewBattleReport.html.twig', [
				'version'=>1, 'start'=>$start, 'survivors'=>$survivors, 'nobles'=>$nobles, 'report'=>$report, 'location'=>$location, 'access'=>$check
			]);
		} else {
			$count = $report->getGroups()->count(); # These return in a specific order, low to high, ID ascending.
			$fighters = new ArrayCollection();
			foreach ($report->getGroups() as $group) {
				$totalRounds = $group->getCombatStages()->count();
				foreach ($group->getCharacters() as $each) {
					$fighters->add($each);
				}
			}

			return $this->render('Character/viewBattleReport.html.twig', [
				'version'=>2, 'report'=>$report, 'location'=>$location, 'count'=>$count, 'roundcount'=>$totalRounds, 'access'=>$check, 'fighters'=>$fighters
			]);
		}
	}

}
