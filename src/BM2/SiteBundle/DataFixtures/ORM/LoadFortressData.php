<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Collections\ArrayCollection;

use BM2\SiteBundle\Entity\BuildingType;
use BM2\SiteBundle\Entity\BuildingResource;


class LoadBuildingData extends AbstractFixture implements OrderedFixtureInterface {

/* If this looks exactly like the Buildings Data, that's because basically, it is. Rather than rework the entire system from the ground
up, it seems like its incredibly simpler to expand on what already exists, and rework from there. 

In order, these are 'Building Name',  Auto build time,  min population, ideal worker count, ratio of how many people in fort to how
many will work in this building, and then the requirements array of what other buildings this one requires, first at this fort and
then secondly in the region. Lastly, does it have conditions for it's construction (default false) and defining if it has an image, 
if it has one.--Andrew */

	private $fortbuildings = array(
		'STUFF'				=> array('auto' =>      0, 'min' =>   9000, 'work' =>  0, 'ratio' => 12000, 'defenses' => 0, 'requires' => array('University','Garrison','Mason'), 'icon'=>'buildings/academy.png'),
		'Fort Guardpost'		=> array('auto' =>      0, 'min' =>   1000, 'work' =>  0, 'ratio' =>  5000, 'defenses' => 10),
		'Fort Watchtower'		=> array('auto' =>      0, 'min' =>    400, 'work' =>   0, 'ratio' =>  3000),
		'Fort Barracks'			=> array('auto' =>      0, 'min' =>    600, 'work' =>  10000, 'ratio' =>  2000, 'requires' => array('Fort Guardpost')),
		'Fort Mess Hall'		=> array('auto' =>      0, 'min' =>    900, 'work' =>  10000, 'ratio' =>  1500, 'requires' => array('Fort Barracks')),
		'Fort Warehouse'		=> array('auto' =>      0, 'min' =>   1000, 'work' =>  10000, 'ratio' =>  4000, 'requires' => array('Fort Guardpost')),
		'Fort Armoury'			=> array('auto' =>      0, 'min' =>   4000, 'work' =>  20000, 'ratio' =>  2000, 'requires' => array('Fort Warehouse')),
		'Fort Garrison'			=> array('auto' =>      0, 'min' =>    300, 'work' =>  12000, 'ratio' =>  4000, 'requires' => array('Fort Barracks')),
		'Fort Smith			=> array('auto' =>      0, 'min' =>    250, 'work' =>  10000, 'ratio' =>  1000, 'requires' => array('Fort Warehouse')),
		'Fort Palisade			=> array('auto' =>      0, 'min' =>   1500, 'work' =>  16000, 'ratio' =>  1800, 'requires' => array('Fort Guardpost')),
		'Fort Wood Wall'                => array('auto' =>      0, 'min' =>    200, 'work' =>   8000, 'ratio' =>   600, 'requires' => array('Fort Palisade')),
		'Fort Wood Towers		=> array('auto' =>      0, 'min' =>     80, 'work' =>   9000, 'ratio' =>   400, 'requires' => array('Fort Wood Wall')),
		'Fort Wood Keep'		=> array('auto' =>      0, 'min' =>   1000, 'work' =>  0, 'ratio' =>  5000, 'defenses' => 10, 'requires' => array('Fort Wood Towers', 'Fort Wood Wall')),
		'Fort Moat			=> array('auto' =>      0, 'min' =>   6000, 'work' =>1500000, 'ratio' =>  5000, 'defenses' => 70, 'requires' => array('Fort Palisade'), 'conditions'=>true),
		'Fort Filled Moat		=> array('auto' =>      0, 'min' =>   5000, 'work' =>  25000, 'ratio' =>  3500, 'requires' => array('Fort Moat')),
		'Fort Yard'			=> array('auto' =>      0, 'min' =>   1000, 'work' =>  0, 'ratio' =>  5000, 'requires' => array('Fort Guardpost'),
		'Fort Bailey			=> array('auto' =>      0, 'min' =>     50, 'work' =>   5000, 'ratio' =>  1000, 'requires' => array('Fort Yard'),
		'Fort Apothecary		=> array('auto' =>      0, 'min' =>   1200, 'work' =>  10000, 'ratio' =>  1000, 'requires' => array('Carpenter','Market')),
		'Fort Stone Wall		=> array('auto' =>      0, 'min' =>   4000, 'work' => 500000, 'ratio' =>  4000, 'defenses' => 60, 'requires' => array('Paved Streets','Armoury','Stone Castle','Mason'), 'conditions'=>true),
		'Fort Stone Towers		=> array('auto' =>      0, 'min' =>    400, 'work' =>  15000, 'ratio' =>  8000, 'requires' => array('Carpenter','Barracks')),
		'Fort Stone Keep		=> array('auto' =>      0, 'min' =>   5000, 'work' =>  50000, 'ratio' =>  3000, 'requires' => array('University','Temple','Master Mason','Paved Streets')),
		'Fort Stables			=> array('auto' =>      0, 'min' =>    200, 'work' =>   8000, 'ratio' =>  5000, 'requires' => array('Training Ground')),
		'Fort Market			=> array('auto' =>      0, 'min' =>   4000, 'work' =>  12000, 'ratio' =>  4000, 'requires' => array('Armourer')),
		'Fort Inn'			=> array('auto' =>      0, 'min' =>    400, 'work' =>   6000, 'ratio' =>   500, 'requires' => array('Tavern', 'Market')),
		'Fort Tavern'   		=> array('auto' =>      0, 'min' =>    300, 'work' =>   8000, 'ratio' =>  1500),
		'Fort Expanded Stores'		=> array('auto' =>   	0, 'min' =>    500, 'work' =>  25000, 'ratio' =>  2500, 'requires' => array('Carpenter', 'Mason', 'Blacksmith')),
		'Fort Fortified Stores'		=> array('auto' =>      0, 'min' =>    700, 'work' =>  15000, 'ratio' =>  5000, 'requires' => array('School','Temple')),
		'Fort Underground Stores	=> array('auto' =>      0, 'min' =>    400, 'work' =>   5000, 'ratio' =>   750, 'requires' => array('Dirt Streets'), 'icon'=>'buildings/market.png'),
	);

	private $fortresources = array(
		'Academy'               => array('wood'=>array('construction'=>12000), 'metal'=>array('construction'=>1500), 'goods'=>array('construction'=>200, 'operation'=>15), 'money'=>array('construction'=>100, 'operation'=>5)),
		'Alchemist'             => array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>500), 'goods'=>array('construction'=>100, 'operation'=>5, 'bonus'=>1), 'money'=>array('construction'=>150, 'operation'=>5, 'bonus'=>3)),
		'Archery Range'         => array('wood'=>array('construction'=>1200, 'operate'=>5), 'metal'=>array('construction'=>250, 'operate'=>5)),
		'Archery School'        => array('wood'=>array('construction'=>1600, 'operate'=>5), 'metal'=>array('construction'=>400, 'operate'=>5)),
		'Armourer'              => array('wood'=>array('construction'=>4000, 'operation'=>15), 'metal'=>array('construction'=>1500, 'operation'=>100), 'goods'=>array('provides'=>2)),
		'Armoury'               => array('wood'=>array('construction'=>3500), 'metal'=>array('construction'=>1200)),
		'Bank'                  => array('wood'=>array('construction'=>3000), 'metal'=>array('construction'=>500), 'goods'=>array('construction'=>1000), 'money'=>array('provides'=>20, 'bonus'=>6)),
		'Barracks'              => array('wood'=>array('construction'=>3000), 'metal'=>array('construction'=>400)),
		'Blacksmith'            => array('wood'=>array('construction'=>3000, 'operation'=>25), 'metal'=>array('construction'=>800, 'operation'=>80), 'goods'=>array('provides'=>8)),
		'Bladesmith'            => array('wood'=>array('construction'=>6000, 'operation'=>30), 'metal'=>array('construction'=>2500, 'operation'=>120), 'goods'=>array('operation'=>2)),
		'Bowyer'                => array('wood'=>array('construction'=>1800, 'operate'=>50), 'metal'=>array('construction'=>300, 'operate'=>10)),
		'Carpenter'             => array('wood'=>array('construction'=>2000), 'metal'=>array('construction'=>250), 'goods'=>array('provides'=>5)),
		'Citadel'               => array('wood'=>array('construction'=>25000), 'metal'=>array('construction'=>8000), 'goods'=>array('construction'=>1500, 'operation'=>10), 'money'=>array('construction'=>2500, 'operation'=>100)),
		'City Hall'             => array('wood'=>array('construction'=>10000), 'metal'=>array('construction'=>500), 'goods'=>array('construction'=>500, 'operation'=>2), 'money'=>array('construction'=>500, 'operation'=>50, 'bonus'=>10)),
		'Dirt Streets'          => array('goods'=>array('bonus'=>5), 'money'=>array('bonus'=>2)),
		'Fairground'            => array('food'=>array('bonus'=>5),'wood'=>array('construction'=>1000, 'bonus'=>5), 'metal'=>array('construction'=>100, 'bonus'=>5), 'goods'=>array('provides'=>10, 'bonus'=>10), 'money'=>array('provides'=>10, 'bonus'=>2)),
		'Fortress'              => array('wood'=>array('construction'=>20000), 'metal'=>array('construction'=>4000), 'goods'=>array('construction'=>1000, 'operation'=>5), 'money'=>array('construction'=>1000, 'operation'=>50)),
		'Garrison'              => array('wood'=>array('construction'=>4000), 'metal'=>array('construction'=>650)),
		'Great Temple'          => array('wood'=>array('construction'=>8000), 'metal'=>array('construction'=>1000), 'money'=>array('operation'=>25, 'bonus'=>8)),
		'Guardhouse'            => array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>250)),
		'Heavy Armourer'        => array('wood'=>array('construction'=>6000, 'operation'=>20), 'metal'=>array('construction'=>2500, 'operation'=>150), 'goods'=>array('provides'=>2)),
		'Inn' 				      => array('wood'=>array('construction'=>1500), 'metal'=>array('construction'=>50), 'goods'=>array('construction'=>100, 'operation'=>4)),
		'Leather Tanner'        => array('wood'=>array('construction'=>2000), 'metal'=>array('construction'=>200), 'goods'=>array('provides'=>10)),
		'Lendan Tower'  	      => array('wood'=>array('construction'=>4000), 'metal'=>array('construction'=>250), 'goods'=>array('construction'=>50), 'money'=>array('construction'=>100)),
		'Library'               => array('wood'=>array('construction'=>2000), 'metal'=>array('construction'=>100), 'money'=>array('construction'=>250, 'operation'=>5)),
		'Market'                => array('food'=>array('bonus'=>5), 'wood'=>array('construction'=>500, 'bonus'=>5), 'metal'=>array('bonus'=>5), 'goods'=>array('provides'=>10, 'bonus'=>10)),
		'Mason'                 => array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>300)),
		'Master Mason'          => array('wood'=>array('construction'=>4000), 'metal'=>array('construction'=>500), 'money'=>array('construction'=>100, 'operation'=>25)),
		'Merchants Quarter'     => array('wood'=>array('construction'=>3500), 'goods'=>array('construction'=>400, 'provides'=>10, 'bonus'=>10), 'money'=>array('construction'=>200, 'provides'=>20, 'bonus'=>12)),
		'Mill'                  => array('food'=>array('bonus'=>10), 'wood'=>array('construction'=>2000), 'metal'=>array('construction'=>200)),
		'Mine'                  => array('wood'=>array('construction'=>4000), 'metal'=>array('bonus'=>50), 'goods'=>array('construction'=>200, 'operation'=>5), 'money'=>array('bonus'=>12)),
		'Palisade'              => array('wood'=>array('construction'=>3000)),
		'Paved Streets'         => array('goods'=>array('bonus'=>5), 'money'=>array('bonus'=>3)),
		'Royal Mews'            => array('food'=>array('operation'=>150),'wood'=>array('construction'=>6000), 'metal'=>array('construction'=>500)),
		'Saddler'               => array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>400), 'goods'=>array('provides'=>2)),
		'School'                => array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>200), 'goods'=>array('operation'=>5), 'money'=>array('construction'=>100, 'operation'=>5)),
		'Shrine'                => array('wood'=>array('construction'=>1000), 'metal'=>array('construction'=>20), 'money'=>array('operation'=>10, 'bonus'=>2)),
		'Stables'               => array('food'=>array('operation'=>100), 'wood'=>array('construction'=>3500), 'metal'=>array('construction'=>200)),
		'Stone Castle'          => array('wood'=>array('construction'=>6000), 'metal'=>array('construction'=>3000), 'goods'=>array('construction'=>400), 'money'=>array('construction'=>500, 'operation'=>50)),
		'Stone Towers'          => array('wood'=>array('construction'=>3000), 'metal'=>array('construction'=>2000), 'goods'=>array('construction'=>200), 'money'=>array('construction'=>250, 'operation'=>20)),
		'Stone Wall'            => array('wood'=>array('construction'=>4000), 'metal'=>array('construction'=>1000), 'money'=>array('construction'=>100)),
		'Tailor'                => array('wood'=>array('construction'=>1500), 'metal'=>array('construction'=>50, 'operation'=>1), 'goods'=>array('provides'=>10)),
		'Tavern'				      => array('wood'=>array('construction'=>1000), 'metal'=>array('construction'=>20), 'goods'=>array('construction'=>50, 'operation'=>2)),
		'Temple'                => array('wood'=>array('construction'=>3000), 'metal'=>array('construction'=>200), 'money'=>array('operation'=>25, 'bonus'=>4)),
		'Town Hall'             => array('wood'=>array('construction'=>5000), 'metal'=>array('construction'=>300), 'money'=>array('construction'=>100, 'operation'=>50, 'bonus'=>7)),
		'Training Ground'       => array('wood'=>array('construction'=>500), 'metal'=>array('construction'=>200)),
		'University'            => array('wood'=>array('construction'=>15000), 'metal'=>array('construction'=>1000), 'goods'=>array('construction'=>250, 'operation'=>10), 'money'=>array('construction'=>120, 'operation'=>10)),
		'Weaponsmith'           => array('wood'=>array('construction'=>4000, 'operation'=>25), 'metal'=>array('construction'=>2000, 'operation'=>100), 'goods'=>array('provides'=>1)),
		'Wood Castle'           => array('wood'=>array('construction'=>6000), 'metal'=>array('construction'=>800), 'goods'=>array('construction'=>150), 'money'=>array('construction'=>200, 'operation'=>40)),
		'Wood Towers'           => array('wood'=>array('construction'=>4000), 'metal'=>array('construction'=>200), 'goods'=>array('construction'=>50), 'money'=>array('construction'=>100, 'operation'=>10)),
		'Wood Wall'             => array('wood'=>array('construction'=>5000), 'metal'=>array('construction'=>100)),

		'Fishery'               => array('wood'=>array('construction'=>800), 'metal'=>array('construction'=>100), 'goods'=>array('construction'=>50), 'food'=>array('provides'=>50, 'bonus'=>5)),
		'Lumber Yard'           => array('wood'=>array('construction'=>500, 'bonus'=>4), 'metal'=>array('construction'=>100, 'operation'=>1)),
		'Irrigation Ditches'    => array('wood'=>array('construction'=>600), 'metal'=>array('construction'=>25), 'goods'=>array('construction'=>20), 'food'=>array('provides'=>100, 'bonus'=>1)),
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
