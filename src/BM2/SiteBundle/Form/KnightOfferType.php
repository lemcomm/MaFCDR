<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class KnightOfferType extends AbstractType {

	private $settlement;

	public function __construct($settlement) {
		$this->settlement = $settlement;
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
		$builder->add('givesettlement', 'checkbox', array(
			'label' => 'recruit.offers.givesettlement',
			'required' => false
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
			'max_length'=>240,
			'trim'=>true,
			'required'=>true,
		));

	}


}
