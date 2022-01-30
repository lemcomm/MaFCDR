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
	protected $lawman;

	public function __construct(EntityManager $em, History $history, DescriptionManager $descman, ConversationManager $convman, LawManager $lawman) {
		$this->em = $em;
		$this->history = $history;
		$this->descman = $descman;
		$this->convman = $convman;
		$this->lawman = $lawman;
	}

	public function create($data, Place $place, Character $founder, $superior = null) {
		$assoc = $this->_create($data['name'], $data['formal_name'], $data['type'], $data['motto'], $data['public'], $data['short_description'], $data['description'], $data['founder'], $place, $founder, $data['superior']);

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
		$topic = $assoc->getName().' Announcements';
		$this->convman->newConversation(null, null, $topic, null, null, $assoc, 'announcements');
		$topic = $assoc->getName().' General Discussion';
		$this->convman->newConversation(null, null, $topic, null, null, $assoc, 'general');
		return $assoc;
	}

	private function _create($name, $formal, $type, $motto, $public, $short_desc, $full_desc, $founderRank, $place, Character $founder, $superior) {
		$assoc = new Association;
		$this->em->persist($assoc);
		$assoc->setName($name);
		$assoc->setFormalName($formal);
		$assoc->setType($type);
		$assoc->setMotto($motto);
		$assoc->setShortDescription($short_desc);

		if ($superior) {
			$assoc->setSuperior($superior);
			$superior->addCadet($assoc);
		}

		$assoc->setFounder($founder);
		$assoc->setGold(0);
		$assoc->setActive(true);
		$this->em->flush();

		# Because I'll never remember this, these are, in order:
		# Realm/Assocation , Law Name, 'Value', Law Title, Description (fluff), allowed/disallowed, mandatory/guideline, cascades to subs, statute of limitations cycles, db flush;
		if ($public) {
			$lawman->updateLaw($assoc, 'assocVisibility', 'true', null, null, $founder, null, true, null, null);
			$lawman->updateLaw($assoc, 'rankVisibility', 'all', null, null, $founder, null, true, null, null);
		} else {
			$lawman->updateLaw($assoc, 'assocVisibility', 'false', null, null, $founder, null, true, null, null);
			$lawman->updateLaw($assoc, 'rankVisibility', 'direct', null, null, $founder, null, true, null, null);
		}
		$rank = $this->newRank($assoc, null, $founderRank, true, 0, 0, true, null, true, true, true, false);
		$this->newLocation($assoc, $place, true, false);
		$this->descman->newDescription($assoc, $full_desc, $founder, TRUE); #Descman includes a flush for the EM.
		$this->updateMember($assoc, $rank, $founder, true);

		return $assoc;
	}

	public function newRank($assoc, AssociationRank $myRank = null, $name, $viewAll, $viewUp, $viewDown, $viewSelf, AssociationRank $superior=null, $createSubs, $manager, $owner = false, $flush=true) {
		$rank = new AssociationRank;
		$this->em->persist($rank);
		$rank->setAssociation($assoc);
		$this->updateRank($myRank, $rank, $name, $viewAll, $viewUp, $viewDown, $viewSelf, $superior, $createSubs, $manager, $owner, $flush);
		if ($flush) {
			$this->em->flush();
		}
		return $rank;
	}

	public function updateRank(AssociationRank $myRank = null, AssociationRank $rank, $name, $viewAll, $viewUp, $viewDown, $viewSelf, AssociationRank $superior=null, $createSubs, $manager, $createAssocs, $owner = false, $flush=true) {
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
			if ($myRank->getOwner()) {
				$rank->setOwner($owner);
				$rank->setManager($manager);
			} else {
				$rank->setOwner(false);
				if ($myRank->getManager()) {
					$rank->setManager($manager);
				} else {
					$rank->setManager(false);
				}
				if ($myRank->getCreateAssocs()) {
					$rank->setCreateAssocs($createAssocs);
				} else {
					$rank->setCreateAssocs(false);
				}
			}
		} else {
			# No creator rank, must be a new association, assume all inputs correct.
			$rank->setViewAll($viewAll);
			$rank->setViewUp($viewUp);
			$rank->setViewDown($viewDown);
			$rank->setViewSelf($viewSelf);
			$rank->setSubcreate($createSubs);
			$rank->setCreateAssocs($createAssocs);
			$rank->setManager($manager);
			$rank->setOwner($owner);
			$rank->setSuperior($superior);
		}
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

	public function updateMember($assoc, $rank=null, $char, $flush=true) {
		$member = $this->em->getRepository('BM2SiteBundle:AssociationMember')->findOneBy(["association"=>$assoc, "character"=>$char]);
		if ($member && $rank && $member->getRank() === $rank) {
			return 'no change';
		}
		$now = new \DateTime("now");
		if (!$member) {
			$member = new AssociationMember;
			$this->em->persist($member);
			$member->setJoinDate($now);
			$member->setAssociation($assoc);
			$member->setCharacter($char);
		}
		if ($rank) {
			$member->setRankDate($now);
			$member->setRank($rank);
		}
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
