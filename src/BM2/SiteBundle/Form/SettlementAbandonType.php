<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettlementAbandonType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'settlementabandon_490',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('keep', CheckboxType::class, array(
			'label' => 'control.abandon.keep',
			'required' => false
		));
		$builder->add('sure', CheckboxType::class, array(
			'label' => 'control.abandon.sure',
			'required' => true
		));

		$builder->add('submit', SubmitType::class, array('label'=>'control.abandon.submit'));
	}

	public function getName() {
		return 'settlementabandon';
	}

}
