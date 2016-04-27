<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class DailyNewsCommand extends ContainerAwareCommand {


	protected function configure() {
		$this
			->setName('maf:newsletter')
			->setDescription('Send daily newsletter to players (retention mailings)')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$mailer = $this->getContainer()->get('mailer');
		$spool = $mailer->getTransport()->getSpool();
		$transport = $this->getContainer()->get('swiftmailer.transport.real');
		$translator = $this->getContainer()->get('translator');
		$em = $this->getContainer()->get('doctrine')->getManager();
		$cycle = $this->getContainer()->get('appstate')->getCycle();

		$query = $em->createQuery('SELECT u FROM BM2SiteBundle:User u WHERE u.newsletter=true');
		$iterableResult = $query->iterate();
		$i=1; $batchsize=500;
		while ($row = $iterableResult->next()) {
			$user = $row[0];
			$days = $user->getCreated()->diff(new \DateTime("now"), true)->days;
			$fakestart = new \DateTime("2015-10-30");
			$fakedays = $fakestart->diff(new \DateTime("now"), true)->days;
			$days = min($days, $fakedays);
			if ($user->getLastLogin()) {
				$last = $user->getLastLogin()->diff(new \DateTime("now"), true)->days;
			} else {
				$last = -1;
			}

			$text = false; $subject = "Might & Fealty Newsletter";
			// daily "new player guide"
			if ($days < 6) {
				$text = "newplayer.$days";
				$subject = "newplayer.subject";
			} elseif ($days == 8 ) {
				$text = "newplayer.a";
				$subject = "newplayer.subject";
			} elseif ($days == 12 ) {
				$text = "newplayer.b";
				$subject = "newplayer.subject";
			} elseif ($days == 20 ) {
				$text = "newplayer.c";
				$subject = "newplayer.subject";
			}

			// player gone absent - this trumps the other content, but we only want to send one per day
			if ($last == 5) {
				// "everything ok?"
				$text = "retention.1";
				$subject = "retention.subject";
			} elseif ($last == 16) {
				// "hey, you haven't played in a while"
				$text = "retention.2";
				$subject = "retention.subject";
			} elseif ($last == 30) {
				// "are you still there? if not, want to tell us why?"
				$text = "retention.3";
				$subject = "retention.subject";
			}


			if ($text) {
				$subject = $translator->trans($subject, array(), "newsletter");
				$content = $translator->trans($text, array(), "newsletter");

				$content .= "<br /><br />".$translator->trans("footer", array(), "newsletter");

				$message = \Swift_Message::newInstance()
					->setSubject($subject)
					->setFrom('server@mightandfealty.com')
					->setReplyTo('tom@mightandfealty.com')
					->setTo($user->getEmail())
					->setBody(strip_tags($content))
					->addPart($content, 'text/html');
				$mailer->send($message);
			}

			if (($i++ % $batchsize) == 0) {
				$spool->flushQueue($transport);
				$em->flush();
				$em->clear();
			}
		}
		$spool->flushQueue($transport);
		$em->flush();
		$em->clear();
	}


}
