<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Quest;
use BM2\SiteBundle\Entity\Quester;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Form\QuestType;
use BM2\SiteBundle\Service\History;

use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;


/**
 * @Route("/quests")
 */
class QuestsController extends Controller {

	/**
	  * @Route("/local")
	  */
	public function localQuestsAction() {
		$character = $this->get('dispatcher')->gateway('locationQuestsTest', false, false);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$geo = $this->get('geography')->findMyRegion($character);
		$settlement = $geo->getSettlement();

		return $this->render('Quests/localQuests.html.twig', [
			'quests'=>$settlement->getQuests()
		]);
	}

	/**
	  * @Route("/my")
	  */
	public function myQuestsAction() {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Quests/myQuests.html.twig', [
			'my_quests'=>$character->getQuestings(),
			'owned_quests'=>$character->getQuestsOwned()
		]);
	}


	/**
	  * @Route("/details/{id}", requirements={"id"="\d+"})
	  */
	public function detailsAction(Quest $id) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$quest = $id;

		$metas = $em->getRepository('BM2SiteBundle:EventMetadata')->findBy(array('log'=>$quest->getLog(), 'reader'=>$character));
		if ($metas) {
			foreach ($metas as $meta) {
				$meta->setLastAccess(new \DateTime('now'));
			}
		}
		$em->flush();

		return $this->render('Quests/details.html.twig', [
			'quest'=>$quest,
			'metas'=>$metas
		]);
	}

	/**
	  * @Route("/create/{settlement}", requirements={"settlement"="\d+"})
	  * @Template
	  */
	public function createAction(Settlement $settlement, Request $request) {
		$character = $this->get('dispatcher')->gateway('locationQuestsTest', false, false);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$quest = new Quest;
		$form = $this->createForm(new QuestType(), $quest);
		$form->handleRequest($request);
		if ($form->isValid()) {
			$quest->setCompleted(false);
			$quest->setNotes('');
			$quest->setHome($settlement);
			$quest->setOwner($character);
			$em = $this->getDoctrine()->getManager();
			$em->persist($quest);
			$em->flush();

			$this->get('history')->logEvent(
				$quest,
				'event.quest.created',
				array(),
				History::MEDIUM, true
			);

			$this->get('history')->openLog($quest, $character);
			$em->flush();

			return $this->redirectToRoute('bm2_site_settlement_quests', array('id'=>$settlement->getId()));
		}

		return $this->render('Quests/create.html.twig', [
			'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/join/{quest}", requirements={"id"="\d+"})
	  */
	public function joinAction(Quest $quest) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		foreach ($quest->getQuesters() as $q) {
			if ($q->getCharacter() == $character) {
				throw new \Exception("You are already on this quest.");
			}
		}
		$quester = new Quester;
		$quester->setCharacter($character);
		$quester->setQuest($quest);
		$quester->setStarted($this->get('appstate')->getCycle());
		$quester->setOwnerComment('')->setQuesterComment('');
		$em->persist($quester);

		$quest->addQuester($quester);

		$this->get('history')->logEvent(
			$quest,
			'event.quest.started',
			array("%link-character%"=>$character->getId()),
			History::LOW, true
		);

		$em->flush();

		// TODO: flash message

		return $this->redirectToRoute('bm2_site_quests_details', array('id'=>$quest->getId()));
	}

	/**
	  * @Route("/leave/{quest}", requirements={"id"="\d+"})
	  */
	public function leaveAction(Quest $quest) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		foreach ($quest->getQuesters() as $q) {
			if ($q->getCharacter() == $character) {
				$quest->removeQuester($q);
				$em->remove($q);
			}
		}

		$this->get('history')->logEvent(
			$quest,
			'event.quest.abandoned',
			array("%link-character%"=>$character->getId()),
			History::LOW, true
		);

		$em->flush();

		// TODO: flash message

		return $this->redirectToRoute('bm2_site_quests_details', array('id'=>$quest->getId()));
	}

	/**
	  * @Route("/completed/{quest}", requirements={"id"="\d+"})
	  */
	public function completedAction(Quest $quest) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		foreach ($quest->getQuesters() as $q) {
			if ($q->getCharacter() == $character) {
				$q->setClaimCompleted($this->get('appstate')->getCycle());
			}
		}

		$this->get('history')->logEvent(
			$quest,
			'event.quest.completed',
			array("%link-character%"=>$character->getId()),
			History::LOW, true
		);

		$em->flush();

		// TODO: flash message

		return $this->redirectToRoute('bm2_site_quests_details', array('id'=>$quest->getId()));
	}

	/**
	  * @Route("/confirm/{quester}", requirements={"id"="\d+"})
	  */
	public function confirmAction(Quester $quester) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		if ($quester->getQuest()->getOwner() != $character) {
			throw new \Exception("You are not the owner of this quest.");
		}

		$quester->setConfirmedCompleted($this->get('appstate')->getCycle());
		$quester->getQuest()->setCompleted(true);

		$this->get('history')->logEvent(
			$quester->getQuest(),
			'event.quest.confirmed',
			array("%link-character%"=>$quester->getCharacter()->getId()),
			History::HIGH, true
		);

		$em->flush();

		// TODO: flash message

		return $this->redirectToRoute('bm2_site_quests_details', array('id'=>$quester->getQuest()->getId()));
	}

	/**
	  * @Route("/reject/{quester}", requirements={"id"="\d+"})
	  */
	public function rejectAction(Quester $quester) {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		if ($quester->getQuest()->getOwner() != $character) {
			throw new \Exception("You are not the owner of this quest.");
		}

		$quester->setConfirmedCompleted(-1);

		$this->get('history')->logEvent(
			$quester->getQuest(),
			'event.quest.rejected',
			array("%link-character%"=>$quester->getCharacter()->getId()),
			History::MEDIUM, true
		);

		$em->flush();

		// TODO: flash message

		return $this->redirectToRoute('bm2_site_quests_details', array('id'=>$quester->getQuest()->getId()));
	}


}
