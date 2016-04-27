<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;


class TranslationCheckCommand extends ContainerAwareCommand {

	private $em;

	protected function configure() {
		$this
			->setName('maf:check')
			->setDescription('Check translation files, output an updated file')
			->addArgument('domain', InputArgument::REQUIRED, 'translation domain to check')
			->addArgument('lang', InputArgument::REQUIRED, 'foreign language to check')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$domain = $input->getArgument('domain');
		$lang = $input->getArgument('lang');

		$file = "src/BM2/SiteBundle/Resources/translations/$domain.en.yml";
		$source = Yaml::parse(file_get_contents($file));

		$file = "src/BM2/SiteBundle/Resources/translations/$domain.$lang.yml";
		if (file_exists($file)) {
			$target = Yaml::parse(file_get_contents($file));
		} else {
			$target = array();
		}

		$this->recursive_check($output, 0, $source, $target);

		return true;
	}


	private function recursive_check(OutputInterface $output, $level, $source, $target) {
		$spaces = "";
		for ($i=0;$i<$level;$i++) { $spaces.=" "; }
		foreach ($source as $key=>$data) {
			if (is_array($data)) {
				if ($target && isset($target[$key])) {
					$next = $target[$key];
				} else {
					$next = false;
				}
				$output->writeln("${spaces}$key:");
				$this->recursive_check($output, $level+1, $data, $next);
			} else {
				if (isset($target[$key])) {
					$output->writeln("${spaces}$key: ".$target[$key]);
				} else {
					$output->writeln("#${spaces}$key: ((missing translation - original: ".trim($data)."))");
				}
			}
		}
	}

}
