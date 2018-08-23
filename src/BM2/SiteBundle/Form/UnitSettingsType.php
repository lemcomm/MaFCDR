<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
			'translation_domain' => 'settings',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$char = $this->char;
		$supply = $this->supply;

		$builder->add('name', 'text', array(
			'label'=>'setting.unit.name',
			'required'=>true,
			'placeholder'=>$char->getName()."'s Force";
		));
		if ($supply) {
			$builder->add('supplier', 'entity', array(
				'label' => 'setting.unit.supplier',
				'multiple'=>false,
				'expanded'=>true,
				'class'=>'BM2SiteBundle:Settlement', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($char) {
					$qb = $er->createQueryBuilder('e');
					$qb->where('e.owner = :char')->setParameter('char', $char);
					$qb->orderBy('e.name');
					return $qb;
				},
			));
		}
		$builder->add('strategy', 'choice', array(
			'label'=>'setting.unit.strategy.name',
			'required'=>false,
			'choices'=>array(
				'advance' => 'settings.unit.strategy.advance',
				'hold' => 'settings.unit.strategy.hold',
				'distance' => 'settings.unit.strategy.distance'
			),
		));
		$builder->add('tactic', 'choice', array(
			'label'=>'setting.unit.tactic.name',
			'required'=>false,
			'choices'=>array(
				'melee' => 'setting.unit.tactic.melee',
				'ranged' => 'settings.unit.tactic.ranged',
				'mixed' => 'settings.unit.tactic.mixed'
			),
		));
		$builder->add('respect_fort', 'checkbox', array(
			'label'=>'setting.unit.tactic.name',
			'required'=>false
		));
		$builder->add('line', 'choice', array(
			'label'=>'setting.unit.line.name',
			'required'=>false,
			'choices'=>array(
				'1' => 'setting.unit.line.1',
				'2' => 'setting.unit.line.2',
				'3' => 'setting.unit.line.3',
				'4' => 'setting.unit.line.4',
				'5' => 'setting.unit.line.5',
				'6' => 'setting.unit.line.6',
				'7' => 'setting.unit.line.7',
			),
		));
		$builder->add('siege_orders', 'choice', array(
			'label'=>'setting.unit.siege.name',
			'required'=>false,
			'choices'=>array(
				'engage' => 'setting.unit.siege.engage',
				'stayback' => 'settings.unit.siege.stayback',
				'avoid' => 'settings.unit.siege.avoid'
			),
		));
		$builder->add('submit', 'submit', array('label'=>'submit'));
	}

	public function getName() {
		return 'unitsettings';
	}
			
}
