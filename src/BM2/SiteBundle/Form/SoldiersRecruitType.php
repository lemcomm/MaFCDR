<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

use Doctrine\ORM\EntityRepository;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use BM2\SiteBundle\Entity\Unit;
use BM2\SiteBundle\Entity\EquipmentType;

class SoldiersRecruitType extends AbstractType {

	private $available_equipment;
	private $units;

	public function __construct($available_equipment, $units) {
		$this->available_equipment = array();
		foreach ($available_equipment as $a) {
			$this->available_equipment[] = $a['item']->getId();
		}
		$this->units = $units;
	}

	public function getName() {
		return 'recruitment';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'recruit_23469',
			'attr'		=> array('class'=>'wide'),
			'validation_constraint' => new Assert\Collection(array(
				'number' => new Assert\Range(array('min'=>1)),
				'weapon' => null,
				'armour' => null,
				'equipment' => null
		        ))
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$available = $this->available_equipment;
		$units = $this->units;

		$builder->add('unit', EntityType::class, array(
			'label' => 'recruit.troops.unit',
			'required' => true,
			'class' => Unit::class,
			'choice_label' => 'settings.name',
			'choices' => $units,
			'placeholder'=>'recruit.troops.nounit',
			'translation_domain'=>'actions'
		));

		$builder->add('number', IntegerType::class, array(
			'attr' => array('size'=>3)
		));

		$fields = array('weapon', 'armour', 'equipment', 'mount');
		foreach ($fields as $field) {
			$builder->add($field, EntityType::class, array(
				'label'=>$field,
				'placeholder'=>$field=='weapon'?'item.choose':'item.none',
				'required'=>$field=='weapon'?true:false,
				'choice_label'=>'nameTrans',
				'class'=>EquipmentType::class,
				'choice_translation_domain' => true,
				'query_builder'=>function(EntityRepository $er) use ($available, $field) {
					return $er->createQueryBuilder('e')->where('e in (:available)')->andWhere('e.type = :type')->orderBy('e.name')
						->setParameters(array('available'=>$available, 'type'=>$field));
			}));
		}

		$builder->add('submit', SubmitType::class, array(
			'label'=>'recruit.troops.submit',
			'translation_domain'=>'actions'
		));
	}


}
