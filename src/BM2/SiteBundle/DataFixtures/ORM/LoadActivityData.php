<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\ActivityType;
use BM2\SiteBundle\Entity\ActivityRequirement;
use BM2\SiteBundle\Entity\BuildingType;
use BM2\SiteBundle\Entity\PlaceType;


class LoadActivityData extends AbstractFixture implements OrderedFixtureInterface {

	private $types = array(
		'duel'			=> ['enabled' => True],
		'arena'			=> ['enabled' => False, 'buildings' => ['arena', 'tournament'], 'places' => ['arena', 'tournament']],
		'melee tournament'	=> ['enabled' => False, 'buildings' => ['arena', 'tournament'], 'places' => ['arena', 'tournament']],
		'joust'			=> ['enabled' => False, 'places' => ['tournament']],
		'grand tournament'	=> ['enabled' => False, 'buildings' => ['arena', 'archery range'], 'places' => ['tournament']],
		'race'			=> ['enabled' => False, 'buildings' => ['race track'], 'places' => ['track']],
		'hunt'			=> ['enabled' => False, 'buildings' => ['hunters lodge'], 'places' => ['tournament']],
		'ball'			=> ['enabled' => False, 'places' =>['home', 'capital', 'castle', 'embassy']],
	);

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 15; // Must be after Buildings (1), Places (1), and Activities (1).
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		foreach ($this->types as $name=>$data) {
			$type = $manager->getRepository(ActivityType::class)->findOneBy(['name'=>$name]);
			if (!$type) {
				$type = new ActivityType();
				$manager->persist($type);
				$type->setName($name);
			}
			$type->setEnabled($data['enabled']);
			$manager->flush();
			if ($type) {
				$id = $type->getId();
				if ($data['buildings']) {
					foreach ($data['buildings'] as $bldg) {
						$bldgType = $manager->getRepository(BuildingType::class)->findOneBy(['name'=>$bldg]);
						if ($bldgType) {
							$req = $manager->getRepository(ActivityRequirement::class)->findOneBy(['type'=>$id, 'building'=>$bldgType->getId()]);
							if (!$req) {
								$req = new ActivityRequirement();
								$manager->persist($req);
								$req->setType($id);
								$req->setBuilding($bldgType);
							}
						} else {
							echo 'No Building Type found matching string of '.$bldg.', loading skipped.';
						}
					}
				}
				if ($data['places']) {
					foreach ($data['places'] as $place) {
						$placeType = $manager->getRepository(PlaceType::class)->findOneBy(['name'=>$place]);
						if ($placeType) {
							$req = $manager->getRepository(ActivityRequirement::class)->findOneBy(['type'=>$id, 'place'=>$placeType->getId()]);
							if (!$req) {
								$req = new ActivityRequirement();
								$manager->persist($req);
								$req->setType($id);
								$req->setPlace($placeType);
							}
						} else {
							echo 'No Place Type found matching string of '.$place.', loading skipped.';
						}
					}
				}
			} else {
				echo 'No Activty Type found matching string of '.$name.', loading skipped.';
			}
		}
		$manager->flush();
	}
}
