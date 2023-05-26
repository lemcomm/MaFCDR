<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\ActivityReport;
use BM2\SiteBundle\Entity\BattleReport;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Journal;
use BM2\SiteBundle\Entity\UserReport;
use BM2\SiteBundle\Form\JournalType;
use BM2\SiteBundle\Form\UserReportType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/journal")
 */
class JournalController extends Controller {

	/**
	  * @Route("/{id}", name="maf_journal", requirements={"id"="\d+"})
	  */

	public function journalAction(Journal $id) {
		$char = $this->get('appstate')->getCharacter(FALSE, TRUE, TRUE); #Not required, allow dead, allow not started.
		$user = $this->getUser();
		if ($char instanceof Character) {
			$gm = $this->get('security.authorization_checker')->isGranted('ROLE_OLYMPUS');
			$admin = $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN');
		} else {
			$gm = false;
			$admin = false;
		}
		$bypass = false;
		if ($id->isPrivate() && !$gm) {
			if ($char && $char !== $id->getCharacter()) {
				$this->addFlash('notice', $this->get('translator')->trans('journal.view.redirect', array(), 'messages'));
				return $this->redirectToRoute('bm2_character');
			} elseif (!$char) {
				$this->addFlash('notice', $this->get('translator')->trans('journal.view.redirect', array(), 'messages'));
				return $this->redirectToRoute('bm2_characters');
			}
		} elseif ($id->isPrivate() && $gm) {
			$bypass = true;
		}

		return $this->render('Journal/view.html.twig',  [
			'journal'=>$id,
			'user'=>$user,
			'gm'=>$gm,
			'admin'=>$admin,
			'bypass'=>$bypass
		]);
	}

	/**
	  * @Route("/write", name="maf_journal_write")
	  * @Route("/write/")
	  */

	public function journalWriteAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('journalWriteTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new JournalType());
		$form->handleRequest($request);

		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			$journal = $this->newJournal($character, $data);

			$em = $this->getDoctrine()->getManager();
			$em->persist($journal);
			$em->flush();
			if(!$journal->isPrivate() && !$journal->isGraphic()) {
				$this->get('notification_manager')->spoolJournal($journal);
			}
			$this->addFlash('notice', $this->get('translator')->trans('journal.write.success', array(), 'messages'));
			return $this->redirectToRoute('maf_journal_mine');
		}

		return $this->render('Journal/write.html.twig', [
			'form'=>$form->createView()
		]);
	}

      private function newJournal(Character $char, $data) {
	      $journal = new Journal;
	      $journal->setCharacter($char);
	      $journal->setDate(new \DateTime('now'));
	      $journal->setCycle($this->get('appstate')->getCycle());
	      $journal->setLanguage('English');
	      $journal->setTopic($data['topic']);
	      $journal->setEntry($data['entry']);
	      $journal->setOoc($data['ooc']);
	      $journal->setPublic($data['public']);
	      $journal->setGraphic($data['graphic']);
	      $journal->setPendingReview(false);
	      $journal->setGMReviewed(false);
	      $journal->setGMPrivate(false);
	      $journal->setGMGraphic(false);
	      return $journal;
      }

	/**
	  * @Route("/write/battle/{report}", name="maf_journal_write_battle")
	  */
	public function journalWriteAboutBattleAction(Request $request, BattleReport $report) {
		$character = $this->get('dispatcher')->gateway('journalWriteBattleTest', null, null, null, $report);
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$form = $this->createForm(new JournalType());
		$form->handleRequest($request);

		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			$journal = $this->newJournal($character, $data);
			$journal->setBattleReport($report);

			$em = $this->getDoctrine()->getManager();
			$em->persist($journal);
			$em->flush();
                        if(!$journal->isPrivate() && !$journal->isGraphic()) {
                                $this->get('notification_manager')->spoolJournal($journal);
                        }
			$this->addFlash('notice', $this->get('translator')->trans('journal.write.success', array(), 'messages'));
			return $this->redirectToRoute('maf_journal_mine');
		}

		return $this->render('Journal/write.html.twig', [
			'form'=>$form->createView(),
			'report'=>$report
		]);
	}

  	/**
  	  * @Route("/write/activity/{report}", name="maf_journal_write_activity")
  	  */
  	public function journalWriteAboutActivityAction(Request $request, ActivityReport $report) {
  		$character = $this->get('dispatcher')->gateway('journalWriteActivityTest', null, null, null, $report);
  		if (! $character instanceof Character) {
  			return $this->redirectToRoute($character);
  		}

  		$form = $this->createForm(new JournalType());
  		$form->handleRequest($request);

  		if ($form->isValid() && $form->isSubmitted()) {
  			$data = $form->getData();
  			$journal = $this->newJournal($character, $data);
  			$journal->setActivityReport($report);

  			$em = $this->getDoctrine()->getManager();
  			$em->persist($journal);
  			$em->flush();
                        if(!$journal->isPrivate() && !$journal->isGraphic()) {
                                $this->get('notification_manager')->spoolJournal($journal);
                        }
  			$this->addFlash('notice', $this->get('translator')->trans('journal.write.success', array(), 'messages'));
  			return $this->redirectToRoute('maf_journal_mine');
  		}

  		return $this->render('Journal/write.html.twig', [
  			'form'=>$form->createView(),
  			'report'=>$report
  		]);
  	}

	/**
	  * @Route("/mine", name="maf_journal_mine")
	  */

	public function journalMineAction() {
		$character = $this->get('dispatcher')->gateway('journalMineTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Journal/mine.html.twig', [
			'char' => $character
		]);
	}

	/**
	  * @Route("/user/{id}", name="maf_journal_character", requirements={"id"="\d+"})
	  */

	public function journalCharacterAction(Character $id) {
		return $this->render('Journal/user.html.twig', [
			'char' => $id
		]);
	}

	/**
	  * @Route("/report/{id}", name="maf_journal_report", requirements={"id"="\d+"})
	  */

	public function journalReportAction(Request $request, Journal $id) {
		if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
			$form = $this->createForm(new UserReportType());
			$form->handleRequest($request);
			if ($form->isValid() && $form->isSubmitted()) {
				$em = $this->getDoctrine()->getManager();
				$user = $this->getUser();
				$report = new UserReport();
				$report->setUser($this->getUser());
				$report->setJournal($id);
				$report->setType('Journal');
				$report->setDate(new \DateTime('now'));
				if ($id->getPendingReview()) {
					$id->setPendingReview(true);
				}
				$em->persist($report);
				$em->flush();
				$text = '['.$user->getUsername().'](https://mightandfealty.com/user/'.$user->getId().') has reported the journal: ['.$id->getTopic().'](https://mightandfealty.com/journal/'.$id->getId().').';
				$this->get('discord_integrator')->pushToOlympus($text);
				$this->addFlash('notice', $this->get('translator')->trans('journal.report.success', array(), 'messages'));
				return $this->redirectToRoute('maf_journal', array('id'=>$id->getId()));
			} else {
				return $this->render('Journal/report.html.twig', [
					'journal' => $id,
					'form' => $form->createView()
				]);
			}
		} else {
			$this->addFlash('notice', $this->get('translator')->trans('journal.report.failure', array(), 'messages'));
			return $this->redirectToRoute('maf_journal', array('id'=>$id->getId()));
		}
	}

	/**
	  * @Route("/gmprivate/{id}", name="maf_journal_gmprivate", requirements={"id"="\d+"})
	  */

	public function journalGMPrivateAction(Journal $id) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_OLYMPUS')) {
			$id->setGMPrivate(true);
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('journal.gm.private.success', array(), 'messages'));
		} else {
			$this->addFlash('notice', $this->get('translator')->trans('journal.gm.private.failure', array(), 'messages'));
		}
		return $this->redirectToRoute('maf_journal', array('id'=>$id->getId()));
	}

	/**
	  * @Route("/gmgraphic/{id}", name="maf_journal_gmgraphic", requirements={"id"="\d+"})
	  */

	public function journalGMGraphicAction(Journal $id) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_OLYMPUS')) {
			$id->setGMGraphic(true);
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('journal.gm.graphic.success', array(), 'messages'));
		} else {
			$this->addFlash('notice', $this->get('translator')->trans('journal.gm.graphic.failure', array(), 'messages'));
		}
		return $this->redirectToRoute('maf_journal', array('id'=>$id->getId()));
	}

	/**
	  * @Route("/gmremove/{id}", name="maf_journal_gmremove", requirements={"id"="\d+"})
	  */

	public function journalGMRemoveAction(Journal $id) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
			$em = $this->getDoctrine()->getManager();
			$em->remove($id);
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('journal.gm.remove.success', array(), 'messages'));
			return $this->redirectToRoute('maf_gm_pending');
		} else {
			$this->addFlash('notice', $this->get('translator')->trans('journal.gm.remove.failure', array(), 'messages'));
			return $this->redirectToRoute('bm2_homepage');
		}
	}

}
