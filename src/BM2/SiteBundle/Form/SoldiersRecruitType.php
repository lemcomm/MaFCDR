<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

use Doctrine\ORM\EntityRepository;


class SoldiersRecruitType extends AbstractType {

	private $available_equipment;

	public function __construct($available_equipment) {
		$this->available_equipment = array();
		foreach ($available_equipment as $a) {
			$this->available_equipment[] = $a['item']->getId();
		}
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

		$builder->add('number', 'integer', array(
			'attr' => array('size'=>3)
		));

		$fields = array('weapon', 'armour', 'equipment');
		foreach ($fields as $field) {
			$builder->add($field, 'entity', array(
				'label'=>$field,
				'class'=>'BM2SiteBundle:EquipmentType',
				'placeholder'=>$field=='weapon'?'item.choose':'item.none',
				'required'=>$field=='weapon'?true:false,
				'choice_label'=>'nameTrans',
				'choice_translation_domain' => true,
				'query_builder'=>function(EntityRepository $er) use ($available, $field) {
					return $er->createQueryBuilder('e')->where('e in (:available)')->andWhere('e.type = :type')->orderBy('e.name')
						->setParameters(array('available'=>$available, 'type'=>$field));
			}));
		}

		$builder->add('submit', 'submit', array('label'=>'recruit.troops.submit', 'translation_domain'=>'actions'));
	}


}
