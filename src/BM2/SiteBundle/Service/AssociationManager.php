<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\AssociationDeity;
use BM2\SiteBundle\Entity\AssociationMember;
use BM2\SiteBundle\Entity\AssociationPlace;
use BM2\SiteBundle\Entity\AssociationRank;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Deity;
use BM2\SiteBundle\Entity\DeityAspect;
use BM2\SiteBundle\Entity\LawType;
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
		$assoc = $this->_create($data['name'], $data['formal_name'], $data['faith_name'], $data['follower_name'], $data['type'], $data['motto'], $data['public'], $data['short_description'], $data['description'], $data['founder'], $place, $founder, $data['superior']);

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

	private function _create($name, $formal, $faith, $follower, $type, $motto, $public, $short_desc, $full_desc, $founderRank, $place, Character $founder, $superior) {
		$assoc = new Association;
		$this->em->persist($assoc);
		$assoc->setName($name);
		$assoc->setFormalName($formal);
		$assoc->setFaithName($faith);
		$assoc->setFollowerName($follower);
		$assoc->setType($type);
		$assoc->setMotto($motto);
		$assoc->setShortDescription($short_desc);

		if ($superior) {
			$assoc->setSuperior($superior);
			$superior->addInferior($assoc);
		}

		$assoc->setFounder($founder);
		$assoc->setActive(true);
		$this->em->flush();

		$vis = $this->em->getRepository(LawType::class)->findOneBy(['category'=>'assoc', 'name'=>'assocVisibility']);
		$ranks = $this->em->getRepository(LawType::class)->findOneBy(['category'=>'assoc', 'name'=>'rankVisibility']);
		# Because I'll never remember this, these are, in order:
		# Realm/Assocation , Law Name, 'Value', Law Title, Description (fluff), allowed/disallowed, mandatory/guideline, cascades to subs, statute of limitations cycles, db flush;
		if ($public) {
			$this->lawman->updateLaw($assoc, $vis, 'assocVisibility.yes', null, null, $founder, null, true, null, null);
			$this->lawman->updateLaw($assoc, $ranks, 'rankVisibility.all', null, null, $founder, null, true, null, null);
		} else {
			$this->lawman->updateLaw($assoc, $vis, 'assocVisibility.no', null, null, $founder, null, true, null, null);
			$this->lawman->updateLaw($assoc, $ranks, 'rankVisibility.direct', null, null, $founder, null, true, null, null);
		}
		$rank = $this->newRank($assoc, null, $founderRank, true, 0, 0, true, null, true, true, true, true, true, false);
		$this->newLocation($assoc, $place, true, false);
		$this->descman->newDescription($assoc, $full_desc, $founder, TRUE); #Descman includes a flush for the EM.
		$this->updateMember($assoc, $rank, $founder, true);

		return $assoc;
	}

	public function update($assoc, $data, $char) {
		if ($assoc->getName() !== $data['name']) {
			$assoc->setName($data['name']);
		}
		if ($assoc->getFormalName() !== $data['formal_name']) {
			$assoc->setFormalName($data['formal_name']);
		}
		if ($assoc->getFaithName() !== $data['faith_name']) {
			$assoc->setFaithName($data['faith_name']);
		}
		if ($assoc->getFollowerName() !== $data['follower_name']) {
			$assoc->setFollowerName($data['follower_name']);
		}
		if ($assoc->getType() !== $data['type']) {
			$assoc->setType($data['type']);
		}
		if ($assoc->getMotto() !== $data['motto']) {
			$assoc->setMotto($data['motto']);
		}
		if ($assoc->getShortDescription() !== $data['short_description']) {
			$assoc->setShortDescription($data['short_description']);
		}

		if ($assoc->getSuperior() !== $data['superior']) {
			if ($assoc->getSuperior()) {
				$assoc->getSuperior()->removeInferior($assoc);
			}
			$assoc->setSuperior($data['superior']);
			$data['superior']->addInferior($assoc);
		}
		if ($assoc->getDescription()->getText() != $data['description']) {
			$this->descman->newDescription($assoc, $data['description'], $char); #Descman includes a flush for the EM.
		}


		return $assoc;
	}

	public function newRank($assoc, AssociationRank $myRank = null, $name, $viewAll, $viewUp, $viewDown, $viewSelf, AssociationRank $superior=null, $build, $createSubs, $manager, $createAssocs, $owner = false, $flush=true) {
		$rank = new AssociationRank;
		$this->em->persist($rank);
		$rank->setAssociation($assoc);
		$this->updateRank($myRank, $rank, $name, $viewAll, $viewUp, $viewDown, $viewSelf, $superior, $build, $createSubs, $manager, $createAssocs, $owner, $flush);
		if ($flush) {
			$this->em->flush();
		}
		return $rank;
	}

	public function updateRank(AssociationRank $myRank = null, AssociationRank $rank, $name, $viewAll, $viewUp, $viewDown, $viewSelf, AssociationRank $superior=null, $build, $createSubs, $manager, $createAssocs, $owner = false, $flush=true) {
		$rank->setName($name);
		if ($myRank) {
			if ($myRank->getViewAll()) {
				$rank->setViewAll($viewAll);
				$rank->setViewUp($viewUp);
			} else {
				$rank->setViewAll(false);
				if ($superior === $myRank) {
					$rank->setViewUp($superior->getViewUp() + 1);
					$rank->setSuperior($superior);
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
				$rank->setBuild($build);
			} else {
				$rank->setOwner(false);
				if ($myRank->getManager()) {
					$rank->setManager($manager);
				} else {
					$rank->setManager(false);
				}
				if ($myRank->getSubcreate()) {
					$rank->setSubcreate($createAssocs);
				} else {
					$rank->setSubcreate(false);
				}
				if ($myRank->getBuild()) {
					$rank->setBuild($build);
				} else {
					$rank->setBuild(false);
				}
			}
			$rank->setViewDown($viewDown);
			$rank->setViewSelf($viewSelf);
			$rank->setSuperior($superior);
		} else {
			# No creator rank, must be a new association, assume all inputs correct.
			$rank->setViewAll($viewAll);
			$rank->setViewUp($viewUp);
			$rank->setViewDown($viewDown);
			$rank->setViewSelf($viewSelf);
			$rank->setBuild($build);
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

	public function removeLocation($assoc, $place, $flush=true) {
		$loc = $this->em->getRepository('BM2SiteBundle:AssociationPlace')->findOneBy(["association"=>$assoc, "place"=>$place]);
		if ($loc) {
			$this->em->remove($loc);
			if ($flush) {
				$this->em->flush();
			}
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

	public function removeMember(Association $assoc, Character $char) {
		$member = $this->em->getRepository('BM2SiteBundle:AssociationMember')->findOneBy(["association"=>$assoc, "character"=>$char]);
		if ($member) {
			$this->em->remove($member);
			$this->em->flush();
			foreach ($assoc->getConversations() as $conv) {
				if ($perm = $conv->findActiveCharPermission($char)) {
					$perm->setActive(FALSE);
					$perm->setEndTime(new \DateTime("now"));
				}
			}
			if ($assoc->getMembers()->count() == 0) {
				# Collapsed.
				$assoc->setActive(false);
				foreach ($assoc->getPlaces() as $place) {
					$this->history->logEvent(
						$place->getPlace(),
						'event.place.assoc.collapsed',
						array('%link-assoc%'=>$assoc->getId()),
						History::HIGH, true
					);
					$this->em->remove($place);
				}
				foreach ($assoc->getRecognizedDeities() as $deity) {
					$deity->setMainRecognizer(NULL);
				}

				$this->history->logEvent(
					$assoc,
					'event.assoc.collapsed',
					array(),
					History::ULTRA, true
				);
			}
			$this->em->flush();
		}
	}

	public function findMember(Association $assoc, Character $char) {
		$member = $this->em->getRepository('BM2SiteBundle:AssociationMember')->findOneBy(["association"=>$assoc, "character"=>$char]);
		return $member;
	}

	public function findDeity(Association $assoc, Deity $deity) {
		$result = $this->em->getRepository('BM2SiteBundle:AssociationDeity')->findOneBy(["association"=>$assoc, "deity"=>$deity]);
		return $result;
	}

	public function newDeity(Association $assoc, Character $char, $data) {
		$deity = new Deity();
		$this->em->persist($deity);
		$deity->setMainRecognizer($assoc);
		$deity->setName($data['name']);
		foreach ($data['aspects'] as $each) {
			$aspect = new DeityAspect();
			$this->em->persist($aspect);
			$aspect->setAspect($each);
			$aspect->setDeity($deity);
		}
		$this->descman->newDescription($deity, $data['description'], $char, TRUE); #Descman includes a flush for the EM.
		$this->addDeity($assoc, $deity, $char, $data['words']);
	}

	public function updateDeity(Deity $deity, Character $char, $data) {
		if ($deity->getName() !== $data['name']) {
			$deity->setName($data['name']);
		}
		$list = [];
		foreach ($deity->getAspects() as $old) {
			$list[] = $old->getAspect();
			if (!in_array($old->getAspect(), $data['aspects'])) {
				$this->em->remove($old);
			}
		}
		foreach ($data['aspects'] as $new) {
			if (!in_array($new, $list)) {
				$aspect = new DeityAspect();
				$this->em->persist($aspect);
				$aspect->setAspect($new);
				$aspect->setDeity($deity);
			}
		}
		if ($deity->getDescription()->getText() !== $data['description']) {
			$this->descman->newDescription($deity, $data['description'], $char, TRUE); #Not new, but we pass it to save processing time.
		}
		$this->em->flush();
		return $deity;
	}

	public function adoptDeity(Association $assoc, Deity $deity, Character $char) {
		$deity->setMainRecognizer($assoc);
		$this->history->logEvent(
			$assoc,
			'event.assoc.deity.adopted',
			['%link-character%'=>$char->getId(), '%link-deity%'=>$deity->getId()],
			History::HIGH, true
		);
		$this->em->flush();
	}

	public function addDeity(Association $assoc, Deity $deity, Character $char, $words = null) {
		$aDeity = new AssociationDeity();
		$this->em->persist($aDeity);
		$aDeity->setAssociation($assoc);
		$aDeity->setDeity($deity);
		if ($words) {
			$aDeity->setWords($words);
			$aDeity->setWordsTimestamp(new \DateTime("now"));
			$aDeity->setWordsFrom($char);
		}
		$this->history->logEvent(
			$assoc,
			'event.assoc.deity.recognized',
			['%link-character%'=>$char->getId(), '%link-deity%'=>$deity->getId()],
			History::HIGH, true
		);
		$this->em->flush();
	}

	public function removeDeity(Association $assoc, Deity $deity, Character $char) {
		$aDeity = $this->em->getRepository('BM2SiteBundle:AssociationDeity')->findOneBy(['association'=>$assoc, 'deity'=>$deity]);
		$this->em->remove($aDeity);
		if ($aDeity->getDeity()->getMainRecognizer() === $assoc) {
			$aDeity->getDeity()->setMainRecognizer(null);
		}
		$this->history->logEvent(
			$assoc,
			'event.assoc.deity.removed',
			['%link-character%'=>$char->getId(), '%link-deity%'=>$deity->getId()],
			History::HIGH, true
		);
		$this->em->flush();
	}

}
