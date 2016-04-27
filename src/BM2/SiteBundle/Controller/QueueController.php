<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Service\History;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


/**
 * @Route("/queue")
 */
class QueueController extends Controller {
	 
	/**
	  * @Route("/")
	  * @Template
	  */
	public function manageAction() {
		$character = $this->get('dispatcher')->gateway();

		return array("queue" => $character->getActions(), "now" => new \DateTime("now"));
	}

	/**
	  * @Route("/details/{id}", name="bm2_actiondetails", requirements={"id"="\d+"})
	  * @Template
	  */
	public function detailsAction($id) {
		$character = $this->get('dispatcher')->gateway();

		$em = $this->getDoctrine()->getManager();
		$action = $em->getRepository('BM2SiteBundle:Action')->find($id);
		if (!$action) {
			throw $this->createNotFoundException('error.notfound.action');
		}
		if ($action->getCharacter() != $character) {
			$can_see = false;
			foreach ($action->getSupportingActions() as $support) {
				if ($support->getCharacter() == $character) { $can_see = true; }
			}
			if (!$can_see) foreach ($action->getOpposingActions() as $oppose) {
				if ($oppose->getCharacter() == $character) { $can_see = true; }
			}
			if (!$can_see) {
				throw $this->createNotFoundException('error.notfound.action');
			}
		}

		return array("action" => $action, "now" => new \DateTime("now"));
	}


	/**
	  * @Route("/battle/{id}", name="bm2_battle", requirements={"id"="\d+"})
	  * @Template
	  */
	public function battleAction($id) {
		$character = $this->get('dispatcher')->gateway();

		$em = $this->getDoctrine()->getManager();
		$battle = $em->getRepository('BM2SiteBundle:Battle')->find($id);
		if (!$battle) {
			throw $this->createNotFoundException('error.notfound.battle');
		}
		// TODO: verify that we are a participant in this battle

		if ($battle->getSettlement()) {
			$location = array('key'=>'battle.location.of', 'entity'=>$battle->getSettlement());
		} else {
			$loc = $this->get('geography')->locationName($battle->getLocation());
			$location = array('key'=>'battle.location.'.$loc['key'], 'entity'=>$loc['entity']);
		}

		// FIXME:
		// preparation timer should be in the battle, not in the individual actions

		// TODO: add progress and time when battle will happen (see above)

		return array("battle" => $battle, "location" => $location, "now" => new \DateTime("now"));
	}



	/**
	  * @Route("/update", defaults={"_format"="json"})
	  */
	public function updateAction(Request $request) {
		$character = $this->get('dispatcher')->gateway();

		$id = $this->get('request')->request->get('id');
		$option = $this->get('request')->request->get('option');

		$action = false;
		foreach ($character->getActions() as $act) {
			if ($act->getId() == $id) {
				$action = $act;
			}
		}

		if ($action) {
			$em = $this->getDoctrine()->getManager();
			switch ($option) {
				case 'up':
					$prio = $action->getPriority();
					$last = 0;
					$other = false;
					foreach ($character->getActions() as $act) {
						if ($act->getPriority() < $prio && $act->getPriority() > $last) {
							$other = $act;
							$last = $act->getPriority();
						}
					}
					if ($other) {
						$op = $other->getPriority();
						$other->setPriority($prio);
						$action->setPriority($op);
					}
					break;
				case 'down':
					$prio = $action->getPriority();
					$last = 99999;
					$other = false;
					foreach ($character->getActions() as $act) {
						if ($act->getPriority() > $prio && $act->getPriority() < $last) {
							$other = $act;
							$last = $act->getPriority();
						}
					}
					if ($other) {
						$op = $other->getPriority();
						$other->setPriority($prio);
						$action->setPriority($op);
					}
					break;
				case 'cancel':
					if (! $action->getCanCancel()) {
						return new JsonResponse(false);
					}
					switch ($action->getType()) {
						case 'settlement.take':
							$this->get('history')->logEvent(
								$action->getTargetSettlement(),
								'event.settlement.take.stopped',
								array('%link-character%'=>$action->getCharacter()->getId()),
								History::HIGH, true, 20
							);
							break;
						case 'task.research':
							foreach ($action->getAssignedEntourage() as $npc) {
								$npc->setAction(null);
							}
							break;
					}
					// TODO: notify supporting and opposing actions (they get deleted automatically, but a notice would be nice)
					$em->remove($action);
					break;
			}
			$em->flush();
			return new JsonResponse(true);
		} else {
            return new JsonResponse(false);
		}
	}


}
