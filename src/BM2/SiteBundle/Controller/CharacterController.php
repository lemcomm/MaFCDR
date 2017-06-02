<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\CharacterBackground;
use BM2\SiteBundle\Entity\CharacterRating;
use BM2\SiteBundle\Entity\CharacterRatingVote;

use BM2\SiteBundle\Form\CharacterBackgroundType;
use BM2\SiteBundle\Form\CharacterPlacementType;
use BM2\SiteBundle\Form\CharacterRatingType;
use BM2\SiteBundle\Form\EntourageManageType;
use BM2\SiteBundle\Form\SoldiersManageType;
use BM2\SiteBundle\Form\InteractionType;
use BM2\SiteBundle\Service\Geography;
use BM2\SiteBundle\Service\History;

use CrEOF\Spatial\PHP\Types\Geometry\LineString;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
			if (!isset($spottings[$id])) {
				$spottings[$id] = array('target'=>$spotevent->getTarget(), 'details'=>false, 'events'=>array());
			}
			// TODO: figure out if we can see details or not - by distance between spotter or watchtower?
			$spottings[$id]['events'][] = $spotevent;
		}
		return $spottings;
	}


   /**
     * @Route("/", name="bm2_character")
     * @Template("BM2SiteBundle:Character:character.html.twig")
     */
	public function indexAction() {
		$character = $this->get('appstate')->getCharacter(true, true, true);

		if ($location=$character->getLocation()) {
			$geo = $this->get('geography');
			$nearest = $geo->findNearestSettlement($character);
			$settlement=array_shift($nearest);
			$location = $settlement->getGeoData();
		} else {
			return $this->redirectToRoute('bm2_site_character_start');
		}

		return array(
			'location' => $location,
			'familiarity' => $geo->findRegionFamiliarityLevel($character, $location),
			'spot' => $geo->calculateSpottingDistance($character),
			'act' => $geo->calculateInteractionDistance($character),
			'settlement' => $settlement,
			'nearest' => $nearest,
			'others' => $this->get('geography')->findCharactersInSpotRange($character),
			'spottings' => $this->getSpottings($character),
			'entourage' => $character->getActiveEntourageByType(),
			'soldiers' => $character->getActiveSoldiersByType(),
			'dead_entourage' => $character->getDeadEntourage()->count(),
			'dead_soldiers' => $character->getDeadSoldiers()->count(),
		);

	}

	/**
	  * @Route("/summary", name="bm2_recent")
	  * @Template
	  */
	public function summaryAction() {
		$character = $this->get('appstate')->getCharacter(true, true, true);

		if (!$character->getLocation()) {
			return $this->redirectToRoute('bm2_site_character_start');
		}

		// FIXME: this auto-gathers all our soldiers. we want that automatic, but not immediate (hidden action at turn?)
		$hungry = 0; $starving = 0; $wounded = 0; $lost = 0; $dead = 0; $alive = 0; $distance = 0;
		foreach ($character->getSoldiers() as $soldier) {
			$soldier->setRouted(false);
			if ($soldier->isAlive()) {
				$distance += sqrt($soldier->getDistanceHome()/1000);
				$alive++;
				if ($soldier->getHungry()>50) {
					$hungry++;
					if ($soldier->getHungry()>90) {
						$starving++;
					}
				}
				if ($soldier->getWounded()>10) {
					$wounded++;
				}
				if (!$soldier->getHasWeapon() || !$soldier->getHasArmour() || !$soldier->getHasEquipment()) {
					$lost++;
				}
			} else {
				$dead++;
			}
		}
		if ($alive > 0) {
			$hungry = ($hungry*100) / $alive;
			$starving = ($starving*100) / $alive;
			$wounded = ($wounded*100) / $alive;
			$lost = ($lost*100) / $alive;
			$distance = $distance / $alive;
		}
		$this->getDoctrine()->getManager()->flush();

		$msguser = $this->get('message_manager')->getCurrentUser();

		return array(
			'events' => $this->get('character_manager')->findEvents($character),
			'unread' => $this->get('message_manager')->getUnreadMessages($msguser),
			'others' => $this->get('geography')->findCharactersInSpotRange($character),
			'spottings' => $this->getSpottings($character),
			'battles' => $this->get('geography')->findBattlesNearMe($character, Geography::DISTANCE_BATTLE),
			'dungeons' => $this->get('geography')->findDungeonsNearMe($character, Geography::DISTANCE_DUNGEON),
			'spotrange' => $this->get('geography')->calculateSpottingDistance($character),
			'actrange' => $this->get('geography')->calculateInteractionDistance($character),
			'soldiers' => array('hungry'=>$hungry, 'starving'=>$starving, 'wounded'=>$wounded, 'lost'=>$lost, 'alive'=>$alive, 'dead'=>$dead, 'distance'=>$distance)
		);
	}

	/**
	  * @Route("/scouting", name="bm2_scouting")
	  * @Template
	  */
	public function scoutingAction() {
		$character = $this->get('appstate')->getCharacter(true, true, true);

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

			$spotted[] = array(
				'char' => $char,
				'distance' => $other['distance'],
				'realms' => $realms,
				'ultimates' => $ultimates,
				'entourage' => $char->getLivingEntourage()->count(),
				'soldiers' => $char->getLivingSoldiers()->count(),
			);
		}

		return array(
			'spotted' => $spotted
		);
	}

	/**
	  * @Route("/estates", name="bm2_estates")
	  * @Template
	  */
	public function estatesAction() {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		$em = $this->getDoctrine()->getManager();

		$estates = array();
		foreach ($character->getEstates() as $estate) {
			// FIXME: better: some trend analysis
			$query = $em->createQuery('SELECT s.population as pop FROM BM2SiteBundle:StatisticSettlement s WHERE s.settlement = :here ORDER BY s.cycle DESC');
			$query->setParameter('here', $estate);
			$query->setMaxResults(3);
			$data = $query->getArrayResult();
			if (isset($data[2])) {
				$popchange = $data[0]['pop'] - $data[2]['pop'];
			} else {
				$popchange = 0;
			}
			if ($estate->getRealm()) {
				$r = $estate->getRealm();
				$u = $estate->getRealm()->findUltimate();
				$realm = array('id'=>$r->getId(), 'name'=>$r->getName());
				$ultimate = array('id'=>$u->getId(), 'name'=>$u->getName());
			} else {
				$realm = null; $ultimate = null;
			}
			$build = array();
			foreach ($estate->getBuildings()->filter(
				function($entry) {
					return ($entry->getActive()==false && $entry->getWorkers()>0);
				}) as $building) {
				$build[] = array('id'=>$building->getType()->getId(), 'name'=>$building->getType()->getName());
			}

			$estates[] = array(
				'id' => $estate->getId(),
				'name' => $estate->getName(),
				'pop' => $estate->getFullPopulation(),
				'peasants' => $estate->getPopulation(),
				'thralls' => $estate->getThralls(),
				'size' => $estate->getSize(),
				'popchange' => $popchange,
				'militia' => $estate->getActiveMilitia()->count(),
				'recruits' => $estate->getRecruits()->count(),
				'realm' => $realm,
				'ultimate' => $ultimate,
				'build' => $build,
			);
		}

		$poly = $this->get('geography')->findRegionsPolygon($character->getEstates());
		return array('estates'=>$estates, 'poly'=>$poly);
	}

   /**
     * @Route("/first", name="bm2_first")
     * @Template
     */
   public function firstAction() {
   	$character = $this->get('appstate')->getCharacter(true, true, true);

   	if (!$character->getLocation()) {
   		return $this->redirectToRoute('bm2_site_character_start');
   	}

   	$msguser = $this->get('message_manager')->getCurrentUser();

   	return array(
   		'unread' => $this->get('message_manager')->getUnreadMessages($msguser),
   	);
   }

   /**
     * @Route("/start")
     * @Template
     */
   public function startAction(Request $request) {
		$character = $this->get('appstate')->getCharacter(true, false, true);
		if ($character->getLocation()) {
			return $this->redirectToRoute('bm2_character');
		}

		$form_offer = $this->createForm(new CharacterPlacementType('offer', $character));
		$form_existing = $this->createForm(new CharacterPlacementType('family', $character));
		$form_map = $this->createFormBuilder()->add('settlement_id', 'hidden')->getForm();
		if ($request->isMethod('POST')) {
			$startlocation = false;
			$historydone=false;
			$em = $this->getDoctrine()->getManager();

			$form_offer->bind($request);
			if ($form_offer->isValid()) {
				// sign up with a lord by taking his offer
				$data = $form_offer->getData();
				if (!$data['offer']) {
					throw $this->createNotFoundException('error.notfound.offer');				
				}

				$startlocation = $data['offer']->getSettlement();
				$liege = $startlocation->getOwner();
				if (!$liege) {
					// invalid offer, should never happen, but catch it anyways
					throw $this->createNotFoundException('error.notfound.newliege');
				}
				if ($data['offer']->getGiveSettlement()) {
					$act = new Action;
					$act->setType('settlement.grant')->setStringValue('keep_claim')->setCharacter($liege);
					$act->setTargetSettlement($startlocation)->setTargetCharacter($character);
					$act->setBlockTravel(false);
					$complete = new \DateTime("now");
					$complete->add(new \DateInterval("PT15M"));
					$act->setComplete($complete);
					$this->get('action_resolution')->queue($act);

					// pseudo-action to prevent that he moves away in this time
					$act->setType('settlement.receive')->setCharacter($character);
					$act->setTargetSettlement($startlocation);
					$act->setBlockTravel(true);
					$complete = new \DateTime("now");
					$complete->add(new \DateInterval("PT15M"));
					$act->setComplete($complete);
					$this->get('action_resolution')->queue($act);

					$this->get('history')->logEvent(
						$character,
						'politics.oath.taken2',
						array('%link-character%'=>$liege->getId(), '%link-settlement%'=>$startlocation->getId()),
						History::HIGH, true
					);
				} else {
					foreach ($data['offer']->getSoldiers() as $soldier) {
						$this->get('military')->assign($soldier, $character);
					}
					$this->get('history')->logEvent(
						$character,
						'politics.oath.taken',
						array('%link-character%'=>$liege->getId(), '%link-settlement%'=>$startlocation->getId()),
						History::HIGH, true
					);
				}
				$character->setLiege($liege);
				$historydone=true;

				// TODO: propagate downwards through all vassals, their vassals, etc.

				// message to new liege - set to high so it triggers a notification
				$this->get('history')->logEvent(
					$liege,
					'politics.oath.offer2',
					array('%link-character%'=>$character->getId(), '%link-settlement%'=>$startlocation->getId()),
					History::HIGH
				);
				$em->remove($data['offer']);

				$em->flush(); // because some DQL below needs it, probably

				// join realm conversations
				$msg_user = $this->get('message_manager')->getMsgUser($character);

				$my_realms = $character->findRealms();
				if ($my_realms) {
					$query = $em->createQuery("SELECT c FROM MsgBundle:Conversation c JOIN c.app_reference r WHERE r IN (:realms)");
					$query->setParameter('realms', $my_realms->toArray());
					foreach ($query->getResult() as $conversation) {
						$this->get('message_manager')->updateMembers($conversation);
					}
				}

				// TODO: announce to lowest-level realm - that's easy to get because it's the realm of our settlement. :-)
				// also TODO: allow "join-as-vassal" for the other join options, with the same announcement to realm and liege,
				//					so basically a lot of this would then shift to the more general and out of this branch

				// create a conversation with my new liege
				// TODO: this should be configurable
				$topic = 'Welcome from '.$liege->getName().' to '.$character->getName();
				$content = 'Welcome to my service, [c:'.$character->getId().']. I am [c:'.$liege->getId().'] and your liege now, since you accepted my knight offer. Please introduce yourself by replying to this message and I will let you know what you can do to earn your stay.';
				list($meta, $message) = $this->get('message_manager')->newConversation($msg_user, array($this->get('message_manager')->getMsgUser($liege)), $topic, $content);
				$this->get('message_manager')->setAllUnread($msg_user);
			}

			$form_existing->bind($request);
			if ($form_existing->isValid()) {
				// place at estate of family member
				$data = $form_existing->getData();
				$startlocation = $data['estate'];
			}

			$form_map->bind($request);
			if ($form_map->isValid()) {
				$data = $form_map->getData();
				$id = $data['settlement_id'];

				$startlocation = $em->getRepository('BM2SiteBundle:Settlement')->find($id);
				if (!$startlocation->getOwner() || $startlocation->getAllowSpawn()==false) {
					$startlocation = false;
				}
			}

			if ($startlocation) {
				$character->setLocation($startlocation->getGeoData()->getCenter());
				$character->setInsideSettlement($startlocation);
				if (!$historydone) {
					$this->get('history')->logEvent(
						$character,
						'event.character.start',
						array('%link-settlement%'=>$startlocation->getId()),
						History::HIGH,	true
					);
				}
				$this->get('history')->logEvent(
					$startlocation,
					'event.settlement.charstart',
					array('%link-character%'=>$character->getId()),
					History::MEDIUM, true, 15
				);
				$this->get('history')->visitLog($startlocation, $character);
				$em->flush();

				return $this->redirectToRoute('bm2_first');
			}
		}
		return array(
			'form_offer'=>$form_offer->createView(),
			'form_existing'=>$form_existing->createView(),
			'form_map'=>$form_map->createView()
		);
	}


	/**
	  * @Route("/view/{id}", requirements={"id"="\d+"})
	  * @Template
	  */
	public function viewAction(Character $id) {
		$char = $id;
		$character = $this->get('appstate')->getCharacter(false, true, true);
		if ($character) {
			$details = $this->get('interactions')->characterViewDetails($character, $char);
		} else {
			$details = array('spot' => false, 'spy' => false);
		}
		if ($details['spot']) {
			$entourage = $char->getActiveEntourageByType();
			$soldiers = $char->getActiveSoldiersByType();
		} else {
			$entourage = null;
			$soldiers = null;
		}
		return array(
			'char'		=> $char,
			'details'	=> $details,
			'entourage'	=> $entourage,
			'soldiers'	=> $soldiers,
		);
	}

	/**
	  * @Route("/reputation/{id}", requirements={"id"="\d+"})
	  * @Template
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
		return array(
			'char'		=> $char,
			'ratings'	=> $data,
			'respect'	=> $respect,
			'honor'		=> $honor,
			'trust'		=> $trust,
			'form'		=> $form->createView()
		);
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
	  * @Template("BM2SiteBundle:Account:familytree.html.twig")
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
			$dot = $this->renderView('BM2SiteBundle:Account:familytree.dot.twig', array('characters'=>$characters));

			fwrite($pipes[0], $dot);
			fclose($pipes[0]);

			$svg = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			$return_value = proc_close($process);
		}

		return array('svg' => $svg);
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
     * @Template
     */
	public function backgroundAction(Request $request) {
		$character = $this->get('appstate')->getCharacter(true, true, true);

		$em = $this->getDoctrine()->getManager();
		if ($request->query->get('starting')) {
			$starting = true;
		} else {
			$starting = false;
		}

		// dynamically create when needed
		if (!$character->getBackground()) {
			$background = new CharacterBackground;
			$character->setBackground($background);
			$background->setCharacter($character);
			$em->persist($background);
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
						return $this->redirectToRoute('bm2_site_character_start', array('id'=>$character->getId()));
					}
				} else {
					return $this->redirectToRoute('bm2_characters');
				}
			} else {
				$this->addFlash('notice', $this->get('translator')->trans('meta.background.updated', array(), 'actions'));
			}
		}

		return array(
			'form' => $form->createView(),
			'starting' => $starting
		);
	}

	/**
	  * @Route("/rename")
	  * @Template
	  */
	public function renameAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();

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

			return array('result'=>array('success'=>true), 'newname'=>$newname);
		}

		return array('form'=>$form->createView());
	}

   /**
     * @Route("/kill")
     * @Template
     */
	public function killAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();
		$form = $this->createFormBuilder()
			->add('sure', 'checkbox', array(
				'required'=>true,
				'label'=>'meta.kill.sure',
				'translation_domain' => 'actions'
				))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			// FIXME: validation - it only checks for the checkbox on the browser side so far
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();

			// TODO: if killed while prisoner of someone, some consequences? we might simply have that one count as the killer here (for killers rights)
			// TODO: we should somehow store that it was a suicide, to catch various exploits
			$reclaimed = array();
			foreach ($character->getSoldiers() as $soldier) {
				if ($liege = $soldier->getLiege()) {
					if (!isset($reclaimed[$liege->getId()])) {
						$reclaimed[$liege->getId()] = array('liege'=>$liege, 'number'=>0);
					}
					$reclaimed[$liege->getId()]['number']++;
					// FIXME: this does not, in fact, work AT ALL - the message is sent, but soldiers are not re-assigned!
					$soldier->setCharacter($liege);
					$soldier->setLiege(null)->setAssignedSince(null);
				}
			}
			$em->flush();
			$this->get('character_manager')->kill($character);
			foreach ($reclaimed as $rec) {
				$this->get('history')->logEvent(
					$rec['liege'],
					'event.character.deathreclaim',
					array('%link-character%'=>$character->getId(), '%amount%'=>$rec['number']),
					History::MEDIUM
				);
			}
			$em->flush();

			// TODO: this should bring up the background screen or something, to enter a death roleplay description
			return array('result'=>array('success'=>true));
		}

		return array('form'=>$form->createView());
	}

   /**
     * @Route("/respawn")
     * @Template
     */
	public function respawnAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();
		$form = $this->createFormBuilder()
			->add('sure', 'checkbox', array(
				'required'=>true,
				'label'=>'meta.respawn.sure',
				'translation_domain' => 'actions'
				))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			// FIXME: validation - it only checks for the checkbox on the browser side so far
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();

			$this->get('character_manager')->respawn($character);
			$em->flush();
			return array('result'=>array('success'=>true));
		}

		return array('form'=>$form->createView());
	}


	/**
	  * @Route("/surrender")
	  * @Template
	  */
	public function surrenderAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('personalSurrenderTest');

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

		return array('form'=>$form->createView(), 'gold'=>$character->getGold());
	}

	/**
	  * @Route("/escape")
	  * @Template
	  */
	public function escapeAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('personalEscapeTest');

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
			$result = $this->get('action_resolution')->queue($act);

			return array('queued'=>true, 'hours'=>$hours);
		}

		return array(
			'captor_active' => $captor_active,
			'form'=>$form->createView()
		);
	}


	/**
	  * @Route("/crest")
	  * @Template
	  */
	public function heraldryAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('metaHeraldryTest');

		$available = array();
		foreach ($character->getUser()->getCrests() as $crest) {
			$available[] = $crest->getId();
		}

		if (empty($available)) {
			return array('nocrests'=>true);
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

		return array('form'=>$form->createView());
	}


	/**
	  * @Route("/entourage")
	  * @Template
	  */
	public function entourageAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();
		$others = $this->get('dispatcher')->getActionableCharacters();
		$em = $this->getDoctrine()->getManager();

		$form = $this->createForm(new EntourageManageType($character->getEntourage(), $others));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			$settlement = $this->get('dispatcher')->getActionableSettlement();
			$this->get('military')->manage($character->getEntourage(), $data, $settlement, $character);

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

		return array(
			'entourage' => $character->getEntourage(),
			'form' => $form->createView(),
			'food_days' => $food_days,
			'can_resupply' => $character->getInsideSettlement()?$this->get('permission_manager')->checkSettlementPermission($character->getInsideSettlement(), $character, 'resupply'):false,
			'resupply' => $resupply
		);
	}


	/**
	  * @Route("/groupby/{by}")
	  */
	public function groupsoldiersAction($by) {
		$character = $this->get('appstate')->getCharacter();

		if ($by=="type") {
			$this->get('military')->groupByType($character->getSoldiers());
		} else {
			$this->get('military')->groupByEquipment($character->getSoldiers());
		}
		$this->getDoctrine()->getManager()->flush();

		$this->addFlash('notice', $this->get('translator')->trans('recruit.manage.grouped', array(), 'actions'));

		return $this->redirectToRoute('bm2_site_character_soldiers');
	}

	/**
	  * @Route("/soldiers")
	  * @Template
	  */
	public function soldiersAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();
		$settlement = $this->get('dispatcher')->getActionableSettlement();
		if ($character->getPrisonerOf()) {
			$others = array($character->getPrisonerOf());
		} else {
			$others = $this->get('dispatcher')->getActionableCharacters(true);
		}

		$em = $this->getDoctrine()->getManager();

		$resupply=array();
		$training=array();
		if ($settlement) {
			if ($this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'resupply')) {
				$resupply = $this->get('military')->findAvailableEquipment($settlement, false);
			}
			if ($this->get('permission_manager')->checkSettlementPermission($settlement, $character, 'recruit')) {
				$training = $this->get('military')->findAvailableEquipment($settlement, true);
			}
		} else {
			foreach ($character->getEntourage() as $entourage) {
				if ($entourage->getEquipment()) {
					$item = $entourage->getEquipment()->getId();
					if (!isset($resupply[$item])) {
						$resupply[$item] = array('item'=>$entourage->getEquipment(), 'resupply'=>0);
					}
					$resupply[$item]['resupply'] += $entourage->getSupply();
				}
			}
		}
		$form = $this->createForm(new SoldiersManageType($em, $character->getSoldiers(), $resupply, $training, $others, $settlement));

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			list($success, $fail) = $this->get('military')->manage($character->getSoldiers(), $data, $settlement, $character);
			// TODO: notice with result

			$em = $this->getDoctrine()->getManager();
			$em->flush();
			$this->get('appstate')->setSessionData($character); // update, because maybe we changed our soldiers count
			return $this->redirect($request->getUri());
		}

		return array(
			'soldiers' => $character->getSoldiers(),
			'resupply' => $resupply,
			'settlement' => $settlement,
			'training' => $training,
			'form' => $form->createView(),
			'limit' => $this->get('appstate')->getGlobal('pagerlimit', 100)
		);
	}

   /**
     * @Route("/set_travel", defaults={"_format"="json"})
     */
	public function setTravelAction(Request $request) {
		if ($request->isMethod('POST') && $request->request->has("route")) {
			$character = $this->get('appstate')->getCharacter();
			if ($character->isPrisoner()) {
				// prisoners cannot travel on their own
				return new Response(json_encode(array('turns'=>0, 'prisoner'=>true)));
			}
			if ($character->getUser()->getRestricted()) {
				return new Response(json_encode(array('turns'=>0, 'restricted'=>true)));				
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
					return new Response(json_encode(array('turns'=>0, 'leftworld'=>true)));
				}
			}

			// validate that we have at least 2 points
			if (count($points) < 2) {
				return new Response(json_encode(false));
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

		return new Response(json_encode($result));
	}

	/**
	  * @Route("/clear_travel")
	  */
	public function clearTravelAction() {
		$character = $this->get('appstate')->getCharacter();
		$character->setTravel(null)->setProgress(null)->setSpeed(null)->setTravelEnter(false)->setTravelDisembark(false);
		$this->getDoctrine()->getManager()->flush();
		return new Response();
	}


   /**
     * @Route("/battlereport/{id}", name="bm2_battlereport", requirements={"id"="\d+"})
     * @Template
     */
	public function viewBattleReportAction($id) {
		$character = $this->get('appstate')->getCharacter(true,true,true);

		$em = $this->getDoctrine()->getManager();
		$report = $em->getRepository('BM2SiteBundle:BattleReport')->find($id);
		if (!$report) {
			throw $this->createNotFoundException('error.notfound.battlereport');
		}

   	if (!$this->getUser()->hasRole('ROLE_ADMIN')) {
			$query = $em->createQuery('SELECT p FROM BM2SiteBundle:BattleParticipant p WHERE p.battle_report = :br AND p.character = :me');
			$query->setParameters(array('br'=>$report, 'me'=>$character));
			$check = $query->getOneOrNullResult();
			if (!$check) {
				throw $this->createNotFoundException('error.noaccess.battlereport');
			}
		}

		if ($loc = $report->getLocationName()) {
			$location = array('key' => $loc['key'], 'entity'=>$em->getRepository("BM2SiteBundle:Settlement")->find($loc['id']));
		} else {
			$location = array('key'=>'battle.location.nowhere');
		}

		// get entity references
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

		return array('start'=>$start, 'survivors'=>$survivors, 'nobles'=>$nobles, 'report'=>$report, 'location'=>$location);
	}

	/**
	  * @Route("/mercenaries/{id}", name="bm2_mercenaries", requirements={"id"="\d+"})
	  * @Template
	  */
	public function mercenariesAction($id) {
		$character = $this->get('appstate')->getCharacter(true);

		$em = $this->getDoctrine()->getManager();
		$mercs = $em->getRepository('BM2SiteBundle:Mercenaries')->find($id);
		if (!$mercs) {
			throw $this->createNotFoundException('error.notfound.mercenaries');
		}

		return array('mercs'=>$mercs, 'hiredbyme'=>$mercs->getHiredBy()==$character);
	}

}
