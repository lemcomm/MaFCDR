<?php

namespace BM2\DungeonBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use BM2\SiteBundle\Entity\Action;

use BM2\DungeonBundle\Form\ChatType;
use BM2\DungeonBundle\Form\CardSelectType;
use BM2\DungeonBundle\Form\TargetSelectType;

use BM2\DungeonBundle\Entity\Dungeon;
use BM2\DungeonBundle\Entity\Dungeoneer;
use BM2\DungeonBundle\Entity\DungeonLevel;
use BM2\DungeonBundle\Entity\DungeonMonster;
use BM2\DungeonBundle\Entity\DungeonTreasure;

/**
 * @Route("/dungeon")
 */
class DungeonController extends Controller {

	private function gateway($check_in_dungeon = true) {
		$character = $this->get('appstate')->getCharacter();
		$dungeoneer = $this->get('dungeon_master')->getcreateDungeoneer($character);
		if ($check_in_dungeon && !$dungeoneer->isInDungeon()) {
			throw $this->createNotFoundException("dungeons::error.notin");
		}
		return $dungeoneer;
	}

	/**
	  * @Route("/")
	  * @Template
	  */
	public function indexAction() {
		$dungeoneer = $this->gateway();

		$dungeon = $dungeoneer->getCurrentDungeon();
		list($party, $missing, $wait) = $this->get('dungeon_master')->calculateTurnTime($dungeon);
		$timeleft = max(0, $wait-$dungeon->getTick());

		$chat = $this->createForm(new ChatType);
		$cardselect = $this->createForm(new CardSelectType);

		$target_monster=false;
		$target_treasure=false;
		$target_dungeoneer=false;
		if ($dungeoneer->getCurrentAction()) {
			$type = $dungeoneer->getCurrentAction()->getType();
			$level = $dungeoneer->getParty()->getCurrentLevel();

			if ($type->getTargetMonster()) {
				$target_monster = $this->MonsterTargetSelector($level, $dungeoneer->getTargetMonster(), $type->getMonsterClass());
			}
			if ($type->getTargetTreasure()) {
				$target_treasure = $this->TreasureTargetSelector($level, $dungeoneer->getTargetTreasure());
			}
			if ($type->getTargetDungeoneer()) {
				$target_dungeoneer = $this->DungeoneerTargetSelector($dungeon, $dungeoneer->getTargetDungeoneer());
			}
		}

		return array(
			'party' => $party,
			'missing' => $missing,
			'wait' => $wait,
			'timeleft' => $timeleft,
			'me' => $dungeoneer,
			'dungeon' => $dungeon,
			'cards' => $dungeoneer->getCards(),
			'messages' => $dungeon->getParty()->getMessages()->slice(0, 5),
			'events' => $dungeon->getParty()->getEvents()->slice(-25, 25),
			'chat' => $chat->createView(),
			'cardselect' => $cardselect->createView(),
			'target_monster' => $target_monster?$target_monster->createView():false,
			'target_treasure' => $target_treasure?$target_treasure->createView():false,
			'target_dungeoneer' => $target_dungeoneer?$target_dungeoneer->createView():false,
		);
	}

	/**
	  * @Route("/enter/{dungeon}", requirements={"dungeon"="\d+"})
	  * @Template
	  */
	public function enterAction(Dungeon $dungeon) {
		$dungeoneer = $this->gateway(false);
		if ($dungeoneer->isInDungeon()) {
			throw new AccessDeniedHttpException("dungeons::error.already");
		}

		$dungeons = $this->get('geography')->findDungeonsInActionRange($dungeoneer->getCharacter());
		foreach ($dungeons as $d) {
			if ($d['dungeon'] == $dungeon) {
				$check = $this->get('dungeon_master')->joinDungeon($dungeoneer, $dungeon);
				if ($check===true) {
					$act = new Action;
					$act->setType('dungeon.explore')->setCharacter($dungeoneer->getCharacter());
					$act->setBlockTravel(true);
					$act->setCanCancel(false);
					$result = $this->get('action_manager')->queue($act);
					$dungeoneer->getCharacter()->setSpecial(true); // turn on the special navigation menu
					$this->getDoctrine()->getManager()->flush();
					return $this->redirectToRoute('bm2_dungeon_dungeon_index');
				} else {
					return array('reason'=>$check);
				}
			}
		}
		throw $this->createNotFoundException("dungeon not found or not in action range");
	}

	/**
	  * @Route("/chat")
	  * @Template
	  */
	public function chatAction(Request $request) {
		$dungeoneer = $this->gateway();

		$chat = $this->createForm(new ChatType);
		$chat->handleRequest($request);
		if ($chat->isValid()) {
			$msg = $chat->getData();
			if (strlen($msg->getContent())>200) {
				$chat->addError(new FormError("chat.long"));
			} else {
				$msg->setSender($dungeoneer);
				$msg->setTs(new \DateTime("now"));
				$msg->setParty($dungeoneer->getParty());
				$dungeoneer->getParty()->addMessage($msg);

				$em = $this->getDoctrine()->getManager();
				$em->persist($msg);
				$em->flush();
				$chat = $this->createForm(new ChatType);
			}
			return $this->redirectToRoute('bm2_dungeon_dungeon_index');
		}

		return array(
			'dungeon' => $dungeoneer->getCurrentDungeon(),
			'messages' => $dungeoneer->getParty()->getMessages(),
			'chat' => $chat->createView()
		);
	}

	/**
	  * @Route("/events")
	  * @Template
	  */
	public function eventsAction(Request $request) {
		$dungeoneer = $this->gateway();

		return array(
			'dungeon' => $dungeoneer->getCurrentDungeon(),
			'events' => $dungeoneer->getParty()->getEvents(),
		);
	}

	/**
	  * @Route("/cardselect")
	  * @Template
	  */
	public function cardselectAction(Request $request) {
		$dungeoneer = $this->gateway();
		$dungeon = $dungeoneer->getCurrentDungeon();

		$cardselect = $this->createForm(new CardSelectType);
		$cardselect->handleRequest($request);
		if ($cardselect->isValid()) {
			$data = $cardselect->getData();
			$card_id = $data['card'];
			$em = $this->getDoctrine()->getManager();

			foreach ($dungeoneer->getCards() as $card) {
				if ($card->getId() == $card_id) {
					if (!$dungeon->getCurrentLevel() && $card->getType()->getName()=='basic.leave') {
						// leaving before the dungeon started...
						$this->get('dungeon_master')->exitDungeon($dungeoneer,0,0);
						if ($dungeoneer->isInDungeon()) {
							$this->get('logger')->error('leaving dungeon failed for dungeoneer #'.$dungeoneer->getId().' - still in '.$dungeoneer->getCurrentDungeon()->getId());
						}
						$em->flush();
						return $this->redirectToRoute('bm2_recent');
					} else {
						if ($dungeoneer->getCurrentAction()) {
							$dungeoneer->getCurrentAction()->setPlayed($dungeoneer->getCurrentAction()->getPlayed()-1);
						}
						$card->setPlayed($card->getPlayed()+1);
						$dungeoneer->setCurrentAction($card);
						$dungeoneer->setTargetMonster(null);
						$dungeoneer->setTargetTreasure(null);
						$dungeoneer->setTargetDungeoneer(null);
					}
				}
			}
			$em->flush();
		}

		return $this->redirectToRoute('bm2_dungeon_dungeon_index');
	}

	/**
	  * @Route("/target/{type}")
	  * @Template
	  */
	public function targetAction($type, Request $request) {
		$dungeoneer = $this->gateway();
		$dungeon = $dungeoneer->getCurrentDungeon();

		switch ($type) {
			case 'monster':		$target = $this->MonsterTargetSelector($dungeon->getCurrentLevel()); break;
			case 'treasure':	$target = $this->TreasureTargetSelector($dungeon->getCurrentLevel()); break;
			case 'dungeoneer':	$target = $this->DungeoneerTargetSelector($dungeon); break;
			default:			throw $this->createNotFoundException("invalid target request");
		}
		$target->handleRequest($request);
		if ($target->isValid()) {
			$data = $target->getData();
			$em = $this->getDoctrine()->getManager();

			switch ($data['type']) {
				case 'monster':
					if ($data['target']==0) {
						$dungeoneer->setTargetMonster(null);
					} else {
						$monster = $em->getRepository('DungeonBundle:DungeonMonster')->find($data['target']);
						if (!$monster) {
							throw $this->createNotFoundException("monster #".$data['target']." not found");
						}
						if ($monster->getLevel() != $dungeon->getCurrentLevel()) {
							throw $this->createNotFoundException("monster #".$data['target']." not part of this dungeon level");
						}
						$dungeoneer->setTargetMonster($monster);
					}
					break;
				case 'treasure':
					if ($data['target']==0) {
						$dungeoneer->setTargetTreasure(null);
					} else {
						$treasure = $em->getRepository('DungeonBundle:DungeonTreasure')->find($data['target']);
						if (!$treasure) {
							throw $this->createNotFoundException("treasure #".$data['target']." not found");
						}
						if ($treasure->getLevel() != $dungeon->getCurrentLevel()) {
							throw $this->createNotFoundException("treasure #".$data['target']." not part of this dungeon level");
						}
						$dungeoneer->setTargetTreasure($treasure);
					}
					break;
				case 'dungeoneer':
					if ($data['target']==0) {
						$dungeoneer->setTargetDungeoneer(null);
					} else {
						$dungeoneer = $em->getRepository('DungeonBundle:Dungeoneer')->find($data['target']);
						if (!$dungeoneer) {
							throw $this->createNotFoundException("dungeoneer #".$data['target']." not found");
						}
						if ($dungeoneer->getCurrentDungeon() != $dungeon) {
							throw $this->createNotFoundException("dungeoneer #".$data['target']." not in this dungeon");
						}
						$dungeoneer->setTargetDungeoneer($dungeoneer);
					}
					break;
			}
			$em->flush();
		}

		return $this->redirectToRoute('bm2_dungeon_dungeon_index');
	}




	private function MonsterTargetSelector(DungeonLevel $level=null, DungeonMonster $current=null, $class=null) {
		$choices = array(0=>$this->get('translator')->trans('target.random', array(), "dungeons"));
		if ($level) {
			if ($level->getScoutLevel() > 1) {
				$valid = false;
				foreach ($level->getMonsters() as $monster) if ($monster->getAmount()>0 && (($class==null || $class=='') || in_array($class, $monster->getType()->getClass()))) {
					$valid = true;
					$size = $this->get('translator')->trans('size.'.$monster->getSize(), array(), "dungeons");
					$type = $this->get('translator')->transChoice('monster.'.$monster->getType()->getName(), $monster->getAmount(), array(), "dungeons");
					$choices[$monster->getId()] = $this->get('translator')->trans('target.monster', array("%amount%" => $monster->getAmount(), "%size%" =>  $size, "%type%" => $type), "dungeons");
				}
				if (!$valid) {
					$choices = array(0=>$this->get('translator')->trans('target.invalid', array(), "dungeons"));
				}
			} elseif ($level->getScoutLevel() > 0) {
				foreach ($level->getMonsters() as $monster) {
					$choices[$monster->getId()] = $this->get('translator')->trans('target.nr.monster', array("%nr%"=>$monster->getNr()), "dungeons");
				}
			}
		}
		return $this->createForm(new TargetSelectType('monster', $choices, $current?$current->getId():false));
	}

	private function TreasureTargetSelector(DungeonLevel $level=null, DungeonTreasure $current=null) {
		$choices = array(0=>$this->get('translator')->trans('target.random', array(), "dungeons"));
		if ($level) {
			if ($level->getScoutLevel() > 2) {
				foreach ($level->getTreasures() as $treasure) if ($treasure->getValue()>0) {
					$choices[$treasure->getId()] = $this->get('translator')->trans('target.nr.treasure', array("%nr%"=>$treasure->getNr()), "dungeons");
				}
			}
		}
		return $this->createForm(new TargetSelectType('treasure', $choices, $current?$current->getId():false));
	}

	private function DungeoneerTargetSelector(Dungeon $dungeon, Dungeoneer $current=null) {
		$choices = array(0=>$this->get('translator')->trans('target.random', array(), "dungeons"));
		foreach ($dungeon->getParty()->getMembers() as $dungeoneer) {
			$choices[$dungeoneer->getId()] = $dungeoneer->getCharacter()->getName();
		}
		return $this->createForm(new TargetSelectType('dungeoneer', $choices, $current?$current->getId():false));
	}

}
