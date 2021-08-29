<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\ItemType;


class LoadItemData extends AbstractFixture implements OrderedFixtureInterface {

	private $items = array(
		'short sword'			=> ['type' => '1HSword', 'slot' => 'hands',],
		'rapier'			=> ['type' => '1HSword', 'slot' => 'hands',],
		'scimitar'			=> ['type' => '1HSword', 'slot' => 'hands',],
		'backsword'			=> ['type' => '1HSword', 'slot' => 'hands',],
		'longsword'			=> ['type' => '2HSword', 'slot' => 'hands',],
		'greatsword'			=> ['type' => '2HSword', 'slot' => 'hands',],
		'halberd'			=> ['type' => 'polearm', 'slot' => 'hands',],
		'pike'				=> ['type' => 'polearm', 'slot' => 'hands',],
		'guangdao'			=> ['type' => 'polearm', 'slot' => 'hands',],
		'naginata'			=> ['type' => 'polearm', 'slot' => 'hands',],
		'swordstaff'			=> ['type' => 'polearm', 'slot' => 'hands',],
		'guangdao'			=> ['type' => 'polearm', 'slot' => 'hands',],
		'battle axe'			=> ['type' => '1haxe', 'slot' => 'hands',],
		'gloves'			=> ['type' => 'gloves', 'slot' => 'hands',],
		'dagger'			=> ['type' => 'dagger', 'slot' => 'hands',],
		'baselard'			=> ['type' => 'dagger', 'slot' => 'hands',],
		'stiletto'			=> ['type' => 'dagger', 'slot' => 'hands',],
		'misericorde'			=> ['type' => 'dagger', 'slot' => 'hands',],
		'cudgel'			=> ['type' => 'club', 'slot' => 'hands',],
		'mace'				=> ['type' => 'club', 'slot' => 'hands',],
		'morning star'			=> ['type' => 'club', 'slot' => 'hands',],
		'nunchaku'			=> ['type' => 'club', 'slot' => 'hands',],
		'blackjack'			=> ['type' => 'club', 'slot' => 'hands',],
		'stick'				=> ['type' => 'club', 'slot' => 'hands',],
		'cudgel'			=> ['type' => 'club', 'slot' => 'hands',],
		'agricultural flail'		=> ['type' => 'flail', 'slot' => 'hands',],
		'mace and chain'		=> ['type' => 'flail', 'slot' => 'hands',],
		'war hammer'			=> ['type' => 'hammer', 'slot' => 'hands',],
		'maul'				=> ['type' => 'hammer', 'slot' => 'hands',],
		'bow'				=> ['type' => 'bow', 'slot' => 'hands',],
		'crossbow'			=> ['type' => 'crossbow', 'slot' => 'hands',],
		'throwing axe'			=> ['type' => 'thrown', 'slot' => 'hands',],
		'sling'				=> ['type' => 'sling', 'slot' => 'hands',],
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
		foreach ($this->items as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:ItemType')->findOneByName($name);
			if (!$type) {
				$type = new ItemType();
				$manager->persist($type);
			}
			$type->setName($name);
			$type->setType($data['type']);
			$type->setSlot($data['slot']);
			$manager->persist($type);
		}
		$manager->flush();
	}
}
