<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use BM2\SiteBundle\Entity\Culture;
use BM2\SiteBundle\Entity\NameList;


class LoadNames extends AbstractFixture implements OrderedFixtureInterface, ContainerAwareInterface {

	private $cultures = array(
		array('name'=>'european.central', 'colour'=>'#ffffff', 'free'=>true, 'cost'=>0, 'contains'=>array('names')),
		array('name'=>'european.northern', 'colour'=>'#ddddff', 'free'=>false, 'cost'=>250, 'contains'=>array('names')),
		array('name'=>'european.southern', 'colour'=>'#ffdddd', 'free'=>false, 'cost'=>250, 'contains'=>array('names')),
		array('name'=>'european.eastern', 'colour'=>'#ddffdd', 'free'=>false, 'cost'=>250, 'contains'=>array('names')),
		array('name'=>'oriental', 'colour'=>'#f02040', 'free'=>false, 'cost'=>500, 'contains'=>array('names')),
		array('name'=>'indian', 'colour'=>'#702020', 'free'=>false, 'cost'=>500, 'contains'=>array('names')),
		array('name'=>'asian', 'colour'=>'#803060', 'free'=>false, 'cost'=>500, 'contains'=>array('names')),
		array('name'=>'african', 'colour'=>'#302000', 'free'=>false, 'cost'=>500, 'contains'=>array('names')),
	);

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 1;
	}

	/**
	 * @var ContainerInterface
	 */
	private $container;

	/**
	 * {@inheritDoc}
	 */
	public function setContainer(ContainerInterface $container = null) {
		$this->container = $container;
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {

		foreach ($this->cultures as $data) {
			$type = new Culture;
			$type->setName($data['name']);
			$type->setColourHex($data['colour']);
			$type->setFree($data['free']);
			$type->setCost($data['cost']);
			$type->setContains($data['contains']);
			$manager->persist($type);
			$this->addReference('culture: '.strtolower($data['name']), $type);            
		}
		$manager->flush();

		$env = $this->container->get('kernel')->getEnvironment();
		if ($env == "test") {
			$batchsize = 50;
			$max=100;
		} else {
			$batchsize = 1000;
			$max=-1;
		}

		$handle = @fopen("names_african.txt", "r");
		if (!$handle) {
			throw new \Exception("name database names_african.txt missing");
		}
		$count=1;
		$culture = $this->getReference('culture: african');
		while (($buffer = fgets($handle, 4096)) !== false) {
			if ($buffer[0]=='#') continue; // comment line
			list($name, $gender) = explode(", ", trim($buffer));
			$name=ucfirst(strtolower($name));

			$data = new NameList;
			$data->setName($name);
			if ($gender=='m') {
				$data->setMale(true);
			} else {
				$data->setMale(false);
			}
			$data->setCulture($culture);
			$manager->persist($data);

			if (($count++ % $batchsize) == 0) {
				echo ".";
				$manager->flush();
			}
			if ($max>0 && $count>=$max) {
				break;
			}
		}
		fclose($handle);
		echo ".";
		$manager->flush();
		echo "\n";

		$handle = @fopen("nam_dict.txt", "r");
		if (!$handle) {
			throw new \Exception("name database nam_dict.txt missing");
		}
		$count=1;
		while (($buffer = fgets($handle, 4096)) !== false) {
			if ($buffer[0]=='#') continue; // comment line
			if ($buffer[0]=='=') continue; // short/long name equivalence line
			if ($buffer[30]=='+') continue; // duplicated entry for sorting
			$name = trim(substr($buffer, 3, 25));
			// TODO: replace "+" with all options?
			$name = strtr($name, '+', '-');
			if ($this->check_utf8($name) && strstr($name, '<')===false && $name!="Tom") {
				// valid utf8 string with no special symbolism

				// find cultures from the frequency values
				$cultures=array();
				for ($i=31;$i<85;$i++) {
					$magnitude = $buffer[$i-1];
					if ($magnitude!=' ') {
						$culture=false;
						if ($i==31 || $i==32 || ($i>=38 && $i<=45)) {
							$culture = 'european.central';
						} else if ($i>=46 && $i<=53) {
							$culture = 'european.northern';
						} else if ($i>=34 && $i<=37) {
							$culture = 'european.southern';
						} else if ($i>=54 && $i<=76) {
							$culture = 'european.eastern';
						} else if (($i>=77 && $i<=79)) {
							$culture = 'oriental';
						} else if ($i==81) {
							$culture = 'indian';
						} else if ($i == 80 || ($i>=82 && $i<=84)) {
							$culture = 'asian';
						}
						if ($culture) {
							switch ($magnitude) {
								case 'D':	$value=13; break;
								case 'C':	$value=12; break;
								case 'B':	$value=11; break;
								case 'A':	$value=10; break;
								default:	$value=intval($magnitude);
							}
							if (isset($cultures[$culture])) {
								$cultures[$culture] = max($value,$cultures[$culture])+1;
							} else {
								$cultures[$culture]=$value;
							}
						}
					}
				}
				$myculture=false;
				$maxval=0;
				foreach ($cultures as $cul=>$val) {
					if ($val>$maxval) {
						$myculture = $cul;
						$maxval = $val;
					}
				}
				if ($myculture) {
					$data = new NameList;
					$data->setName($name);
					switch ($buffer[0]) {
						case 'M':   $data->setMale(true); break;
						case 'F':   $data->setMale(false); break;
						default:    $data->setMale(null); // unisex
					}
					$culture = $this->getReference('culture: '.strtolower($myculture));
					$data->setCulture($culture);

					$manager->persist($data);
				}
			}
			if (($count++ % $batchsize) == 0) {
				echo ".";
				$manager->flush();
				$manager->clear();
			}
			if ($max>0 && $count>=$max) {
				break;
			}
		}
		echo ".";
		fclose($handle);
		echo "\n";

		$manager->flush();
	}

	private function check_utf8($str) {
		$len = strlen($str);
		for($i = 0; $i < $len; $i++){
			$c = ord($str[$i]);
			if ($c > 128) {
				if (($c > 247)) return false;
				elseif ($c > 239) $bytes = 4;
				elseif ($c > 223) $bytes = 3;
				elseif ($c > 191) $bytes = 2;
				else return false;
				if (($i + $bytes) > $len) return false;
				while ($bytes > 1) {
					$i++;
					$b = ord($str[$i]);
					if ($b < 128 || $b > 191) return false;
					$bytes--;
				}
			}
		}
		return true;
	} // end of check_utf8

}
