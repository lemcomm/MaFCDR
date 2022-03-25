<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HouseJoinType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'housesubcreate_4321',
			'translation_domain' => 'politics'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('subject', 'text', array(
			'label' => 'house.subcreate.subject',
			'required' => true
		));
		$builder->add('text', 'textarea', array(
			'label' => 'house.subcreate.text',
			'required' => true
		));

		$builder->add('submit', 'submit', array('label'=>'requet.generic.submit', 'translation_domain' => 'actions'));
	}

	public function getName() {
		return 'housesubcreate';
	}
}
