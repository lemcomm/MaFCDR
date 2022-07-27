<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Activity;
use BM2\SiteBundle\Entity\ActivityType;
use BM2\SiteBundle\Entity\ActivityParticipant;
use BM2\SiteBundle\Entity\ActivityBout;
use BM2\SiteBundle\Entity\ActivityGroup;
use BM2\SiteBundle\Entity\ActivityBoutGroup;
use BM2\SiteBundle\Entity\ActivityBoutParticipant;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Skill;
use BM2\SiteBundle\Entity\SkillType;

use Doctrine\ORM\EntityManager;

/*
As you might expect, ActivityManager handles Activities.
*/

class ActivityManager {

	private $em;
	private $geo;

	public function __construct(EntityManager $em, Geography $geo) {
		$this->em = $em;
		$this->geo = $geo;
	}

	/*
	HELPER FUNCTIONS
	*/

        public function verify(ActivityType $act, Character $char) {
		$valid = True;
		$reqs = $act->getRequires();
		if (!$reqs->isEmpty()) {
			# ActivityRequirements will always have ither places or buildings or both, if the activity has requirements.
			# Buildings require all to be present, so we set $hasBldgs to True, while Place only requires any to be owned, so we default to false.
			$hasBldgs = True;
			$hasPlace = False;
			foreach ($reqs as $req) {
				# If the requirement has a building type, as $hasBldgs is still true, we check. If getBuilding is null this one is for a place,
				# and if $hasBldgs is false, then we've already failed the verification.
				if ($bldg = $req->getBuilding() && $hasBldgs) {
					if ($char->getInsideSettlement() && !$char->getInsideSettlement()->getBuildingByName($bldg)) {
						$hasBldgs = False;
					}
				}
				# If getPlace is null, this requirement is for a building.
				# If $hasPlace is True, then we've already passed this check.
				if ($place = $req->getPlace() && !$hasPlace) {
					$inPlace = $char->getInsidePlace();
					if ($inPlace && $inPlace->getType()->getName() == $place && $inPlace->getOwner() == $char) {
						$hasPlace = True;
					}
				}
			}
			# Since all activities that have requirements require a place both $hasPlace and $hasBldgs should be true for this to verify.
			if (!$hasPlace || !$hasBldgs) {
				$valid = False;
			}
		}
		return $valid;
	}

        public function create(ActivityType $type, ActivitySubType $subType=null, Character $char, Activity $mainAct = null) {
		if (!$type->getEnabled()) {
			return False;
		}
		if ($this->verify($type, $char)) {
			$now = new \DateTime("now");
			$act = new Activity();
			$this->em->persist($act);
			$act->setType($type);
			$act->setSubType($subType);
			if ($place = $char->getInsidePlace()) {
				$act->setPlace($place);
				$act->setGeoData($place->getGeoData());
			} elseif ($settlement = $char->getInsideSettlement()) {
				$act->setSettlement($settlement);
				$act->setGeoData($settlement->getGeoData());
			} else {
				$act->setLocation($char->getLocation());
				$act->setGeoData($this->geo->findMyRegion($char));
			}
			$act->setMainEvent($mainAct);
			$act->setCreated($now);
			return $act;
		} else {
			return False;
		}
        }

	public function createBout(Activity $act, ActivityType $type, $same=true, $accepted = true, $round=null) {
		$bout = new ActivityBout();
		$this->em->persist($bout);
		$bout->setActivity($act);
		$bout->setType($type);
		$bout->setNumber($round);
		return $bout;
	}

	public function createParticipant(Activity $act, Character $char, Style	$style=null, $weapon=null, $same=false) {
		$part = new ActivityParticipant();
		$this->em->persist($part);
		$part->setActivity($act);
		$part->setCharacter($char);
		$part->setStyle($style);
		$part->setWeapon($weapon);
		if ($same) {
			$part->setAccepted(true);
		}
		return $part;
	}

	public function createGroup(Activity $act, $participants) {
		# $participants should be an array or arraycollection of ActivityParticipant objects.
		$group = new ActivityGroup();
		$this->em->persist($group);
		$group->setActivity($act);
		foreach ($participants as $part) {
			$part->setGroup($group);
		}
		return $group;
	}

	public function createBoutParticipant(ActivityBout $bout, ActivityParticipant $part) {
		$boutPart = new ActivityBoutParticipant();
		$this->em->persist($boutPart);
		$boutPart->setBout($bout);
		$boutPart->setParticipant($part);
		return $boutPart;
	}

	public function createBoutGroup(ActivityBout $bout, ActivityGroup $group) {
		$boutGroup = new ActivityBoutGroup();
		$this->em->persist($boutGroup);
		$boutGroup->setBout($bout);
		$boutGroup->setParticipant($group);
		return $boutGroup;
	}

	/*
	ACTIVITY CREATE FUNCTIONS
	*/

	public function createDuel(Character $me, Character $them, $name=null, $same, EquipmentType $weapon, Style $meStyle = null, Style $themStyle = null) {
		$type = $em->getRepository('BM2SiteBundle:ActivityType')->findOneBy(['name'=>'duel']);
		if ($act = $this->create($type, null, $char)) {
			if (!$name) {
				$act->setName('Duel between '.$char->getName().' and '.$recip->getName());
			} else {
				$act->setName($name);
			}
			$act->setSame($same);

			$mePart = $this->createParticipant($act, $me, $meStyle, $weapon, $same);
			$themPart = $this->createParticipant($act, $them, $themStyle);

			$this->em->flush();
			return $act;
		} else {
			return 'Verification check failed.';
		}
	}

	/*
	ACTIVITY DELETE FUNCTIONS
	*/

	public function refuseDuel($act) {
		if ($act->getType->getName() === 'duel') {
			foreach ($act->getParticipants() as $p) {
				$this->em->remove($p);
			}
			$this->em->remove($act->getBouts()->first());
			$this->em->remove($act);
			$this->em->flush();
			return true;
		}
		return false;
	}

	/*
	SKILL FUNCTIONS
	*/

	public function trainSkill(Character $char, SkillType $type=null, $pract = 0, $theory = 0) {
		if (!$type) {
			# Not all weapons have skills, this just catches those.
			return false;
		}
		$training = false;
		foreach ($char->getSkills() as $skill) {
			if ($skill->getType() === $type) {
				$training = $skill;
				break;
			}
		}
		if ($pract && $pract < 1) {
			$pract = 1;
		} elseif ($pract) {
			$pract = round($pract);
		}
		if ($theory && $theory < 1) {
			$theory = 1;
		} elseif ($theory) {
			$theory = round($theory);
		}
		if (!$training) {
			$training = new Skill();
			$this->em->persist($training);
			$training->setCharacter($char);
			$training->setType($type);
			$training->setCategory($type->getCategory());
			$training->setPractice($pract);
			$training->setTheory($theory);
			$training->setPracticeHigh($pract);
			$training->setTheoryHigh($theory);
		} else {
			if ($pract) {
				$newPract = $training->getPractice() + $pract;
				$training->setPractice($newPract);
				if ($newPract > $training->getPracticeHigh()) {
					$training->setPracticeHigh($newPract);
				}
			}
			if ($theory) {
				$newTheory = $training->getTheory() + $theory;
				$training->getTheory($newTheory);
				if ($newTheory > $training->getTheoryHigh()) {
					$training->setTheoryHigh($newTheory);
				}
			}
		}
		$training->setUpdated(new \DateTime('now'));
		$this->em->flush();
	}

}
