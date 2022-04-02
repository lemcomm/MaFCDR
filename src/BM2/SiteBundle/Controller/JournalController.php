<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Journal;
use BM2\SiteBundle\Form\JournalType;
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
			$journal = new Journal;
			$journal->setCharacter($character);
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

			$em = $this->getDoctrine()->getManager();
			$em->persist($journal);
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('journal.write.success', array(), 'messages'));
			return $this->redirectToRoute('maf_journal_mine');
		}

		return $this->render('Journal/write.html.twig', [
			'form'=>$form->createView()
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
	  * @Route("/report/{id}", name="maf_journal_report", requirements={"id"="\d+"})
	  */

	public function journalReportAction(Journal $id) {
		if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
			$em = $this->getDoctrine()->getManager();
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
			$this->addFlash('notice', $this->get('translator')->trans('journal.report.success', array(), 'messages'));
		} else {
			$this->addFlash('notice', $this->get('translator')->trans('journal.report.failure', array(), 'messages'));
		}
		return $this->redirectToRoute('maf_journal', array('id'=>$id->getId()));
	}

}
