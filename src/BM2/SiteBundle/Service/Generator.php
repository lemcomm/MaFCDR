<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Entourage;
use BM2\SiteBundle\Entity\EntourageType;
use BM2\SiteBundle\Entity\EquipmentType;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Soldier;
use Doctrine\ORM\EntityManager;


class Generator {

	protected $em;
	protected $military;

	public function __construct(EntityManager $em, Military $military) {
		$this->em = $em;
		$this->military = $military;
	}

	public function randomName(Settlement $home=null, $gender=false) {
        $culture = null;
		if ($home) {
			$culture = $home->getCulture();
		}
		$qb = $this->em->createQueryBuilder();
		$qb->select('count(n.id)')->from('BM2SiteBundle:NameList', 'n');
		if ($culture) {
			$qb->where('n.culture = :culture')->setParameter('culture', $culture);
		}
		if ($gender) {
			if ($gender=='female') {
				$qb->where('n.male = false');
			} else {
				$qb->where('n.male = true');
			}
		}
		$nameCount = $qb->getQuery()->getSingleScalarResult();

		// this will fail with fatal error if there are no names in the database, but that should never happen anyways,
		// so we save the processing power to test for it

		$qb = $this->em->createQueryBuilder();
		$qb->select('n')->from('BM2SiteBundle:NameList', 'n');
		if ($culture) {
			$qb->where('n.culture = :culture')->setParameter('culture', $culture);
		}
		$query = $qb->getQuery();
		$query->setFirstResult(rand(0,$nameCount-1))->setMaxResults(1);
		$name = $query->getSingleResult();
		return $name->getName();
	}

	public function randomSoldier(EquipmentType $weapon=null, EquipmentType $armour=null, EquipmentType $equipment=null, Settlement $home=null, $corruption=0) {
		$soldier = new Soldier;
		$soldier->setName($this->randomName($home));
		$soldier->setLocked(false);
		$soldier->setRouted(false)->setHungry(0)->setWounded(0);
		$soldier->setHasWeapon(true)->setHasArmour(true)->setHasEquipment(true);
	
		$soldier->setExperience(0)->setTraining(0);
		if ($home) {
			if ($this->military->acquireItem($home, $weapon, true, false)
				&& $this->military->acquireItem($home, $armour, true, false)
				&& $this->military->acquireItem($home, $equipment, true, false)) {

				$this->military->acquireItem($home, $weapon, true);
				$soldier->setWeapon($weapon);
				$this->military->acquireItem($home, $armour, true);
				$soldier->setArmour($armour);
				$this->military->acquireItem($home, $equipment, true);
				$soldier->setEquipment($equipment);
			} else {
				return null;
			}
		} else {
			$soldier->setWeapon($weapon);
			$soldier->setArmour($armour);
			$soldier->setEquipment($equipment);
		}
		// this is somewhat duplicated in military->retrain, but not trivial to merge
		$train = 10; // FIXME - shouldn't this be a global variable?
		if ($soldier->getWeapon()) { $train += $soldier->getWeapon()->getTrainingRequired(); }
		if ($soldier->getArmour()) { $train += $soldier->getArmour()->getTrainingRequired(); }
		if ($soldier->getEquipment()) { $train += $soldier->getEquipment()->getTrainingRequired(); }

		// effect of corruption: double corruption in training time demand % penalty
		// so at 4% corruption, training will take 8% longer
		$train = round($train * (1+($corruption*2)) );

		$soldier->setTrainingRequired(max(1,$train));

		$soldier->setHome($home)->setDistanceHome(0)->setBase($home);
		$soldier->setAlive(true);

		$this->em->persist($soldier);
		return $soldier;
	}

	public function randomEntourageMember(EntourageType $type, Settlement $home=null) {
		$servant = new Entourage();
		$servant->setType($type);
		$servant->setName($this->randomName($home));
		$servant->setExperience(0);
		$servant->setHome($home)->setDistanceHome(0);
		$servant->setAlive(true);
		$servant->setLocked(false);
		$servant->setHungry(0)->setWounded(0)->setSupply(5); // we start with a little supply

		$this->em->persist($servant);
		return $servant;
	}

}
