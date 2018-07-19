<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HouseJoinType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'housejoin_843215',
			'translation_domain' => 'politics'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('subject', 'text', array(
			'label' => 'house.join.subject',
			'required' => true
		));
		$builder->add('text', 'textarea', array(
			'label' => 'house.join.text',
			'required' => true
		));
		$builder->add('sure', 'checkbox', array(
			'label' => 'settings.areyousure',
			'translation_domain' => 'settings',
			'required' => true
		));

		$builder->add('submit', 'submit', array('label'=>'settings.submit'));
	}

	public function getName() {
		return 'housejoin';
	}
}
