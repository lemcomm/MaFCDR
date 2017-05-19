<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\Code;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class CodeGeneratorCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:codes')
			->setDescription('Generate codes and send them to e-mail addresses file')
			->addArgument('file', InputArgument::REQUIRED, 'file with e-mails to load')
			->addArgument('credits', InputArgument::REQUIRED, 'value of credits for each code')
			->addArgument('vip', InputArgument::OPTIONAL, 'vip level (0, 10, 20, 30)')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {

		$file = $input->getArgument('file');
		$credits = $input->getArgument('credits');
		$vip = $input->getArgument('vip');
		if (!$vip) $vip=0;

		$em = $this->getContainer()->get('doctrine')->getManager();
		$mailer = $this->getContainer()->get('mailer');
		$pm = $this->getContainer()->get('payment_manager');
		$spool = $mailer->getTransport()->getSpool();
		$transport = $this->getContainer()->get('swiftmailer.transport.real');

		$mails = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		$failed = array();
		$sent = 0;
		foreach ($mails as $mail) {
			$mail = trim($mail);
			$code = $pm->createCode($credits, $vip, $mail, null, true);

			$msg = "Hallo - \n\n";
			$msg.= "Danke für Deine Hilfe beim Video zu Might & Fealty.\n\n";
			$msg.= "Ich habe zwar mein Crowdfunding-Ziel nie erreicht, bin aber dank einiger treuer Fans\n";
			$msg.= "jetzt aus der Beta und das Spiel läuft ein wenig. Als Dankeschön ist hier ein Code, der\n";
			$msg.= "im Spiel 2000 Credits und einen VIP-Status wert ist:\n";
			$msg.= $code->getCode();
			$msg.= "\n\nAußerdem bekommst Du gleich noch 2 Mails mit Codes für jeweils 1000 Credits, die Du!\n";
			$msg.= "selbst benutzen oder an Freunde weitergeben kannst.\n";
			$msg.= "\n\nTom\n";


			$message = \Swift_Message::newInstance()
				 ->setSubject('Might & Fealty Dankeschön')
				 ->setFrom('server@mightandfealty.com')
				 ->setTo($mail)
				 ->setBody($msg);
			$sent += $mailer->send($message, $failed);
		}
		$spool->flushQueue($transport);
		echo "$sent messages sent.";
		if ($failed) {
			echo "failed: ";
			print_r($failed);
		}
		echo "\n";
	}

}

