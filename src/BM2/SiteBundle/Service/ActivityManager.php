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
use BM2\SiteBundle\Entity\EquipmentType;
use BM2\SiteBundle\Entity\Skill;
use BM2\SiteBundle\Entity\SkillType;
use BM2\SiteBundle\Entity\Style;

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

	public function createParticipant(Activity $act, Character $char, Style	$style=null, $weapon=null, $same=false, $organizer=false) {
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

	public function createDuel(Character $me, Character $them, $name=null, $level, $same, EquipmentType $weapon, $weaponOnly, Style $meStyle = null, Style $themStyle = null) {
		$type = $this->em->getRepository('BM2SiteBundle:ActivityType')->findOneBy(['name'=>'duel']);
		# TODO: Verify there isn't alreayd a duel between these individuals!
		if ($act = $this->create($type, null, $me)) {
			if (!$name) {
				$act->setName('Duel between '.$me->getName().' and '.$them->getName());
			} else {
				$act->setName($name);
			}
			$act->setSame($same);
			$act->setWeaponOnly($weaponOnly);
			$act->setSubType($this->em->getRepository('BM2SiteBundle:ActivitySubType')->findOneBy(['name'=>$level]));

			$mePart = $this->createParticipant($act, $me, $meStyle, $weapon, $same, true);
			$themPart = $this->createParticipant($act, $them, $themStyle, $same?$weapon:null, false);

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
		$meRanged = $this->combat->RangedPower($me, false, $me->getWeapon());
		$meScore = $me->findSkill($me->getWeapon()->getName())->getScore();
		$meMelee = $this->combat->MeleePower($me, false, $me->getWeapon());
		$themRanged = $this->combat->RangedPower($them, false, $them->getWeapon());
		$themMelee = $this->combat->MeleePower($them, false, $them->getWeapon());
		$themScore = $them->findSkill($them->getWeapon()->getName())->getScore();
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
		$wpnOnly = $act->getWeaponOnly();
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
		if (!$act->getReport()) {
			$report = new ActivityReport;
			$report->setPlace($act->getPlace());
			$report->setSettlement($act->getSettlement());
			$report->setType($act->getType());
			$report->setSubType($act->getSubType());
			$report->setLocation($act->getLocation());
			$report->setGeoData($act->getGeoData());
			$report->setDateTime(new \DateTime("now"));
			$em->persist($report);
			$act->setReport($report);
			$this->report = $report;
		} else {
			$this->report = $act->getReport();
		}
		if ($this->report->getObservers()->count() === 0) {
			$this->helper->addObservers($act, $report);
			$em->flush();
		}

		$charReports = $this->report->getCharacter();
		$count = $charReports->count();
		$haveMe = false;
		$haveThem = false;
		if ($count === 2) {
			foreach ($charReports as $each) {
				if ($each->getCharacter() === $meC) {
					$haveMe = true;
					$meReport = $each;
					continue;
				}
				if ($each->getCharacter() === $themC) {
					$haveThem = true;
					$themReport = $each;
				}
			}
		} elseif ($count === 1) {
			foreach ($this->report->getCharacters() as $each) {
				if ($each->getCharacter() === $meC) {
					$haveMe = true;
					$meReport = $each;
				}
				if ($each->getCharacter() === $themC) {
					$haveThem = true;
					$themReport = $each;
				}
			}
		}
		if (!$haveMe) {
			$meReport = new ActivityCharacterReport;
			$em->persist($meReport);
			$this->report->addCharacter($meReport);
			$meReport->setCharacter($meC);
			$meReport->setWeapon($me->getWeapon());
			if (!$wpnOnly) {
				$meReport->setArmour($meC->getArmour());
				$meReport->setEquipment($meC->getEquipment());
				$meReport->setMount($meC->getMount());
			}
			$meReport->setActivityReport($this->report);
		}
		if (!$haveThem) {
			$themReport = new ActivityCharacterReport;
			$em->persist($themReport);
			$this->report->addCharacter($themReport);
			$themReport->setCharacter($themC);
			$themReport->setWeapon($them->getWeapon());
			if (!$wpnOnly) {
				$themReport->setArmour($themC->getArmour());
				$themReport->setEquipment($themC->getEquipment());
				$themReport->setMount($themC->getMount());
			}
			$themReport->setActivityReport($this->report);
		}

		$em->flush();

		# Setup
		$round = 1;
		$continue = true;

		# Special first round logic.
		if ($meFreeAttack) {
			$data = [];
			$result = $this->duelAttack($me, $meC, $meRanged, $meMelee, $meScore, $themC, $themScore, $act, true);
			$data['result'] = $result;
			$newWounds = $this->duelApplyResult($result);
			$data['new'] = $newWounds;
			if ($result !== 'miss') {
				$this->log(10, $themC->getName()." takes ".$newWounds." damage from the attack.\n");
			}
			$themWounds = $themWounds + $newWounds;
			$data['wounds'] = $themWounds;
			if ($themWounds >= $limit) {
				$continue = false;
			}
			$this->createStageReport(null, $meReport, $round, $data);
			$this->createStageReport(null, $themReport, $round, ['result'=>'freehit']);
			$round++;
			$em->flush();
		} elseif ($themFreeAttack) {
			$data = [];
			$result = $this->duelAttack($them, $themC, $themRanged, $themMelee, $themScore, $meC, $meScore, $act, true);
			$data['result'] = $result;
			$newWounds = $this->duelApplyResult($result);
			$data['new'] = $newWounds;
			if ($result !== 'miss') {
				$this->log(10, $meC->getName()." takes ".$newWounds." damage from the attack.\n");
			}
			$meWounds = $meWounds + $newWounds;
			$data['wounds'] = $meWounds;
			if ($meWounds >= $limit) {
				$continue = false;
			}
			$this->createStageReport(null, $themReport, $round, $data);
			$this->createStageReport(null, $meReport, $round, ['result'=>'freehit']);
			$round++;
			$em->flush();
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
				$data = [];
				$result = $this->duelAttack($me, $meC, $meRanged, $meMelee, $meScore, $themC, $themScore, $act, $meUseRanged);
				$data['result'] = $result;
				$newWounds = $this->duelApplyResult($result);
				$data['new'] = $newWounds;
				if ($result !== 'miss') {
					$this->log(10, $themC->getName()." takes ".$newWounds." damage from the attack.\n");
				}
				$themWounds = $themWounds + $newWounds;
				$data['wounds'] = $themWounds;
				$this->createStageReport(null, $meReport, $round, $data);

				# Challenged attacks.
				$data = [];
				$result = $this->duelAttack($them, $themC, $themRanged, $themMelee, $themScore, $meC, $meScore, $act, $themUseRanged);
				$data['result'] = $result;
				$newWounds = $this->duelApplyResult($result);
				$data['new'] = $newWounds;
				if ($result !== 'miss') {
					$this->log(10, $meC->getName()." takes ".$newWounds." damage from the attack.\n");
				}
				$meWounds = $meWounds + $newWounds;
				$data['wounds'] = $meWounds;
				$this->createStageReport(null, $themReport, $round, $data);

				$round++;
				$em->flush();
			}
		}

		$this->duelConclude($me, $meWounds, $meReport, $them, $themWounds, $themReport, $limit, $act, $round);

		return true;
	}

	private function duelAttack($me, $meChar, $meRanged, $meMelee, $meScore, $themChar, $themScore, $act, $ranged=false) {
		if ($ranged) {
			$this->helper->trainSkill($meChar, $me->getWeapon()->getSkill());
			$this->log(10, $meChar->getName()." fires - ");
			if ($this->combat->RangedRoll(0, 1, 0, $meScore)) {
				list($result, $sublogs) = $this->combat->RangedHit($me, $themChar, $meRanged, $act, false, 1, $themScore);
				foreach ($sublogs as $each) {
					$this->log(10, $each);
				}
			} else {
				$result = 'miss';
			}
		} else {
			$this->helper->trainSkill($meChar, $me->getWeapon()->getSkill());
			$this->log(10, $meChar->getName()." attacks - ");
			if ($this->combat->MeleeRoll(0, 1, 0, $meScore)) {
				list($result, $sublogs) = $this->combat->MeleeAttack($me, $themChar, $meMelee, $act, false, 1, $themScore);
				foreach ($sublogs as $each) {
					$this->log(10, $each);
				}
			} else {
				$result = 'miss';
			}
		}
		return $result;
	}

	private function duelApplyResult($result) {
		# Do nothing for misses.
		$new = 0;
		if ($result === 'no damage') {
			# Even taking no obvious damage still wears you down.
			$new = rand(0,3);
		} elseif ($result === 'wound') {
			# Sometimes you do more, sometimes less, but always something.
			# Works out to between 1 and 20 wound points.
			$new = rand(1,20);
		} elseif ($result === 'kill') {
			$new = rand(20,100);
		}
		return $new;
	}

	private function createStageReport($group = null, $char = null, $round, $data, $extra = null) {
		if ($group !== null || $char !== null) {
			$rpt = new ActivityReportStage;
			$this->em->persist($rpt);
			if ($group) {
				$rpt->setGroup($group);
			}
			if ($char) {
				$rpt->setCharacter($char);
			}
			$rpt->setRound($round);
			$rpt->setData($data);
			$rpt->setExtra($extra);
			return $rpt;
		}
		return false;
	}

	private function duelConclude($me, $meWounds, $meReport, $them, $themWounds, $themReport, $limit, $act, $round) {
		$meData = [];
		$themData = [];
		if ($themWounds >= $limit && $meWounds < $limit) {
			# My victory.
			$meData['result'] = 'victory';
			$themData['result'] = 'loss';
			list($meData['skillCheck'], $meData['skillAcc'], $themData['skillCheck'], $themData['skillAcc']) = $this->skillEval($me, $meReport->getWeapon(), $them, $themReport->getWeapon());
		} elseif ($themWounds >= $limit && $meWounds >= $limit) {
			# Draw.
			$meData['result'] = 'draw';
			$themData['result'] = 'draw';
			list($meData['skillCheck'], $meData['skillAcc'], $themData['skillCheck'], $themData['skillAcc']) = $this->skillEval($me, $meReport->getWeapon(), $them, $themReport->getWeapon());
		} elseif ($meWounds >= $limit && $themWounds < $limit) {
			# Their victory.
			$meData['result'] = 'loss';
			$themData['result'] = 'loss';
			list($meData['skillCheck'], $meData['skillAcc'], $themData['skillCheck'], $themData['skillAcc']) = $this->skillEval($me, $meReport->getWeapon(), $them, $themReport->getWeapon());
		} else {
			# Inconclusive. Shouldn't end up here. Process as draw, flag as error.
			$meData['result'] = 'loss';
			$themData['result'] = 'loss';
			list($meData['skillCheck'], $meData['skillAcc'], $themData['skillCheck'], $themData['skillAcc']) = $this->skillEval($me, $meReport->getWeapon(), $them, $themReport->getWeapon());
			$this->log(10, "Duel ended inconclusively!\n");
		}
		# 32767 is the smallint max value, if you're curious.
		$this->createStageReport(null, $meReport, 32767, $meData);
		$this->createStageReport(null, $themReport, 32767, $themData);
		$this->em->flush();
		if ($limit == 100) {
			# Duels to the death have separate handling.
		} else {
			$this->applyWounds($me->getCharacter(), $meWounds);
			$this->applyWounds($them->getCharacter(), $themWounds);
		}

	}

	private function applyWounds(Character $me, $wounds) {
		$me->setWounded($me->getWounded() + $wounds); # Character health is out of 100.
		if ($me->healthValue() > 1) {
			# TODO: Event for near death! :(
		}
	}

	private function skillEval(Character $me, EquipmentType $meW, Character $them, EquipmentType $themW) {
		if ($meW === $themW) {
			$threshold = 0.9;
			$skillAcc = 'high';
		} else {
			if ($meW->getSkill()->getCategory() === $themW->getSkill()->getCategory()) {
				$threshold = 0.6;
				$skillAcc = 'medium';
			} elseif ($meW->getSkill()->getCategory()->getCategory() && $themW->getSkill()->getCategory()->getCategory() && $meW->getSkill()->getCategory()->getCategory() === $themW->getSkill()->getCategory()->getCategory()) {
				$threshold = 0.3;
				$skillAcc = 'low';
			} else {
				$threshold = 0.1;
				$skillAcc = 'none';

			}
		}
		$meS = $me->findSkill($meW->getName())->getScore();
		$themS = $them->findSkill($themW->getName())->getScore();
		# So this figures out which character has the higher skill, sets them as $a, sets the other as $b,
		# and sets $flip so we can figure out who is who later.
		if ($meS > $themS && $meS * $threshold > $themS) {
			$aS = $meS;
			$bS = $themS;
			$diff = $meS - $themS;
			$flip = 1;
		} elseif ($themS > $meS && $themS * $threshold > $meS) {
			$aS = $themS;
			$bS = $meS;
			$diff = $themS - $meS;
			$flip = -1;
		} else {
			$ratio = 1;
			$flip = 0;
			$diff = 0;
		}

		# Check if there's anything to compare.
		if ($flip !== 0) {
			# Figure out how much higher A is than B.
			$limit = $aS * $threshold;
			if ($limit > $diff) {
				# We have a comparable score difference.
				if ($bS * 3 < $limit) {
					# Major difference.
					$aCheck = 'very high';
					$bCheck = 'very low';
				} elseif ($themS * 2 < $limit) {
					# Moderate difference.
					$aCheck = 'high';
					$bCheck = 'low';
				} else {
					# Minor difference.
					$aCheck = 'minor high';
					$bCheck = 'minor low';
				}
			} else {
				# No measurable difference.
				$aCheck = 'similar';
				$bCheck = 'similar';
			}
		}

		if ($flip === 1) {
			# My score was higher, assign me A and them B.
			$meCheck = $aCheck;
			$themCheck = $bCheck;
		} elseif ($flip === 0) {
			# Skills about the same.
			$meCheck = 'similar';
			$themCheck = 'similar';
		} else {
			# Their skill is higher. They are A, and I am B.
			$meCheck = $bCheck;
			$themCheck = $aCheck;
		}
		return [$meCheck, $skillAcc, $themCheck, $skillAcc];
	}

}
