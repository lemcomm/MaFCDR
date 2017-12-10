<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class KnightOfferType extends AbstractType {

	private $settlement;
	private $welcomers;

	public function __construct($settlement, $welcomers) {
		$this->settlement = $settlement;
		$this->welcomers = $welcomers;
	}

	public function getName() {
		return 'knightoffer';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'knightoffer_5945',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$settlement = $this->settlement;
		$welcomers = $this->welcomers;
		$builder->add('givesettlement', 'checkbox', array(
			'label' => 'recruit.offers.givesettlement',
			'required' => false
		));
		$builder->add('welcomers', 'entity', array(
			'label'=>'recruit.offers.welcomer',
			'required'=>false,
			'placeholder'=>'recruit.offers.nowelcomer',
			'choices'=>$welcomers,
			'class'=>'BM2SiteBundle:RealmPosition',
			'choice_label'=>'name',
			'group_by' => function($val, $key, $index) {
				return $val->getRealm()->getName();
			}
		));
		$builder->add('soldiers', 'entity', array(
			'label'=>'recruit.offers.soldiers',
			'multiple'=>true,
			'expanded'=>true,
			'class'=>'BM2SiteBundle:Soldier', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($settlement) {
				return $er->createQueryBuilder('s')->where('s.base = :here')->andWhere('s.offered_as is null')->andWhere('s.training_required <= 0')->orderBy('s.name')->setParameters(array('here'=>$settlement));
		}));
		$builder->add('intro', 'textarea', array(
			'label'=>'recruit.offers.offertext',
			'max_length'=>500,
			'trim'=>true,
			'required'=>true
		));

	}


}
