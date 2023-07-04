<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\User;
use BM2\SiteBundle\Entity\AppKey;

use BM2\SiteBundle\Entity\UserLog;
use BM2\SiteBundle\Form\CharacterCreationType;
use BM2\SiteBundle\Form\ListSelectType;
use BM2\SiteBundle\Form\NpcSelectType;
use BM2\SiteBundle\Form\SettingsType;
use BM2\SiteBundle\Form\UserDataType;

use BM2\SiteBundle\Service\CharacterManager;

use Doctrine\Common\Collections\ArrayCollection;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

/**
 * @Route("/account")
 */
class AccountController extends Controller {

	private $sellerIdentifier = "12386652935808730771";
	private $sellerSecret = "FDZFTp7L9tFpeSRvEdCVYQ"; // this is the sandbox secret. real one should not actually be here, but in a safe place

	private function notifications() {
		$announcements = file_get_contents(__DIR__."/../Announcements.md");

		$notices = array();
		$codes = $this->getDoctrine()->getManager()->getRepository('BM2SiteBundle:Code')->findBy(array('sent_to_email' => $this->getUser()->getEmail(), 'used' => false));
		foreach ($codes as $code) {
			// code found, activate and create a notice
			$result = $this->get('payment_manager')->redeemCode($this->getUser(), $code);
			if ($result === true) {
				$result = 'success';
			}
			$notices[] = array('code' => $code, 'result' => $result);
		}

		return array($announcements, $notices);
	}

	/**
	  * @Route("/", name="bm2_account")
	  */
	public function indexAction() {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_TOS')) {
			throw new AccessDeniedException('error.banned.tos');
		}
		$user = $this->getUser();
		$app = $this->get('appstate');
		if ($app->exitsCheck($user)) {
			return $this->redirectToRoute('maf_ip_req');
		}

		// clean out character id so we have a clear slate (especially for the template)
		$user->setCurrentCharacter(null);
		$em = $this->getDoctrine()->getManager();
		$em->flush();

		list($announcements, $notices) = $this->notifications();
		$update = $em->createQuery('SELECT u from BM2SiteBundle:UpdateNote u ORDER BY u.id DESC')->setMaxResults(1)->getResult()[0];

		return $this->render('Account/account.html.twig', [
			'announcements' => $announcements,
			'update' => $update,
			'notices' => $notices
		]);
	}


	/**
	  * @Route("/data")
	  */
	public function dataAction(Request $request) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_TOS')) {
			throw new AccessDeniedException('error.banned.tos');
		}
		$gm = false;
		$admin = false;
		if ($this->get('security.authorization_checker')->isGranted('ROLE_OLYMPUS')) {
			$gm = true;
			if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
				$admin = true;
			}
		}
		$user = $this->getUser();

		$desc = $user->getDescription();
		if ($desc) {
			$text = $desc->getText();
		} else {
			$text = null;
		}

		$form = $this->createForm(new UserDataType($gm, $text, $admin), $user);

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form['text']->getData();
			$this->get('user_manager')->updateUser($user);
			if ($data && $text != $data) {
				$desc = $this->get('description_manager')->newDescription($user, $data);
			}
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('account.data.saved'));
			return $this->redirectToRoute('bm2_account');
		}

		return $this->render('Account/data.html.twig', [
	   		'user' => $user,
	   		'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/characters", name="bm2_characters")
	  */
	public function charactersAction() {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_TOS')) {
			throw new AccessDeniedException('error.banned.tos');
		}
		$user = $this->getUser();
		$app = $this->get('appstate');
		if ($app->exitsCheck($user)) {
			return $this->redirectToRoute('maf_ip_req');
		}
		$em = $this->getDoctrine()->getManager();

		// clean out character id so we have a clear slate (especially for the template)
		$user->setCurrentCharacter(null);

		$canSpawn = $this->get('user_manager')->checkIfUserCanSpawnCharacters($user, false);
		if ($user->getLimits() === null) {
			$this->get('user_manager')->createLimits($user);
		}
		$em->flush();
		if (!$canSpawn) {
			$this->addFlash('error', $this->get('translator')->trans('newcharacter.overspawn2', array('%date%'=>$user->getNextSpawnTime()->format('Y-m-d H:i:s')), 'messages'));
		}


		$characters = array();
		$npcs = array();

		$now = new \DateTime("now");
		$a_week_ago = $now->sub(new \DateInterval("P7D"));

		foreach ($user->getCharacters() as $character) {
			//building our list of character statuses --Andrew
			$annexing = false;
			$supporting = false;
			$opposing = false;
			$looting = false;
			$blocking = false;
			$granting = false;
			$renaming = false;
			$reclaiming = false;
			$unretirable = false;
			$preBattle = false;
			$siege = false;
			$alive = $character->getAlive();
			if ($alive && $character->getLocation()) {
				$nearest = $this->get('geography')->findNearestSettlement($character);
				$settlement=array_shift($nearest);
				$at_settlement = ($nearest['distance'] < $this->get('geography')->calculateActionDistance($settlement));
				$location = $settlement->getName();
			} else {
				$location = false;
				$at_settlement = false;
			}
			if ($character->getList()<100) {
				$unread = $character->countNewMessages();
				$events = $character->countNewEvents();
			} else {
				// dead characters don't have events or messages...
				$unread = 0;
				$events = 0;
			}
			if ($character->getBattling() && $character->getBattleGroups()->isEmpty() == TRUE) {
				# NOTE: Because sometimes, battling isn't reset after a battle. May be related to entity locking.
				$character->setBattling(false);
				$em->flush();
			}

			// This adds in functionality for detecting character actions on this page. --Andrew
			if ($alive && $character->getActions()) {
				foreach ($character->getActions() as $actions) {
					switch($actions->getType()) {
						case 'settlement.take':
							$annexing = true;
							break;
						case 'support':
							$supporting = true;
							break;
						case 'oppose':
							$opposing = true;
							break;
						case 'settlement.loot':
							$looting = true;
							break;
						case 'military.block':
							$blocking = true;
							break;
						case 'settlement.grant':
							$granting = true;
							break;
						case 'settlement.rename':
							$renaming = true;
							break;
						case 'military.reclaim':
							$reclaiming = true;
							break;
					}
				}
			}
			if ($alive && !is_null($character->getRetiredOn()) && $character->getRetiredOn()->diff(new \DateTime("now"))->days > 7) {
				$unretirable = true;
			} else {
				$unretirable = false;
			}
			if ($alive && !$character->getBattleGroups()->isEmpty()) {
				foreach ($character->getBattleGroups() as $group) {
					if ($group->getBattle()) {
						$preBattle = true;
					}
					if ($group->getSiege()) {
						$siege = true;
					}
				}
			}

			$data = array(
				'id' => $character->getId(),
				'name' => $character->getName(),
				'list' => $character->getList(),
				'alive' => $character->getAlive(),
				'battling' => $character->getBattling(),
				'retired' => $character->getRetired(),
				'unretirable' => $unretirable,
				'npc' => $character->isNPC(),
				'slumbering' => $character->getSlumbering(),
				'prisoner' => $character->getPrisonerOf(),
				'log' => $character->getLog(),
				'location' => $location,
				'at_settlement' => $at_settlement,
				'at_sea' => $character->getTravelAtSea()?true:false,
				'travel' => $character->getTravel()?true:false,
				'prebattle' => $preBattle,
				'sieging' => $siege,
				'annexing' => $annexing,
				'supporting' => $supporting,
				'opposing' => $opposing,
				'looting' => $looting,
				'blocking' => $blocking,
				'granting' => $granting,
				'renaming' => $renaming,
				'reclaiming' => $reclaiming,
				'unread' => $unread,
				'requests' => count($this->get('game_request_manager')->findAllManageableRequests($character)),
				'events' => $events
			);

			if ($character->isNPC()) {
				$npcs[] = $data;
			} else {
				$characters[] = $data;
			}
			unset($character);
		}
		uasort($characters, array($this,'character_sort'));
		uasort($npcs, array($this,'character_sort'));

		list($announcements, $notices) = $this->notifications();

		$this->checkCharacterLimit($user);

		if (count($npcs)==0) {
			$free_npcs = $this->get('npc_manager')->getAvailableNPCs();
			if (count($free_npcs) > 0) {
				$npcs_form = $this->createForm(new NpcSelectType($free_npcs))->createView();
			} else {
				$npcs_form = null;
			}
		} else {
			$npcs_form = null;
			$free_npcs = array();
		}

		// check when our next payment is due and if we have enough to pay it
		$now = new \DateTime("now");
		$daysleft = (int)$now->diff($user->getPaidUntil())->format("%r%a");
		$next_fee = $this->get('payment_manager')->calculateUserFee($user);
		if ($user->getCredits() >= $next_fee) {
			$enough_credits = true;
		} else {
			$enough_credits = false;
		}

		$list_form = $this->createForm(new ListSelectType);

		$app->logUser($user, 'characters');

		foreach ($user->getPatronizing() as $patron) {
			if ($patron->getUpdateNeeded()) {
				$this->addFlash('warning', 'It appears we need a new access token for your patreon account in order to ensure you get your rewards. To corrected this, please click <a href="https://www.patreon.com/oauth2/authorize?response_type=code&client_id='.$patron->getCreator()->getClientId().'&redirect_uri='.$patron->getCreator()->getReturnUri().'&scope=identity">here</a> and allow us to re-establish our connection to your patreon account.');
			}
		}

		$update = $em->createQuery('SELECT u from BM2SiteBundle:UpdateNote u ORDER BY u.id DESC')->setMaxResults(1)->getResult();

		return $this->render('Account/characters.html.twig', [
			'announcements' => $announcements,
			'notices' => $notices,
			'update' => $update[0],
			'locked' => ($user->getAccountLevel()==0),
			'list_form' => $list_form->createView(),
			'characters' => $characters,
			'npcs' => $npcs,
			'free_npcs' => count($free_npcs),
			'npcsform' => $npcs_form,
			'user' => $user,
			'daysleft' => $daysleft,
			'enough_credits' => $enough_credits,
			'canSpawn' => $canSpawn
		]);
	}

	private function character_sort($a, $b) {
		if ($a['list'] < $b['list']) return -1;
		if ($b['list'] < $a['list']) return 1;

		return strcasecmp($a['name'], $b['name']);
	}


	/**
	  * @Route("/overview")
	  */
	public function overviewAction() {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_TOS')) {
			throw new AccessDeniedException('error.banned.tos');
		}
		$user = $this->getUser();

		$characters = array();
		$settlements = new ArrayCollection;
		$claims = new ArrayCollection;
		foreach ($user->getLivingCharacters() as $character) {

			foreach ($character->getOwnedSettlements() as $settlement) {
				$settlements->add($settlement);
			}
			foreach ($character->getSettlementClaims() as $claim) {
				$claims->add($claim->getSettlement());
			}

			$characters[] = array(
				'id' => $character->getId(),
				'name' => $character->getName(),
				'location' => $character->getLocation(),
			);

		}

		return $this->render('Account/overview.html.twig', [
			'characters' => $characters,
			'settlements' => $this->get('geography')->findRegionsPolygon($settlements),
			'claims' => $this->get('geography')->findRegionsPolygon($claims)
		]);
	}


	/**
	  * @Route("/newchar", name="bm2_newchar")
	  */
	public function newcharAction(Request $request) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_TOS')) {
			throw new AccessDeniedException('error.banned.tos');
		}
		$user = $this->getUser();
		$em = $this->getDoctrine()->getManager();
		$form = $this->createForm(new CharacterCreationType($user, $user->getNewCharsLimit()>0));

		list($make_more, $characters_active, $characters_allowed) = $this->checkCharacterLimit($user);
		if (!$make_more) {
			throw new AccessDeniedHttpException('newcharacter.overlimit');
		}
		$canSpawn = $this->get('user_manager')->checkIfUserCanSpawnCharacters($user, true);
		$em->flush();
		if (!$canSpawn) {
			$this->addFlash('error', $this->get('translator')->trans('newcharacter.overspawn2', array('%date%'=>$user->getNextSpawnTime()->format('Y-m-d H:i:s')), 'messages'));
		}

		// Don't allow "reserves" - set a limit of 2 created but unspawned characters
		$unspawned = $user->getCharacters()->filter(
			function($entry) {
				return ($entry->isAlive() && $entry->getLocation()==false && $entry->getRetired()!=true);
			}
		);
		if ($unspawned->count() >= 2) {
			$spawnlimit = true;
		} else {
			$spawnlimit = false;
		}

		if ($request->isMethod('POST') && $request->request->has("charactercreation")) {
			$form->handleRequest($request);
			if ($form->isValid()) {
				$data = $form->getData();
				if ($user->getNewCharsLimit() <= 0) { $data['dead']=true; } // validation doesn't catch this because the field is disabled

				$works = true;

				// avoid bursts / client bugs by only allowing a character creation every 60 seconds
				$query = $em->createQuery('SELECT c FROM BM2\SiteBundle\Entity\Character c WHERE c.user = :me AND c.created > :recent');
				$now = new \DateTime("now");
				$recent = $now->sub(new \DateInterval("PT60S"));
				$query->setParameters(array(
					'me' => $user,
					'recent' => $recent
				));
				if ($query->getResult()) {
					$form->addError(new FormError("character.burst"));
					$works = false;
				}
				if (preg_match('/[01234567890\!\@\#\$\%\^\&\*\(\)_\+\-\=\[\]\{\}\:\;\<\>\.\?\/\\\|\~\"]/', $data['name'])) {
					$form->addError(new FormError("character.illegaltext"));
					$works = false;
				}

				if ($spawnlimit) {
					$form->addError(new FormError("character.spawnlimit"));
					$works = false;
				}

				if ($data['partner']) {
					if (($data['gender']=='f' && $data['partner']->getMale()==false)
						|| ($data['gender']!='f' && $data['partner']->getMale()==true)) {
							$form->addError(new FormError("character.homosexual"));
							$works = false;
					}
				}

				// check that at least 1 parent is my own
				if ($data['father'] && $data['mother']) {
					if ($data['father']->getUser() != $user && $data['mother']->getUser() != $user) {
						$form->addError(new FormError("character.foreignparent"));
						$works = false;
					} else {
						// check that parents have a relation that includes sex
						$havesex = false;
						foreach ($data['father']->getPartnerships() as $p) {
							if ($p->getOtherPartner($data['father']) == $data['mother'] && $p->getWithSex()==true) {
								$havesex = true;
							}
						}
						if (!$havesex) {
							$form->addError(new FormError("character.nosex"));
							$works = false;
						}
					}
				} else if ($data['father']) {
					if ($data['father']->getUser() != $user) {
						$form->addError(new FormError("character.foreignparent"));
						$works = false;
					}
				} else if ($data['mother']) {
					if ($data['mother']->getUser() != $user) {
						$form->addError(new FormError("character.foreignparent"));
						$works = false;
					}
				}

				if ($works) {
					$character = $this->get('character_manager')->create($user, $data['name'], $data['gender'], !$data['dead'], $data['father'], $data['mother'], $data['partner']);

					if ($data['dead']!=true) {
						$user->setNewCharsLimit($user->getNewCharsLimit()-1);
					}
					$user->setCurrentCharacter($character);
					$em->flush();

					return $this->redirectToRoute('bm2_site_character_background', array('starting'=>true));
				}
			}
		}

		$mychars = array();
		foreach ($user->getCharacters() as $char) {
			$mypartners = array();
			foreach ($this->findSexPartners($char) as $partner) {
				$mypartners[] = array('id'=>$partner['id'], 'name'=>$partner['name'], 'mine'=>($partner['user']==$user->getId()));
				if ($partner['user']!=$user->getId()) {
					$theirpartners = array();
					foreach ($this->findSexPartners($partner) as $reverse) {
						$theirpartners[] = array('id'=>$reverse['id'], 'name'=>$reverse['name'], 'mine'=>($reverse['user']==$user->getId()));
					}
					$mychars[$partner['id']] = array('id'=>$partner['id'], 'name'=>$partner['name'], 'mine'=>false, 'partners'=>$theirpartners);
				}
			}
			$mychars[$char->getId()] = array('id'=>$char->getId(), 'name'=>$char->getName(), 'mine'=>true, 'gender'=>($char->getMale()?'m':'f'), 'partners'=>$mypartners);
		}

		return $this->render('Account/charactercreation.html.twig', [
			'characters' => $mychars,
			'limit' => $user->getNewCharsLimit(),
			'spawnlimit' => $spawnlimit,
			'characters_active' => $characters_active,
			'characters_allowed' => $characters_allowed,
			'form' => $form->createView()
		]);
	}

	private function findSexPartners($char) {
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT p.id, p.name, u.id as user FROM BM2SiteBundle:Character p JOIN p.user u JOIN p.partnerships m WITH m.with_sex=true JOIN m.partners me WITH p!=me WHERE me=:me AND me.male != p.male ORDER BY p.name');
		if (is_object($char)) {
			$query->setParameter('me', $char);
		} else {
			$query->setParameter('me', $char['id']);
		}
		return $query->getResult();
	}

	private function checkCharacterLimit(User $user) {
		$levels = $this->get('payment_manager')->getPaymentLevels($user);
		$level = $levels[$user->getAccountLevel()];
		$characters_allowed = $level['characters'];
		$characters_active = $user->getActiveCharacters()->count();
		if ($characters_active > $characters_allowed) {
			if (!$user->getRestricted()) {
				$user->setRestricted(true);
				$this->getDoctrine()->getManager()->flush();
			}
			$make_more = false;
		} else {
			$make_more = true;
			if ($user->getRestricted()) {
				$user->setRestricted(false);
			}
		}
		return array($make_more, $characters_active, $characters_allowed);
	}

	/**
	  * @Route("/settings")
	  */
	public function settingsAction(Request $request) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_TOS')) {
			throw new AccessDeniedException('error.banned.tos');
		}
		$user = $this->getUser();
		$languages = $this->get('appstate')->availableTranslations();
		$form = $this->createForm(new SettingsType($user, $languages));

		if ($request->isMethod('POST') && $request->request->has("settings")) {
			$form->handleRequest($request);
			if ($form->isValid()) {
   			$data = $form->getData();

   			$user->setLanguage($data['language']);
   			$user->setNotifications($data['notifications']);
			$user->setEmailDelay($data['emailDelay']);
   			$user->setNewsletter($data['newsletter']);
   			$this->get('user_manager')->updateUser($user);
				$this->addFlash('notice', $this->get('translator')->trans('account.settings.saved'));
				return $this->redirectToRoute('bm2_account');
			}
		}

		return $this->render('Account/settings.html.twig', [
			'form' => $form->createView(),
			'user' => $user
		]);
	}

	/**
	  * @Route("/endemails/{user}/{token}", name="maf_end_emails")
	  */
	public function endEmailsAction(User $user, $token=null) {
		if ($user && $user->getEmailOptOutToken() === $token) {
			$user->setNotifications(false);
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('mail.optout.success', [], "communication"));
			return $this->redirectToRoute('bm2_homepage');
		} else {
			$this->addFlash('notice', $this->get('translator')->trans('mail.optout.failure', [], "communication"));
			return $this->redirectToRoute('bm2_homepage');
		}
	}

	/**
	  * @Route("/secret/{id}", name="bm2_secret", defaults={"_format"="json"})
	  */
	public function secretAction() {
		// generate a new one and save it
		$key = sha1(time()."-maf-".mt_rand(0,1000000));
		$user = $this->getUser();
		$user->setAppKey($key);
		$this->getDoctrine()->getManager()->flush();

		return new Response(json_encode($key));
	}

	/**
	  * @Route("/listset")
	  */
	public function listsetAction(Request $request) {
		$user = $this->getUser();
		$list_form = $this->createForm(new ListSelectType);
		$list_form->handleRequest($request);
		if ($list_form->isValid()) {
			$data = $list_form->getData();
			echo "---";
			var_dump($data);
			echo "---";
			$em = $this->getDoctrine()->getManager();
			$character = $em->getRepository('BM2SiteBundle:Character')->find($data['char']);
			if (!$character || $character->getUser() != $user) {
				return new Response("error");
			}
			$character->setList($data['list']);
			$em->flush();
			return new Response("done");
		}
		return new Response("invalid form");
	}


	/**
	  * @Route("/listtoggle", defaults={"_format"="json"})
	  */
	public function listtoggleAction(Request $request) {
		$user = $this->getUser();
		$id = $request->request->get('id');

		$em = $this->getDoctrine()->getManager();
		$character = $em->getRepository('BM2SiteBundle:Character')->find($id);
		if (!$character) {
			throw new AccessDeniedHttpException('error.notfound.character');
		}
		if ($character->getUser() != $user) {
			throw new AccessDeniedHttpException('error.noaccess.character');
		}

		if ($character->isAlive()) {
			if ($character->getList() < 3) {
				$character->setList($character->getList()+1);
			} else {
				$character->setList(1);
			}
			$em->flush();
		}

		return new Response();
	}


	/**
	  * @Route("/play/{id}", name="bm2_play", requirements={"id"="\d+"})
	  */
	public function playAction(Request $request, $id) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_TOS')) {
			throw new AccessDeniedException('error.banned.tos');
		}
		$user = $this->getUser();
		$app = $this->get('appstate');
		if ($app->exitsCheck($user)) {
			return $this->redirectToRoute('maf_ip_req');
		}
		$logic = $request->query->get('logic');
		$app->logUser($user, 'play_char_'.$id.'_'.$logic);
		$this->checkCharacterLimit($user);

		$em = $this->getDoctrine()->getManager();
		$character = $em->getRepository('BM2SiteBundle:Character')->find($id);
		if (!$character) {
			throw $this->createAccessDeniedException('error.notfound.character');
		}
		if ($character->getBattling()) {
			throw $this->createAccessDeniedException('error.noaccess.battling');
		}
		if ($character->getUser() != $user) {
			throw $this->createAccessDeniedException('error.noaccess.character');
		}
		# Make sure this character can return from retirement. This function will throw an exception if the given character has not been retired for a week.
		$this->get('character_manager')->checkReturnability($character);

		$user->setCurrentCharacter($character);

		// time-based action resolution
		$this->get('action_resolution')->progress();

		if ($user->getLimits() === null) {
			$this->get('user_manager')->createLimits($user);
		}

		$this->get('appstate')->setSessionData($character);
		switch ($logic) {
			case 'play':
				$character->setLastAccess(new \DateTime("now"));
				$character->setSlumbering(false);
				if ($character->getSystem() == 'procd_inactive') {
					$character->setSystem(NULL);
				}
				$em->flush();
				if ($character->getSpecial()) {
					// special menu active - check for reasons
					if ($character->getDungeoneer() && $character->getDungeoneer()->isInDungeon()) {
						return $this->redirectToRoute('bm2_dungeon_dungeon_index');
					}
				}
				return $this->redirectToRoute('bm2_recent');
				break;
			case 'placenew':
				$character->setLastAccess(new \DateTime("now"));
				$character->setSlumbering(false);
				if ($character->getSystem() == 'procd_inactive') {
					$character->setSystem(NULL);
				}
				$em->flush();
				return $this->redirectToRoute('maf_character_start', array('logic'=>'new'));
				break;
			case 'viewhist':
				if ($character->getList() < 100 ) {
					// move to historic list now that we've looked at his final days
					$character->setList(100);
				}
				$em->flush();
				return $this->redirectToRoute('bm2_eventlog', array('id'=>$character->getLog()->getId()));
				break;
			case 'newbackground':
				$character->setLastAccess(new \DateTime("now"));
				$character->setSlumbering(false);
				if ($character->getSystem() == 'procd_inactive') {
					$character->setSystem(NULL);
				}
				$em->flush();
				return $this->redirectToRoute('bm2_site_character_background', ['id'=>$character->getId(), 'starting'=>'1']);

			case 'edithist':
				$em->flush();
				/* I don't have words for how stupid I think this is.
				Apparently, if you don't flush after setting session data, the game has no idea which character you're trying to edit the background of.
				Which is super odd to me, because session data doesn't involve the database... --Andrew, 20180213 */
				return $this->redirectToRoute('bm2_site_character_background');
				break;
			case 'unretire':
				# This should look a lot like 'placenew' above, because it's a very similar process ;) --Andrew, 20180213
				$character->setLastAccess(new \DateTime("now"));
				$character->setSlumbering(false);
				if ($character->getSystem() == 'procd_inactive') {
					$character->setSystem(NULL);
				}
				$em->flush();
				return $this->redirectToRoute('maf_character_start', array('logic'=>'retired'));
				break;
			default:
				throw new AccessDeniedHttpException('error.notfound.playlogic');
				return $this->redirectToRoute('bm2_characters');
				break;
		}
	}

	/**
	  * @Route("/familytree")
	  */
	public function familytreeAction() {
		$descriptorspec = array(
			0 => array("pipe", "r"),  // stdin
			1 => array("pipe", "w"),  // stdout
			2 => array("pipe", "w") // stderr
		);

		$process = proc_open('dot -Tsvg', $descriptorspec, $pipes, '/tmp', array());

		if (is_resource($process)) {
			$dot = $this->renderView('Account/familytree.dot.twig', array('characters'=>$this->getUser()->getNonNPCCharacters()));

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


	/**
	  * @Route("/familytree.json", defaults={"_format"="json"})
	  */
	public function familytreedataAction() {
		$user = $this->getUser();

		// FIXME: broken for non-same-user characters - but we want to allow them!
		$nodes = array();
		foreach ($user->getCharacters() as $character) {
			$group = $character->getGeneration();
			$nodes[] = array('id'=>$character->getId(), 'name'=>$character->getName(), 'group'=>$character->getGeneration());
		}

		$links = array();
		foreach ($user->getCharacters() as $character) {
			if (!$character->getChildren()->isEmpty()) {
				$parent_id = $this->node_find($character->getId(), $nodes);
				foreach ($character->getChildren() as $child) {
					$child_id = $this->node_find($child->getId(), $nodes);
					$links[] = array('source'=>$parent_id, 'target'=>$child_id,'value'=>1);
				}
			}
		}

		return $this->render('Account/familytreedata.json.twig', [
			'tree' => [
				'nodes'=>$nodes,
				'links'=>$links
			]
		]);
	}

	private function node_find($id, $data) {
		$index=0;
		foreach ($data as $d) {
			if ($d['id']==$id) return $index;
			$index++;
		}
		return false;
	}

	/**
	  * @Route("/keys", name="maf_keys")
	  */
	public function keysAction() {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_TOS')) {
			throw new AccessDeniedException('error.banned.tos');
		}
		$user = $this->getUser();
		if ($user->getKeys()->count() === 0) {
			$em = $this->getDoctrine()->getManager();
			$valid = false;
			$i = 0;
			while (!$valid && $i < 10) {
                                $token = bin2hex(random_bytes($length));
                                $result = $em->getRepository(User::class)->findOneBy(['user'=>$user->getId(), 'token' => $token]);
                                if (!$result) {
                                        $valid = true;
                                } else {
					$i++;
				}
                        }
			$key = new AppKey;
			$em->persist($key);
			$key->setUser($user);
			$key->setToken($token);
			$em->flush();
		}

		return $this->render('Account/keys.html.twig', [
			'keys' => $user->getKeys(),
		]);
	}

	/**
	  * @Route("/key/{key}/reset", name="maf_key_reset", requirements={"key"="\d+"})
	  */
	public function keyResetAction(AppKey $key) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_TOS')) {
			throw new AccessDeniedException('error.banned.tos');
		}
		$user = $this->getUser();
		if ($user->getKeys()->containes($key)) {
			$em = $this->getDoctrine()->getManager();
			$valid = false;
			while (!$valid) {
                                $token = bin2hex(random_bytes(32));
                                $result = $em->getRepository(User::class)->findOneBy(['user'=>$user->getId(), 'token' => $token]);
                                if (!$result) {
                                        $valid = true;
                                }
			}
			$key->setToken($token);
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('account.key.reset.success', [], "communication"));
		} else {
			$this->addFlash('notice', $this->get('translator')->trans('account.key.unauthorized', [], "communication"));
		}
		return $this->redirectToRoute('maf_keys');
	}

	/**
	  * @Route("/key/{key}/delete", name="maf_key_reset", requirements={"key"="\d+"})
	  */
	public function keyDeleteAction(AppKey $key) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_TOS')) {
			throw new AccessDeniedException('error.banned.tos');
		}
		$user = $this->getUser();
		if ($user->getKeys()->containes($key)) {
			$em = $this->getDoctrine()->getManager();
			$em->remove($key);
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('account.key.delete.success', [], "communication"));
		} else {
			$this->addFlash('notice', $this->get('translator')->trans('account.key.unauthorized', [], "communication"));
		}
		return $this->redirectToRoute('maf_keys');
	}

	/**
	  * @Route("/key/new", name="maf_key_reset", requirements={"key"="\d+"})
	  */
	public function keyNewAction() {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_TOS')) {
			throw new AccessDeniedException('error.banned.tos');
		}
		$user = $this->getUser();
		$valid = false;
		$i = 0;
		if ($user->getKeys()->contains() > 10) {
			$this->addFlash('notice', $this->get('translator')->trans('account.key.toomany', [], "communication"));
		} else {
			while (!$valid && $i < 10) {
	                        $token = bin2hex(random_bytes(32));
	                        $result = $em->getRepository(User::class)->findOneBy(['user'=>$user->getId(), 'token' => $token]);
				if (!$result) {
					$valid = true;
				} else {
					$i++;
				}
	                }
			if ($valid) {
				$em = $this->getDoctrine()->getManager();
				$key = new AppKey;
				$em->persist($key);
				$key->setUser($user);
				$key->setToken($token);
				$em->flush();
				$this->addFlash('notice', $this->get('translator')->trans('account.key.reset.success', [], "communication"));
			} else {
				$this->addFlash('notice', $this->get('translator')->trans('account.key.reset.fail', [], "communication"));
			}
		}
		return $this->redirectToRoute('maf_keys');
	}

}
