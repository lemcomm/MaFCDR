<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Activity;
use BM2\SiteBundle\Entity\ActivityType;
use BM2\SiteBundle\Entity\ActivityParticipant;
use BM2\SiteBundle\Entity\ActivityBout;
use BM2\SiteBundle\Entity\ActivityGroup;
use BM2\SiteBundle\Entity\ActivityBoutGroup;
use BM2\SiteBundle\Entity\ActivityBoutParticipant;
use BM2\SiteBundle\Entity\ActivityReport;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Skill;
use BM2\SiteBundle\Entity\SkillType;

use Doctrine\ORM\EntityManager;
use Monolog\Logger;

/*
As you might expect, ActivityManager handles Activities.
*/

class ActivityManager {

	private $em;
	private $geo;
	private $helper;
	private $logger;
	private $combat;

	private $debug=0;

	public function __construct(EntityManager $em, Geography $geo, HelperService $helper, Logger $logger, CombatManager $combat) {
		$this->em = $em;
		$this->geo = $geo;
		$this->helper = $helper;
		$this->logger = $logger;
		$this->combat = $combat;
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

	public function createParticipant(Activity $act, Character $char, Style	$style=null, $weapon=null, $same=false, $organizer) {
		$part = new ActivityParticipant();
		$this->em->persist($part);
		$part->setActivity($act);
		$part->setCharacter($char);
		$part->setStyle($style);
		$part->setWeapon($weapon);
		$part->setOrganizer($organizer);
		if ($same) {
			$part->setAccepted(true);
		} else {
			$part->setAccepted(false);
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

	public function log($level, $text) {
		if ($this->report) {
			$this->report->setDebug($this->report->getDebug().$text);
		}
		if ($level <= $this->debug) {
			$this->logger->info($text);
		}
	}

	/*
	ACTIVITY CREATE FUNCTIONS
	*/

	public function createDuel(Character $me, Character $them, $name=null, $level, $same, EquipmentType $weapon, Style $meStyle = null, Style $themStyle = null) {
		$type = $em->getRepository('BM2SiteBundle:ActivityType')->findOneBy(['name'=>'duel']);
		if ($act = $this->create($type, null, $char)) {
			if (!$name) {
				$act->setName('Duel between '.$char->getName().' and '.$recip->getName());
			} else {
				$act->setName($name);
			}
			$act->setSame($same);
			$act->setWeaponOnly($weaponOnly);
			$act->setSubType($em->getRepository('BM2SiteBundle:ActivitySubType')->findOneBy(['name'=>$level]));

			$mePart = $this->createParticipant($act, $me, $meStyle, $weapon, $same, true);
			$themPart = $this->createParticipant($act, $them, $themStyle, false);

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
	ACTIVITY RUNNING FUNCTIONS
	*/

	public function run(Activity $act) {
		$type = $act->getType()->getName();
		if ($type === 'duel') {
			return $this->runDuel($act);
		}
		return 'typeNotFound';
	}

	private function runDuel(Activity $act) {
		$em = $this->em;
		$me = $act->findChallenger();
		$meC = $me->getCharacter();
		$them = $act->findChallenged();
		$themC = $them->getCharacter();
		$meRanged = $me->getWeapon()->getRangedPower();
		$meMelee = $me->getWeapon()->getMeleePower();
		$themRanged = $them->getWeapon()->getRangedPower();
		$themMelee = $them->getWeapon()->getMeleePower();
		if ($meRanged && !$themRanged) {
			$meFreeAttack = true;
			$themFreeAttack = false;
			$stayRanged = false;
		} elseif (!$meRanged && $themRanged) {
			$meFreeAttack = false;
			$themFreeAttack = true;
			$stayRanged = false;
		} elseif ($meRanged && $themRanged) {
			$meFreeAttack = false;
			$themFreeAttack = false;
			$stayRanged = true;
		} else {
			$meFreeAttack = false;
			$themFreeAttack = false;
			$stayRanged = false;
		}
		switch ($act->getSubType()->getName()) {
			case 'first blood':
				$limit = 10;
				break;
			case 'wound':
				$limit = 40;
				break;
			case 'surrender':
				$limit = 70;
				break;
			case 'death':
				$limit = 100;
				break;
		}

		#Create Report
		$report = new ActivityReport;
		$report->setPlace($act->getPlace());
		$report->setSettlement($act->getSettlement());
		$report->setType($act->getType());
		$report->setSubType($act->getSubType());
		$report->setLocation($act->getLocation());
		$report->setGeoData($act->getGeoData());
		$report->setDateTime(new \DateTime("now"));
		$em->persist($report);
		$this->report = $report;
		$this->helper->addObservers($act, $report);
		$em->flush();

		$meReport = new ActivityCharacterReport;
		$em->persist($meReport);
		$this->report->addGroup($meReport);
		$meReport->setActivityReport($this->report);

		$themReport = new ActivityCharacterReport;
		$em->persist($themReport);
		$this->report->addGroup($themReport);
		$themReport->setActivityReport($this->report);

		$em->flush();

		# Setup
		$round = 1;
		$continue = true;

		# Special first round logic.
		if ($meFreeAttack) {
			$result = $this->duelAttack($me, $meC, $meRanged, $meMelee, $themChar, $act, true);
			$themWounds = $this->duelApplyResult($them, $themWounds, $result, $limit, $themReport, $round, $act);
			if ($themWounds >= $limit) {
				$continue = false;
			}
			$round++;
		} elseif ($themFreeAttack) {
			$result = $this->duelAttack($them, $themC, $themRanged, $themMelee, $meChar, $act, true);
			$meWounds = $this->duelApplyResult($me, $meWounds, $result, $limit, $round, $act, false);
			if ($meWounds >= $limit) {
				$continue = false;
			}
			$round++;
		}

		if ($meRanged > $meMelee) {
			$meUseRanged = true;
		} else {
			$meUseRanged = false;
		}
		if ($themRanged > $themMelee) {
			$themUseRanged = true;
		} else {
			$themUseRanged = false;
		}

		if ($continue) {
			while ($themWounds >= $limit && $meWounds >= $limit) {
				# Challenger attacks.
				$result = $this->duelAttack($me, $meC, $meRanged, $meMelee, $themChar, $act, $meUseRanged);
				$themWounds = $this->duelApplyResult($them, $themWounds, $result, $limit, $round, $act, true);

				# Challenged attacks.
				$result = $this->duelAttack($them, $themC, $themRanged, $themMelee, $meChar, $act, $themUseRanged);
				$meWounds = $this->duelApplyResult($me, $meWounds, $result, $limit, $round, $act, false);

				$round++;
			}
		}

		$this->concludeDuel($me, $meWounds, $them, $themWounds, $act, $round);

		/*
		TODO: Finish this function. Link in reports. Expand duels to force no extra gear or not. Test. Test. Test.
		*/
	}

	private function duelAttack($me, $meChar, $meRanged, $meMelee, $themChar, $act, $ranged=false) {
		if ($ranged) {
			$this->helper->trainSkill($meChar, $me->getWeapon()->getSkill());
			$this->log(10, $meChar->getName()." fires - ");
			if ($this->combat->RangedRoll()) {
				list($result, $sublogs) = $this->combat->RangedHit($me, $themChar, $meRanged, $act);
				foreach ($sublogs as $each) {
					$this->log(10, $each);
				}
			} else {
				$result = 'miss';
			}
		} else {
			$this->helper->trainSkill($meChar, $me->getWeapon()->getSkill());
			$this->log(10, $meChar->getName()." attacks - ");
			if ($this->combat->MeleeRoll()) {
				list($result, $sublogs) = $this->combat->RangedHit($me, $themChar, $meRanged, $act);
				foreach ($sublogs as $each) {
					$this->log(10, $each);
				}
			} else {
				$result = 'miss';
			}
		}
		return $result;
	}

	private function duelApplyResult($wounds, $result, $limit, $round, $act, $challenger=true) {
		# Do nothing for misses.
		if ($result === 'no damage') {
			# Even taking no obvious damage still wears you down.
			$wounds = $wounds + rand(0,3);
		} elseif ($result === 'wound') {
			# Sometimes you do more, sometimes less, but always something.
			# Works out to between 1 and 20 wound points.
			$wounds = $wounds + (rand(1,20)/10);
		} elseif ($result === 'kill') {
			$wounds = $wounds + (rand(20,100)/10);
		}
		return $wounds;
	}

}
