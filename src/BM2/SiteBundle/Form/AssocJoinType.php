<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AssocJoinType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'assocjoin_8',
			'translation_domain' => 'orgs'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('subject', 'text', array(
			'label' => 'assoc.form.join.subject',
			'required' => true
		));
		$builder->add('text', 'textarea', array(
			'label' => 'assoc.form.join.text',
			'required' => true
		));
		$builder->add('sure', 'checkbox', array(
			'label' => 'assoc.form.areyousure',
			'required' => true
		));

		$builder->add('submit', 'submit', array('label'=>'assoc.form.submit'));
	}

	public function getName() {
		return 'assocjoin';
	}
}
