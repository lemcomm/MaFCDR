<?php

namespace BM2\SiteBundle\Form;

use BM2\SiteBundle\Entity\EquipmentType;
use BM2\SiteBundle\Entity\Unit;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;
use Doctrine\Common\Collections\ArrayCollection;


class UnitSoldiersType extends AbstractType {

	private $em;
	private $soldiers;
	private $available_resupply;
	private $available_training;
	private $others;
	private $settlement;
	private $reassign;
	private $unit;
	private $me;
	private $hasUnitsPerm;

	public function __construct($em, $soldiers, $available_resupply, $available_training, $units, $settlement, $reassign, $unit, $me, $hasUnitsPerm) {
		$this->em = $em;
		if (is_array($soldiers)) {
			$this->soldiers = $soldiers;
		} else {
			$this->soldiers = $soldiers->toArray();
		}

		$this->available_resupply = new ArrayCollection;
		foreach ($available_resupply as $a) {
			$this->available_resupply->add($a['item']);
		}
		$this->available_training = new ArrayCollection;
		foreach ($available_training as $a) {
			$this->available_training->add($a['item']);
		}

		$this->others = $units;
		$this->settlement = $settlement;
		$this->reassign = $reassign;
		$this->unit = $unit;
		$this->me = $me;
		$this->hasUnitsPerm = $hasUnitsPerm;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'soldiersmanage_1533',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$avail_train = array();
		foreach ($this->available_training as $a) {
			$avail_train[] = $a->getId();
		}

		$builder->add('npcs', 'form');
		$in_battle = -1;
		$is_looting = -1;
		$unit = $this->unit;
		$me = $this->me;

		$local = false;
		if ($unit->getCharacter() == $me || (!$unit->getCharacter() && ($this->hasUnitsPerm || $unit->getMarshal() == $me))) {
			$local = true;
		}

		foreach ($this->soldiers as $soldier) {
			$actions = [];
			if ($in_battle == -1) {
				if ($soldier->getCharacter()) {
					$in_battle = $soldier->getCharacter()->isInBattle();
					$is_looting = $soldier->getCharacter()->isLooting();
				} else {
					$in_battle = false;
					$is_looting = false;
				}
			}
			$idstring = (string)$soldier->getId();
			$builder->get('npcs')->add($idstring, 'form', array('label'=>$soldier->getName()));
			$field = $builder->get('npcs')->get($idstring);

			if ($soldier->isLocked() || $is_looting) {
				// disallow almost all actions if soldier is locked or if character is in a battle or looting
				if (!$soldier->isAlive() && $local) {
					$actions = array('bury' => 'recruit.manage.bury2');
				}
			} else {
				if ($soldier->isAlive()) {
					if (!$in_battle) {
						if ($local) {
							if (!empty($avail_train) && $soldier->isActive()) {
								$actions['retrain'] = 'recruit.manage.retrain';
								$actions['disband'] = 'recruit.manage.disband';
							}
							if ($this->reassign) {
								$actions['assignto'] = 'recruit.manage.reassign';
								$actions['disband'] = 'recruit.manage.disband';
								# This might be duplicate, or it might not. Array key, so doesn't matter.
							}
						}
					}
					$resupply = false;
					if (!empty($this->available_resupply)) {
						if ( (!$soldier->getHasWeapon() && $this->available_resupply->contains($soldier->getTrainedWeapon()))
							|| (!$soldier->getHasArmour() && $this->available_resupply->contains($soldier->getTrainedArmour()))
							|| (!$soldier->getHasEquipment() && $this->available_resupply->contains($soldier->getTrainedEquipment()))
							|| (!$soldier->getHasMount() && $this->available_resupply->contains($soldier->getTrainedMount()))
						) {
							$resupply = true;
						}
					}
					if ($resupply) {
						$actions['resupply'] = 'recruit.manage.resupply';
					}
				} else {
					$actions = array('bury' => 'recruit.manage.bury');
				}
			} // endif locked
			if (!empty($actions)) {
				$field->add('action', ChoiceType::class, array(
					'choices' => $actions,
					'required' => false,
					'attr' => array('class'=>'action')
				));
			}
		}
		if (!empty($this->others) && $this->reassign) {
			$others = $this->others;
			$builder->add('assignto', EntityType::class, array(
				'placeholder' => 'form.choose',
				'label' => 'recruit.manage.assignto',
				'required' => false,
				'choice_label'=>'settings.name',
				'class'=>'BM2SiteBundle:Unit',
				'query_builder'=>function(EntityRepository $er) use ($others) {
					$qb = $er->createQueryBuilder('u');
					$qb->where('u IN (:others)');
					$qb->setParameter('others', $others);
					return $qb;
				},
			));
		}
		if (!empty($avail_train)) {
			$fields = array('weapon', 'armour', 'equipment', 'mount');
			foreach ($fields as $field) {
				$builder->add($field, EntityType::class, array(
					'label'=>$field,
					'placeholder'=>'item.current',
					'required'=>false,
					'translation_domain'=>'messages',
					'class'=>'BM2SiteBundle:EquipmentType',
					'choice_label'=>'nameTrans',
					'choice_translation_domain'=>'messages',
					'query_builder'=>function(EntityRepository $er) use ($avail_train, $field) {
						return $er->createQueryBuilder('e')->where('e in (:available)')->andWhere('e.type = :type')->orderBy('e.name')
							->setParameters(array('available'=>$avail_train, 'type'=>$field));
				}));
			}
		}
	}

}
