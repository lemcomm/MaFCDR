<?php

namespace BM2\SiteBundle\Controller;

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
}
