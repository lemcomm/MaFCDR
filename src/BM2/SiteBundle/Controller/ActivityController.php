<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Activity;
use BM2\SiteBundle\Entity\ActivityReport;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\EquipmentType;

use BM2\SiteBundle\Form\ActivitySelectType;
use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\EquipmentLoadoutType;
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

		$opts = $em->getRepository('BM2SiteBundle:EquipmentType')->findBy(['type'=>'weapon']);

		$form = $this->createForm(new ActivitySelectType('duel', $this->get('geography')->calculateInteractionDistance($char), $char, $opts));
		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
                        $type = $em->getRepository('BM2SiteBundle:ActivityType')->findOneBy(['name'=>'duel']);
                        $data = $form->getData();
                        $duel = $this->get('activity_manager')->createDuel($char, $data['target'], $data['name'], $data['context'], $data['sameWeapon'], $data['weapon'], $data['weaponOnly']);
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

                $query = $em->createQuery('SELECT a, p FROM BM2SiteBundle:Activity a JOIN a.participants p WHERE p.character = :char AND p.accepted = :acc');
		$query->setParameters(['char'=>$char, 'acc'=>false]);
		$duels = $query->getResult();

		return $this->render('Activity/duelAnswer.html.twig', [
                     'duels'=>$duels,
		     'char'=>$char
		]);
	}

	/**
	  * @Route("/duel/accept/{act}", name="maf_activity_duel_accept", requirements={"act"="\d+"})
	  */

	public function duelAcceptAction(Request $request, Activity $act) {
		$char = $this->gateway('activityDuelAcceptTest', $act);
		if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
		}
		foreach ($act->getParticipants() as $p) {
			if ($p->getCharacter() !== $char) {
				$them = $p;
			}
			if ($p->getCharacter() === $char) {
				$me = $p;
			}
		}
		$em = $this->getDoctrine()->getManager();

		if ($me === $act->findChallenged()) {
			if ($act->getSame()) {
				# Same weapon, we accept. Standard duel. Set Ready and Accepted.
				$me->setAccepted(true);
				$act->setReady(true);
				$em->flush();
				$this->addFlash('notice', $this->get('translator')->trans('duel.answer.accepted', ['%target%'=>$them->getCharacter()->getName()]));
				return $this->redirectToRoute('bm2_actions');
			} else {
				# Different weapons, we select ours, then they accept duel. No Act->setReady here.
				$opts = $em->getRepository('BM2SiteBundle:EquipmentType')->findBy(['type'=>'weapon']);
				$form = $this->createForm(new EquipmentLoadoutType($opts, 'loadout.weapon', 'settings'));
				$form->handleRequest($request);
				if ($form->isSubmitted() && $form->isValid()) {
					$me->setWeapon($form->getData()['equipment']);
					$me->setAccepted(true);
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('duel.answer.accepted', ['%target%'=>$them->getCharacter()->getName()]));
					return $this->redirectToRoute('bm2_actions');
				}
				return $this->render('Activity/duelAccept.html.twig', [
					'form' => $form->createView(),
					'them' => $them,
					'duel' => $act
				]);
			}
		} else {
			# We're accepting their weapon choice. Set ready and accepted.
			$me->setAccepted(true);
			$act->setReady(true);
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('duel.answer.accepted2', ['%target%'=>$them->getCharacter()->getName()]));
			return $this->redirectToRoute('bm2_actions');
		}
	}

	/**
	  * @Route("/duel/refuse/{act}", name="maf_activity_duel_refuse", requirements={"act"="\d+"})
	  */

	public function duelRefuseAction(Activity $act) {
		$char = $this->gateway('activityDuelRefuseTest', $act);
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
		$this->addFlash('notice', $this->get('translator')->trans('duel.answer.refused', ['%target%'=>$them->getCharacter()->getName()]));
		return $this->redirectToRoute('bm2_actions');
	}

	/**
	  * @Route("/activity/report/{report}", name="maf_activity_report", requirements={"report"="\d+"})
	  */

        public function activityReport(ActivityReport $report) {
		$char = $this->get('appstate')->getCharacter(true,true,true);
		if (! $char instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$check = false;
		if (!$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
			$check = $report->checkForObserver($char);
			$admin = false;
		} else {
			$check = true;
			$admin = true;
		}

		if ($report->getPlace()) {
			$place = $report->getPlace();
			$settlement = $place->getSettlement();
			$inside = true;
		} elseif ($report->getSettlement()) {
			$place = false;
			$settlement = $report->getSettlement();
			$inside = true;
		} else {
			$place = false;
			$settlement = $report->getGeoData()->getSettlement();
			$inside = false;
		}
		foreach ($report->getCharacters() as $group) {
			$totalRounds = $group->getStages()->count();
			break;
		}

		return $this->render('Activity/viewReport.html.twig', ['report'=>$report, 'place'=>$place, 'settlement'=>$settlement, 'inside'=>$inside, 'access'=>$check, 'admin'=>$admin, 'roundcount'=>$totalRounds]);
        }

	/**
	  * @Route("/train/{skill}", name="maf_train_skill", requirements={"id"="[A-Za-z_-]+"})
	  */

	public function trainSkillAction($skill) {
		$character = $this->gateway('activityTrainTest', $skill);
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
