<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Service\History;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class UserPaymentCommand extends ContainerAwareCommand {

	private $inactivityDays = 21;

	protected function configure() {
		$this
			->setName('maf:payment:user')
			->setDescription('Manually process a payment')
			->addArgument('user', InputArgument::REQUIRED, 'user email or id')
			->addArgument('type', InputArgument::REQUIRED, 'type (e.g. "PayPal Payment")')
			->addArgument('amount', InputArgument::REQUIRED, 'amount (in EUR) to credit, will be multiplied by 100')
			->addArgument('id', InputArgument::REQUIRED, 'transaction id')
		;
	}



	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
		$pm = $this->getContainer()->get('payment_manager');

		$u = $input->getArgument('user');
		if (intval($u)) {
			$user = $em->getRepository('BM2SiteBundle:User')->find(intval($u));
		} else {
			$user = $em->getRepository('BM2SiteBundle:User')->findOneByEmail($u);
		}
		if (!$user) {
			throw new \Exception("Cannot find user $u");
		}

		$type = $input->getArgument('type');
		$amount = floatval($input->getArgument('amount'));
		$id = $input->getArgument('id');

		$output->writeln("Manually processing a $type payment for ".$user->getUsername()." of $amount EUR.");

		$pm->account($user, $type, 'EUR', $amount, $id);

		$em->flush();
		$output->writeln("Done. User account now hold ".$user->getCredits()." credits.");
	}


}
