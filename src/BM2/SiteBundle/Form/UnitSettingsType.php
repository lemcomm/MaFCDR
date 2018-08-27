<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

use BM2\SiteBundle\Entity\UnitSettings;

class UnitSettingsType extends AbstractType {

	private $char;

	public function __construct($char, $supply) {
		$this->char = $char;
		$this->supply = $supply;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'unitsettings_1337',
			'translation_domain' 	=> 'settings',
			'attr'			=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$char = $this->char;
		$supply = $this->supply;

		$builder->add('name', 'text', array(
			'label'=>'unit.name',
			'required'=>true
		));
		if ($supply) {
			$builder->add('supplier', 'entity', array(
				'label' => 'unit.supplier',
				'multiple'=>false,
				'expanded'=>false,
				'class'=>'BM2SiteBundle:Settlement', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($char) {
					$qb = $er->createQueryBuilder('e');
					$qb->where('e.owner = :char')->setParameter('char', $char);
					$qb->orderBy('e.name');
					return $qb;
				},
			));
		}
		$builder->add('strategy', 'choice', array(
			'label'=>'unit.strategy.name',
			'required'=>false,
			'choices'=>array(
				'advance' => 'unit.strategy.advance',
				'hold' => 'unit.strategy.hold',
				'distance' => 'unit.strategy.distance'
			),
		));
		$builder->add('tactic', 'choice', array(
			'label'=>'unit.tactic.name',
			'required'=>false,
			'choices'=>array(
				'melee' => 'unit.tactic.melee',
				'ranged' => 'unit.tactic.ranged',
				'mixed' => 'unit.tactic.mixed'
			),
		));
		$builder->add('respect_fort', 'checkbox', array(
			'label'=>'unit.usefort',
			'required'=>false
		));
		$builder->add('line', 'choice', array(
			'label'=>'unit.line.name',
			'required'=>false,
			'choices'=>array(
				'1' => 'unit.line.1',
				'2' => 'unit.line.2',
				'3' => 'unit.line.3',
				'4' => 'unit.line.4',
				'5' => 'unit.line.5',
				'6' => 'unit.line.6',
				'7' => 'unit.line.7',
			),
		));
		$builder->add('submit', 'submit', array('label'=>'submit'));
	}

	public function getName() {
		return 'unitsettings';
	}
			
}
