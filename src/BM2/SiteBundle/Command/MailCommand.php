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

	protected function configure() {
		$this
			->setName('maf:mail')
			->setDescription('Process internal mail spool and send email to users')
		;
	}

	private function getRecipients() {
		$em = $this->getContainer()->get('doctrine')->getManager();

		$query = $em->createQuery('SELECT u FROM BM2SiteBundle:User u WHERE u.account_level > :min');
		$query->setParameter('min', 0);
		return $query->getResult();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$mailer = $this->getContainer()->get('mailer');
		$mailer->sendEmails();
	}


}
