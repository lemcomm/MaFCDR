<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\EventLog;
use BM2\SiteBundle\Entity\Soldier;
use BM2\SiteBundle\Form\EntourageAssignType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


/**
 * @Route("/events")
 */
class EventsController extends Controller {

	/**
	* @Route("/", name="bm2_events")
	*/
	public function eventsAction() {
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT l FROM BM2SiteBundle:EventLog l JOIN l.metadatas m WHERE m.reader = :me GROUP BY l');
		$query->setParameter('me', $character);
		$logs = $query->getResult();

		// check/update realm logs
		$change = false;
		$realms = $character->findRealms();
		foreach ($logs as $log) {
			if ($log->getRealm() && !$realms->contains($log->getRealm())) {
				// not in that realm anymore, close log
				$this->get('history')->closeLog($log->getRealm(), $character);
				$change = true;
			}
		}
		foreach ($realms as $realm) {
			if (!in_array($realm->getLog(), $logs)) {
				// missing from our logs, open it
				$this->get('history')->openLog($realm, $character);
				$change = true;
			}
		}

		if ($change) {
			$this->getDoctrine()->getManager()->flush();
		}

		$metas = $character->getReadableLogs();
		$logs = array();
		foreach ($metas as $meta) {
			$id = $meta->getLog()->getId();
			$new = $meta->countNewEvents();
			if (isset($logs[$id])) {
				if ($logs[$id]['new']<$new) {
					$logs[$id]['new'] = $new;
				}
			} else {
				$logs[$id] = array(
					'name' => $meta->getLog()->getName(),
					'type' => $meta->getLog()->getType(),
					'events' => $meta->getLog()->getEvents()->count(),
					'new' => $new
				);
			}
		}

		return $this->render('Events/events.html.twig', [
			'logs'=>$logs
		]);
	}


	/**
	* @Route("/log/{id}", name="bm2_eventlog", requirements={"id"="\d+"})
	*/
	public function eventlogAction($id, Request $request) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		$log = $em->getRepository('BM2SiteBundle:EventLog')->find($id);
		if (!$log) {
			throw $this->createNotFoundException('error.notfound.log');
		}
		$metas = $em->getRepository('BM2SiteBundle:EventMetadata')->findBy(array('log'=>$log, 'reader'=>$character));
		if (!$metas) {
			throw new AccessDeniedHttpException('error.noaccess.log');
		}

		foreach ($metas as $meta) {
			$meta->setLastAccess(new \DateTime('now'));
		}
		$em->flush();

		$scholar_type = $em->getRepository('BM2SiteBundle:EntourageType')->findOneByName('scholar');
		$myscholars = $character->getAvailableEntourageOfType($scholar_type);

		$research = $character->getActions()->filter(
			function($entry) use ($log) {
				$func = 'getTarget'.ucfirst($log->getType());
				return ($entry->getType()=='task.research' && $entry->$func()==$log->getSubject());
			}
		);
		if ($research) { $research = $research->first(); }

		if ($myscholars->count()>0) {
			$form = $this->createForm(new EntourageAssignType('research', $myscholars));
			$formView = $form->createView();
			$form->handleRequest($request);
			if ($form->isValid()) {
				$data = $form->getData();
				if (!$research) {
					$act = new Action;
					$act->setType('task.research')->setCharacter($character);
					if (strtolower($log->getType()) == 'settlement') {
						$act->setBlockTravel(true);
					} else {
						$act->setBlockTravel(false);
					}
					$act->setCanCancel(true);
					$func = 'setTarget'.ucfirst($log->getType());
					$act->$func($log->getSubject());
					$result = $this->get('action_manager')->queue($act);
					$research = $act;
				}
				foreach ($data['entourage'] as $npc) {
					$npc->setAction($research);
					$research->addAssignedEntourage($npc);
					$myscholars->removeElement($npc);
				}
				$em->flush();
				$form = $this->createForm(new EntourageAssignType('research', $myscholars));
				$formView = $form->createView();
			}
		} else {
			$formView = null;
		}

		return $this->render('Events/eventlog.html.twig', [
			'log'=>$log,
			'metas'=>$metas,
			'scholars'=>$myscholars->count(),
			'research'=>$research,
			'form'=>$formView
		]);
	}

	/**
	* @Route("/allread/{log}")
	*/
	public function allreadAction(EventLog $log) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT m FROM BM2SiteBundle:EventMetadata m JOIN m.reader r WHERE m.log = :log AND r.user = :me');
		$query->setParameters(array('log'=>$log, 'me'=>$character->getUser()));
		foreach ($query->getResult() as $meta) {
			// FIXME: this should use the display time, not now - just in case the player looks at the screen for a long time and new events happen inbetween!
			$meta->setLastAccess(new \DateTime('now'));
		}
		$em->flush();

		return new Response();
	}

	/**
	* @Route("/fullread/{which}")
	*/
	public function fullreadAction($which) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();

		switch ($which) {
			case 'all':
				$events = $this->get('character_manager')->findEvents($character);
				$logs = array();
				// get all logs and then clear only them...
				foreach ($events as $event) {
					$logid=$event->getLog()->getId();
					if (!isset($logs[$logid])) {
						$logs[$logid] = $logid;
					}
				}
				$query = $em->createQuery('SELECT m FROM BM2SiteBundle:EventMetadata m JOIN m.reader r WHERE r.user = :me and m.log in (:logs)');
				$query->setParameters(array('me'=>$character->getUser(), 'logs'=>$logs));
				break;
			default:
			$query = $em->createQuery('SELECT m FROM BM2SiteBundle:EventMetadata m JOIN m.reader r WHERE r = :me');
			$query->setParameters(array('me'=>$character));
		}
		foreach ($query->getResult() as $meta) {
			// FIXME: this should use the display time, not now - just in case the player looks at the screen for a long time and new events happen inbetween!
			$meta->setLastAccess(new \DateTime('now'));
		}
		$em->flush();

		return new Response();
	}

	/**
	* @Route("/soldierlog/{soldier}")
	*/
	public function soldierlogAction(Soldier $soldier) {
		$character = $this->get('appstate')->getCharacter(true, true, true);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$access = false;
		if ($soldier->getCharacter() == $character) {
			$access = true;
		} elseif ($soldier->getBase() && ($soldier->getBase()->getOwner() == $character || $soldier->getBase()->getSteward() == $character)) {
			$access = true;
		}

		if (!$access) {
			throw new AccessDeniedHttpException('error.noaccess.log');
		}

		return $this->render('Events/soldierlog.html.twig', [
			'soldier'=>$soldier
		]);
	}
}
