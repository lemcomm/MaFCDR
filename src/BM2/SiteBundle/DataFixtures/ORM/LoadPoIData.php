<?php
namespace BM2\SiteBundle\DataFixtures\ORM;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Collections\ArrayCollection;
use BM2\SiteBundle\Entity\BuildingType;
use BM2\SiteBundle\Entity\BuildingResource;
class LoadBuildingData extends AbstractFixture implements OrderedFixtureInterface {
	private $buildings = array(
		'Park'               => array('auto' =>      0, 'min' =>   9000, 'work' =>  25000, 'ratio' => 12000, 'requires' => array('University','Garrison','Mason'), 'icon'=>'buildings/academy.png'),
		'Plaza'             => array('auto' =>   4000, 'min' =>   1000, 'work' =>  12000, 'ratio' =>  5000, 'defenses' => 10, 'icon'=>'buildings/alchemist.png'),
		'Statue'         => array('auto' =>      0, 'min' =>    400, 'work' =>   6000, 'ratio' =>  3000, 'requires' => array('Bowyer','Training Ground'), 'conditions'=>true),
		'Memorial'        => array('auto' =>      0, 'min' =>    600, 'work' =>  10000, 'ratio' =>  2000, 'requires' => array('Archery Range', 'Carpenter')),
		'Manor'              => array('auto' =>   5000, 'min' =>    900, 'work' =>  10000, 'ratio' =>  1500, 'requires' => array('Leather Tanner','Blacksmith'), 'icon'=>'buildings/armorer.png'),
		'Garden'               => array('auto' =>      0, 'min' =>   1000, 'work' =>  10000, 'ratio' =>  4000, 'requires' => array('Armourer','Weaponsmith')),
	);
	
	private $resources = array(
		'Park'               => array('wood'=>array('construction'=>12000), 'metal'=>array('construction'=>1500), 'goods'=>array('construction'=>200, 'operation'=>15), 'money'=>array('construction'=>100, 'operation'=>5)),
		'Plaza'             => array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>500), 'goods'=>array('construction'=>100, 'operation'=>5, 'bonus'=>1), 'money'=>array('construction'=>150, 'operation'=>5, 'bonus'=>3)),
		'Statue'         => array('wood'=>array('construction'=>1200, 'operate'=>5), 'metal'=>array('construction'=>250, 'operate'=>5)),
		'Memorial'        => array('wood'=>array('construction'=>1600, 'operate'=>5), 'metal'=>array('construction'=>400, 'operate'=>5)),
		'Manor'              => array('wood'=>array('construction'=>4000, 'operation'=>15), 'metal'=>array('construction'=>1500, 'operation'=>100), 'goods'=>array('provides'=>2)),
		'Garden'               => array('wood'=>array('construction'=>3500), 'metal'=>array('construction'=>1200)),
	);
	
	/**
	 * {@inheritDoc}
	 */
	
	public function getOrder() {
		return 5; // requires resourcedata
	}
	
	/**
	 * {@inheritDoc}
	 */
	
	public function load(ObjectManager $manager) {
		$all = new ArrayCollection();
		foreach ($this->buildings as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:BuildingType')->findOneByName($name);
			if (!$type) {
				$type = new BuildingType();
				$manager->persist($type);
			}
			$type->setName($name);
			$type->setBuildHours($data['work']);
			$type->setAutoPopulation($data['auto'])->setMinPopulation($data['min']);
			$type->setPerPeople($data['ratio']);
			$type->setDefenses(isset($data['defenses'])?$data['defenses']:0);
			$type->setSpecialConditions(isset($data['conditions'])?true:false);
			if (isset($data['icon'])) {
				$type->setIcon($data['icon']);
			}
			$all->add($type);
			$this->addReference('buildingtype: '.strtolower($name), $type);
			foreach ($this->resources[$name] as $resourcename => $resourcedata) {
				$rt = $manager->getRepository('BM2SiteBundle:ResourceType')->findOneByName($resourcename);
				if (!$rt) {
					echo "can't find $resourcename needed by $name.\n";
				}
				$br = null;
				foreach ($type->getResources() as $r) {
					if ($r->getResourceType() == $rt) {
						$br = $r;
						break;
					}
				}
				if (!$br) {
					$br = new BuildingResource;
					$manager->persist($br);
				}
				$br->setBuildingType($type);
				$br->setResourceType($rt);
				$br->setRequiresConstruction(isset($resourcedata['construction'])?$resourcedata['construction']:0);
				$br->setRequiresOperation(isset($resourcedata['operation'])?$resourcedata['operation']:0);
				$br->setProvidesOperation(isset($resourcedata['provides'])?$resourcedata['provides']:0);
				$br->setProvidesOperationBonus(isset($resourcedata['bonus'])?$resourcedata['bonus']:0);
			}
		}
		// FIXME: this does not yet clean out old data (for updates)
		foreach ($this->buildings as $name=>$data) {
			if (isset($data['requires'])) {
				$me = $all->filter(function($type) use ($name) {
					return $type->getName() == $name;
				})->first();
				foreach ($data['requires'] as $requires) {
					$enabler = $all->filter(function($type) use ($requires) {
						return $type->getName() == $requires;
					})->first();
					if ($enabler) {
						if (!$me->getRequires()->contains($enabler)) {
							$me->getRequires()->add($enabler);
						}
					} else {
						echo "can't find $requires needed by $name.\n";
					}
				}
			}
		}
		$manager->flush();
	}
}
