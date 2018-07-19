<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AreYouSureType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'doublecheck_159753',
			'translation_domain' => 'settings'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('sure', 'checkbox', array(
			'label' => 'settings.areyousure',
			'required' => true
		));

		$builder->add('submit', 'submit', array('label'=>'settings.submit'));
	}

	public function getName() {
		return 'areyousure';
	}

}
