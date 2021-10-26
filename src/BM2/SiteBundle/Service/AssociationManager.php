<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\AssociationMember;
use BM2\SiteBundle\Entity\AssociationPlace;
use BM2\SiteBundle\Entity\AssociationRank;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Place;
use Doctrine\ORM\EntityManager;


class AssociationManager {

	protected $em;
	protected $history;
	protected $descman;
	protected $convman;

	public function __construct(EntityManager $em, History $history, DescriptionManager $descman, ConversationManager $convman) {
		$this->em = $em;
		$this->history = $history;
		$this->descman = $descman;
		$this->convman = $convman;
	}

	public function create($data, Place $place, Character $founder, $superior = null) {
		$assoc = $this->_create($data['name'], $data['formal_name'], $data['type'], $data['motto'], $data['public'], $data['short_description'], $data['description'], $data['founder'], $place, $founder, $superior);

		$this->history->openLog($assoc, $founder);
		$this->history->logEvent(
			$assoc,
			'event.assoc.founded',
			array('%link-character%'=>$founder->getId()),
			History::ULTRA, true
		);
		if($data['public']) {
			$this->history->logEvent(
				$founder,
				'event.character.assoc.founded',
				array('%link-association%'=>$assoc->getId()),
				History::HIGH, true
			);
		}
		$this->em->flush();
		return $assoc;
	}

	private function _create($name, $formal, $type, $motto, $public, $short_desc, $full_desc, $founderRank, $place, Character $founder, $superior) {
		$assoc = new Association;
		$this->em->persist($assoc);
		$assoc->setName($name);
		$assoc->setFormalName($formal);
		$assoc->setType($type);
		$assoc->setMotto($motto);
		$assoc->setPublic($public);
		$assoc->setShortDescription($short_desc);

		if ($superior) {
			$assoc->setSuperior($superior);
			$superior->addCadet($assoc);
		}

		$assoc->setFounder($founder);
		$assoc->setGold(0);
		$assoc->setActive(true);
		$this->em->flush();
		$rank = $this->newRank($assoc, null, $founderRank, true, 0, 0, null, false, true, false);
		$this->newLocation($assoc, $place, true, false);
		$this->descman->newDescription($assoc, $full_desc, $founder, TRUE); #Descman includes a flush for the EM.
		$this->updateMember($assoc, $rank, $founder, true);

		return $assoc;
	}

	public function newRank($assoc, AssociationRank $myRank = null, $name, $viewAll, $viewUp, $viewDown, AssociationRank $superior=null, $createSubs, $manager, $owner = false, $flush=true) {
		$rank = new AssociationRank;
		$this->em->persist($rank);
		$rank->setAssociation($assoc);
		$this->updateRank($myRank, $rank, $name, $viewAll, $viewUp, $viewDown, $superior, $createSubs, $manager, $owner, $flush);
		if ($flush) {
			$this->em->flush();
		}
		return $rank;
	}

	public function updateRank(AssociationRank $myRank = null, $rank, $name, $viewAll, $viewUp, $viewDown, AssociationRank $superior=null, $createSubs, $manager, $owner = false, $flush=true) {
		$rank->setName($name);
		if ($myRank) {
			if ($myRank->getViewAll()) {
				$rank->setViewAll($viewAll);
				$rank->setViewUp($viewUp);
			} else {
				$rank->setViewAll(false);
				if ($superior === $myRank) {
					$rank->setViewUp($superior->getViewUp() + 1);
				} else {
					$diff = $myRank->findRankDifference($superior);
					if ($diff > 0) {
						$diff++;
						if ($viewUp > $diff) {
							$rank->setViewUp($diff);
						} else {
							$rank->setViewUp($viewUp);
						}
					} else {
						return false; #Can't edit superiors or those not in your hierarchy.
					}
				}
			}
		} else {
			$rank->setViewAll($viewAll);
			$rank->setViewUp($viewUp);
		}
		$rank->setViewDown($viewDown);
		$rank->setSubcreate($createSubs);
		$rank->setManager($manager);
		$rank->setOwner($owner);
		$rank->setSuperior($superior);
		if ($flush) {
			$this->em->flush();
		}
		return $rank;
	}

	public function newLocation($assoc, $place, $hq=false, $flush=true) {
		$loc = new AssociationPlace;
		$this->em->persist($loc);
		$loc->setAssociation($assoc);
		$loc->setPlace($place);
		if ($hq) {
			$loc->setHeadquarters(true);
		}
		if ($flush) {
			$this->em->flush();
		}
		return $loc;
	}

	public function updateMember($assoc, $rank, $char, $flush=true) {
		$member = $this->em->getRepository('BM2SiteBundle:AssociationMember')->findOneBy(["association"=>$assoc, "character"=>$char]);
		if ($member && $old->getRank() === $rank) {
			return 'no change';
		}
		$now = new \DateTime("now");
		if ($member) {
			$joinDate = $old->getJoinDate();
		} else {
			$member = new AssociationMember;
			$this->em->persist($member);
			$member->setJoinDate($now);
			$member->setAssociation($assoc);
			$member->setCharacter($char);
		}
		$member->setRankDate($now);
		$member->setRank($rank);
		if ($flush) {
			$this->em->flush();
		}
		return $member;
	}

	public function findMember(Association $assoc, Character $char) {
		$member = $this->em->getRepository('BM2SiteBundle:AssociationMember')->findOneBy(["association"=>$assoc, "character"=>$char]);
		return $member;
	}

}
