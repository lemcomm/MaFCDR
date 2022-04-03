<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Character;

use BM2\SiteBundle\Form\AreYouSureType;

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

        /**
          * @Route("/duel/challenge", name="maf_activity_duel_challenge")
          */

        public function duelChallengeAction(Request $request) {
                $character = $this->get('dispatcher')->gateway('activityDuelChallengeTest');
                if (! $character instanceof Character) {
                        return $this->redirectToRoute($character);
                }
                $em = $this->getDoctrine()->getManager();

                return $this->render('Activity/duelChallenge.html.twig', [
                        'form' => $form,
                ]);
        }

	/**
	  * @Route("/train/{skill}", name="maf_train_skill", requirements={"id"="[A-Za-z_-]+"})
	  */

	public function trainSkillAction($skill) {
                $character = $this->get('activity_dispatcher')->gateway('activityTrainTest', null, null, null, $skill);
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
