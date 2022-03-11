<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Law;
use BM2\SiteBundle\Entity\Realm;
use Doctrine\ORM\EntityManager;


class LawManager {

	protected $em;
	protected $history;
	protected $descman;
	protected $convman;

	public function __construct(EntityManager $em) {
		$this->em = $em;
	}

	public function updateLaw($org, $type, $setting, $title, $description = null, Character $character, $allowed, $mandatory, $cascades, $sol, $flush=true) {
		# All laws are kept eternal, new laws are made whenever a law is changed, the old is inactivated.

		if ($org instanceof Association) {
			$assoc = $org;
			$realm = false;
			$cat = 'assoc';
		} else {
			$assoc = false;
			$realm = $org;
			$cat = 'realm';
		}
		if ($type != 'freeform') {
			$oldLaw = $org->findLaw($type);
		} else {
			$oldLaw = false;
		}

		if (!$oldLaw || ($oldLaw->getSetting() != $setting)) {
			$law = new Law();
			$this->em->persist($law);
			$lawType = $this->em->getRepository(LawType::class)->findOneBy(['name'=>$type, 'category'=>$cat]);
			if ($lawType) {
				$law->setType($lawType);
			} else {
				return ['error', 'badType']; #Bad Type passed.
			}
			if ($assoc) {
				$law->setAssociation($org);
			} else {
				$law->setRealm($org);
			}
			$law->setEnacted(new \DateTime("now"));
			$law->setCharacter($character);
			$law->setTitle($title);
			$law->setAllowed($allowed);
			$law->setMandatory($mandatory);
			$law->setCascades($cascades);
			$law->setSolCycles($sol);
			if ($oldLaw) {
				$changes = $this->lawSequenceUpdater($oldLaw, $law, $type, $setting);
			} else {
				$changes = null;
			}
			if ($flush) {
				$this->em->flush();
			}
			return [$law, $changes];
		} else {
			# No change to the law. Inform the user they did nothing.
			return ['no change', null];
		}
	}

	public function lawSequenceUpdater($old, $law, $type, $setting, $changes = []) {
		# This primarily exists for cascading law changes,
		$simpleLaws = ['assocVisibility', 'rankVisibility'];
		if (in_array($type, $simpleLaws)) {
			$old->setInactivatedBy($law);
			$old->setInactivatedOn(new \DateTime("now"));
			$changes[] = $old;
		}
		return $changes;
	}

}
