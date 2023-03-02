<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\PlaceType;
use BM2\SiteBundle\Entity\PlaceSubType;
use BM2\SiteBundle\Entity\PlaceUpgradeType;

class LoadPlaceData extends AbstractFixture implements OrderedFixtureInterface {

	private $placetypes = array(
		'academy'	=> array('requires' => 'academy',	'visible' => false,	'defensible'=>true,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'arena'		=> array('requires' => 'arena',		'visible' => true,	'defensible'=>true,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'blacksmith'	=> array('requires' => 'smith',		'visible' => true,	'defensible'=>true,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'bridge'	=> array('requires' => '',		'visible' => false,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>false,	'pop' => 0),
		'capital'	=> array('requires' => 'ruler',		'visible' => true,	'defensible'=>true,	'public'=>false, 'assocs'=>false,	'spawnable'=>true,	'vassals'=>false,	'pop' => 0),
		'castle'	=> array('requires' => 'castle',	'visible' => true,	'defensible'=>true,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'cave'		=> array('requires' => 'outside',	'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>false,	'pop' => 0),
		'collective'	=> array('requires' => '',		'visible' => true,	'defensible'=>true,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'embassy'	=> array('requires' => 'ambassador',	'visible' => true,	'defensible'=>true,	'public'=>false, 'assocs'=>false,	'spawnable'=>false,	'vassals'=>true,	'pop' => 2),
		'farm'		=> array('requires' => 'outside',	'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'fort'		=> array('requires' => 'outside',	'visible' => true,	'defensible'=>true,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'guild house'	=> array('requires' => 'guilds',	'visible' => true,	'defensible'=>true,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'hall'		=> array('requires' => '',		'visible' => true,	'defensible'=>true,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'home'		=> array('requires' => '',		'visible' => true,	'defensible'=>true,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'inn'		=> array('requires' => 'inn',		'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'intersection'	=> array('requires' => '',		'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>false,	'pop' => 0),
		'library'	=> array('requires' => '',		'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'list field'	=> array('requires' => 'list field',	'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'lumber yard'	=> array('requires' => 'forested',	'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'mine'		=> array('requires' => 'metals',	'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'monument'	=> array('requires' => 'lord',		'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>false,	'spawnable'=>true,	'vassals'=>false,	'pop' => 0),
		'plaza'		=> array('requires' => 'lord',		'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>false,	'spawnable'=>true,	'vassals'=>false,	'pop' => 0),
		'port'		=> array('requires' => 'docks',		'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'portal' 	=> array('requires' => 'magic',		'visible' => false,	'defensible'=>false,	'public'=>false, 'assocs'=>false,	'spawnable'=>false,	'vassals'=>false,	'pop' => 0),
		'passage'	=> array('requires' => 'warren',	'visible' => false,	'defensible'=>false,	'public'=>false, 'assocs'=>false,	'spawnable'=>true,	'vassals'=>false,	'pop' => 0),
		'quarry'	=> array('requires' => 'stone',		'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'signpost'	=> array('requires' => '',		'visible' => false,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>false,	'pop' => 0),
		'temple'	=> array('requires' => 'temple',	'visible' => true,	'defensible'=>true,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'track'		=> array('requires' => 'track',		'visible' => true,	'defensible'=>false,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'tavern'	=> array('requires' => 'tavern',	'visible' => true,	'defensible'=>false,	'public'=>true,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'tournament'	=> array('requires' => 'tournament',	'visible' => true,	'defensible'=>false,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 10),
		'warehouse'	=> array('requires' => 'warehouse',	'visible' => true,	'defensible'=>false,	'public'=>false, 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
		'watchtower'	=> array('requires' => 'outside',	'visible' => false,	'defensible'=>true,	'public'=>false,	 'assocs'=>true,	'spawnable'=>true,	'vassals'=>true,	'pop' => 0),
	);

	private $placesubtypes = array(
		'memorial'	=> array('type' =>	'monument'),
		'statue'	=> array('type' =>	'monument'),
		'obelisk'	=> array('type' =>	'monument'),
		'tomb'		=> array('type' =>	'monument'),
		'market'	=> array('type' =>	'plaza'),
		'scenic'	=> array('type' =>	'plaza'),
		'event'		=> array('type' =>	'plaza'),
		'personal'	=> array('type' =>	'house'),
		'dynastic'	=> array('type' =>	'house'),
	);

	private $placeupgradetypes = array(
		'horses'		=> array('type' =>	'track'),
		'chariot'		=> array('type' =>	'track'),
		'small cages'		=> array('type' =>	'arena'),
		'large cages'		=> array('type' =>	'arena', 'requires' =>	'small cages'),
		'traps'			=> array('type' =>	'arena'),
		'melee'			=> array('type' =>	'tournament',	'pop' =>	10),
		'ranged'		=> array('type' =>	'tournament',	'pop' =>	10),
		'joust'			=> array('type' =>	'tournament',	'pop' =>	10),
		'small guard'		=> array('type' =>	'embassy',	'pop' =>	10),
		'medium guard'		=> array('type' =>	'embassy',	'pop' =>	15, 'requires' =>	'small guard'),
		'large guard'		=> array('type' =>	'embassy',	'pop' =>	25, 'requires' =>	'medium guard'),
		'local guard'		=> array('type' =>	'embassy',	'pop' =>	25),
		'regional guard'	=> array('type' =>	'embassy',	'pop' =>	50, 'requires' =>	'local guard'),
		'royal guard'		=> array('type' =>	'embassy',	'pop' =>	125, 'requires' =>	'regional guard'),
		'imperial guard'	=> array('type' =>	'embassy',	'pop' =>	200, 'requires' =>	'royal guard')
	);

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 1; // or anywhere, really
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		# Load place types.
		foreach ($this->placetypes as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:PlaceType')->findOneByName($name);
			if (!$type) {
				$type = new PlaceType();
				$manager->persist($type);
			}
			$type->setName($name);
			if ($data['requires']) {
				$type->setRequires($data['requires']);
			}
			$type->setPublic($data['public']);
			$type->setVisible($data['visible']);
			$type->setVassals($data['vassals']);
			$type->setSpawnable($data['spawnable']);
			$type->setDefensible($data['defensible']);
			$type->setAssociations($data['assocs']);
			$manager->persist($type);
		}
		$manager->flush();

		# Load Place subtypes.
		foreach ($this->placesubtypes as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:PlaceSubType')->findOneByName($name);
			if (!$type) {
				$type = new PlaceSubType();
				$manager->persist($type);
			}
			$type->setName($name);
			$type->setPlaceType($manager->getRepository('BM2SiteBundle:PlaceType')->findOneByName($data['type']));
			$manager->persist($type);
		}
		# Load Place upgrades.
		foreach ($this->placeupgradetypes as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:PlaceUpgradeType')->findOneByName($name);
			if (!$type) {
				$type = new PlaceUpgradeType();
				$manager->persist($type);
			}
			$type->setName($name);
			$type->setPlaceType($manager->getRepository('BM2SiteBundle:PlaceType')->findOneByName($data['type']));
			if (array_key_exists('requires', $data)) {
				$type->setRequires($data['requires']);
			}
			/*if (array_key_exists('pop', $data)) {
				$type->setWorkers($data['pop']);
			} else {
				$type->setWorkers(0);
			}*/
			$manager->persist($type);
		}
		$manager->flush();
	}
}
