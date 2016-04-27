<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

use Sepia\PoParser;


class TranslationConvertCommand extends ContainerAwareCommand {

	private $em;

	protected function configure() {
		$this
			->setName('maf:trans:convert')
			->setDescription('Convert translation files to and from YAML to PO')
			->addArgument('target', InputArgument::REQUIRED, 'target to translate into (one of "yml" or "po")')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$target = $input->getArgument('target');
		switch ($target) {
			case 'yml':		$source = 'po'; break;
			case 'po':		$source = 'yml'; break;
			default:			throw new \Exception("invalid target");
		}

		$fs = new Filesystem();

		$finder = new Finder();
		$finder->files()->in('src/BM2/SiteBundle/Resources/translations/')->name('*.'.$source);
		foreach ($finder as $file) {
			$parts = explode('.', $file->getRelativePathname());
			$domain = $parts[0];
			$lang = $parts[1];

			if ($source == 'yml') {
				$source_data = Yaml::parse($file->getContents());
				$target_data = $this->convert_yml2po(null, $source_data);
			} else {
				$source_data = PoParser::parseString($file->getContents());
				$target_data = $this->convert_po2yml($source_data);
			}
			$fs->dumpFile('src/BM2/SiteBundle/Resources/translations/'.$domain.'.'.$lang.'.'.$target, $target_data);
		}

		return true;
	}


	private function convert_yml2po($prefix, $source) {
		$output = '';
		if ($prefix===null) $prefix=''; else $prefix.='.';
		foreach ($source as $key=>$data) {
			if (is_array($data)) {
				$output .= $this->convert_yml2po($prefix.$key, $data);
			} else {
				$data = trim($data);
				if (strpos($data, '|') === false) {
					$output.="msgid \"${prefix}$key\"\nmsgstr \"".str_replace('"', '\"', $data)."\"\n";
				} else {
					$parts = explode('|', $data);
					$output .= "msgid \"${prefix}$key.singular\"\nmsgid_plural \"${prefix}$key.plural\"\n";
					foreach ($parts as $i=>$part) {
						$output .= "msgstr[$i] \"".str_replace('"', '\"', $part)."\"\n";
					}
				}
			}
		}
		return $output;
	}

	private function convert_po2yml($source) {
		$yaml_data = array();
		foreach ($source->getEntries() as $key=>$data) {
			if (isset($data['msgid_plural'])) {
				// FIXME: this only works for 0,1 but the above (yml2po) works for more than 2
				$content = $data['msgstr[0]'][0].'|'.$data['msgstr[1]'][0];
			} else {
				$content = $data['msgstr'][0];
			}
			$parts = explode('.', $data['msgid'][0]);
			switch (count($parts)) {
				case 1:	$yaml_data[$parts[0]] = $content; break;
				case 2:
					if (!isset($yaml_data[$parts[0]])) { $yaml_data[$parts[0]] = array(); }
					$yaml_data[$parts[0]][$parts[1]] = $content;
					break;
				case 3:
					if (!isset($yaml_data[$parts[0]])) { $yaml_data[$parts[0]] = array(); }
					if (!isset($yaml_data[$parts[0]][$parts[1]])) { $yaml_data[$parts[0]][$parts[1]] = array(); }
					$yaml_data[$parts[0]][$parts[1]][$parts[2]] = $content;
					break;
				case 4:
					if (!isset($yaml_data[$parts[0]])) { $yaml_data[$parts[0]] = array(); }
					if (!isset($yaml_data[$parts[0]][$parts[1]][$parts[2]])) { $yaml_data[$parts[0]][$parts[1]][$parts[2]] = array(); }
					$yaml_data[$parts[0]][$parts[1]][$parts[2]][$parts[3]] = $content;
					break;
				case 5:
					if (!isset($yaml_data[$parts[0]])) { $yaml_data[$parts[0]] = array(); }
					if (!isset($yaml_data[$parts[0]][$parts[1]][$parts[2]][$parts[3]])) { $yaml_data[$parts[0]][$parts[1]][$parts[2]][$parts[3]] = array(); }
					$yaml_data[$parts[0]][$parts[1]][$parts[2]][$parts[3]][$parts[4]] = $content;
					break;
				case 6:
					echo "level 6\n";
					break;
			}
		}
		return Yaml::dump($yaml_data, 5, 1);
	}

	private function convert_po2yml_manual($source) {
		$yml = '';
		foreach ($source->getEntries() as $key=>$data) {
			if (isset($data['msgid_plural'])) {
				// FIXME: this only works for 0,1 but the above (yml2po) works for more than 2
				$yml.=$data['msgid'][0].":\t".$data['msgstr[0]'][0].'|'.$data['msgstr[1]'][0]."\n";
			} else {
				$yml.=$data['msgid'][0].":\t".$data['msgstr'][0]."\n";
			}
		}
		return $yml;
	}

}
