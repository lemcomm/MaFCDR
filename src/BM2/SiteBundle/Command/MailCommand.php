<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/* It may be worthwhile to actually update this into a full fledged command for including files to use as content to send in custom emails.
Shouldn't be too hard to do. --Andrew 20170507 */

class MailCommand extends ContainerAwareCommand {

	private $subject = "Might & Fealty Outtage Report";
	private $text = 
"My sincere apologies for the issues that took the game down for a 
few days recently. This was an unfortunate combination of a bug that
only appeared under certain circumstances, but got the entire game
stuck, and me being on holiday at the time it happened, with little to
no Internet access.

I have now fixed this bug, and added code that will, hopefully, ensure
that this bug will never happen again.

As an apology I have alos added 200 credits to the account of everyone
who has a subscription running currently.


Tom
";


	protected function configure() {
		$this
			->setName('maf:mail')
			->setDescription('Send mail to players')
		;
	}

	private function getRecipients() {
		$em = $this->getContainer()->get('doctrine')->getManager();

		$query = $em->createQuery('SELECT u FROM BM2SiteBundle:User u WHERE u.account_level > :min');
		$query->setParameter('min', 0);
		return $query->getResult();
	}

	/* TODO: check and info about environment so see if mails are actually sent or not (dev doesn't send) */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$mailer = $this->getContainer()->get('mailer');
		$spool = $mailer->getTransport()->getSpool();
		$transport = $this->getContainer()->get('swiftmailer.transport.real');

		foreach ($this->getRecipients() as $user) {
			$mail = trim($user->getEmail());
			$text = "Hello, ".$user->getUsername()."\n\n".$this->text;

			$message = \Swift_Message::newInstance()
				 ->setSubject($this->subject)
				 ->setFrom('mafserver@lemuriacommunity.org')
				 ->setReplyTo('mafteam@lemuriacommunity.org')
				 ->setTo($mail)
				 ->setBody($text);
			$mailer->send($message);
			echo "$mail\n";
		}
		$spool->flushQueue($transport);
		echo "all mails sent\n";
	}


}
