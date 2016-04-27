<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class EntourageAssignType extends AbstractType {

	private $actions;
	private $entourage;

	public function __construct($actions, $entourage) {
		$this->actions = $actions;
		$this->entourage = $entourage;
	}

	public function getName() {
		return 'assign';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'assign_123956',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$entourage = $this->entourage;

		if (is_array($this->actions)) {
			$builder->add('action', 'choice', array(
				'label' => 'entourage.assign.action',
				'placeholder' => 'form.choose',
				'choice_translation_domain' => true,
				'choices' => $this->actions
			));
		} else {
			$builder->add('action', 'hidden', array(
				'data' => $this->actions
			));
		}

		$builder->add('entourage', 'entity', array(
			'label' => 'entourage.assign.select',
			'placeholder' => 'form.choose',
			'multiple'=>true,
			'class'=>'BM2SiteBundle:Entourage', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($entourage) {
				$qb = $er->createQueryBuilder('e');
				$qb->where('e IN (:entourage)');
				$qb->setParameter('entourage', $entourage->toArray());
				return $qb;
			},
		));
	}

}
