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

	public function updateLaw($org, $type, $setting, $description = null, Character $character, $flush=true) {
		# All laws are kept eternal, new laws are made whenever a law is changed, the old is inactivated.
		
		$oldLaw = $assoc->findLaw($type);
		if (!$oldLaw || ($oldLaw->getSetting() != $setting)) {
			$law = new Law();
			$this->em->persist($law);
			$lawType = $this->em->getRepository(LawType::class)->findOneBy(['name'=>$type, 'category'=>'assocation']);
			if ($type) {
				$law->setType($lawType);
			} else {
				return false; #Bad Type passed.
			}
			if ($org instanceof Association) {
				$law->setAssociation($org);
			} else {
				$law->setRealm($org);
			}
			$law->setEnacted(new \DateTime("now"));
			$law->setCharacter($character);
			if ($oldLaw) {
				$changes = $this->lawSequenceUpdater($oldLaw, $law, $type, $setting);
			}
			if ($flush) {
				$this->em->flush();
			}
			return [$law, $changes];
		} else {
			# No change to the law. Inform the user they did nothing.
			return ['no change', []];
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
