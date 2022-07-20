<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\EquipmentType;

use BM2\SiteBundle\Form\ActivitySelectType;
use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\InteractionType;

use BM2\SiteBundle\Service\GameRequestManager;
use BM2\SiteBundle\Service\History;
use BM2\SiteBundle\Service\MilitaryManager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ActivityController extends Controller {

	private function gateway($test, $secondary = null) {
		return $this->get('activity_dispatcher')->gateway($test, null, true, false, $secondary);
	}

	/**
	  * @Route("/duel/challenge", name="maf_activity_duel_challenge")
	  */

	public function duelChallengeAction(Request $request) {
		$char = $this->gateway('activityDuelChallengeTest');
		if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
		}
		$em = $this->getDoctrine()->getManager();

                $query = $em->createQuery('SELECT e FROM BM2SiteBundle:EquipmentType e WHERE e.skill IS NOT NULL AND e.type = :type');
                $query->setParameters(['type'=>'weapon']);

		$form = $this->createForm(new ActivitySelectType('duel', $this->get('geography')->calculateInteractionDistance($char), $char, $query->getResult()));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
                        $type = $em->getRepository('BM2SiteBundle:ActivityType')->findOneBy(['name'=>'duel']);
                        $data = $form->getData();
                        $duel = $this->get('activity_manager')->createDuel($char, $data['target'], $data['name'], $data['sameWeapon'], $data['weapon']);
                        if ($duel instanceof Activity) {
                                $this->addFlash('notice', $this->get('translator')->trans('duel.challenge.sent', ['%target%'=>$data['target']->getName()], 'activity'));
                		return $this->redirectToRoute('bm2_actions');
                        } else {
                                $this->addFlash('error', $this->get('translator')->trans('duel.challenge.unsent', array(), 'activity'));
                        }
		}

		return $this->render('Activity/duelChallenge.html.twig', [
                      'form' => $form->createView(),
		]);
	}

	/**
	  * @Route("/duel/answer", name="maf_activity_duel_answer")
	  */

	public function duelAnswerAction(Request $request) {
		$char = $this->gateway('activityDuelAnswerTest');
		if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
		}
		$em = $this->getDoctrine()->getManager();

                $query = $em->createQuery('SELECT a, p, b FROM BM2SiteBundle:Acitivity a JOIN a.participants p WHERE p.character = :char AND a.accepted = :acc');
		$query->setParameters(['char'=>$char, 'acc'=>false]);
		$duels = $query->getResult();

		return $this->render('Activity/duelAnswer.html.twig', [
                     'duels'=>$duels
		]);
	}

	/**
	  * @Route("/duel/accept/{act}", name="maf_activity_duel_accept")
	  */

	public function duelAcceptAction(Activity $act) {
		$char = $this->gateway('activityDuelAcceptOrRefuseTest', null, null, null, $act);
		if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
		}
		foreach ($act->getParticipants() as $p) {
			if ($p !== $char) {
				$them = $p;
				break; #For duels this is either us first or us second, so this can save us a worthless loop pass.
			}
		}

		$act->getBouts()->first()->setAccepted(true); #Duels only have one and the dispatcher has already validated.
		$em = $this->getDoctrine()->getManager()->flush();
		$this->addFlash('notice', $this->get('translator')->trans('duel.answer.accepted', ['%target%'=>$them->getName()]));
		return $this->redirectToRoute('maf_activity_duel_answer');
	}

	/**
	  * @Route("/duel/refuse/{act}", name="maf_activity_duel_refuse")
	  */

	public function duelRefuseAction(Activity $act) {
		$char = $this->gateway('activityDuelAcceptOrRefuseTest', null, null, null, $act);
		if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
		}
		foreach ($act->getParticipants() as $p) {
			if ($p !== $char) {
				$them = $p;
				break;
			}
		}

		$this->get('activity_manager')->refuseDuel($act); # Delete the activity, basically. ActMan flushes.
		$this->addFlash('notice', $this->get('translator')->trans('duel.answer.refused', ['%target%'=>$them->getName()]));
		return $this->redirectToRoute('maf_activity_duel_answer');
	}

	/**
	  * @Route("/duel/{report}", name="maf_duel_report", requirements={"report"="\d+"})
	  */

        public function activityReport(ActivityReport $report) {

        }

	/**
	  * @Route("/train/{skill}", name="maf_train_skill", requirements={"id"="[A-Za-z_-]+"})
	  */

	public function trainSkillAction($skill) {
		$character = $this->gateway('activityTrainTest', null, null, null, $skill);
		if (! $character instanceof Character) {
                        return $this->redirectToRoute($character);
                }
                if ($character->findActions('train.skill')->count() > 0) {
                        # Auto cancel duplicate actions.
                        foreach ($character->findActions('train.skill') as $each) {
                                $em = $this->getDoctrine()->getManager();
                                $em->remove($each);
                        }
                        $em->flush();
                        $this->addFlash('notice', $this->get('translator')->trans('train.noduplicate', array(), 'activity'));
                }
                $type = $this->getDoctrine()->getManager()->getRepository('BM2SiteBundle:SkillType')->findOneBy(['name'=>$skill]);
                if ($type) {
                        $act = new Action;
                        $act->setType('train.skill');
                        $act->setCharacter($character);
                        $act->setBlockTravel(false);
                        $act->setCanCancel(true);
                        $act->setTargetSkill($type);
                        $act->setHourly(false);
                        $result = $this->get('action_manager')->queue($act); #Includes a flush.
                        $this->addFlash('notice', $this->get('translator')->trans('train.'.$skill.'.success', array(), 'activity'));
		} else {
		$this->addFlash('notice', $this->get('translator')->trans('train.'.$skill.'.notfound', array(), 'activity'));
		}

		return $this->redirectToRoute('bm2_actions');
	}
}
